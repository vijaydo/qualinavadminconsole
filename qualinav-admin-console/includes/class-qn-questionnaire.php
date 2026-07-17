<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Questionnaire
{
    public static function seed_default_questionnaire()
    {
        global $wpdb;

        self::ensure_progress_tracking_column();

        $now = current_time('mysql');
        // Preserve saved answers while retiring questionnaire fields that are no longer
        // part of the active Hospital Setup experience.
        $wpdb->update(
            QN_DB::questionnaire_sections_table(),
            array('is_active' => 0, 'updated_at' => $now),
            array('is_active' => 1)
        );
        $wpdb->update(
            QN_DB::questionnaire_questions_table(),
            array('is_active' => 0, 'updated_at' => $now),
            array('is_active' => 1)
        );
        foreach (self::default_steps() as $index => $step) {
            $section_id = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . QN_DB::questionnaire_sections_table() . ' WHERE section_key = %s', $step['section_key']));
            $section_data = array(
                'section_key' => $step['section_key'],
                'title' => $step['title'],
                'description' => $step['description'],
                'sort_order' => $index + 1,
                'is_active' => 1,
                'updated_at' => $now,
            );

            if ($section_id) {
                $wpdb->update(QN_DB::questionnaire_sections_table(), $section_data, array('id' => absint($section_id)));
            } else {
                $section_data['created_at'] = $now;
                $wpdb->insert(QN_DB::questionnaire_sections_table(), $section_data);
                $section_id = $wpdb->insert_id;
            }

            foreach ($step['questions'] as $sort => $question) {
                $question_id = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . QN_DB::questionnaire_questions_table() . ' WHERE question_key = %s', $question['question_key']));
                $question_data = wp_parse_args($question, array(
                    'section_id' => absint($section_id),
                    'help_text' => '',
                    'options_json' => null,
                    'validation_json' => null,
                    'conditional_logic_json' => null,
                    'is_required' => 0,
                    'is_progress_tracked' => 0,
                    'sort_order' => $sort + 1,
                    'is_active' => 1,
                    'updated_at' => $now,
                ));
                foreach (array('options_json', 'validation_json', 'conditional_logic_json') as $json_field) {
                    if (is_array($question_data[$json_field])) {
                        $question_data[$json_field] = wp_json_encode($question_data[$json_field]);
                    }
                }

                if ($question_id) {
                    $wpdb->update(QN_DB::questionnaire_questions_table(), $question_data, array('id' => absint($question_id)));
                } else {
                    $question_data['created_at'] = $now;
                    $wpdb->insert(QN_DB::questionnaire_questions_table(), $question_data);
                }
            }
        }

        self::retire_replaced_questions(array(
            'survey_history',
            'projected_next_survey_window',
            'current_readiness_activities',
            'accreditation_status',
            'cms_certification_pathway',
            'open_plans_of_correction',
            'historical_deficiency_areas',
            'accreditation_360',
            'quality_director_role_start_date',
            'quality_director_background',
            'active_qi_projects',
        ), $now);
        self::backfill_last_survey_date($now);
    }

    private static function retire_replaced_questions($question_keys, $now)
    {
        global $wpdb;

        foreach ((array) $question_keys as $question_key) {
            $wpdb->update(
                QN_DB::questionnaire_questions_table(),
                array('is_active' => 0, 'updated_at' => $now),
                array('question_key' => sanitize_key($question_key))
            );
        }
    }

    private static function backfill_last_survey_date($now)
    {
        global $wpdb;

        $questions_table = QN_DB::questionnaire_questions_table();
        $answers_table = QN_DB::questionnaire_answers_table();
        $old_question_id = absint($wpdb->get_var($wpdb->prepare("SELECT id FROM {$questions_table} WHERE question_key = %s", 'survey_history')));
        $new_question_id = absint($wpdb->get_var($wpdb->prepare("SELECT id FROM {$questions_table} WHERE question_key = %s", 'last_accreditation_licensing_survey_date')));
        if (!$old_question_id || !$new_question_id) {
            return;
        }

        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$answers_table} WHERE question_id = %d", $old_question_id), ARRAY_A);
        foreach ((array) $rows as $row) {
            $history = json_decode(isset($row['answer_json']) ? $row['answer_json'] : '[]', true);
            $dates = array();
            foreach ((array) $history as $survey) {
                $date = is_array($survey) && isset($survey['survey_date']) ? self::normalize_date_answer($survey['survey_date']) : '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $dates[] = $date;
                }
            }
            if (!$dates) {
                continue;
            }
            rsort($dates, SORT_STRING);
            $organization_id = absint($row['organization_id']);
            $existing_id = absint($wpdb->get_var($wpdb->prepare("SELECT id FROM {$answers_table} WHERE organization_id = %d AND question_id = %d", $organization_id, $new_question_id)));
            if ($existing_id) {
                continue;
            }
            $wpdb->insert($answers_table, array(
                'organization_id' => $organization_id,
                'question_id' => $new_question_id,
                'answer_json' => wp_json_encode($dates[0]),
                'completed_by' => isset($row['completed_by']) ? absint($row['completed_by']) : 0,
                'completed_at' => !empty($row['completed_at']) ? $row['completed_at'] : $now,
                'updated_at' => $now,
            ));
        }
    }

    private static function ensure_progress_tracking_column()
    {
        global $wpdb;

        $table = QN_DB::questionnaire_questions_table();
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'is_progress_tracked'));
        if (!$column) {
            $wpdb->query("ALTER TABLE {$table} ADD is_progress_tracked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_required");
        }
    }

    public static function get_sections()
    {
        global $wpdb;

        return $wpdb->get_results('SELECT * FROM ' . QN_DB::questionnaire_sections_table() . ' WHERE is_active = 1 ORDER BY sort_order ASC, id ASC', ARRAY_A);
    }

    public static function get_section($section_key)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . QN_DB::questionnaire_sections_table() . ' WHERE section_key = %s AND is_active = 1', sanitize_key($section_key)), ARRAY_A);
    }

    public static function get_questions($section_key = null)
    {
        global $wpdb;

        if ($section_key) {
            $section = self::get_section($section_key);
            if (!$section) {
                return array();
            }
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . QN_DB::questionnaire_questions_table() . ' WHERE section_id = %d AND is_active = 1 ORDER BY sort_order ASC, id ASC', absint($section['id'])), ARRAY_A);
        } else {
            $rows = $wpdb->get_results('SELECT q.* FROM ' . QN_DB::questionnaire_questions_table() . ' q INNER JOIN ' . QN_DB::questionnaire_sections_table() . ' s ON q.section_id = s.id WHERE q.is_active = 1 AND s.is_active = 1 ORDER BY s.sort_order ASC, q.sort_order ASC', ARRAY_A);
        }

        return array_map(array(__CLASS__, 'normalize_question'), $rows);
    }

    public static function get_question_by_key($question_key)
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . QN_DB::questionnaire_questions_table() . ' WHERE question_key = %s AND is_active = 1', sanitize_key($question_key)), ARRAY_A);

        return $row ? self::normalize_question($row) : null;
    }

    public static function save_answer($organization_id, $question_key, $answer, $user_id)
    {
        global $wpdb;

        $question = self::get_question_by_key($question_key);
        if (!$question) {
            return new WP_Error('qn_question_not_found', __('Question not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $valid = self::validate_answer($question, $answer);
        if (is_wp_error($valid)) {
            return $valid;
        }
        $valid = self::validate_organization_user_references($organization_id, $question_key, $valid);
        if (is_wp_error($valid)) {
            return $valid;
        }

        $data = array(
            'organization_id' => absint($organization_id),
            'question_id' => absint($question['id']),
            'answer_json' => wp_json_encode($valid),
            'completed_by' => absint($user_id),
            'completed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . QN_DB::questionnaire_answers_table() . ' WHERE organization_id = %d AND question_id = %d', absint($organization_id), absint($question['id'])));
        if ($existing) {
            $wpdb->update(QN_DB::questionnaire_answers_table(), $data, array('id' => absint($existing)));
        } else {
            $wpdb->insert(QN_DB::questionnaire_answers_table(), $data);
        }

        if (class_exists('QN_Org_Setup_Data')) {
            QN_Org_Setup_Data::save_answer($organization_id, $question_key, $valid, $user_id);
        }

        return $valid;
    }

    public static function save_answers($organization_id, $answers, $user_id)
    {
        $saved = array();
        foreach ((array) $answers as $question_key => $answer) {
            $result = self::save_answer($organization_id, $question_key, $answer, $user_id);
            if (is_wp_error($result)) {
                return $result;
            }
            $saved[$question_key] = $result;
        }

        return $saved;
    }

    public static function get_answers($organization_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare('SELECT q.question_key, a.answer_json FROM ' . QN_DB::questionnaire_answers_table() . ' a INNER JOIN ' . QN_DB::questionnaire_questions_table() . ' q ON a.question_id = q.id WHERE a.organization_id = %d', absint($organization_id)), ARRAY_A);
    }

    public static function get_answer_map($organization_id, $hydrate_data_hub = true)
    {
        $map = array();
        foreach (self::get_answers($organization_id) as $row) {
            $map[$row['question_key']] = json_decode($row['answer_json'], true);
        }

        return $hydrate_data_hub && class_exists('QN_Data_Hub_Integration')
            ? QN_Data_Hub_Integration::hydrate_answers($organization_id, $map)
            : $map;
    }

    public static function calculate_section_progress($organization_id, $section_key)
    {
        $answers = self::get_answer_map($organization_id);
        return self::calculate_question_set_progress(self::get_questions($section_key), $answers);
    }

    public static function calculate_total_progress($organization_id)
    {
        $sections = self::get_sections();
        if (!$sections) {
            return 0;
        }

        $total = 0;
        foreach ($sections as $section) {
            $total += self::calculate_section_progress($organization_id, $section['section_key']);
        }

        return (int) round($total / count($sections));
    }

    public static function calculate_progress_snapshot($organization_id)
    {
        $snapshots = self::calculate_progress_snapshots(array($organization_id));
        return isset($snapshots[absint($organization_id)]) ? $snapshots[absint($organization_id)] : array(
            'total_percent' => 0,
            'step_progress' => array(),
        );
    }

    public static function calculate_progress_snapshots($organization_ids)
    {
        global $wpdb;

        $organization_ids = array_values(array_unique(array_filter(array_map('absint', (array) $organization_ids))));
        if (!$organization_ids) {
            return array();
        }

        $sections = self::get_sections();
        if (!$sections) {
            return array_fill_keys($organization_ids, array('total_percent' => 0, 'step_progress' => array()));
        }

        $answer_maps = array_fill_keys($organization_ids, array());
        $placeholders = implode(',', array_fill(0, count($organization_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT a.organization_id, q.question_key, a.answer_json FROM ' . QN_DB::questionnaire_answers_table() . ' a INNER JOIN ' . QN_DB::questionnaire_questions_table() . " q ON a.question_id = q.id WHERE a.organization_id IN ({$placeholders})",
                $organization_ids
            ),
            ARRAY_A
        );
        foreach ($rows as $row) {
            $answer_maps[absint($row['organization_id'])][$row['question_key']] = json_decode($row['answer_json'], true);
        }

        $questions_by_section = array();
        foreach (self::get_questions() as $question) {
            $section_id = absint($question['section_id']);
            if (!isset($questions_by_section[$section_id])) {
                $questions_by_section[$section_id] = array();
            }
            $questions_by_section[$section_id][] = $question;
        }

        $snapshots = array();
        foreach ($organization_ids as $organization_id) {
            $answers = $answer_maps[$organization_id];
            if (class_exists('QN_Data_Hub_Integration')) {
                $answers = QN_Data_Hub_Integration::hydrate_answers($organization_id, $answers, false);
            }
            $total = 0;
            $step_progress = array();
            foreach ($sections as $section) {
                $questions = isset($questions_by_section[absint($section['id'])]) ? $questions_by_section[absint($section['id'])] : array();
                $percent = self::calculate_question_set_progress($questions, $answers);

                $total += $percent;
                $step_progress[] = array(
                    'organization_id' => $organization_id,
                    'section_key' => $section['section_key'],
                    'status' => $percent >= 100 ? 'completed' : ($percent > 0 ? 'in_progress' : 'not_started'),
                    'percent_complete' => $percent,
                );
            }
            $snapshots[$organization_id] = array(
                'total_percent' => (int) round($total / count($sections)),
                'step_progress' => $step_progress,
            );
        }

        return $snapshots;
    }

    public static function update_section_progress($organization_id, $section_key, $user_id)
    {
        global $wpdb;

        $percent = self::calculate_section_progress($organization_id, $section_key);
        $status = $percent >= 100 ? 'completed' : ($percent > 0 ? 'in_progress' : 'not_started');
        $data = array(
            'organization_id' => absint($organization_id),
            'section_key' => sanitize_key($section_key),
            'status' => $status,
            'percent_complete' => $percent,
            'started_at' => $percent > 0 ? current_time('mysql') : null,
            'completed_at' => $percent >= 100 ? current_time('mysql') : null,
            'completed_by' => $percent >= 100 ? absint($user_id) : null,
            'updated_at' => current_time('mysql'),
        );

        $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . QN_DB::onboarding_progress_table() . ' WHERE organization_id = %d AND section_key = %s', absint($organization_id), sanitize_key($section_key)));
        if ($existing) {
            $wpdb->update(QN_DB::onboarding_progress_table(), $data, array('id' => absint($existing)));
        } else {
            $wpdb->insert(QN_DB::onboarding_progress_table(), $data);
        }

        return $data;
    }

    private static function calculate_question_set_progress($questions, $answers)
    {
        $questions = array_values((array) $questions);
        if (!$questions) {
            // Informational handoff steps, such as the Data Hub handoff, should not
            // reduce Hospital Setup completion.
            return 100;
        }

        $tracked_questions = array_values(array_filter($questions, function ($question) {
            return !empty($question['is_progress_tracked']);
        }));

        if (!$tracked_questions) {
            $tracked_questions = array_values(array_filter($questions, function ($question) {
                return !empty($question['is_required']);
            }));
        }

        if (!$tracked_questions) {
            $tracked_questions = $questions;
        }

        $applicable_questions = array_values(array_filter($tracked_questions, function ($question) use ($answers) {
            return self::evaluate_conditional_logic($question, $answers);
        }));

        if (!$applicable_questions) {
            return 0;
        }

        $completed = 0;
        foreach ($applicable_questions as $question) {
            if (array_key_exists($question['question_key'], $answers) && self::answer_has_value($answers[$question['question_key']])) {
                $completed++;
            }
        }

        return (int) round(($completed / count($applicable_questions)) * 100);
    }

    public static function validate_answer($question, $answer)
    {
        $type = $question['field_type'];
        if ($question['question_key'] === 'backup_visibility_users') {
            return is_array($answer) ? self::sanitize_deep($answer) : array();
        }
        $structured_keys = array(
            'minutes_owner_location', 'board_agenda_timing',
            'morbidity_mortality_monitoring', 'blood_usage_review', 'medication_safety_monitoring',
            'operative_invasive_review', 'anesthesia_sedation_monitoring', 'sentinel_never_event_protocol',
            'ancillary_services_review', 'contracted_service_quality_data_flow', 'weakest_monitoring_areas',
            'state_flex_contact', 'state_office_rural_health_contact', 'state_hospital_association_contact',
            'state_survey_agency_contacts', 'peer_cah_contacts', 'accreditation_liaison', 'referral_hospital_contacts',
        );
        if (in_array($question['question_key'], $structured_keys, true)) {
            $structured = is_array($answer) ? self::sanitize_deep($answer) : array('legacy' => sanitize_textarea_field($answer));
            if (isset($structured['email']) && $structured['email'] !== '' && !is_email($structured['email'])) {
                return new WP_Error('qn_invalid_contact_email', sprintf(__('Enter a valid email address for %s.', 'qualinav-admin-console'), $question['label']), array('status' => 400));
            }
            return $structured;
        }
        if ($question['question_key'] === 'survey_history') {
            return self::sanitize_survey_history($answer);
        }
        if (in_array($type, array('repeater', 'plan_status', 'multiselect'), true)) {
            return is_array($answer) ? self::sanitize_deep($answer) : array();
        }
        if ($type === 'number') {
            if ($answer === '' || $answer === null) {
                return '';
            }
            return is_numeric($answer) ? max(0, floor(0 + $answer)) : '';
        }
        if ($type === 'checkbox') {
            return (bool) $answer;
        }
        if ($type === 'date') {
            return self::normalize_date_answer($answer);
        }
        if ($type === 'url') {
            $url = esc_url_raw(trim((string) $answer));
            if ($url === '') {
                return '';
            }
            if (!wp_http_validate_url($url)) {
                return new WP_Error('qn_invalid_url', sprintf(__('Enter a valid URL for %s.', 'qualinav-admin-console'), $question['label']), array('status' => 400));
            }
            return $url;
        }
        if (in_array($question['question_key'], array('historical_deficiency_areas', 'current_readiness_activities', 'surgery_procedure_types', 'radiology_model', 'anesthesia_moderate_sedation_model', 'mbqip_measure_set', 'department_goals_this_year', 'department_goals_two_three_years'), true)) {
            return is_array($answer) ? self::sanitize_deep($answer) : sanitize_textarea_field($answer);
        }

        return sanitize_textarea_field($answer);
    }

    private static function validate_organization_user_references($organization_id, $question_key, $answer)
    {
        $id_fields = array();
        if ($question_key === 'backup_preparer') {
            $user_id = absint($answer);
            if ($user_id && !QN_Users::user_has_organization($user_id, $organization_id)) {
                return new WP_Error('qn_invalid_organization_user', __('Select an active user from this hospital.', 'qualinav-admin-console'), array('status' => 400));
            }
            return $user_id;
        } elseif ($question_key === 'reporting_obligations') {
            $id_fields = array('owner_user_id', 'backup_user_id');
        } elseif ($question_key === 'backup_visibility_users') {
            $id_fields = array('user_id');
        } else {
            return $answer;
        }

        foreach ((array) $answer as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($id_fields as $field) {
                $user_id = isset($row[$field]) ? absint($row[$field]) : 0;
                $answer[$index][$field] = $user_id;
                if ($user_id && !QN_Users::user_has_organization($user_id, $organization_id)) {
                    return new WP_Error('qn_invalid_organization_user', __('Select an active user from this hospital.', 'qualinav-admin-console'), array('status' => 400));
                }
            }
            if ($question_key === 'reporting_obligations' && isset($row['measure_key'])) {
                $answer[$index]['measure_key'] = sanitize_key($row['measure_key']);
            }
        }

        return array_values((array) $answer);
    }

    private static function normalize_date_answer($answer)
    {
        $value = trim(sanitize_text_field((string) $answer));
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return $value;
    }

    public static function evaluate_conditional_logic($question, $answer_map)
    {
        $logic = isset($question['conditional_logic']) ? $question['conditional_logic'] : array();
        if (!$logic) {
            return true;
        }

        if (!empty($logic['hide_if'])) {
            foreach ($logic['hide_if'] as $key => $value) {
                if (isset($answer_map[$key]) && $answer_map[$key] === $value) {
                    return false;
                }
            }
        }

        if (!empty($logic['show_if'])) {
            foreach ($logic['show_if'] as $key => $value) {
                if (!isset($answer_map[$key]) || $answer_map[$key] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function default_steps()
    {
        $yes_no = array('yes', 'no', 'not_sure');
        $hospital_types = array('Critical Access Hospital', 'Rural PPS Hospital', 'General Acute Care IPPS Hospital', 'Rural Emergency Hospital');
        $system_network_statuses = array(
            array('value' => 'independent', 'label' => 'Independent'),
            array('value' => 'system_owned', 'label' => 'System-Owned'),
            array('value' => 'network_affiliated', 'label' => 'Network-Affiliated'),
            array('value' => 'other', 'label' => 'Other'),
        );
        $quality_leader_titles = array('VP of Quality', 'Quality Director', 'Quality Manager', 'Other');
        $quality_leader_experience = array('Less than one year', '1 - 5 years', '6 - 10 years', 'More than 10 years');
        $survey_processes = array(
            array('value' => 'direct_cms_state_survey', 'label' => 'Direct certification through a triennial CMS survey, conducted by our state survey body on behalf of CMS'),
            array('value' => 'deemed_accreditation_body_survey', 'label' => 'Deemed status through a triennial accreditation body survey, such as The Joint Commission'),
        );
        $accrediting_bodies = array(
            array('value' => 'joint_commission', 'label' => 'The Joint Commission'),
            array('value' => 'dnv', 'label' => 'DNV'),
            array('value' => 'hfap_hqic', 'label' => 'HFAP / HQIC'),
            array('value' => 'other', 'label' => 'Other'),
        );
        $life_safety_agency_options = array(
            array('value' => 'same_as_state_survey_agency', 'label' => 'Same as state survey agency'),
            array('value' => 'different_agency', 'label' => 'Different agency'),
            array('value' => 'not_sure', 'label' => 'Not sure'),
        );
        $other_survey_statuses = array(
            array('value' => 'yes', 'label' => 'Yes'),
            array('value' => 'no', 'label' => 'No'),
            array('value' => 'not_sure', 'label' => 'Not sure'),
        );
        $core_services = array('Emergency Department', 'Inpatient Acute Care', 'Swing Bed Services', 'Observation Services', 'Laboratory Services', 'Diagnostic Imaging', 'Pharmacy', 'Respiratory Therapy', 'Physical Therapy', 'Infusion Services', 'Anesthesia Coverage', 'Dietary Services');
        $common_services = array('Rural Health Clinic / Primary Care', 'General Surgery', 'Endoscopy and Colonoscopy', 'Obstetrics / Labor and Delivery', 'Gynecology', 'Orthopedics', 'Occupational Therapy', 'Speech-Language Pathology', 'Cardiac Rehabilitation', 'Pulmonary Rehabilitation', 'Sleep Studies', 'Wound Care', 'Visiting Specialist Clinics', 'Telehealth Services', 'Ambulance and EMS', 'Occupational Health Services', 'Diabetes Education', 'Nutrition Counseling', 'Mammography', 'Bone Density Screening (DEXA)', 'Echocardiography / Cardiac Diagnostics');
        $growth_services = array('Behavioral Health Integration', 'Senior Behavioral Health Unit', 'Skilled Nursing / Long-Term Care', 'Retail or 340B Contract Pharmacy', 'Oncology / Chemotherapy', 'Dialysis', 'Pain Management', 'Specialty Clinic Expansion', 'Home Health / Hospice Partnerships', 'Other Growth Service');
        $internal_patient_safety = array('Falls With Injury', 'Falls Without Injury', 'Restraint Use', 'Seclusion Use', 'Pressure Injuries (HAPI)', 'Medication Errors', 'Adverse Drug Events', 'Sentinel / Serious Safety Events', 'Near Miss Reporting', 'Wrong-Site / Universal Protocol', 'Workplace Violence Events', 'Patient Identification Errors');
        $internal_infection = array('CAUTI', 'CLABSI', 'Surgical Site Infections', 'C. difficile Infections', 'MRSA Bacteremia', 'Hand Hygiene Compliance', 'Staff Influenza Vaccination', 'Antibiotic Stewardship', 'Isolation Precaution Compliance');
        $internal_medication = array('High-Alert Medication Monitoring', 'Anticoagulation Monitoring', 'Glycemic Control / Hypoglycemia', 'Opioid Stewardship', 'Medication Reconciliation', 'IV Safety / Smart Pump Compliance');
        $internal_case_review = array('Mortality Reviews', 'Sepsis Mortality', 'Morbidity / Complication Reviews', 'Peer Review', 'Surgical / Procedural Case Review', 'Blood Utilization Review', 'Code Blue / Rapid Response Reviews');
        $internal_transitions = array('ED Transfer Communication (EDTC)', 'Door-to-Provider Time', 'ED Length of Stay', 'Left Without Being Seen', 'Sepsis Bundle Compliance (SEP-1)', 'Stroke Care Measures', 'Chest Pain / AMI Measures', 'Behavioral Health Boarding', 'Transfer Timeliness / Appropriateness', '30-Day Readmissions', '72-Hour ED Returns', 'Discharge Follow-Up Calls');
        $internal_experience = array('Grievances and Complaints', 'Swing Bed Quality Measures', 'Obstetric Outcomes', 'Critical Value Reporting', 'Informed Consent Compliance', 'Medical Record Delinquency', 'Advance Directive Documentation', 'Telehealth Quality Monitoring');
        $external_mbqip = array('EDTC All-or-None Composite', array('value' => 'HCAHPS', 'label' => 'HCAHPS All-or-None (includes all HCAHPS measures)'), 'Safe Use of Opioids eCQM', array('value' => 'ED Patient Experience (EDPEC)', 'label' => 'ED Patient Experience (EDPEC) (MBQIP)'), array('value' => 'OP-18 ED Throughput', 'label' => 'OP-18 ED Throughput (MBQIP / OQR)'), array('value' => 'OP-22 Left Without Being Seen', 'label' => 'OP-22 Left Without Being Seen (MBQIP / OQR)'), 'Antibiotic Stewardship (NHSN Survey)', array('value' => 'HCP Influenza Vaccination', 'label' => 'HCP Influenza Vaccination (MBQIP / NHSN)'), 'CAH Quality Infrastructure');
        $external_iqr = array('SEP-1 Sepsis Bundle', 'PC-01 Early Elective Delivery', 'Hybrid Hospital-Wide Readmission', 'Hybrid Hospital-Wide Mortality', 'eCQMs (Opioids, Malnutrition, etc.)', 'Patient Safety Structural Measure (PSSM) (NHSN Survey)');
        $external_oqr = array('OP-29 Colonoscopy Follow-Up Interval', 'Outpatient eCQMs / Web-Based Measures');
        $external_payment = array('HRRP 30-Day Readmissions', 'Hospital VBP Program', 'HAC Reduction Program');
        $external_nhsn = array('CLABSI', 'CAUTI', 'SSI (Colon / Hysterectomy)', 'C. difficile LabID Events', 'MRSA Bacteremia LabID Events', 'Antimicrobial Use and Resistance (AUR)', 'Respiratory Pathogen Reporting');
        $external_pi = array('eCQM Submission', 'Security Risk Analysis', 'SAFER Guides Attestation', 'Public Health Data Exchange (ELR, Syndromic, Immunizations)');
        $external_state = array('State Adverse / Sentinel Event Reporting', 'Reportable Communicable Diseases', 'State Immunization Registry', 'State Trauma Registry', 'State Cancer Registry', 'Vital Records (Birth / Death)', 'State Medicaid Quality Programs', 'State Hospital Association Data Programs');
        $external_voluntary = array('Get With The Guidelines (Stroke / HF)', 'ACC Chest Pain / NCDR Registries', 'Leapfrog Hospital Survey', 'Patient Safety Organization (PSO)', 'Surveys on Patient Safety Culture', 'Accreditation Measure Reporting (TJC / DNV)');
        return array(
            self::step('hospital_director_info', 'Hospital & Quality Leader Info', 'Confirm the hospital and Quality Leader details Scout should use.', array(
                array('hospital_name', 'Hospital name', 'text'), array('ccn', 'CCN', 'text'), array('hospital_city', 'City', 'text'), array('hospital_state', 'State', 'text'), array('hospital_zip', 'ZIP code', 'text'), array('licensed_beds', 'Licensed beds', 'number'), array('swing_beds', 'Swing beds', 'number'), array('hospital_type', 'Hospital type', 'select', false, $hospital_types), array('independent_or_system', 'Independent / system / network status', 'select', false, $system_network_statuses), array('system_network_name', 'System/network name', 'text'), array('quality_leader_name', 'Quality Leader name', 'text'), array('quality_leader_email', 'Quality Leader email', 'text'), array('quality_leader_title', 'Quality Leader title', 'select', false, $quality_leader_titles), array('quality_leader_title_other', 'Other Quality Leader title', 'text'),
            )),
            self::step('accreditation_survey_readiness', 'Regulatory and Accreditation & Survey Process', 'This helps Scout build your survey readiness roadmap.', array(
                array('survey_compliance_process', 'Which survey process does your hospital utilize to demonstrate compliance with Medicare Conditions of Participation?', 'select', false, $survey_processes), array('accrediting_body', 'Name of accreditation body', 'select', false, $accrediting_bodies), array('accrediting_body_other', 'Other accreditation body', 'text'), array('state_survey_agency', 'Name of your state survey body', 'text'), array('state_survey_agency_url', 'State survey body website', 'text'), array('life_safety_survey_agency_status', 'Life safety survey agency', 'select', false, $life_safety_agency_options), array('life_safety_survey_agency', 'Life safety survey agency name', 'text'), array('life_safety_survey_agency_url', 'Life safety survey agency website', 'text'), array('last_accreditation_licensing_survey_date', 'Date of last accreditation, certification, or licensing survey', 'date'), array('other_certification_licensing_surveys_status', 'Does your hospital get surveyed by other organizations for certifications or licensing?', 'select', false, $other_survey_statuses), array('other_certification_licensing_surveys', 'Other certification/licensing survey details', 'textarea'),
            )),
            self::step('services_clinical_model', 'Services & Clinical Model', 'Please check all services currently offered at your hospital.', array(
                array('service_lines_core', 'Core Services', 'multiselect', false, $core_services), array('service_lines_common', 'Commonly Offered Services', 'multiselect', false, $common_services), array('service_lines_growth_expansion', 'Growth and Expansion Services', 'multiselect', false, $growth_services), array('service_lines_other', 'Other services offered', 'textarea'), array('laboratory_model', 'Laboratory model', 'text'), array('laboratory_model_other', 'Other laboratory model details', 'textarea'), array('radiology_model', 'Radiology model', 'text'), array('radiology_model_other', 'Other radiology model details', 'textarea'), array('pharmacy_model', 'Pharmacy model', 'text'), array('pharmacy_model_other', 'Other pharmacy model details', 'textarea'), array('anesthesia_moderate_sedation_model', 'Anesthesia / moderate sedation model', 'text'), array('anesthesia_moderate_sedation_model_other', 'Other anesthesia or moderate sedation details', 'textarea'), array('blood_bank_model', 'Blood bank model', 'text'), array('blood_bank_model_other', 'Other blood bank model details', 'textarea'), array('transfusions_per_year', 'Transfusions per year', 'number'), array('emergency_department', 'Legacy emergency department', 'yes_no', false, $yes_no), array('surgery_invasive_procedures', 'Legacy surgery or invasive procedures', 'select', false, array('offered', 'limited', 'not_offered')), array('surgery_procedure_types', 'Legacy surgery procedure types', 'textarea'), array('obstetrics_labor_delivery', 'Legacy obstetrics / labor & delivery', 'yes_no', false, $yes_no), array('respiratory_therapy', 'Legacy respiratory therapy', 'yes_no', false, $yes_no), array('rehabilitation_services', 'Legacy rehabilitation services', 'yes_no', false, $yes_no), array('dietary_nutrition_services', 'Legacy dietary and nutrition services', 'yes_no', false, $yes_no), array('visiting_specialists', 'Legacy visiting specialists', 'yes_no', false, $yes_no), array('contracted_quality_monitoring_agreements', 'Legacy contracted quality monitoring agreements', 'textarea'),
            )),
            self::step('measures_qi_projects', 'Data & Reporting', 'Manage measures, submissions, deadlines, and performance monitoring in Data Hub.', array(
            )),
            self::step('committees_reporting', 'Meeting Cadence', 'Tell Scout where quality information is reviewed and how meetings roll up through the hospital.', array(
                array('report_lead_time', 'Default preparation lead time', 'text'), array('backup_preparer', 'Default backup preparer', 'text'), array('committee_list', 'Quality meeting cadence', 'repeater'),
            )),
            self::step('plans_policies_monitoring', 'Plans & Policies', 'Review required plans and policies and attach current documents for authorized Scout use.', array(
                array('plan_policy_inventory', 'Plan and policy inventory', 'repeater', true),
            )),
            self::step('regulatory_tools_preferences', 'Review & Access', 'Review missing setup details, confirm backup access, and submit when ready.', array(
                array('backup_visibility_users', 'Backup visibility users', 'textarea'), array('final_review_confirmation', 'Final review confirmation', 'checkbox', true),
            )),
        );
    }

    private static function step($key, $title, $description, $questions)
    {
        return array(
            'section_key' => $key,
            'title' => $title,
            'description' => $description,
            'questions' => array_map(function ($question) use ($key) {
                return array(
                    'question_key' => $question[0],
                    'label' => $question[1],
                    'field_type' => $question[2],
                    'is_required' => !empty($question[3]) ? 1 : 0,
                    'options_json' => isset($question[4]) ? $question[4] : self::default_options($question[0], $question[2]),
                    'conditional_logic_json' => self::default_conditional_logic($question[0]),
                    'help_text' => self::default_help_text($key, $question[0]),
                    'is_progress_tracked' => self::default_progress_tracked($question[0]) ? 1 : 0,
                );
            }, $questions),
        );
    }

    private static function default_progress_tracked($question_key)
    {
        static $tracked = null;
        if ($tracked === null) {
            $tracked = array_fill_keys(array(
                'hospital_name', 'ccn', 'hospital_city', 'hospital_state', 'hospital_zip', 'licensed_beds', 'swing_beds', 'hospital_type', 'independent_or_system', 'system_network_name', 'quality_leader_name', 'quality_leader_email', 'quality_leader_title', 'quality_leader_title_other',
                'survey_compliance_process', 'accrediting_body', 'accrediting_body_other', 'state_survey_agency', 'state_survey_agency_url', 'life_safety_survey_agency_status', 'life_safety_survey_agency', 'life_safety_survey_agency_url', 'last_accreditation_licensing_survey_date', 'other_certification_licensing_surveys_status', 'other_certification_licensing_surveys',
                'service_lines_core', 'service_lines_common', 'service_lines_growth_expansion', 'service_lines_other', 'laboratory_model', 'laboratory_model_other', 'radiology_model', 'radiology_model_other', 'pharmacy_model', 'pharmacy_model_other', 'anesthesia_moderate_sedation_model', 'anesthesia_moderate_sedation_model_other', 'blood_bank_model', 'blood_bank_model_other',
                'internal_monitoring_patient_safety_events', 'internal_monitoring_infection_prevention', 'internal_monitoring_medication_safety', 'internal_monitoring_clinical_case_review', 'internal_monitoring_ed_care_transitions', 'internal_monitoring_patient_experience', 'internal_monitoring_other', 'external_reporting_flex_mbqip', 'external_reporting_cms_iqr', 'external_reporting_cms_oqr', 'external_reporting_cms_payment_programs', 'external_reporting_nhsn', 'external_reporting_medicare_pi', 'external_reporting_state_other', 'external_reporting_voluntary', 'external_reporting_other',
                'committee_list', 'standing_agenda_items', 'minutes_owner_location', 'board_agenda_timing', 'reporting_obligations', 'backup_preparer', 'report_lead_time',
                'plan_policy_inventory', 'qapi_plan_status', 'patient_safety_plan_status', 'infection_prevention_plan_status', 'emergency_preparedness_plan_status', 'risk_management_plan_status', 'plan_location_authority', 'policy_management_system', 'annual_policy_review_cycle', 'templates_needed', 'morbidity_mortality_monitoring', 'blood_usage_review', 'medication_safety_monitoring', 'operative_invasive_review', 'anesthesia_sedation_monitoring', 'sentinel_never_event_protocol', 'ancillary_services_review', 'contracted_service_quality_data_flow', 'weakest_monitoring_areas',
                'department_goals_this_year', 'department_goals_two_three_years', 'protected_workflow_goals', 'program_gaps', 'strategic_plan_alignment', 'time_in_current_role', 'new_to_quality_director_role', 'quality_certifications', 'confidence_foundational', 'confidence_qi_patient_safety', 'confidence_specialized_areas', 'confidence_professional_development', 'activate_first_30_days_track', 'learning_format_preference', 'state_flex_contact', 'state_office_rural_health_contact', 'state_hospital_association_contact', 'state_survey_agency_contacts', 'peer_cah_contacts', 'accreditation_liaison', 'referral_hospital_contacts',
                'update_preference', 'auto_propose_task_adjustments', 'current_tools', 'calendar_system', 'ehr_system', 'incident_reporting_system', 'nhsn_qualitynet_access', 'reminder_lead_time', 'reminder_buffer_time', 'backup_visibility_users', 'final_review_confirmation',
            ), true);
        }

        return isset($tracked[$question_key]);
    }

    private static function default_options($question_key, $field_type)
    {
        if ($field_type !== 'repeater') {
            return null;
        }

        $columns = array(
            'survey_history' => array('survey_date', 'survey_type', 'surveying_agency', 'survey_outcome', 'poc_status', 'follow_up_window'),
            'committee_list' => array('committee_name', 'local_name', 'committee_frequency', 'committee_week_of_month', 'committee_weekday', 'committee_time', 'frequency_timing', 'user_role', 'reports_to', 'prep_lead_time'),
            'reporting_obligations' => array('is_reported', 'measure_key', 'report_name', 'category', 'program_tags', 'frequency', 'due_date_rule', 'due_date_details', 'due_dates', 'source_link', 'who_prepares', 'backup_preparer', 'owner_user_id', 'backup_user_id', 'submit_to_method', 'approval_required', 'prep_lead_time', 'payment_linked', 'event_triggered', 'measure_version_id', 'measure_version_label', 'effective_start_date', 'effective_end_date', 'canonical_source', 'from_step4'),
            'plan_policy_inventory' => array('policy_key', 'policy_name', 'category', 'date_last_approved', 'status', 'folded_into', 'notes', 'upload_status', 'document_id', 'document_name', 'document_version_id', 'ingestion_job_id', 'storage_path', 'scout_status'),
            'active_qi_projects' => array('project_aim', 'method', 'measure', 'status_next_milestone'),
        );

        return isset($columns[$question_key]) ? $columns[$question_key] : array('note');
    }

    private static function default_conditional_logic($question_key)
    {
        $logic = array(
            'quality_leader_title_other' => array('show_if' => array('quality_leader_title' => 'Other')),
            'system_network_name' => array('hide_if' => array('independent_or_system' => 'independent')),
            'accrediting_body' => array('show_if' => array('survey_compliance_process' => 'deemed_accreditation_body_survey')),
            'accrediting_body_other' => array('show_if' => array('accrediting_body' => 'other')),
            'life_safety_survey_agency' => array('show_if' => array('life_safety_survey_agency_status' => 'different_agency')),
            'life_safety_survey_agency_url' => array('show_if' => array('life_safety_survey_agency_status' => 'different_agency')),
            'other_certification_licensing_surveys' => array('show_if' => array('other_certification_licensing_surveys_status' => 'yes')),
            'surgery_procedure_types' => array('hide_if' => array('surgery_invasive_procedures' => 'not_offered')),
            'operative_invasive_review' => array('hide_if' => array('surgery_invasive_procedures' => 'not_offered')),
            'blood_usage_review' => array('not_applicable_if' => array('transfusions_per_year' => 0, 'blood_bank_model' => 'no blood bank')),
            'activate_first_30_days_track' => array('show_if' => array('new_to_quality_director_role' => 'yes')),
            'contracted_quality_monitoring_agreements' => array('hide_if' => array('visiting_specialists' => 'no')),
        );

        return isset($logic[$question_key]) ? $logic[$question_key] : null;
    }

    private static function default_help_text($section_key, $question_key)
    {
        if ($question_key === 'licensed_beds') {
            return __('For Critical Access Hospitals, this is usually the 25 licensed-bed limit.', 'qualinav-admin-console');
        }
        if ($question_key === 'acute_beds') {
            return __('For non-CAH hospitals only. Critical Access Hospitals do not need to split the 25 licensed beds into acute beds.', 'qualinav-admin-console');
        }
        if ($question_key === 'licensed_for_swing_beds') {
            return __('For Critical Access Hospitals only. If yes, enter how many of the 25 licensed beds are licensed for swing-bed use.', 'qualinav-admin-console');
        }
        if ($question_key === 'swing_beds') {
            return __('For Critical Access Hospitals, enter the swing-bed count within the 25 licensed beds.', 'qualinav-admin-console');
        }
        return '';
    }

    private static function normalize_question($row)
    {
        $row['id'] = absint($row['id']);
        $row['section_id'] = absint($row['section_id']);
        if (in_array($row['question_key'], array('state_survey_agency_url', 'life_safety_survey_agency_url'), true)) {
            $row['field_type'] = 'url';
        }
        $row['is_required'] = (bool) $row['is_required'];
        $row['is_progress_tracked'] = !empty($row['is_progress_tracked']);
        $stored_options = $row['options_json'] ? json_decode($row['options_json'], true) : array();
        $row['options'] = self::canonical_question_options($row['question_key'], $row['field_type'], is_array($stored_options) ? $stored_options : array());
        $row['validation'] = $row['validation_json'] ? json_decode($row['validation_json'], true) : array();
        $row['conditional_logic'] = $row['conditional_logic_json'] ? json_decode($row['conditional_logic_json'], true) : array();
        unset($row['options_json'], $row['validation_json'], $row['conditional_logic_json']);

        return $row;
    }

    private static function canonical_question_options($question_key, $field_type, $stored_options = array())
    {
        $step_three_service_lines = array(
            'service_lines_core' => array('emergency_department' => 'Emergency Department', 'inpatient_acute_care' => 'Inpatient Acute Care', 'swing_bed_services' => 'Swing Bed Services', 'observation_services' => 'Observation Services', 'laboratory_services' => 'Laboratory Services', 'diagnostic_imaging' => 'Diagnostic Imaging', 'pharmacy' => 'Pharmacy', 'respiratory_therapy' => 'Respiratory Therapy', 'physical_therapy' => 'Physical Therapy', 'infusion_services' => 'Infusion Services', 'anesthesia_coverage' => 'Anesthesia Coverage', 'dietary_services' => 'Dietary Services'),
            'service_lines_common' => array('rural_health_clinic_primary_care' => 'Rural Health Clinic / Primary Care', 'general_surgery' => 'General Surgery', 'endoscopy_colonoscopy' => 'Endoscopy and Colonoscopy', 'obstetrics_labor_delivery' => 'Obstetrics / Labor and Delivery', 'gynecology' => 'Gynecology', 'orthopedics' => 'Orthopedics', 'occupational_therapy' => 'Occupational Therapy', 'speech_language_pathology' => 'Speech-Language Pathology', 'cardiac_rehabilitation' => 'Cardiac Rehabilitation', 'pulmonary_rehabilitation' => 'Pulmonary Rehabilitation', 'sleep_studies' => 'Sleep Studies', 'wound_care' => 'Wound Care', 'visiting_specialist_clinics' => 'Visiting Specialist Clinics', 'telehealth_services' => 'Telehealth Services', 'ambulance_ems' => 'Ambulance and EMS', 'occupational_health_services' => 'Occupational Health Services', 'diabetes_education' => 'Diabetes Education', 'nutrition_counseling' => 'Nutrition Counseling', 'mammography' => 'Mammography', 'bone_density_dexa' => 'Bone Density Screening (DEXA)', 'echocardiography_cardiac_diagnostics' => 'Echocardiography / Cardiac Diagnostics'),
            'service_lines_growth_expansion' => array('behavioral_health_integration' => 'Behavioral Health Integration', 'senior_behavioral_health_unit' => 'Senior Behavioral Health Unit', 'skilled_nursing_long_term_care' => 'Skilled Nursing / Long-Term Care', 'retail_340b_contract_pharmacy' => 'Retail or 340B Contract Pharmacy', 'oncology_chemotherapy' => 'Oncology / Chemotherapy', 'dialysis' => 'Dialysis', 'pain_management' => 'Pain Management', 'specialty_clinic_expansion' => 'Specialty Clinic Expansion', 'home_health_hospice_partnerships' => 'Home Health / Hospice Partnerships', 'other_growth_service' => 'Other Growth Service'),
        );
        if (isset($step_three_service_lines[$question_key])) {
            return self::choice_pairs($step_three_service_lines[$question_key]);
        }

        if ($question_key === 'external_reporting_flex_mbqip') {
            return array(
                array('value' => 'EDTC All-or-None Composite', 'label' => __('EDTC All-or-None Composite', 'qualinav-admin-console')),
                array('value' => 'HCAHPS', 'label' => __('HCAHPS All-or-None (includes all HCAHPS measures)', 'qualinav-admin-console')),
                array('value' => 'Safe Use of Opioids eCQM', 'label' => __('Safe Use of Opioids eCQM', 'qualinav-admin-console')),
                array('value' => 'ED Patient Experience (EDPEC)', 'label' => __('ED Patient Experience (EDPEC) (MBQIP)', 'qualinav-admin-console')),
                array('value' => 'OP-18 ED Throughput', 'label' => __('OP-18 ED Throughput (MBQIP / OQR)', 'qualinav-admin-console')),
                array('value' => 'OP-22 Left Without Being Seen', 'label' => __('OP-22 Left Without Being Seen (MBQIP / OQR)', 'qualinav-admin-console')),
                array('value' => 'Antibiotic Stewardship (NHSN Survey)', 'label' => __('Antibiotic Stewardship (NHSN Survey)', 'qualinav-admin-console')),
                array('value' => 'HCP Influenza Vaccination', 'label' => __('HCP Influenza Vaccination (MBQIP / NHSN)', 'qualinav-admin-console')),
                array('value' => 'CAH Quality Infrastructure', 'label' => __('CAH Quality Infrastructure', 'qualinav-admin-console')),
            );
        }

        $step_four_reporting = array(
            'internal_monitoring_clinical_case_review' => array(
                array('value' => 'Mortality Reviews', 'label' => __('Mortality Reviews', 'qualinav-admin-console')),
                array('value' => 'Sepsis Mortality', 'label' => __('Sepsis Mortality', 'qualinav-admin-console')),
                array('value' => 'Morbidity / Complication Reviews', 'label' => __('Morbidity / Complication Reviews', 'qualinav-admin-console')),
                array('value' => 'Peer Review', 'label' => __('Peer Review', 'qualinav-admin-console')),
                array('value' => 'Surgical / Procedural Case Review', 'label' => __('Surgical / Procedural Case Review', 'qualinav-admin-console')),
                array('value' => 'Blood Utilization Review', 'label' => __('Blood Utilization Review', 'qualinav-admin-console')),
                array('value' => 'Code Blue / Rapid Response Reviews', 'label' => __('Code Blue / Rapid Response Reviews', 'qualinav-admin-console')),
            ),
            'internal_monitoring_patient_experience' => array(
                array('value' => 'Grievances and Complaints', 'label' => __('Grievances and Complaints', 'qualinav-admin-console')),
                array('value' => 'Swing Bed Quality Measures', 'label' => __('Swing Bed Quality Measures', 'qualinav-admin-console')),
                array('value' => 'Obstetric Outcomes', 'label' => __('Obstetric Outcomes', 'qualinav-admin-console')),
                array('value' => 'Critical Value Reporting', 'label' => __('Critical Value Reporting', 'qualinav-admin-console')),
                array('value' => 'Informed Consent Compliance', 'label' => __('Informed Consent Compliance', 'qualinav-admin-console')),
                array('value' => 'Medical Record Delinquency', 'label' => __('Medical Record Delinquency', 'qualinav-admin-console')),
                array('value' => 'Advance Directive Documentation', 'label' => __('Advance Directive Documentation', 'qualinav-admin-console')),
                array('value' => 'Telehealth Quality Monitoring', 'label' => __('Telehealth Quality Monitoring', 'qualinav-admin-console')),
            ),
            'external_reporting_flex_mbqip' => array(
                array('value' => 'EDTC All-or-None Composite', 'label' => __('EDTC All-or-None Composite', 'qualinav-admin-console')),
                array('value' => 'HCAHPS', 'label' => __('HCAHPS (legacy umbrella — saving selects all HCAHPS measures)', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Composite 1: Communication with Nurses', 'label' => __('HCAHPS — Composite 1: Communication with Nurses', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Composite 2: Communication with Doctors', 'label' => __('HCAHPS — Composite 2: Communication with Doctors', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Composite 3: Restfulness of Hospital Environment', 'label' => __('HCAHPS — Composite 3: Restfulness of Hospital Environment', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Composite 4: Responsiveness of Hospital Staff', 'label' => __('HCAHPS — Composite 4: Responsiveness of Hospital Staff', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Composite 5: Communication About Medicines', 'label' => __('HCAHPS — Composite 5: Communication About Medicines', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Composite 6: Discharge Information / Care Coordination', 'label' => __('HCAHPS — Composite 6: Discharge Information / Care Coordination', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Composite 7: Transitions of Care', 'label' => __('HCAHPS — Composite 7: Transitions of Care', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Q7: Cleanliness of Hospital Environment', 'label' => __('HCAHPS — Q7: Cleanliness of Hospital Environment', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Q20: Info About Symptoms to Watch For After Discharge', 'label' => __('HCAHPS — Q20: Info About Symptoms to Watch For After Discharge', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Q24: Overall Rating of Hospital (0-10)', 'label' => __('HCAHPS — Q24: Overall Rating of Hospital (0-10)', 'qualinav-admin-console')),
                array('value' => 'HCAHPS — Q5: Willingness to Recommend Hospital', 'label' => __('HCAHPS — Q5: Willingness to Recommend Hospital', 'qualinav-admin-console')),
                array('value' => 'Safe Use of Opioids eCQM', 'label' => __('Safe Use of Opioids eCQM', 'qualinav-admin-console')),
                array('value' => 'ED Patient Experience (EDPEC)', 'label' => __('ED Patient Experience (EDPEC) (MBQIP)', 'qualinav-admin-console')),
                array('value' => 'OP-18 ED Throughput', 'label' => __('OP-18 ED Throughput (MBQIP / OQR)', 'qualinav-admin-console')),
                array('value' => 'OP-22 Left Without Being Seen', 'label' => __('OP-22 Left Without Being Seen (MBQIP / OQR)', 'qualinav-admin-console')),
                array('value' => 'Antibiotic Stewardship (NHSN Survey)', 'label' => __('Antibiotic Stewardship (NHSN Survey)', 'qualinav-admin-console')),
                array('value' => 'HCP Influenza Vaccination', 'label' => __('HCP Influenza Vaccination (MBQIP / NHSN)', 'qualinav-admin-console')),
                array('value' => 'CAH Quality Infrastructure', 'label' => __('CAH Quality Infrastructure', 'qualinav-admin-console')),
            ),
            'external_reporting_cms_iqr' => array(
                array('value' => 'SEP-1 Sepsis Bundle', 'label' => __('SEP-1 Sepsis Bundle', 'qualinav-admin-console')),
                array('value' => 'PC-01 Early Elective Delivery', 'label' => __('PC-01 Early Elective Delivery', 'qualinav-admin-console')),
                array('value' => 'Hybrid Hospital-Wide Readmission', 'label' => __('Hybrid Hospital-Wide Readmission', 'qualinav-admin-console')),
                array('value' => 'Hybrid Hospital-Wide Mortality', 'label' => __('Hybrid Hospital-Wide Mortality', 'qualinav-admin-console')),
                array('value' => 'eCQMs (Opioids, Malnutrition, etc.)', 'label' => __('eCQMs (Opioids, Malnutrition, etc.)', 'qualinav-admin-console')),
                array('value' => 'Patient Safety Structural Measure (PSSM) (NHSN Survey)', 'label' => __('Patient Safety Structural Measure (PSSM) (NHSN Survey)', 'qualinav-admin-console')),
            ),
            'external_reporting_cms_oqr' => array(
                array('value' => 'OP-29 Colonoscopy Follow-Up Interval', 'label' => __('OP-29 Colonoscopy Follow-Up Interval', 'qualinav-admin-console')),
                array('value' => 'Outpatient eCQMs / Web-Based Measures', 'label' => __('Outpatient eCQMs / Web-Based Measures', 'qualinav-admin-console')),
            ),
            'external_reporting_nhsn' => array(
                array('value' => 'CLABSI', 'label' => __('CLABSI', 'qualinav-admin-console')),
                array('value' => 'CAUTI', 'label' => __('CAUTI', 'qualinav-admin-console')),
                array('value' => 'SSI (Colon / Hysterectomy)', 'label' => __('SSI (Colon / Hysterectomy)', 'qualinav-admin-console')),
                array('value' => 'C. difficile LabID Events', 'label' => __('C. difficile LabID Events', 'qualinav-admin-console')),
                array('value' => 'MRSA Bacteremia LabID Events', 'label' => __('MRSA Bacteremia LabID Events', 'qualinav-admin-console')),
                array('value' => 'Antimicrobial Use and Resistance (AUR)', 'label' => __('Antimicrobial Use and Resistance (AUR)', 'qualinav-admin-console')),
                array('value' => 'Respiratory Pathogen Reporting', 'label' => __('Respiratory Pathogen Reporting', 'qualinav-admin-console')),
            ),
        );
        if (isset($step_four_reporting[$question_key])) {
            return $step_four_reporting[$question_key];
        }

        if (self::has_labelled_options($stored_options)) {
            return $stored_options;
        }

        $yes_no = array(
            array('value' => 'yes', 'label' => __('Yes', 'qualinav-admin-console')),
            array('value' => 'no', 'label' => __('No', 'qualinav-admin-console')),
            array('value' => 'not_sure', 'label' => __('Not sure', 'qualinav-admin-console')),
        );
        $not_applicable = array_merge($yes_no, array(array('value' => 'not_applicable', 'label' => __('Not applicable', 'qualinav-admin-console'))));
        $maps = array(
            'is_critical_access_hospital' => $yes_no,
            'licensed_for_swing_beds' => $yes_no,
            'independent_or_system' => array(
                array('value' => 'independent', 'label' => __('Independent', 'qualinav-admin-console')),
                array('value' => 'system_owned', 'label' => __('System-Owned', 'qualinav-admin-console')),
                array('value' => 'network_affiliated', 'label' => __('Network-Affiliated', 'qualinav-admin-console')),
                array('value' => 'other', 'label' => __('Other', 'qualinav-admin-console')),
            ),
            'accreditation_status' => array(
                array('value' => 'accredited', 'label' => __('Accredited', 'qualinav-admin-console')),
                array('value' => 'cms_state_survey_only', 'label' => __('CMS/state survey only', 'qualinav-admin-console')),
                array('value' => 'not_accredited', 'label' => __('Not accredited', 'qualinav-admin-console')),
                array('value' => 'not_sure', 'label' => __('Not sure', 'qualinav-admin-console')),
            ),
            'survey_compliance_process' => array(
                array('value' => 'direct_cms_state_survey', 'label' => __('Direct certification through a triennial CMS survey, conducted by our state survey body on behalf of CMS', 'qualinav-admin-console')),
                array('value' => 'deemed_accreditation_body_survey', 'label' => __('Deemed status through a triennial accreditation body survey, such as The Joint Commission', 'qualinav-admin-console')),
            ),
            'accrediting_body' => array(
                array('value' => 'joint_commission', 'label' => __('The Joint Commission', 'qualinav-admin-console')),
                array('value' => 'dnv', 'label' => __('DNV', 'qualinav-admin-console')),
                array('value' => 'hfap_hqic', 'label' => __('HFAP / HQIC', 'qualinav-admin-console')),
                array('value' => 'other', 'label' => __('Other', 'qualinav-admin-console')),
            ),
            'life_safety_survey_agency_status' => array(
                array('value' => 'same_as_state_survey_agency', 'label' => __('Same as state survey agency', 'qualinav-admin-console')),
                array('value' => 'different_agency', 'label' => __('Different agency', 'qualinav-admin-console')),
                array('value' => 'not_sure', 'label' => __('Not sure', 'qualinav-admin-console')),
            ),
            'other_certification_licensing_surveys_status' => $yes_no,
            'cms_certification_pathway' => array(
                array('value' => 'cms_state_survey', 'label' => __('CMS certification through state survey agency', 'qualinav-admin-console')),
                array('value' => 'accreditor_deeming_authority', 'label' => __('Accreditation with deeming authority', 'qualinav-admin-console')),
                array('value' => 'not_sure', 'label' => __('Not sure', 'qualinav-admin-console')),
                array('value' => 'not_applicable', 'label' => __('Not applicable', 'qualinav-admin-console')),
            ),
            'open_plans_of_correction' => array(
                array('value' => 'no', 'label' => __('No open POCs', 'qualinav-admin-console')),
                array('value' => 'yes', 'label' => __('Yes, active POC', 'qualinav-admin-console')),
                array('value' => 'not_sure', 'label' => __('Not sure', 'qualinav-admin-console')),
            ),
            'projected_next_survey_window' => array(
                array('value' => 'next_6_months', 'label' => __('Next 6 months', 'qualinav-admin-console')),
                array('value' => 'six_to_12_months', 'label' => __('6-12 months', 'qualinav-admin-console')),
                array('value' => 'twelve_to_24_months', 'label' => __('12-24 months', 'qualinav-admin-console')),
                array('value' => 'twentyfour_plus_months', 'label' => __('24+ months', 'qualinav-admin-console')),
                array('value' => 'unknown', 'label' => __('Unknown', 'qualinav-admin-console')),
            ),
            'historical_deficiency_areas' => self::choice_pairs(array('medication_management' => 'Medication management', 'infection_prevention' => 'Infection prevention', 'physical_environment_life_safety' => 'Physical environment / Life Safety', 'qapi_governance' => 'QAPI / Governance', 'medical_staff_credentialing' => 'Medical staff / Credentialing', 'nursing_services' => 'Nursing services', 'emergency_preparedness' => 'Emergency preparedness', 'patient_rights' => 'Patient rights', 'records_documentation' => 'Records / Documentation', 'dietary_nutrition' => 'Dietary / Nutrition', 'pharmacy' => 'Pharmacy', 'laboratory_radiology' => 'Laboratory / Radiology', 'other' => 'Other', 'not_sure' => 'Not sure')),
            'current_readiness_activities' => self::choice_pairs(array('environment_of_care_rounds' => 'Environment of care rounds', 'mock_tracers' => 'Mock tracers', 'policy_review_rotation' => 'Policy review rotation', 'log_spot_checks' => 'Log spot-checks', 'high_risk_record_reviews' => 'High-risk record reviews', 'emergency_drills' => 'Emergency drills', 'staff_education' => 'Staff education', 'leadership_gap_assessment' => 'Leadership gap assessment', 'external_mock_survey' => 'External mock survey', 'none_currently' => 'None currently', 'not_sure' => 'Not sure')),
            'accreditation_360' => $not_applicable,
            'emergency_department' => $yes_no,
            'surgery_invasive_procedures' => self::choice_pairs(array('offered' => 'Offered', 'limited' => 'Limited', 'not_offered' => 'Not offered')),
            'surgery_procedure_types' => self::choice_pairs(array('endoscopy' => 'Endoscopy', 'general_surgery' => 'General surgery', 'orthopedics' => 'Orthopedics', 'pain_management_injections' => 'Pain management injections', 'cardiac_catheterization' => 'Cardiac catheterization', 'other' => 'Other')),
            'obstetrics_labor_delivery' => $yes_no,
            'laboratory_model' => self::choice_pairs(array('on_site_routine_lab' => 'On-site routine lab', 'on_site_plus_reference_lab' => 'On-site lab plus reference lab for complex testing', 'reference_lab_only' => 'Reference lab only', 'not_offered' => 'Not offered', 'other' => 'Other')),
            'radiology_model' => self::choice_pairs(array('plain_film_on_site' => 'Plain film on-site', 'ct_on_site' => 'CT on-site', 'ultrasound_on_site' => 'Ultrasound on-site', 'mri_on_site' => 'MRI on-site', 'teleradiology_interpretation' => 'Teleradiology interpretation', 'not_offered' => 'Not offered', 'other' => 'Other')),
            'respiratory_therapy' => $yes_no,
            'rehabilitation_services' => $yes_no,
            'dietary_nutrition_services' => $yes_no,
            'pharmacy_model' => self::choice_pairs(array('on_site_pharmacist_24_7' => 'On-site pharmacist 24/7', 'on_site_pharmacist_limited_hours' => 'On-site pharmacist limited hours', 'remote_order_verification' => 'Remote order verification', 'contracted_pharmacy' => 'Contracted pharmacy', 'consulting_pharmacist_visits' => 'Consulting pharmacist visits', 'other' => 'Other')),
            'anesthesia_moderate_sedation_model' => self::choice_pairs(array('crna_on_staff' => 'CRNA on staff', 'contracted_crna_coverage' => 'Contracted CRNA coverage', 'anesthesiologist_coverage' => 'Anesthesiologist coverage', 'moderate_sedation_credentialed_providers' => 'Moderate sedation by credentialed providers', 'most_surgical_patients_transferred' => 'Most surgical patients transferred', 'not_applicable' => 'Not applicable', 'other' => 'Other')),
            'blood_bank_model' => self::choice_pairs(array('on_site_blood_bank' => 'On-site blood bank', 'regional_blood_center_supply' => 'Regional blood center supply', 'no_blood_products_on_site' => 'No blood products on site', 'other' => 'Other')),
            'visiting_specialists' => $yes_no,
            'committee_required_status' => self::choice_pairs(array('required_by_bylaws' => 'Required by bylaws/regulation', 'optional_internal' => 'Optional/internal', 'not_sure' => 'Not sure')),
            'mbqip_measure_set' => self::choice_pairs(array('mbqip_core' => 'MBQIP core', 'cms_iqr' => 'CMS IQR', 'cms_oqr' => 'CMS OQR', 'hcahps' => 'HCAHPS', 'ecqms' => 'eCQMs', 'promoting_interoperability' => 'Promoting Interoperability', 'value_based_programs' => 'Value-based programs', 'other' => 'Other', 'not_sure' => 'Not sure')),
            'report_lead_time' => self::choice_pairs(array('one_week' => '1 week', 'two_weeks' => '2 weeks', 'three_weeks' => '3 weeks', 'four_weeks' => '4 weeks', 'six_weeks' => '6 weeks', 'custom' => 'Custom', 'not_sure' => 'Not sure')),
            'qapi_plan_status' => self::plan_status_options(),
            'patient_safety_plan_status' => self::plan_status_options(),
            'infection_prevention_plan_status' => self::plan_status_options(),
            'emergency_preparedness_plan_status' => self::plan_status_options(),
            'risk_management_plan_status' => self::plan_status_options(),
            'policy_management_system' => self::choice_pairs(array('formal_policy_management_system' => 'Yes, formal policy management system', 'spreadsheet_or_index' => 'Spreadsheet or index', 'shared_drive_folder' => 'Shared drive / folder', 'paper_manual_system' => 'Paper/manual system', 'no_current_system' => 'No current system', 'not_sure' => 'Not sure', 'other' => 'Other')),
            'annual_policy_review_cycle' => self::choice_pairs(array('annual_review_with_owners' => 'Yes, annual review cycle with owners', 'partial_informal_cycle' => 'Partial / informal cycle', 'no_formal_cycle' => 'No formal cycle', 'not_sure' => 'Not sure')),
            'templates_needed' => self::choice_pairs(array('qapi_project_charter' => 'QAPI project charter', 'pdsa_worksheet' => 'PDSA worksheet', 'root_cause_analysis_template' => 'Root cause analysis template', 'fmea_template' => 'FMEA template', 'corrective_action_plan_template' => 'Corrective action plan template', 'board_quality_report_template' => 'Board quality report template', 'run_chart_template' => 'Run chart template', 'survey_readiness_checklist' => 'Survey readiness checklist', 'transfer_communication_checklist' => 'Transfer communication checklist', 'sentinel_event_response_protocol' => 'Sentinel Event response protocol', 'other' => 'Other', 'not_sure' => 'Not sure')),
            'weakest_monitoring_areas' => self::choice_pairs(array('morbidity_mortality_review' => 'Morbidity and mortality review', 'blood_usage_review' => 'Blood usage review', 'medication_safety' => 'Medication safety', 'operative_invasive_procedures' => 'Operative / invasive procedures', 'anesthesia_moderate_sedation' => 'Anesthesia / moderate sedation', 'sentinel_never_event_protocol' => 'Sentinel / never event protocol', 'ancillary_services' => 'Ancillary services', 'contracted_service_data_flow' => 'Contracted service data flow', 'infection_prevention' => 'Infection prevention', 'dietary_monitoring' => 'Dietary monitoring', 'policy_review' => 'Policy review', 'other' => 'Other', 'not_sure' => 'Not sure')),
            'mbqip_upload' => $not_applicable,
            'nhsn_hai_rates_upload' => $not_applicable,
            'patient_experience_scores_upload' => $not_applicable,
            'fall_rates_upload' => $not_applicable,
            'pressure_injury_rates_upload' => $not_applicable,
            'hand_hygiene_upload' => $not_applicable,
            'other_dashboard_metrics' => $not_applicable,
            'data_source_currency' => self::choice_pairs(array('real_time' => 'Real-time', 'same_month' => 'Same month', 'one_month_lag' => '1 month lag', 'quarterly' => 'Quarterly', 'manual_as_available' => 'Manual/as available', 'not_sure' => 'Not sure')),
            'qi_framework' => self::choice_pairs(array('model_for_improvement_pdsa' => 'Model for Improvement / PDSA', 'lean' => 'Lean', 'six_sigma' => 'Six Sigma', 'rca_corrective_action' => 'RCA / corrective action', 'fmea' => 'FMEA', 'combination' => 'Combination', 'not_standardized' => 'Not standardized', 'not_sure' => 'Not sure')),
            'project_charters_status' => self::choice_pairs(array('charters_in_place' => 'Charters in place for active projects', 'some_projects_have_charters' => 'Some projects have charters', 'no_formal_charters' => 'No formal charters', 'need_template_support' => 'Need template/support', 'not_sure' => 'Not sure')),
            'baseline_data_status' => self::choice_pairs(array('baselines_collected' => 'Baselines collected', 'some_baselines_collected' => 'Some baselines collected', 'not_yet_collected' => 'Not yet collected', 'need_help_defining_baselines' => 'Need help defining baselines', 'not_sure' => 'Not sure')),
            'new_to_quality_director_role' => $yes_no,
            'time_in_current_role' => self::choice_pairs(array('less_than_one_year' => 'Less than one year', 'one_to_5_years' => '1 - 5 years', 'six_to_10_years' => '6 - 10 years', 'more_than_10_years' => 'More than 10 years')),
            'quality_certifications' => self::choice_pairs(array('cphq' => 'CPHQ', 'cpps' => 'CPPS', 'rn' => 'RN', 'mph' => 'MPH', 'mba' => 'MBA', 'pursuing_cphq' => 'Pursuing CPHQ', 'pursuing_cpps' => 'Pursuing CPPS', 'other' => 'Other', 'none' => 'None')),
            'confidence_foundational' => self::confidence_options(),
            'confidence_qi_patient_safety' => self::confidence_options(),
            'confidence_specialized_areas' => self::confidence_options(),
            'confidence_professional_development' => self::confidence_options(),
            'activate_first_30_days_track' => $yes_no,
            'learning_format_preference' => self::choice_pairs(array('short_on_demand_modules' => 'Short on-demand modules', 'structured_learning_path' => 'Structured learning path', 'peer_cohort_discussion' => 'Peer/cohort discussion', 'live_coaching_checkins' => 'Live coaching/check-ins', 'templates_examples' => 'Templates and examples', 'certification_prep' => 'Certification prep', 'not_sure' => 'Not sure')),
            'monitored_sources' => self::choice_pairs(array('cms_conditions_of_participation' => 'CMS Conditions of Participation', 'cms_survey_certification_memos' => 'CMS survey/certification memos', 'state_survey_agency' => 'State survey agency', 'state_health_department' => 'State health department', 'accreditor_standards_updates' => 'Accreditor standards updates', 'joint_commission_perspectives' => 'Joint Commission Perspectives', 'mbqip_flex_program_updates' => 'MBQIP / Flex program updates', 'qualitynet_hqr_announcements' => 'QualityNet / HQR announcements', 'sentinel_event_alerts' => 'Sentinel Event Alerts', 'state_hospital_association_updates' => 'State hospital association updates', 'other' => 'Other', 'not_sure' => 'Not sure')),
            'update_preference' => self::choice_pairs(array('weekly_digest' => 'Weekly digest', 'immediate_high_impact' => 'Immediate alerts for high-impact changes', 'digest_and_immediate' => 'Both digest and immediate alerts', 'no_alerts_yet' => 'No alerts yet', 'not_sure' => 'Not sure')),
            'auto_propose_task_adjustments' => self::choice_pairs(array('yes_review' => 'Yes, propose adjustments for review', 'no_flag_only' => 'No, only flag changes', 'not_sure' => 'Not sure')),
            'current_tools' => self::choice_pairs(array('excel_spreadsheets' => 'Excel / spreadsheets', 'outlook_calendar' => 'Outlook calendar', 'outlook_tasks' => 'Outlook tasks', 'google_calendar' => 'Google Calendar', 'microsoft_teams_exchange' => 'Microsoft Teams / Exchange', 'project_management_tool' => 'Project management tool', 'paper_checklist' => 'Paper checklist', 'policy_management_system' => 'Policy management system', 'shared_drive_sharepoint' => 'Shared drive / SharePoint', 'other' => 'Other', 'not_sure' => 'Not sure')),
            'calendar_system' => self::choice_pairs(array('outlook' => 'Outlook', 'google_calendar' => 'Google Calendar', 'microsoft_teams_exchange' => 'Microsoft Teams / Exchange', 'paper_manual' => 'Paper/manual', 'other' => 'Other', 'not_sure' => 'Not sure')),
            'nhsn_qualitynet_access' => self::choice_pairs(array('access_confirmed' => 'Access confirmed', 'access_pending' => 'Access pending', 'need_help_setting_up' => 'Need help setting up', 'not_applicable' => 'Not applicable', 'not_sure' => 'Not sure')),
            'reminder_lead_time' => self::choice_pairs(array('one_week' => '1 week', 'two_weeks' => '2 weeks', 'three_weeks' => '3 weeks', 'four_weeks' => '4 weeks', 'six_weeks' => '6 weeks', 'custom' => 'Custom', 'not_sure' => 'Not sure')),
            'reminder_buffer_time' => self::choice_pairs(array('no_buffer' => 'No buffer', 'three_days' => '3 days', 'one_week' => '1 week', 'two_weeks' => '2 weeks', 'custom' => 'Custom', 'not_sure' => 'Not sure')),
            'final_review_confirmation' => self::choice_pairs(array('checked' => 'I confirm this setup is ready to submit and contains no PHI', 'not_checked' => 'Not checked')),
        );

        if (isset($maps[$question_key])) {
            return $maps[$question_key];
        }
        if ($field_type === 'yes_no') {
            return $yes_no;
        }

        return $stored_options;
    }

    private static function has_labelled_options($options)
    {
        if (empty($options) || !is_array($options)) {
            return false;
        }
        foreach ($options as $option) {
            if (!is_array($option) || !array_key_exists('value', $option) || !array_key_exists('label', $option)) {
                return false;
            }
        }
        return true;
    }

    private static function choice_pairs($options)
    {
        $pairs = array();
        foreach ($options as $value => $label) {
            $pairs[] = array(
                'value' => sanitize_key($value),
                'label' => __($label, 'qualinav-admin-console'),
            );
        }
        return $pairs;
    }

    private static function confidence_options()
    {
        return self::choice_pairs(array('new' => 'New', 'developing' => 'Developing', 'confident' => 'Confident'));
    }

    private static function plan_status_options()
    {
        return self::choice_pairs(array('exists_yes' => 'Exists: Yes', 'exists_no' => 'Exists: No', 'board_approved_yes' => 'Board approved: Yes', 'board_approved_no' => 'Board approved: No', 'board_approved_not_required' => 'Board approved: Not required', 'action_none' => 'Action needed: None', 'action_create' => 'Action needed: Create', 'action_review_update' => 'Action needed: Review/update', 'action_route_for_approval' => 'Action needed: Route for approval', 'action_verify_owner_date' => 'Action needed: Verify owner/date', 'not_sure' => 'Not sure'));
    }

    private static function sanitize_deep($value)
    {
        if (is_array($value)) {
            return array_map(array(__CLASS__, 'sanitize_deep'), $value);
        }

        return sanitize_textarea_field($value);
    }

    private static function sanitize_survey_history($answer)
    {
        if (!is_array($answer)) {
            return array();
        }

        $allowed_keys = array('survey_date', 'survey_type', 'surveying_agency', 'survey_outcome', 'poc_status', 'follow_up_window');
        $key_fields = array('survey_type', 'survey_outcome', 'poc_status', 'follow_up_window');
        $rows = array();

        foreach ($answer as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = array();
            foreach ($allowed_keys as $key) {
                $value = isset($row[$key]) ? $row[$key] : '';
                if ($key === 'survey_date') {
                    $clean[$key] = self::normalize_date_answer($value);
                } elseif (in_array($key, $key_fields, true)) {
                    $clean[$key] = sanitize_key($value);
                } else {
                    $clean[$key] = sanitize_text_field($value);
                }
            }
            if (self::answer_has_value($clean)) {
                $rows[] = $clean;
            }
        }

        return $rows;
    }

    public static function answer_has_value($answer)
    {
        if (is_array($answer)) {
            foreach ($answer as $value) {
                if (self::answer_has_value($value)) {
                    return true;
                }
            }
            return false;
        }

        if (is_bool($answer)) {
            return $answer === true;
        }

        return $answer !== null && $answer !== '';
    }

    public static function validate_required_answer($question, $answer)
    {
        if (empty($question['is_required'])) {
            return true;
        }

        $label = isset($question['label']) ? $question['label'] : $question['question_key'];
        if (!self::answer_has_value($answer)) {
            return new WP_Error('qn_onboarding_required', sprintf(__('Required field missing: %s', 'qualinav-admin-console'), $label), array('status' => 400, 'question_key' => $question['question_key']));
        }

        if ($question['field_type'] === 'checkbox' && $answer !== true) {
            return new WP_Error('qn_onboarding_confirmation_required', __('Confirm that Hospital Setup is ready to submit and contains no PHI or case-level details.', 'qualinav-admin-console'), array('status' => 400, 'question_key' => $question['question_key']));
        }

        if ($question['question_key'] === 'plan_policy_inventory') {
            foreach ((array) $answer as $row) {
                if (!is_array($row) || empty($row['policy_key']) || empty($row['status'])) {
                    $policy_name = is_array($row) && !empty($row['policy_name']) ? sanitize_text_field($row['policy_name']) : __('the highlighted plan or policy', 'qualinav-admin-console');
                    return new WP_Error('qn_onboarding_plan_inventory_incomplete', sprintf(__('Select a status for %s. Document uploads are not required.', 'qualinav-admin-console'), $policy_name), array('status' => 400, 'question_key' => $question['question_key']));
                }
            }
        }

        if ($question['field_type'] === 'plan_status') {
            if (!is_array($answer) || empty($answer['exists']) || empty($answer['action_needed'])) {
                return new WP_Error('qn_onboarding_plan_status_incomplete', sprintf(__('Complete Exists and Action needed for %s. Use Not sure when the information is not currently available.', 'qualinav-admin-console'), $label), array('status' => 400, 'question_key' => $question['question_key']));
            }
            if ($answer['exists'] === 'yes' && empty($answer['board_approved'])) {
                return new WP_Error('qn_onboarding_plan_approval_incomplete', sprintf(__('Select the board approval status for %s.', 'qualinav-admin-console'), $label), array('status' => 400, 'question_key' => $question['question_key']));
            }
        }

        return true;
    }
}
