<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Questionnaire
{
    public static function seed_default_questionnaire()
    {
        global $wpdb;

        $now = current_time('mysql');
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

    public static function get_answer_map($organization_id)
    {
        $map = array();
        foreach (self::get_answers($organization_id) as $row) {
            $map[$row['question_key']] = json_decode($row['answer_json'], true);
        }

        return $map;
    }

    public static function calculate_section_progress($organization_id, $section_key)
    {
        $questions = array_filter(self::get_questions($section_key), function ($question) {
            return !empty($question['is_required']);
        });
        if (!$questions) {
            $questions = self::get_questions($section_key);
        }
        if (!$questions) {
            return 0;
        }

        $answers = self::get_answer_map($organization_id);
        $completed = 0;
        foreach ($questions as $question) {
            if (array_key_exists($question['question_key'], $answers) && self::answer_has_value($answers[$question['question_key']])) {
                $completed++;
            }
        }

        return (int) round(($completed / count($questions)) * 100);
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
        $sections = self::get_sections();
        if (!$sections) {
            return array(
                'total_percent' => 0,
                'step_progress' => array(),
            );
        }

        $answers = self::get_answer_map($organization_id);
        $questions_by_section = array();
        foreach (self::get_questions() as $question) {
            $section_id = absint($question['section_id']);
            if (!isset($questions_by_section[$section_id])) {
                $questions_by_section[$section_id] = array();
            }
            $questions_by_section[$section_id][] = $question;
        }

        $total = 0;
        $step_progress = array();
        foreach ($sections as $section) {
            $questions = isset($questions_by_section[absint($section['id'])]) ? $questions_by_section[absint($section['id'])] : array();
            $required = array_filter($questions, function ($question) {
                return !empty($question['is_required']);
            });
            $tracked_questions = $required ? array_values($required) : $questions;
            $percent = 0;

            if ($tracked_questions) {
                $completed = 0;
                foreach ($tracked_questions as $question) {
                    if (array_key_exists($question['question_key'], $answers) && self::answer_has_value($answers[$question['question_key']])) {
                        $completed++;
                    }
                }
                $percent = (int) round(($completed / count($tracked_questions)) * 100);
            }

            $total += $percent;
            $step_progress[] = array(
                'organization_id' => absint($organization_id),
                'section_key' => $section['section_key'],
                'status' => $percent >= 100 ? 'completed' : ($percent > 0 ? 'in_progress' : 'not_started'),
                'percent_complete' => $percent,
            );
        }

        return array(
            'total_percent' => (int) round($total / count($sections)),
            'step_progress' => $step_progress,
        );
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

    public static function validate_answer($question, $answer)
    {
        $type = $question['field_type'];
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
            return sanitize_text_field($answer);
        }
        if (in_array($question['question_key'], array('historical_deficiency_areas', 'current_readiness_activities', 'surgery_procedure_types', 'radiology_model', 'anesthesia_moderate_sedation_model', 'mbqip_measure_set', 'approval_requirements'), true)) {
            return is_array($answer) ? self::sanitize_deep($answer) : sanitize_textarea_field($answer);
        }

        return sanitize_textarea_field($answer);
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
        return array(
            self::step('hospital_director_info', 'Hospital & Director Info', 'This helps Scout tailor your hospital profile and setup path.', array(
                array('hospital_name', 'Hospital name', 'text', true), array('hospital_city', 'Hospital city', 'text'), array('hospital_state', 'Hospital state', 'text'), array('licensed_beds', 'Licensed beds', 'number'), array('acute_beds', 'Acute beds', 'number'), array('swing_beds', 'Swing beds', 'number'), array('is_critical_access_hospital', 'Is this a Critical Access Hospital?', 'yes_no', false, $yes_no), array('independent_or_system', 'Independent or part of a system?', 'select', false, array('independent', 'system')), array('quality_director_name', 'Quality Director name', 'text'), array('quality_director_role_start_date', 'Role start date', 'date'), array('quality_director_background', 'Quality Director background', 'textarea'),
            )),
            self::step('accreditation_survey_readiness', 'Accreditation & Survey Readiness', 'This helps Scout build your readiness timeline and reminder schedule.', array(
                array('accreditation_status', 'Accreditation status', 'select', true, array('accredited', 'cms_certified', 'in_progress', 'not_sure')), array('accrediting_body', 'Accrediting body', 'select', false, array('The Joint Commission', 'DNV', 'ACHC', 'HFAP', 'State/CMS', 'Other')), array('cms_certification_pathway', 'CMS certification pathway', 'text'), array('state_survey_agency', 'State survey agency', 'text'), array('life_safety_survey_agency', 'Life safety survey agency', 'text'), array('open_plans_of_correction', 'Open plans of correction', 'yes_no', false, $yes_no), array('survey_history', 'Survey history', 'repeater'), array('projected_next_survey_window', 'Projected next survey window', 'text'), array('historical_deficiency_areas', 'Historical deficiency areas', 'textarea'), array('current_readiness_activities', 'Current readiness activities', 'textarea'),
            )),
            self::step('services_clinical_model', 'Services & Clinical Model', 'This helps Scout decide which monitoring areas apply to your hospital.', array(
                array('emergency_department', 'Emergency department', 'yes_no', false, $yes_no), array('surgery_invasive_procedures', 'Surgery or invasive procedures', 'select', false, array('offered', 'limited', 'not_offered')), array('surgery_procedure_types', 'Surgery procedure types', 'textarea'), array('obstetrics_labor_delivery', 'Obstetrics / labor & delivery', 'yes_no', false, $yes_no), array('laboratory_model', 'Laboratory model', 'text'), array('radiology_model', 'Radiology model', 'text'), array('respiratory_therapy', 'Respiratory therapy', 'yes_no', false, $yes_no), array('rehabilitation_services', 'Rehabilitation services', 'yes_no', false, $yes_no), array('dietary_nutrition_services', 'Dietary and nutrition services', 'yes_no', false, $yes_no), array('pharmacy_model', 'Pharmacy model', 'text'), array('anesthesia_moderate_sedation_model', 'Anesthesia / moderate sedation model', 'text'), array('blood_bank_model', 'Blood bank model', 'text'), array('transfusions_per_year', 'Transfusions per year', 'number'), array('visiting_specialists', 'Visiting specialists', 'yes_no', false, $yes_no), array('contracted_quality_monitoring_agreements', 'Contracted quality monitoring agreements', 'textarea'),
            )),
            self::step('committees_reporting', 'Committees & Reporting', 'This helps Scout prepare reports before committee and board meetings.', array(
                array('committee_list', 'Committee list', 'repeater'), array('committee_required_status', 'Committee required status', 'textarea'), array('standing_agenda_items', 'Standing agenda items', 'textarea'), array('minutes_owner_location', 'Minutes owner and location', 'text'), array('board_agenda_timing', 'Board agenda timing', 'text'), array('reporting_obligations', 'Reporting obligations', 'repeater'), array('mbqip_measure_set', 'MBQIP measure set', 'textarea'), array('backup_preparer', 'Backup preparer', 'text'), array('report_lead_time', 'Report lead time', 'text'), array('approval_requirements', 'Approval requirements', 'textarea'),
            )),
            self::step('plans_policies_monitoring', 'Plans, Policies & Monitoring', 'Do not enter patient names, MRNs, provider case details, incident narratives, peer-review details, or specific adverse-event details. QualiNav only stores structural information and aggregate/de-identified data.', array(
                array('qapi_plan_status', 'QAPI plan status', 'plan_status', true), array('patient_safety_plan_status', 'Patient safety plan status', 'plan_status', true), array('infection_prevention_plan_status', 'Infection prevention plan status', 'plan_status', true), array('emergency_preparedness_plan_status', 'Emergency preparedness plan status', 'plan_status', true), array('risk_management_plan_status', 'Risk management plan status', 'plan_status', true), array('plan_location_authority', 'Plan location and approval authority', 'textarea'), array('policy_management_system', 'Policy management system', 'text'), array('annual_policy_review_cycle', 'Annual policy review cycle', 'text'), array('templates_needed', 'Templates needed', 'textarea'), array('morbidity_mortality_monitoring', 'Morbidity and mortality monitoring', 'textarea'), array('blood_usage_review', 'Blood usage review', 'textarea'), array('medication_safety_monitoring', 'Medication safety monitoring', 'textarea'), array('operative_invasive_review', 'Operative / invasive review', 'textarea'), array('anesthesia_sedation_monitoring', 'Anesthesia / sedation monitoring', 'textarea'), array('sentinel_never_event_protocol', 'Sentinel / never event protocol', 'textarea'), array('ancillary_services_review', 'Ancillary services review', 'textarea'), array('contracted_service_quality_data_flow', 'Contracted service quality data flow', 'textarea'), array('weakest_monitoring_areas', 'Weakest monitoring areas', 'textarea'),
            )),
            self::step('measures_qi_projects', 'Measures & QI Projects', 'This helps Scout understand your dashboard, measures, and active improvement work.', array(
                array('mbqip_upload', 'MBQIP upload status', 'textarea'), array('nhsn_hai_rates_upload', 'NHSN HAI rates upload status', 'textarea'), array('patient_experience_scores_upload', 'Patient experience scores upload status', 'textarea'), array('fall_rates_upload', 'Fall rates upload status', 'textarea'), array('pressure_injury_rates_upload', 'Pressure injury rates upload status', 'textarea'), array('hand_hygiene_upload', 'Hand hygiene upload status', 'textarea'), array('other_dashboard_metrics', 'Other dashboard metrics', 'textarea'), array('current_quality_dashboard', 'Current quality dashboard', 'textarea'), array('data_source_currency', 'Data source currency', 'textarea'), array('active_qi_projects', 'Active QI projects', 'repeater'), array('qi_framework', 'QI framework', 'text'), array('project_charters_status', 'Project charters status', 'text'), array('baseline_data_status', 'Baseline data status', 'text'),
            )),
            self::step('goals_learning_contacts', 'Goals, Learning & Contacts', 'This helps Scout personalize learning tracks and contact reminders.', array(
                array('department_goals_this_year', 'Department goals this year', 'textarea'), array('department_goals_two_three_years', 'Department goals over 2-3 years', 'textarea'), array('protected_workflow_goals', 'Protected workflow goals', 'textarea'), array('program_gaps', 'Program gaps', 'textarea'), array('strategic_plan_alignment', 'Strategic plan alignment', 'textarea'), array('new_to_quality_director_role', 'New to Quality Director role?', 'yes_no', false, $yes_no), array('time_in_current_role', 'Time in current role', 'text'), array('quality_certifications', 'Quality certifications', 'textarea'), array('confidence_foundational', 'Confidence: foundational', 'number'), array('confidence_qi_patient_safety', 'Confidence: QI and patient safety', 'number'), array('confidence_specialized_areas', 'Confidence: specialized areas', 'number'), array('confidence_professional_development', 'Confidence: professional development', 'number'), array('activate_first_30_days_track', 'Activate first 30 days track', 'yes_no', false, $yes_no), array('learning_format_preference', 'Learning format preference', 'text'), array('state_flex_contact', 'State Flex contact', 'text'), array('state_office_rural_health_contact', 'State Office of Rural Health contact', 'text'), array('state_hospital_association_contact', 'State hospital association contact', 'text'), array('state_survey_agency_contacts', 'State survey agency contacts', 'textarea'), array('peer_cah_contacts', 'Peer CAH contacts', 'textarea'), array('accreditation_liaison', 'Accreditation liaison', 'text'), array('referral_hospital_contacts', 'Referral hospital contacts', 'textarea'),
            )),
            self::step('regulatory_tools_preferences', 'Regulatory Monitoring & Preferences', 'This helps Scout build your monitoring preferences and reminder cadence.', array(
                array('monitored_sources', 'Monitored sources', 'multiselect', false, array('cms_conditions_of_participation', 'cms_survey_certification_memos', 'state_survey_agency', 'state_health_department', 'accreditor_standards_updates', 'joint_commission_perspectives', 'mbqip_flex_program_updates', 'qualitynet_hqr_announcements', 'sentinel_event_alerts', 'state_hospital_association_updates', 'other', 'not_sure')), array('update_preference', 'Update preference', 'select', false, array('weekly_digest', 'immediate_high_impact', 'digest_and_immediate', 'no_alerts_yet', 'not_sure')), array('auto_propose_task_adjustments', 'Auto-propose task adjustments', 'select', false, array('yes_review', 'no_flag_only', 'not_sure')), array('current_tools', 'Current tools', 'multiselect', false, array('excel_spreadsheets', 'outlook_calendar', 'outlook_tasks', 'google_calendar', 'microsoft_teams_exchange', 'project_management_tool', 'paper_checklist', 'policy_management_system', 'shared_drive_sharepoint', 'other', 'not_sure')), array('calendar_system', 'Calendar system', 'select', false, array('outlook', 'google_calendar', 'microsoft_teams_exchange', 'paper_manual', 'other', 'not_sure')), array('ehr_system', 'EHR system', 'text'), array('incident_reporting_system', 'Incident reporting system', 'text'), array('nhsn_qualitynet_access', 'NHSN / QualityNet access', 'select', false, array('access_confirmed', 'access_pending', 'need_help_setting_up', 'not_applicable', 'not_sure')), array('reminder_lead_time', 'Reminder lead time', 'select', false, array('one_week', 'two_weeks', 'three_weeks', 'four_weeks', 'six_weeks', 'custom', 'not_sure')), array('reminder_buffer_time', 'Reminder buffer time', 'select', false, array('no_buffer', 'three_days', 'one_week', 'two_weeks', 'custom', 'not_sure')), array('backup_visibility_users', 'Backup visibility users', 'textarea'), array('final_review_confirmation', 'Final review confirmation', 'checkbox', true),
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
                );
            }, $questions),
        );
    }

    private static function default_options($question_key, $field_type)
    {
        if ($field_type !== 'repeater') {
            return null;
        }

        $columns = array(
            'survey_history' => array('survey_date', 'survey_type', 'surveying_agency', 'deficiencies_cited', 'poc_due_followup'),
            'committee_list' => array('committee_name', 'frequency_timing', 'user_role', 'reports_to'),
            'reporting_obligations' => array('report_name', 'frequency', 'due_dates', 'who_prepares', 'submit_to_method'),
            'active_qi_projects' => array('project_aim', 'method', 'measure', 'status_next_milestone'),
        );

        return isset($columns[$question_key]) ? $columns[$question_key] : array('note');
    }

    private static function default_conditional_logic($question_key)
    {
        $logic = array(
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
        if ($question_key === 'accrediting_body') {
            return __('If The Joint Commission is selected, Scout will surface Accreditation 360 readiness prompts.', 'qualinav-admin-console');
        }
        if ($section_key === 'plans_policies_monitoring') {
            return __('Do not enter PHI, patient names, MRNs, provider case details, incident narratives, peer-review details, or specific adverse-event details.', 'qualinav-admin-console');
        }

        return '';
    }

    private static function normalize_question($row)
    {
        $row['id'] = absint($row['id']);
        $row['section_id'] = absint($row['section_id']);
        $row['is_required'] = (bool) $row['is_required'];
        $row['options'] = $row['options_json'] ? json_decode($row['options_json'], true) : array();
        $row['validation'] = $row['validation_json'] ? json_decode($row['validation_json'], true) : array();
        $row['conditional_logic'] = $row['conditional_logic_json'] ? json_decode($row['conditional_logic_json'], true) : array();
        unset($row['options_json'], $row['validation_json'], $row['conditional_logic_json']);

        return $row;
    }

    private static function sanitize_deep($value)
    {
        if (is_array($value)) {
            return array_map(array(__CLASS__, 'sanitize_deep'), $value);
        }

        return sanitize_textarea_field($value);
    }

    private static function answer_has_value($answer)
    {
        if (is_array($answer)) {
            return !empty(array_filter($answer));
        }

        return $answer !== null && $answer !== '';
    }
}
