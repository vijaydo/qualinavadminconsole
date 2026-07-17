<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps Hospital Setup measure coverage aligned with My Org > Data Hub.
 *
 * Data Hub remains the system of record for submissions, goals and charts. This
 * bridge only synchronizes the organisation's configured measure coverage.
 */
class QN_Data_Hub_Integration
{
    public static function hydrate_answers($organization_id, $answers, $include_submission_status = true)
    {
        $coverage = self::get_coverage($organization_id);
        $answers = self::collapse_hcahps_setup_answer($answers);
        if (!empty($coverage['saved'])) {
            foreach (self::definitions() as $definition) {
                if ($definition['question_key'] === 'external_reporting_flex_mbqip' && strpos($definition['measure_key'], 'hcahps-') === 0) {
                    continue;
                }
                $question_key = $definition['question_key'];
                $current = isset($answers[$question_key]) && is_array($answers[$question_key]) ? $answers[$question_key] : array();
                $current = array_values(array_filter($current, function ($value) use ($definition) {
                    return !self::matches($value, $definition['setup_values']);
                }));

                $selected = isset($coverage[$definition['coverage_group']]) ? $coverage[$definition['coverage_group']] : array();
                if (self::list_matches($selected, $definition['coverage_values'])) {
                    $current[] = $definition['setup_value'];
                }
                $answers[$question_key] = array_values(array_unique($current));
            }

            if (self::list_has_prefix(isset($coverage['mbqip']) ? $coverage['mbqip'] : array(), 'hcahps')) {
                $current = isset($answers['external_reporting_flex_mbqip']) && is_array($answers['external_reporting_flex_mbqip'])
                    ? $answers['external_reporting_flex_mbqip']
                    : array();
                $current[] = 'HCAHPS';
                $answers['external_reporting_flex_mbqip'] = array_values(array_unique($current));
            }

            $answers['data_hub_measure_keys'] = self::canonical_keys_from_coverage($coverage);
            $answers['data_hub_reporting_rows'] = self::reporting_rows($organization_id, $coverage);
        }
        if ($include_submission_status) {
            $answers['organization_user_options'] = self::organization_users($organization_id);
        }
        if ($include_submission_status) {
            $submission_status = self::submission_status($organization_id, $coverage);
            foreach ($submission_status['answer_values'] as $question_key => $value) {
                $answers[$question_key] = $value;
            }
            $answers['data_hub_submission_status'] = $submission_status['summary'];
        }
        return $answers;
    }

    private static function collapse_hcahps_setup_answer($answers)
    {
        if (!isset($answers['external_reporting_flex_mbqip']) || !is_array($answers['external_reporting_flex_mbqip'])) {
            return $answers;
        }

        $has_hcahps = false;
        $collapsed = array();
        foreach ($answers['external_reporting_flex_mbqip'] as $value) {
            if (strpos(self::normalized($value), 'hcahps') === 0) {
                $has_hcahps = true;
                continue;
            }
            $collapsed[] = $value;
        }
        if ($has_hcahps) {
            $collapsed[] = 'HCAHPS';
        }
        $answers['external_reporting_flex_mbqip'] = array_values(array_unique($collapsed));

        return $answers;
    }

    public static function sync_from_answers($organization_id, $answers, $user_id)
    {
        if (array_key_exists('reporting_obligations', (array) $answers)) {
            self::sync_reporting_ownership($organization_id, $answers['reporting_obligations'], $user_id);
        }
        $measure_question_keys = array_unique(array_column(self::definitions(), 'question_key'));
        if (!array_intersect($measure_question_keys, array_keys((array) $answers))) {
            return;
        }

        $all_answers = QN_Questionnaire::get_answer_map($organization_id, false);
        $coverage = self::get_coverage($organization_id);
        foreach (array('mbqip', 'hacs_hais') as $group) {
            $existing = isset($coverage[$group]) && is_array($coverage[$group]) ? $coverage[$group] : array();
            $managed_values = array();
            foreach (self::definitions() as $definition) {
                if ($definition['coverage_group'] === $group) {
                    $managed_values = array_merge($managed_values, $definition['coverage_values']);
                }
            }
            $coverage[$group] = array_values(array_filter($existing, function ($value) use ($managed_values) {
                return !self::matches($value, $managed_values);
            }));
        }

        $canonical_keys = array();
        $legacy_mbqip = isset($all_answers['external_reporting_flex_mbqip']) && is_array($all_answers['external_reporting_flex_mbqip'])
            ? $all_answers['external_reporting_flex_mbqip']
            : array();
        if (self::list_matches($legacy_mbqip, array('HCAHPS'))) {
            $coverage['mbqip'] = array_merge($coverage['mbqip'], self::hcahps_coverage_values());
            $canonical_keys = array_merge($canonical_keys, self::hcahps_measure_keys());
        }
        foreach (self::definitions() as $definition) {
            $selected = isset($all_answers[$definition['question_key']]) && is_array($all_answers[$definition['question_key']])
                ? $all_answers[$definition['question_key']]
                : array();
            if (!self::list_matches($selected, $definition['setup_values'])) {
                continue;
            }
            $canonical_keys[] = $definition['measure_key'];
            $group = $definition['coverage_group'];
            $coverage[$group] = array_merge($coverage[$group], $definition['coverage_values']);
        }

        $coverage['saved'] = true;
        $coverage['mbqip'] = array_values(array_unique($coverage['mbqip']));
        $coverage['hacs_hais'] = array_values(array_unique($coverage['hacs_hais']));
        $coverage['updated_at'] = current_time('mysql');
        $coverage['updated_by'] = absint($user_id);

        update_option(self::coverage_option_key($organization_id), $coverage, false);
    }

    private static function sync_reporting_ownership($organization_id, $rows, $user_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'qualinav_mbqip_report_ownership';
        if (!QN_DB::table_exists($table)) {
            return;
        }
        $audit_table = $wpdb->prefix . 'qualinav_mbqip_report_ownership_audit';
        $context = self::organization_context($organization_id);
        $groups = array();
        foreach (self::definitions() as $definition) {
            $groups[$definition['measure_key']] = $definition['coverage_group'];
        }

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $measure_key = isset($row['measure_key']) ? sanitize_key($row['measure_key']) : '';
            $owner_user_id = isset($row['owner_user_id']) ? absint($row['owner_user_id']) : 0;
            if ($measure_key === '' || $owner_user_id === 0 || !isset($groups[$measure_key]) || !QN_Users::user_has_organization($owner_user_id, $organization_id)) {
                continue;
            }

            $module_key = $groups[$measure_key] === 'hacs_hais' ? 'hacs_hais' : 'mbqip';
            $event_type_key = $module_key;
            $measure_name = isset($row['report_name']) ? sanitize_text_field($row['report_name']) : $measure_key;
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_key = %s AND module_key = %s AND event_type_key = %s AND measure_key = %s LIMIT 1",
                $context['org_key'],
                $module_key,
                $event_type_key,
                $measure_key
            ), ARRAY_A);
            $previous_owner_id = $existing ? absint($existing['owner_user_id']) : 0;
            if ($previous_owner_id === $owner_user_id) {
                continue;
            }

            $now = current_time('mysql');
            $data = array(
                'organization_id' => absint($organization_id),
                'org_key' => $context['org_key'],
                'organization_name' => $context['organization_name'],
                'module_key' => $module_key,
                'event_type_key' => $event_type_key,
                'measure_key' => $measure_key,
                'measure_name' => $measure_name,
                'owner_user_id' => $owner_user_id,
                'assigned_by_user_id' => absint($user_id),
                'updated_at' => $now,
            );
            if ($existing) {
                $wpdb->update($table, $data, array('id' => absint($existing['id'])));
                $ownership_id = absint($existing['id']);
            } else {
                $data['assigned_at'] = $now;
                $wpdb->insert($table, $data);
                $ownership_id = absint($wpdb->insert_id);
            }

            if ($ownership_id && QN_DB::table_exists($audit_table)) {
                $wpdb->insert($audit_table, array(
                    'ownership_id' => $ownership_id,
                    'organization_id' => absint($organization_id),
                    'org_key' => $context['org_key'],
                    'organization_name' => $context['organization_name'],
                    'module_key' => $module_key,
                    'event_type_key' => $event_type_key,
                    'measure_key' => $measure_key,
                    'measure_name' => $measure_name,
                    'previous_owner_user_id' => $previous_owner_id ?: null,
                    'new_owner_user_id' => $owner_user_id,
                    'changed_by_user_id' => absint($user_id),
                    'changed_at' => $now,
                ));
            }
        }
    }

    private static function submission_status($organization_id, $coverage)
    {
        global $wpdb;

        $context = self::organization_context($organization_id);
        $mbqip_keys = array();
        $hacs_hais_keys = array();
        $mbqip_table = $wpdb->prefix . 'qualinav_mbqip_submissions';
        if (QN_DB::table_exists($mbqip_table)) {
            $mbqip_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT measure_key FROM {$mbqip_table} WHERE (organization_id = %d OR org_key = %s) AND status = %s AND is_current = 1",
                absint($organization_id),
                $context['org_key'],
                'active'
            ));
        }

        $hacs_table = $wpdb->prefix . 'qualinav_hacs_hais_submissions';
        $hacs_values_table = $wpdb->prefix . 'qualinav_hacs_hais_values';
        if (QN_DB::table_exists($hacs_table) && QN_DB::table_exists($hacs_values_table)) {
            $hacs_hais_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT v.measure_key FROM {$hacs_values_table} v INNER JOIN {$hacs_table} s ON s.id = v.submission_id WHERE (s.organization_id = %d OR s.org_key = %s) AND s.status = %s AND s.is_current = 1",
                absint($organization_id),
                $context['org_key'],
                'active'
            ));
        }

        $mbqip_coverage = isset($coverage['mbqip']) ? (array) $coverage['mbqip'] : array();
        $hacs_coverage = isset($coverage['hacs_hais']) ? (array) $coverage['hacs_hais'] : array();
        $hcahps_configured = self::list_has_prefix($mbqip_coverage, 'hcahps');
        $hcahps_submitted = self::list_has_prefix($mbqip_keys, 'hcahps');
        $hai_keys = array('c_diff', 'mrsa', 'cauti', 'clabsi');

        $answer_values = array(
            'mbqip_upload' => self::derived_status(!empty($mbqip_coverage), !empty($mbqip_keys)),
            'nhsn_hai_rates_upload' => self::derived_status((bool) array_intersect($hai_keys, $hacs_coverage), (bool) array_intersect($hai_keys, $hacs_hais_keys)),
            'patient_experience_scores_upload' => self::derived_status($hcahps_configured, $hcahps_submitted),
            'fall_rates_upload' => self::derived_status(in_array('falls_with_injury', $hacs_coverage, true), in_array('falls_with_injury', $hacs_hais_keys, true)),
            'pressure_injury_rates_upload' => self::derived_status(in_array('pressure_ulcers_3_plus', $hacs_coverage, true), in_array('pressure_ulcers_3_plus', $hacs_hais_keys, true)),
        );

        return array(
            'answer_values' => $answer_values,
            'summary' => array(
                'source' => 'data_hub',
                'mbqip_measure_keys' => array_values(array_unique(array_filter(array_map('sanitize_key', $mbqip_keys)))),
                'hacs_hais_measure_keys' => array_values(array_unique(array_filter(array_map('sanitize_key', $hacs_hais_keys)))),
                'derived_answer_keys' => array_keys($answer_values),
            ),
        );
    }

    private static function derived_status($configured, $submitted)
    {
        if ($submitted) {
            return 'yes';
        }
        return $configured ? 'no' : 'not_applicable';
    }

    private static function list_has_prefix($values, $prefix)
    {
        $prefix = sanitize_title($prefix);
        foreach ((array) $values as $value) {
            if (strpos(self::normalized($value), $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function get_coverage($organization_id)
    {
        $stored = get_option(self::coverage_option_key($organization_id), null);
        return is_array($stored) ? wp_parse_args($stored, array('saved' => true, 'mbqip' => array(), 'hacs_hais' => array())) : array('saved' => false, 'mbqip' => array(), 'hacs_hais' => array());
    }

    private static function coverage_option_key($organization_id)
    {
        $context = self::organization_context($organization_id);
        return 'qualinav_data_hub_measure_coverage_' . $context['org_key'];
    }

    private static function organization_context($organization_id)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        $row = QN_DB::table_exists($table) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint($organization_id)), ARRAY_A) : null;
        $org_key = is_array($row) && !empty($row['slug']) ? $row['slug'] : '';
        $organization_name = '';
        if ($org_key === '' && is_array($row)) {
            $name = !empty($row['organization_name']) ? $row['organization_name'] : (isset($row['name']) ? $row['name'] : '');
            $organization_name = $name;
            $org_key = sanitize_title($name);
        } elseif (is_array($row)) {
            $organization_name = !empty($row['organization_name']) ? $row['organization_name'] : (isset($row['name']) ? $row['name'] : '');
        }
        if ($org_key === '') {
            $org_key = 'organization-' . absint($organization_id);
        }
        return array('organization_id' => absint($organization_id), 'org_key' => sanitize_title($org_key), 'organization_name' => (string) $organization_name);
    }

    private static function canonical_keys_from_coverage($coverage)
    {
        $keys = array();
        foreach (self::definitions() as $definition) {
            $values = isset($coverage[$definition['coverage_group']]) ? $coverage[$definition['coverage_group']] : array();
            if (self::list_matches($values, $definition['coverage_values'])) {
                $keys[] = $definition['measure_key'];
            }
        }
        return array_values(array_unique($keys));
    }

    private static function reporting_rows($organization_id, $coverage)
    {
        global $wpdb;

        $measure_keys = self::canonical_keys_from_coverage($coverage);
        if (!$measure_keys) {
            return array();
        }

        $library = array();
        $measure_table = $wpdb->prefix . 'qualinav_data_hub_measures';
        $version_table = $wpdb->prefix . 'qualinav_data_hub_measure_versions';
        if (QN_DB::table_exists($measure_table)) {
            $version_join = QN_DB::table_exists($version_table)
                ? " LEFT JOIN {$version_table} v ON v.measure_id = m.id AND v.is_current = 1 AND v.status = 'active'"
                : '';
            $version_columns = $version_join
                ? ', v.id AS measure_version_id, v.version_label, v.effective_start_date, v.effective_end_date'
                : ', NULL AS measure_version_id, NULL AS version_label, NULL AS effective_start_date, NULL AS effective_end_date';
            $rows = $wpdb->get_results(
                "SELECT m.measure_key, m.programme, m.category, m.title, m.reporting_period_type{$version_columns} FROM {$measure_table} m{$version_join} WHERE m.status = 'active' ORDER BY m.sort_order ASC, m.id ASC",
                ARRAY_A
            );
            foreach ((array) $rows as $row) {
                $key = isset($row['measure_key']) ? (string) $row['measure_key'] : '';
                if ($key !== '' && in_array($key, $measure_keys, true)) {
                    $library[$key] = $row;
                }
            }
        }

        $ownership = array();
        $ownership_table = $wpdb->prefix . 'qualinav_mbqip_report_ownership';
        $context = self::organization_context($organization_id);
        if (QN_DB::table_exists($ownership_table)) {
            $owner_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT measure_key, owner_user_id FROM {$ownership_table} WHERE organization_id = %d OR org_key = %s ORDER BY updated_at DESC, id DESC",
                absint($organization_id),
                $context['org_key']
            ), ARRAY_A);
            foreach ((array) $owner_rows as $owner_row) {
                $key = isset($owner_row['measure_key']) ? (string) $owner_row['measure_key'] : '';
                if ($key !== '' && !isset($ownership[$key])) {
                    $ownership[$key] = absint($owner_row['owner_user_id']);
                }
            }
        }

        $definitions_by_key = array();
        foreach (self::definitions() as $definition) {
            $definitions_by_key[$definition['measure_key']][] = $definition;
        }

        $result = array();
        foreach ($measure_keys as $measure_key) {
            $row = isset($library[$measure_key]) ? $library[$measure_key] : array();
            $routes = array();
            foreach (isset($definitions_by_key[$measure_key]) ? $definitions_by_key[$measure_key] : array() as $definition) {
                $routes[] = array('question_key' => $definition['question_key'], 'setup_value' => $definition['setup_value']);
            }
            $fallback = $routes ? $routes[0]['setup_value'] : $measure_key;
            $result[] = array(
                'measure_key' => $measure_key,
                'report_name' => !empty($row['title']) ? html_entity_decode($row['title'], ENT_QUOTES, 'UTF-8') : $fallback,
                'category' => !empty($row['programme']) ? sanitize_key($row['programme']) : '',
                'program_tags' => !empty($row['programme']) ? strtoupper((string) $row['programme']) : '',
                'frequency' => !empty($row['reporting_period_type']) ? sanitize_key($row['reporting_period_type']) : '',
                'owner_user_id' => isset($ownership[$measure_key]) ? $ownership[$measure_key] : 0,
                'measure_version_id' => !empty($row['measure_version_id']) ? absint($row['measure_version_id']) : 0,
                'measure_version_label' => isset($row['version_label']) ? (string) $row['version_label'] : '',
                'effective_start_date' => isset($row['effective_start_date']) ? (string) $row['effective_start_date'] : '',
                'effective_end_date' => isset($row['effective_end_date']) ? (string) $row['effective_end_date'] : '',
                'canonical_source' => 'data_hub',
                'setup_routes' => $routes,
                'from_step4' => true,
            );
        }

        return $result;
    }

    private static function organization_users($organization_id)
    {
        global $wpdb;

        $mapping_table = QN_DB::user_organizations_table();
        if (!QN_DB::table_exists($mapping_table)) {
            return array();
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, m.qualinav_role FROM {$wpdb->users} u INNER JOIN {$mapping_table} m ON m.user_id = u.ID WHERE m.organization_id = %d AND m.status = %s ORDER BY u.display_name ASC, u.ID ASC",
            absint($organization_id),
            'active'
        ), ARRAY_A);

        return array_map(function ($row) {
            return array(
                'user_id' => absint($row['ID']),
                'display_name' => (string) $row['display_name'],
                'role' => sanitize_key($row['qualinav_role']),
            );
        }, (array) $rows);
    }

    private static function list_matches($values, $aliases)
    {
        foreach ((array) $values as $value) {
            if (self::matches($value, $aliases)) {
                return true;
            }
        }
        return false;
    }

    private static function matches($value, $aliases)
    {
        $needle = self::normalized($value);
        foreach ((array) $aliases as $alias) {
            if ($needle !== '' && $needle === self::normalized($alias)) {
                return true;
            }
        }
        return false;
    }

    private static function normalized($value)
    {
        return sanitize_title(str_replace(array('—', 'â€”'), '-', (string) $value));
    }

    private static function definitions()
    {
        return array(
            self::definition('cah_quality_infrastructure_assessment', 'external_reporting_flex_mbqip', 'CAH Quality Infrastructure', array('CAH Quality Infrastructure'), 'mbqip', array('CAH Quality Infrastructure Assessment')),
            self::definition('hcp_imm_3_healthcare_personnel_influenza_vaccination', 'external_reporting_flex_mbqip', 'HCP Influenza Vaccination', array('HCP Influenza Vaccination'), 'mbqip', array('HCP/IMM-3 — Healthcare Personnel Influenza Vaccination')),
            self::definition('antibiotic_stewardship', 'external_reporting_flex_mbqip', 'Antibiotic Stewardship (NHSN Survey)', array('Antibiotic Stewardship (NHSN Survey)'), 'mbqip', array('Antibiotic Stewardship')),
            self::definition('edtc_emergency_department_transfer_communication', 'external_reporting_flex_mbqip', 'EDTC All-or-None Composite', array('EDTC All-or-None Composite'), 'mbqip', array('EDTC — Emergency Department Transfer Communication')),
            self::definition('op_18_median_ed_arrival_to_departure_time_discharged_patients', 'external_reporting_flex_mbqip', 'OP-18 ED Throughput', array('OP-18 ED Throughput'), 'mbqip', array('OP-18 — Median ED Arrival to Departure Time (Discharged Patients)')),
            self::definition('op_22_patient_left_without_being_seen_lwbs_rate', 'external_reporting_flex_mbqip', 'OP-22 Left Without Being Seen', array('OP-22 Left Without Being Seen'), 'mbqip', array('OP-22 — Patient Left Without Being Seen (LWBS) Rate')),
            self::definition('safe_use_of_opioids_ecqm_mbqip_submission', 'external_reporting_flex_mbqip', 'Safe Use of Opioids eCQM', array('Safe Use of Opioids eCQM'), 'mbqip', array('Safe Use of Opioids eCQM — MBQIP Submission')),
            self::definition('hcahps-composite-1-communication-with-nurses', 'external_reporting_flex_mbqip', 'HCAHPS — Composite 1: Communication with Nurses', array('HCAHPS — Composite 1: Communication with Nurses'), 'mbqip', array('HCAHPS — Composite 1: Communication with Nurses')),
            self::definition('hcahps-composite-2-communication-with-doctors', 'external_reporting_flex_mbqip', 'HCAHPS — Composite 2: Communication with Doctors', array('HCAHPS — Composite 2: Communication with Doctors'), 'mbqip', array('HCAHPS — Composite 2: Communication with Doctors')),
            self::definition('hcahps-composite-3-restfulness-of-hospital-environment', 'external_reporting_flex_mbqip', 'HCAHPS — Composite 3: Restfulness of Hospital Environment', array('HCAHPS — Composite 3: Restfulness of Hospital Environment'), 'mbqip', array('HCAHPS — Composite 3: Restfulness of Hospital Environment')),
            self::definition('hcahps-composite-4-responsiveness-of-hospital-staff', 'external_reporting_flex_mbqip', 'HCAHPS — Composite 4: Responsiveness of Hospital Staff', array('HCAHPS — Composite 4: Responsiveness of Hospital Staff'), 'mbqip', array('HCAHPS — Composite 4: Responsiveness of Hospital Staff')),
            self::definition('hcahps-composite-5-communication-about-medicines', 'external_reporting_flex_mbqip', 'HCAHPS — Composite 5: Communication About Medicines', array('HCAHPS — Composite 5: Communication About Medicines'), 'mbqip', array('HCAHPS — Composite 5: Communication About Medicines')),
            self::definition('hcahps-composite-6-discharge-information-care-coordination', 'external_reporting_flex_mbqip', 'HCAHPS — Composite 6: Discharge Information / Care Coordination', array('HCAHPS — Composite 6: Discharge Information / Care Coordination'), 'mbqip', array('HCAHPS — Composite 6: Discharge Information / Care Coordination')),
            self::definition('hcahps-composite-7-transitions-of-care', 'external_reporting_flex_mbqip', 'HCAHPS — Composite 7: Transitions of Care', array('HCAHPS — Composite 7: Transitions of Care'), 'mbqip', array('HCAHPS — Composite 7: Transitions of Care')),
            self::definition('hcahps-q7-cleanliness-of-hospital-environment', 'external_reporting_flex_mbqip', 'HCAHPS — Q7: Cleanliness of Hospital Environment', array('HCAHPS — Q7: Cleanliness of Hospital Environment'), 'mbqip', array('HCAHPS — Q7: Cleanliness of Hospital Environment')),
            self::definition('hcahps-q20-info-about-symptoms-to-watch-for-after-discharge', 'external_reporting_flex_mbqip', 'HCAHPS — Q20: Info About Symptoms to Watch For After Discharge', array('HCAHPS — Q20: Info About Symptoms to Watch For After Discharge'), 'mbqip', array('HCAHPS — Q20: Info About Symptoms to Watch For After Discharge')),
            self::definition('hcahps-q24-overall-rating-of-hospital-0-10', 'external_reporting_flex_mbqip', 'HCAHPS — Q24: Overall Rating of Hospital (0-10)', array('HCAHPS — Q24: Overall Rating of Hospital (0-10)'), 'mbqip', array('HCAHPS — Q24: Overall Rating of Hospital (0-10)')),
            self::definition('hcahps-q5-willingness-to-recommend-hospital', 'external_reporting_flex_mbqip', 'HCAHPS — Q5: Willingness to Recommend Hospital', array('HCAHPS — Q5: Willingness to Recommend Hospital'), 'mbqip', array('HCAHPS — Q5: Willingness to Recommend Hospital')),
            self::definition('cauti', 'internal_monitoring_infection_prevention', 'CAUTI', array('CAUTI'), 'hacs_hais', array('cauti')),
            self::definition('clabsi', 'internal_monitoring_infection_prevention', 'CLABSI', array('CLABSI'), 'hacs_hais', array('clabsi')),
            self::definition('c_diff', 'internal_monitoring_infection_prevention', 'C. difficile Infections', array('C. difficile Infections'), 'hacs_hais', array('c_diff')),
            self::definition('mrsa', 'internal_monitoring_infection_prevention', 'MRSA Bacteremia', array('MRSA Bacteremia'), 'hacs_hais', array('mrsa')),
            self::definition('falls_with_injury', 'internal_monitoring_patient_safety_events', 'Falls With Injury', array('Falls With Injury'), 'hacs_hais', array('falls_with_injury')),
            self::definition('pressure_ulcers_3_plus', 'internal_monitoring_patient_safety_events', 'Pressure Injuries (HAPI)', array('Pressure Injuries (HAPI)'), 'hacs_hais', array('pressure_ulcers_3_plus')),
            self::definition('readmissions', 'internal_monitoring_ed_care_transitions', '30-Day Readmissions', array('30-Day Readmissions'), 'hacs_hais', array('readmissions')),
            self::definition('sepsis_mortality', 'internal_monitoring_clinical_case_review', 'Sepsis Mortality', array('Sepsis Mortality'), 'hacs_hais', array('sepsis_mortality')),
            self::definition('hcp_imm_3_healthcare_personnel_influenza_vaccination', 'internal_monitoring_infection_prevention', 'Staff Influenza Vaccination', array('Staff Influenza Vaccination'), 'mbqip', array('HCP/IMM-3 — Healthcare Personnel Influenza Vaccination')),
            self::definition('antibiotic_stewardship', 'internal_monitoring_infection_prevention', 'Antibiotic Stewardship', array('Antibiotic Stewardship'), 'mbqip', array('Antibiotic Stewardship')),
            self::definition('edtc_emergency_department_transfer_communication', 'internal_monitoring_ed_care_transitions', 'ED Transfer Communication (EDTC)', array('ED Transfer Communication (EDTC)'), 'mbqip', array('EDTC — Emergency Department Transfer Communication')),
            self::definition('op_22_patient_left_without_being_seen_lwbs_rate', 'internal_monitoring_ed_care_transitions', 'Left Without Being Seen', array('Left Without Being Seen'), 'mbqip', array('OP-22 — Patient Left Without Being Seen (LWBS) Rate')),
            self::definition('clabsi', 'external_reporting_nhsn', 'CLABSI', array('CLABSI'), 'hacs_hais', array('clabsi')),
            self::definition('cauti', 'external_reporting_nhsn', 'CAUTI', array('CAUTI'), 'hacs_hais', array('cauti')),
            self::definition('c_diff', 'external_reporting_nhsn', 'C. difficile LabID Events', array('C. difficile LabID Events'), 'hacs_hais', array('c_diff')),
            self::definition('mrsa', 'external_reporting_nhsn', 'MRSA Bacteremia LabID Events', array('MRSA Bacteremia LabID Events'), 'hacs_hais', array('mrsa'))
        );
    }

    private static function hcahps_coverage_values()
    {
        $values = array();
        foreach (self::definitions() as $definition) {
            if (strpos($definition['measure_key'], 'hcahps-') === 0) {
                $values = array_merge($values, $definition['coverage_values']);
            }
        }
        return array_values(array_unique($values));
    }

    private static function hcahps_measure_keys()
    {
        $keys = array();
        foreach (self::definitions() as $definition) {
            if (strpos($definition['measure_key'], 'hcahps-') === 0) {
                $keys[] = $definition['measure_key'];
            }
        }
        return array_values(array_unique($keys));
    }

    private static function definition($measure_key, $question_key, $setup_value, $setup_values, $coverage_group, $coverage_values)
    {
        return compact('measure_key', 'question_key', 'setup_value', 'setup_values', 'coverage_group', 'coverage_values');
    }
}
