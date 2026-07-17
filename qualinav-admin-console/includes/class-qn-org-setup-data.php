<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Org_Setup_Data
{
    public static function save_answer($organization_id, $question_key, $answer, $user_id)
    {
        $question_key = sanitize_key($question_key);
        $answers = self::saved_answer_map($organization_id);
        $answers[$question_key] = $answer;

        if (in_array($question_key, self::profile_keys(), true)) {
            self::upsert_profile($organization_id, array_intersect_key($answers, array_flip(self::profile_keys())), $user_id);
            return;
        }

        if (in_array($question_key, self::accreditation_keys(), true)) {
            self::upsert_accreditation($organization_id, array_intersect_key($answers, array_flip(self::accreditation_keys())), $user_id);
            self::sync_milestones($organization_id, $answers, $user_id);
            return;
        }

        if (in_array($question_key, self::services_keys(), true)) {
            self::merge_single_json(QN_DB::org_services_table(), $organization_id, array_intersect_key($answers, array_flip(self::services_keys())), $user_id);
            return;
        }

        if (in_array($question_key, self::committee_reporting_keys(), true)) {
            self::sync_committees_and_reporting($organization_id, array_intersect_key($answers, array_flip(self::committee_reporting_keys())), $user_id);
            self::sync_milestones($organization_id, $answers, $user_id);
            return;
        }

        if (in_array($question_key, self::plans_monitoring_keys(), true)) {
            self::sync_plans_policies_monitoring($organization_id, array_intersect_key($answers, array_flip(self::plans_monitoring_keys())), $user_id);
            self::sync_milestones($organization_id, $answers, $user_id);
            return;
        }

        if (in_array($question_key, self::measure_qi_keys(), true)) {
            self::sync_measure_qi_milestones($organization_id, array_intersect_key($answers, array_flip(self::measure_qi_keys())), $user_id);
            return;
        }

        if (in_array($question_key, self::goals_learning_contacts_keys(), true)) {
            self::sync_goals_learning_contacts($organization_id, array_intersect_key($answers, array_flip(self::goals_learning_contacts_keys())), $user_id);
            return;
        }

        if (in_array($question_key, self::regulatory_tools_keys(), true)) {
            self::sync_regulatory_tools_preferences($organization_id, array_intersect_key($answers, array_flip(self::regulatory_tools_keys())), $user_id);
            self::sync_milestones($organization_id, $answers, $user_id);
        }
    }

    public static function save_answers($organization_id, $answers, $user_id)
    {
        $answers = (array) $answers;

        self::upsert_profile($organization_id, array_intersect_key($answers, array_flip(self::profile_keys())), $user_id);
        self::upsert_accreditation($organization_id, array_intersect_key($answers, array_flip(self::accreditation_keys())), $user_id);
        self::merge_single_json(QN_DB::org_services_table(), $organization_id, array_intersect_key($answers, array_flip(self::services_keys())), $user_id);
        self::sync_committees_and_reporting($organization_id, array_intersect_key($answers, array_flip(self::committee_reporting_keys())), $user_id);
        self::sync_plans_policies_monitoring($organization_id, array_intersect_key($answers, array_flip(self::plans_monitoring_keys())), $user_id);
        self::sync_measure_qi_milestones($organization_id, array_intersect_key($answers, array_flip(self::measure_qi_keys())), $user_id);
        self::sync_goals_learning_contacts($organization_id, array_intersect_key($answers, array_flip(self::goals_learning_contacts_keys())), $user_id);
        self::sync_regulatory_tools_preferences($organization_id, array_intersect_key($answers, array_flip(self::regulatory_tools_keys())), $user_id);
        self::sync_milestones($organization_id, $answers, $user_id);
    }

    private static function upsert_profile($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        $data = self::audit_columns($user_id);
        $map = array(
            'hospital_name' => 'hospital_name',
            'hospital_city' => 'city',
            'hospital_state' => 'state_code',
            'licensed_beds' => 'licensed_beds',
            'acute_beds' => 'acute_beds',
            'swing_beds' => 'swing_beds',
            'is_critical_access_hospital' => 'is_critical_access_hospital',
            'independent_or_system' => 'independent_or_system',
            'quality_director_name' => 'quality_director_name',
            'quality_director_role_start_date' => 'quality_director_role_start_date',
            'quality_director_background' => 'quality_director_background',
        );

        foreach ($map as $question_key => $column) {
            if (array_key_exists($question_key, $answers)) {
                $data[$column] = self::scalar($answers[$question_key]);
            }
        }

        $data['source_answers_json'] = self::merge_source_answers(QN_DB::org_profile_table(), $organization_id, $answers, 'source_answers_json');
        self::upsert_single(QN_DB::org_profile_table(), $organization_id, $data);
        self::sync_organization_profile_columns($organization_id, $answers);
    }

    private static function saved_answer_map($organization_id)
    {
        if (class_exists('QN_Questionnaire')) {
            $answers = QN_Questionnaire::get_answer_map($organization_id);
            if (is_array($answers)) {
                return $answers;
            }
        }

        return array();
    }

    private static function upsert_accreditation($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        $data = self::audit_columns($user_id);
        $map = array(
            'accreditation_status' => 'accreditation_status',
            'accrediting_body' => 'accrediting_body',
            'cms_certification_pathway' => 'cms_certification_pathway',
            'state_survey_agency' => 'state_survey_agency',
            'life_safety_survey_agency' => 'life_safety_survey_agency',
            'open_plans_of_correction' => 'open_plans_of_correction',
            'projected_next_survey_window' => 'projected_next_survey_window',
            'historical_deficiency_areas' => 'historical_deficiency_areas',
            'current_readiness_activities' => 'current_readiness_activities',
        );

        foreach ($map as $question_key => $column) {
            if (array_key_exists($question_key, $answers)) {
                $data[$column] = self::value_for_storage($answers[$question_key]);
            }
        }

        $data['source_answers_json'] = self::merge_source_answers(QN_DB::org_accreditation_table(), $organization_id, $answers, 'source_answers_json');
        self::upsert_single(QN_DB::org_accreditation_table(), $organization_id, $data);

        if (array_key_exists('survey_history', $answers)) {
            self::sync_collection(QN_DB::org_survey_history_table(), $organization_id, 'survey_key', 'survey_title', 'survey', $answers['survey_history'], $user_id);
        }
    }

    private static function merge_single_json($table, $organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        $data = self::audit_columns($user_id);
        $data['data_json'] = self::merge_source_answers($table, $organization_id, $answers, 'data_json');
        $data['source_answers_json'] = self::merge_source_answers($table, $organization_id, $answers, 'source_answers_json');
        self::upsert_single($table, $organization_id, $data);
    }

    private static function sync_committees_and_reporting($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        if (array_key_exists('committee_list', $answers)) {
            self::sync_collection(QN_DB::org_committees_table(), $organization_id, 'committee_key', 'committee_name', 'committee', $answers['committee_list'], $user_id);
        }

        foreach (array('committee_required_status', 'standing_agenda_items', 'minutes_owner_location', 'board_agenda_timing') as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_committees_table(), $organization_id, 'committee_key', 'committee_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }

        foreach (array('reporting_obligations', 'mbqip_measure_set', 'backup_preparer', 'report_lead_time', 'approval_requirements') as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_reporting_requirements_table(), $organization_id, 'requirement_key', 'requirement_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }
    }

    private static function sync_plans_policies_monitoring($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        if (array_key_exists('plan_policy_inventory', $answers) && is_array($answers['plan_policy_inventory'])) {
            foreach ($answers['plan_policy_inventory'] as $item) {
                if (!is_array($item) || empty($item['policy_key'])) {
                    continue;
                }
                $policy_key = sanitize_key($item['policy_key']);
                $policy_name = !empty($item['policy_name']) ? sanitize_text_field($item['policy_name']) : self::label_from_key($policy_key);
                self::upsert_collection_item(QN_DB::org_plans_table(), $organization_id, 'plan_key', 'plan_name', $policy_key, $policy_name, $item, $user_id);
            }
        }

        foreach (array(
            'qapi_plan_status',
            'patient_safety_plan_status',
            'infection_prevention_plan_status',
            'emergency_preparedness_plan_status',
            'risk_management_plan_status',
        ) as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_plans_table(), $organization_id, 'plan_key', 'plan_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }

        foreach (array('plan_location_authority', 'policy_management_system', 'annual_policy_review_cycle', 'templates_needed') as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_policy_reviews_table(), $organization_id, 'review_key', 'review_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }

        foreach (array(
            'morbidity_mortality_monitoring',
            'blood_usage_review',
            'medication_safety_monitoring',
            'operative_invasive_review',
            'anesthesia_sedation_monitoring',
            'sentinel_never_event_protocol',
            'ancillary_services_review',
            'contracted_service_quality_data_flow',
            'weakest_monitoring_areas',
        ) as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_monitoring_areas_table(), $organization_id, 'area_key', 'area_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }
    }

    private static function sync_measure_qi_milestones($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        if (array_key_exists('mbqip_measure_set', $answers) || array_key_exists('mbqip_upload', $answers)) {
            $source = array_intersect_key($answers, array_flip(array('mbqip_measure_set', 'mbqip_upload')));
            self::upsert_milestone($organization_id, 'mbqip_reporting_schedule', 'MBQIP Reporting Schedule', 'reporting', 'needs_attention', 'Annual / Quarterly', 'mbqip_report', 0, $source, $user_id);
        }
    }

    private static function sync_goals_learning_contacts($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        foreach (array(
            'department_goals_this_year',
            'department_goals_two_three_years',
            'protected_workflow_goals',
            'program_gaps',
            'strategic_plan_alignment',
        ) as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_goals_table(), $organization_id, 'goal_key', 'goal_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }

        foreach (array(
            'new_to_quality_director_role',
            'time_in_current_role',
            'quality_certifications',
            'confidence_foundational',
            'confidence_qi_patient_safety',
            'confidence_specialized_areas',
            'confidence_professional_development',
            'activate_first_30_days_track',
            'learning_format_preference',
        ) as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_learning_items_table(), $organization_id, 'learning_key', 'learning_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }

        foreach (array(
            'state_flex_contact',
            'state_office_rural_health_contact',
            'state_hospital_association_contact',
            'state_survey_agency_contacts',
            'peer_cah_contacts',
            'accreditation_liaison',
            'referral_hospital_contacts',
        ) as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_contacts_table(), $organization_id, 'contact_key', 'contact_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }
    }

    private static function sync_regulatory_tools_preferences($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        if (array_key_exists('monitored_sources', $answers)) {
            self::sync_collection(QN_DB::org_regulatory_sources_table(), $organization_id, 'source_key', 'source_name', 'regulatory_source', $answers['monitored_sources'], $user_id);
        }

        foreach (array('current_tools', 'calendar_system', 'ehr_system', 'incident_reporting_system', 'nhsn_qualitynet_access') as $key) {
            if (array_key_exists($key, $answers)) {
                self::upsert_collection_item(QN_DB::org_tools_table(), $organization_id, 'tool_key', 'tool_name', $key, self::label_from_key($key), $answers[$key], $user_id);
            }
        }

        $preference_keys = array('update_preference', 'auto_propose_task_adjustments', 'reminder_lead_time', 'reminder_buffer_time', 'backup_visibility_users', 'final_review_confirmation');
        $preferences = array_intersect_key($answers, array_flip($preference_keys));
        if (!$preferences) {
            return;
        }

        $data = self::audit_columns($user_id);
        foreach ($preference_keys as $key) {
            if (array_key_exists($key, $preferences)) {
                $data[$key] = self::value_for_storage($preferences[$key]);
            }
        }
        $data['source_answers_json'] = self::merge_source_answers(QN_DB::org_reminder_preferences_table(), $organization_id, $preferences, 'source_answers_json');
        self::upsert_single(QN_DB::org_reminder_preferences_table(), $organization_id, $data);
    }

    private static function sync_milestones($organization_id, $answers, $user_id)
    {
        if (!$answers) {
            return;
        }

        if (array_key_exists('committee_list', $answers)) {
            foreach (self::normalize_items($answers['committee_list'], 'committee') as $item) {
                self::upsert_milestone($organization_id, $item['key'], $item['name'], 'committee', 'needs_attention', self::extract_cadence($item['value'], 'Monthly'), 'committee', 0, $item['value'], $user_id);
            }
        }

        if (array_key_exists('life_safety_survey_agency', $answers) || array_key_exists('projected_next_survey_window', $answers)) {
            self::upsert_milestone($organization_id, 'life_safety_survey_window', 'Life Safety Survey Window', 'survey', 'on_track', self::scalar(isset($answers['projected_next_survey_window']) ? $answers['projected_next_survey_window'] : 'Annually'), 'survey', 0, $answers, $user_id);
            self::upsert_milestone($organization_id, 'next_accreditation_survey_window', 'Next Accreditation Survey Window', 'survey', 'needs_attention', self::scalar(isset($answers['projected_next_survey_window']) ? $answers['projected_next_survey_window'] : '36 months from last survey'), 'survey', 0, $answers, $user_id);
        }

        if (array_key_exists('weakest_monitoring_areas', $answers) || array_key_exists('morbidity_mortality_monitoring', $answers)) {
            self::upsert_milestone($organization_id, 'clinical_monitoring_reviews', 'Clinical Monitoring Reviews', 'monitoring', 'needs_attention', 'Varies', 'monitoring_area', 0, $answers, $user_id);
        }

        if (array_key_exists('annual_policy_review_cycle', $answers) || array_key_exists('templates_needed', $answers)) {
            self::upsert_milestone($organization_id, 'required_plans_policies_reviews', 'Required Plans & Policies Reviews', 'plans_policies', 'needs_attention', self::scalar(isset($answers['annual_policy_review_cycle']) ? $answers['annual_policy_review_cycle'] : 'Annual'), 'policy_review', 0, $answers, $user_id);
        }

        if (array_key_exists('monitored_sources', $answers) || array_key_exists('update_preference', $answers)) {
            self::upsert_milestone($organization_id, 'regulatory_accreditation_updates_monitoring', 'Regulatory & Accreditation Updates Monitoring', 'regulatory', 'on_track', 'Ongoing', 'regulatory_source', 0, $answers, $user_id);
        }
    }

    private static function sync_collection($table, $organization_id, $key_column, $name_column, $prefix, $answer, $user_id)
    {
        foreach (self::normalize_items($answer, $prefix) as $item) {
            self::upsert_collection_item($table, $organization_id, $key_column, $name_column, $item['key'], $item['name'], $item['value'], $user_id);
        }
    }

    private static function upsert_collection_item($table, $organization_id, $key_column, $name_column, $key, $name, $value, $user_id)
    {
        $data = self::audit_columns($user_id);
        $data[$key_column] = sanitize_key($key);
        $data[$name_column] = self::scalar($name);
        $data['status'] = self::extract_status($value);
        $data['cadence'] = self::extract_cadence($value);
        $data['owner'] = self::extract_owner($value);
        $data['details_json'] = self::encode($value);
        $data['source_answer_json'] = self::encode($value);

        self::upsert_by_key($table, $organization_id, $key_column, $data[$key_column], $data);
    }

    private static function upsert_milestone($organization_id, $key, $title, $category, $status, $cadence, $linked_type, $linked_id, $source, $user_id)
    {
        $data = self::audit_columns($user_id);
        $data['milestone_key'] = sanitize_key($key);
        $data['title'] = self::scalar($title);
        $data['category'] = sanitize_key($category);
        $data['status'] = sanitize_key($status);
        $data['cadence'] = self::scalar($cadence);
        $data['due_window'] = self::scalar($cadence);
        $data['linked_object_type'] = sanitize_key($linked_type);
        $data['linked_object_id'] = absint($linked_id);
        $data['source_answers_json'] = self::encode($source);

        self::upsert_by_key(QN_DB::org_milestones_table(), $organization_id, 'milestone_key', $data['milestone_key'], $data);
    }

    private static function sync_organization_profile_columns($organization_id, $answers)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        if (!QN_DB::table_exists($table)) {
            return;
        }

        $map = array(
            'hospital_name' => 'name',
            'ccn' => 'ccn',
            'hospital_city' => 'city',
            'hospital_zip' => 'zip',
            'licensed_beds' => 'licensed_beds',
            'acute_beds' => 'acute_beds',
            'swing_beds' => 'swing_beds',
        );
        $data = array();
        foreach ($map as $question_key => $column) {
            if (array_key_exists($question_key, $answers) && QN_DB::column_exists($table, $column)) {
                $data[$column] = self::scalar($answers[$question_key]);
            }
        }

        if (array_key_exists('is_critical_access_hospital', $answers) && QN_DB::column_exists($table, 'hospital_type')) {
            $is_cah = self::scalar($answers['is_critical_access_hospital']);
            if (in_array(strtolower($is_cah), array('1', 'yes', 'true', 'critical_access_hospital', 'critical access hospital'), true)) {
                $data['hospital_type'] = 'critical_access_hospital';
            }
        }
        if (array_key_exists('hospital_type', $answers) && QN_DB::column_exists($table, 'hospital_type')) {
            $hospital_type = strtolower(str_replace(array(' ', '-'), '_', self::scalar($answers['hospital_type'])));
            $map = array(
                'critical_access_hospital' => 'critical_access_hospital',
                'rural_pps_hospital' => 'rural_pps_hospital',
                'general_acute_care_ipps_hospital' => 'general_acute_care_ipps_hospital',
                'rural_emergency_hospital' => 'rural_emergency_hospital',
            );
            if (isset($map[$hospital_type])) {
                $data['hospital_type'] = $map[$hospital_type];
            }
        }

        if (array_key_exists('hospital_state', $answers) && QN_DB::column_exists($table, 'state_id')) {
            $state_value = strtolower(trim(self::scalar($answers['hospital_state'])));
            foreach (QN_Organizations::get_states() as $state) {
                if ($state_value !== '' && in_array($state_value, array(strtolower((string) $state['abbreviation']), strtolower((string) $state['name']), (string) absint($state['id'])), true)) {
                    $data['state_id'] = absint($state['id']);
                    break;
                }
            }
        }

        if (array_key_exists('independent_or_system', $answers) && QN_DB::column_exists($table, 'parent_system_id')) {
            $affiliation = sanitize_key(self::scalar($answers['independent_or_system']));
            if ($affiliation === 'independent') {
                $data['parent_system_id'] = null;
            } elseif (!empty($answers['system_network_name']) && class_exists('QN_Health_Systems')) {
                $system_name = trim(self::scalar($answers['system_network_name']));
                foreach (QN_Health_Systems::get_systems(array('limit' => 500)) as $system) {
                    if ($system_name !== '' && strcasecmp($system_name, (string) $system['name']) === 0) {
                        $data['parent_system_id'] = absint($system['id']);
                        break;
                    }
                }
            }
        }

        if ($data) {
            $wpdb->update($table, $data, array('id' => absint($organization_id)));
        }
    }

    private static function upsert_single($table, $organization_id, $data)
    {
        global $wpdb;

        $organization_id = absint($organization_id);
        $data['organization_id'] = $organization_id;
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE organization_id = %d", $organization_id));

        if ($existing) {
            unset($data['created_at']);
            $wpdb->update($table, $data, array('id' => absint($existing)));
        } else {
            $wpdb->insert($table, $data);
        }
    }

    private static function upsert_by_key($table, $organization_id, $key_column, $key, $data)
    {
        global $wpdb;

        $organization_id = absint($organization_id);
        $data['organization_id'] = $organization_id;
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE organization_id = %d AND {$key_column} = %s", $organization_id, $key));

        if ($existing) {
            unset($data['created_at']);
            $wpdb->update($table, $data, array('id' => absint($existing)));
        } else {
            $wpdb->insert($table, $data);
        }
    }

    private static function merge_source_answers($table, $organization_id, $answers, $column)
    {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare("SELECT {$column} FROM {$table} WHERE organization_id = %d", absint($organization_id)));
        $decoded = $existing ? json_decode($existing, true) : array();
        if (!is_array($decoded)) {
            $decoded = array();
        }

        foreach ($answers as $key => $value) {
            $decoded[sanitize_key($key)] = $value;
        }

        return self::encode($decoded);
    }

    private static function normalize_items($answer, $prefix)
    {
        $items = array();
        if ($answer === null || $answer === '') {
            return $items;
        }

        if (is_array($answer)) {
            if (!$answer) {
                return $items;
            }
            $is_list = array_keys($answer) === range(0, count($answer) - 1);
            $rows = $is_list ? $answer : array($answer);
        } else {
            $rows = array($answer);
        }

        foreach ($rows as $index => $row) {
            $name = self::extract_name($row);
            $key = sanitize_key($prefix . '_' . ($name ? $name : ($index + 1)));
            $items[] = array(
                'key' => $key,
                'name' => $name ? $name : self::label_from_key($prefix . '_' . ($index + 1)),
                'value' => $row,
            );
        }

        return $items;
    }

    private static function extract_name($value)
    {
        if (is_array($value)) {
            foreach (array('name', 'title', 'committee', 'label', 'source', 'tool', 'goal') as $key) {
                if (!empty($value[$key])) {
                    return self::scalar($value[$key]);
                }
            }
            $first = reset($value);
            return is_scalar($first) ? self::scalar($first) : '';
        }

        return self::scalar($value);
    }

    private static function extract_status($value)
    {
        if (is_array($value) && !empty($value['status'])) {
            return sanitize_key($value['status']);
        }

        return null;
    }

    private static function extract_cadence($value, $fallback = null)
    {
        if (is_array($value)) {
            foreach (array('cadence', 'frequency', 'interval', 'schedule') as $key) {
                if (!empty($value[$key])) {
                    return self::scalar($value[$key]);
                }
            }
        }

        return $fallback;
    }

    private static function extract_owner($value)
    {
        if (is_array($value)) {
            foreach (array('owner', 'responsible_party', 'lead', 'backup_preparer') as $key) {
                if (!empty($value[$key])) {
                    return self::scalar($value[$key]);
                }
            }
        }

        return null;
    }

    private static function audit_columns($user_id)
    {
        return array(
            'updated_by' => absint($user_id),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );
    }

    private static function value_for_storage($value)
    {
        return is_array($value) ? self::encode($value) : self::scalar($value);
    }

    private static function scalar($value)
    {
        if (is_array($value)) {
            return wp_json_encode($value);
        }

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    private static function encode($value)
    {
        return wp_json_encode($value);
    }

    private static function label_from_key($key)
    {
        return ucwords(str_replace('_', ' ', sanitize_key($key)));
    }

    private static function profile_keys()
    {
        return array('hospital_name', 'ccn', 'hospital_city', 'hospital_state', 'hospital_zip', 'licensed_beds', 'hospital_type', 'acute_beds', 'licensed_for_swing_beds', 'swing_beds', 'is_critical_access_hospital', 'independent_or_system', 'system_network_name', 'quality_leader_name', 'quality_leader_email', 'quality_leader_title', 'quality_leader_title_other', 'quality_director_name');
    }

    private static function accreditation_keys()
    {
        return array('survey_compliance_process', 'accrediting_body', 'accrediting_body_other', 'state_survey_agency', 'state_survey_agency_url', 'life_safety_survey_agency_status', 'life_safety_survey_agency', 'life_safety_survey_agency_url', 'last_accreditation_licensing_survey_date', 'other_certification_licensing_surveys_status', 'other_certification_licensing_surveys');
    }

    private static function services_keys()
    {
        return array('service_lines_core', 'service_lines_common', 'service_lines_growth_expansion', 'service_lines_other', 'emergency_department', 'surgery_invasive_procedures', 'surgery_procedure_types', 'obstetrics_labor_delivery', 'laboratory_model', 'laboratory_model_other', 'radiology_model', 'radiology_model_other', 'respiratory_therapy', 'rehabilitation_services', 'dietary_nutrition_services', 'pharmacy_model', 'pharmacy_model_other', 'anesthesia_moderate_sedation_model', 'anesthesia_moderate_sedation_model_other', 'blood_bank_model', 'blood_bank_model_other', 'transfusions_per_year', 'visiting_specialists', 'contracted_quality_monitoring_agreements');
    }

    private static function committee_reporting_keys()
    {
        return array('committee_list', 'committee_required_status', 'standing_agenda_items', 'minutes_owner_location', 'board_agenda_timing', 'reporting_obligations', 'mbqip_measure_set', 'backup_preparer', 'report_lead_time', 'approval_requirements');
    }

    private static function plans_monitoring_keys()
    {
        return array('plan_policy_inventory', 'qapi_plan_status', 'patient_safety_plan_status', 'infection_prevention_plan_status', 'emergency_preparedness_plan_status', 'risk_management_plan_status', 'plan_location_authority', 'policy_management_system', 'annual_policy_review_cycle', 'templates_needed', 'morbidity_mortality_monitoring', 'blood_usage_review', 'medication_safety_monitoring', 'operative_invasive_review', 'anesthesia_sedation_monitoring', 'sentinel_never_event_protocol', 'ancillary_services_review', 'contracted_service_quality_data_flow', 'weakest_monitoring_areas');
    }

    private static function measure_qi_keys()
    {
        return array('internal_monitoring_patient_safety_events', 'internal_monitoring_infection_prevention', 'internal_monitoring_medication_safety', 'internal_monitoring_clinical_case_review', 'internal_monitoring_ed_care_transitions', 'internal_monitoring_patient_experience', 'internal_monitoring_other', 'external_reporting_flex_mbqip', 'external_reporting_cms_iqr', 'external_reporting_cms_oqr', 'external_reporting_cms_payment_programs', 'external_reporting_nhsn', 'external_reporting_medicare_pi', 'external_reporting_state_other', 'external_reporting_voluntary', 'external_reporting_other', 'mbqip_upload', 'nhsn_hai_rates_upload', 'patient_experience_scores_upload', 'fall_rates_upload', 'pressure_injury_rates_upload', 'hand_hygiene_upload', 'other_dashboard_metrics', 'current_quality_dashboard', 'data_source_currency', 'qi_framework', 'project_charters_status', 'baseline_data_status');
    }

    private static function goals_learning_contacts_keys()
    {
        return array('department_goals_this_year', 'department_goals_two_three_years', 'protected_workflow_goals', 'program_gaps', 'strategic_plan_alignment', 'new_to_quality_director_role', 'time_in_current_role', 'quality_certifications', 'confidence_foundational', 'confidence_qi_patient_safety', 'confidence_specialized_areas', 'confidence_professional_development', 'activate_first_30_days_track', 'learning_format_preference', 'state_flex_contact', 'state_office_rural_health_contact', 'state_hospital_association_contact', 'state_survey_agency_contacts', 'peer_cah_contacts', 'accreditation_liaison', 'referral_hospital_contacts');
    }

    private static function regulatory_tools_keys()
    {
        return array('monitored_sources', 'update_preference', 'auto_propose_task_adjustments', 'current_tools', 'calendar_system', 'ehr_system', 'incident_reporting_system', 'nhsn_qualitynet_access', 'reminder_lead_time', 'reminder_buffer_time', 'backup_visibility_users', 'final_review_confirmation');
    }
}
