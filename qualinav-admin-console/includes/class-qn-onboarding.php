<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Onboarding
{
    public static function get_onboarding_payload($organization_id)
    {
        $hospital = QN_Organizations::get_hospital($organization_id);

        return array(
            'current_organization_id' => absint($organization_id),
            'current_organization_name' => $hospital ? $hospital['name'] : '',
            'city' => $hospital ? $hospital['city'] : '',
            'state_id' => $hospital ? $hospital['state_id'] : null,
            'state_name' => $hospital ? $hospital['state_name'] : '',
            'state_code' => $hospital ? $hospital['state_code'] : '',
            'parent_system_id' => $hospital ? $hospital['parent_system_id'] : null,
            'parent_system_name' => $hospital ? $hospital['parent_system_name'] : '',
            'primary_quality_director' => $hospital && isset($hospital['primary_quality_director']) ? $hospital['primary_quality_director'] : null,
            'hospital_type' => $hospital ? $hospital['hospital_type'] : '',
            'hospital_type_label' => $hospital ? $hospital['hospital_type_label'] : __('Not specified.', 'qualinav-admin-console'),
            'service_model' => $hospital ? $hospital['service_model'] : '',
            'service_model_label' => $hospital ? $hospital['service_model_label'] : __('Not specified.', 'qualinav-admin-console'),
            'licensed_beds' => $hospital && isset($hospital['licensed_beds']) ? $hospital['licensed_beds'] : null,
            'acute_beds' => $hospital && isset($hospital['acute_beds']) ? $hospital['acute_beds'] : null,
            'swing_beds' => $hospital && isset($hospital['swing_beds']) ? $hospital['swing_beds'] : null,
            'states' => QN_Organizations::get_states(),
            'steps' => self::get_step_definitions(),
            'sections' => QN_Questionnaire::get_sections(),
            'questions' => QN_Questionnaire::get_questions(),
            'answers' => QN_Questionnaire::get_answer_map($organization_id),
            'progress' => self::get_progress($organization_id),
            'onboarding_status' => $hospital && !empty($hospital['onboarding_status']) ? sanitize_key($hospital['onboarding_status']) : '',
            'onboarding_submitted' => class_exists('QN_Scout') ? QN_Scout::is_onboarding_submitted($organization_id) : ($hospital && isset($hospital['onboarding_status']) && $hospital['onboarding_status'] === 'submitted'),
            'can_edit' => self::can_edit_onboarding(get_current_user_id(), $organization_id),
        );
    }

    public static function save_step($organization_id, $step_key, $answers, $user_id)
    {
        if (!self::can_edit_onboarding($user_id, $organization_id)) {
            return new WP_Error('qn_onboarding_forbidden', __('You cannot edit onboarding for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $role = QN_Users::get_role_for_organization($user_id, $organization_id);
        if ($role === 'hospital_admin' && !in_array($step_key, array('hospital_director_info'), true)) {
            return new WP_Error('qn_onboarding_step_forbidden', __('Hospital Admins can edit hospital profile setup only.', 'qualinav-admin-console'), array('status' => 403));
        }

        $result = QN_Questionnaire::save_answers($organization_id, $answers, $user_id);
        if (is_wp_error($result)) {
            return $result;
        }

        $step_progress = QN_Questionnaire::update_section_progress($organization_id, $step_key, $user_id);
        self::update_organization_onboarding_columns($organization_id, 'in_progress');
        QN_Audit_Log::log('onboarding_saved', 'organization', $organization_id, null, array('step_key' => $step_key, 'answers' => array_keys($answers)), $organization_id);

        return array(
            'saved' => $result,
            'step_progress' => $step_progress,
            'progress' => self::get_progress($organization_id),
        );
    }

    public static function submit_onboarding($organization_id, $user_id)
    {
        $hospital = QN_Organizations::get_hospital($organization_id);
        if (!$hospital) {
            return new WP_Error('qn_onboarding_invalid_organization', __('Select a valid hospital before submitting Hospital Setup.', 'qualinav-admin-console'), array('status' => 404));
        }

        if (!self::can_edit_onboarding($user_id, $organization_id)) {
            return new WP_Error('qn_onboarding_forbidden', __('You cannot submit onboarding for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $role = QN_Users::get_role_for_organization($user_id, $organization_id);
        if (!QN_Users::is_qualinav_admin($user_id) && $role !== 'quality_director') {
            return new WP_Error('qn_onboarding_submit_forbidden', __('Only Quality Directors and QualiNav admins can submit Hospital Setup.', 'qualinav-admin-console'), array('status' => 403));
        }

        $answers = QN_Questionnaire::get_answer_map($organization_id);
        foreach (QN_Questionnaire::get_questions() as $question) {
            if (!empty($question['is_required']) && (!array_key_exists($question['question_key'], $answers) || $answers[$question['question_key']] === '' || $answers[$question['question_key']] === null)) {
                return new WP_Error('qn_onboarding_required', sprintf(__('Required field missing: %s', 'qualinav-admin-console'), $question['label']), array('status' => 400));
            }
        }

        self::mark_submitted($organization_id, $user_id);
        QN_Audit_Log::log('onboarding_submitted', 'organization', $organization_id, null, self::get_progress($organization_id), $organization_id);

        $response = array(
            'progress' => self::get_progress($organization_id),
            'scout_run' => null,
            'warning' => __('Hospital Setup was submitted. Generate Scout setup preview from the Scout Setup Preview page.', 'qualinav-admin-console'),
            'scout_generation_deferred' => true,
            'bridge_available' => class_exists('QN_Scout') ? QN_Scout::is_bridge_available() : false,
        );

        return $response;
    }

    public static function get_progress($organization_id)
    {
        $steps = self::get_step_definitions();
        $snapshot = QN_Questionnaire::calculate_progress_snapshot($organization_id);
        $progress_by_section = array();
        foreach ($snapshot['step_progress'] as $progress) {
            $progress_by_section[$progress['section_key']] = $progress;
        }

        $step_progress = array();
        foreach ($steps as $step) {
            $step_progress[] = isset($progress_by_section[$step['section_key']])
                ? $progress_by_section[$step['section_key']]
                : array(
                    'organization_id' => absint($organization_id),
                    'section_key' => $step['section_key'],
                    'status' => 'not_started',
                    'percent_complete' => 0,
                );
        }

        return array(
            'organization_id' => absint($organization_id),
            'total_percent' => $snapshot['total_percent'],
            'step_progress' => $step_progress,
        );
    }

    public static function mark_submitted($organization_id, $user_id)
    {
        self::update_organization_onboarding_columns($organization_id, 'submitted');
    }

    public static function can_edit_onboarding($user_id, $organization_id)
    {
        if (QN_Users::is_qualinav_admin($user_id)) {
            return true;
        }

        if (!QN_Users::user_has_organization($user_id, $organization_id)) {
            return false;
        }

        $role = QN_Users::get_role_for_organization($user_id, $organization_id);

        return $role === 'quality_director'
            || ($role === 'hospital_admin' && QN_Permissions::user_can($user_id, 'edit_hospital_profile', $organization_id));
    }

    public static function get_step_definitions()
    {
        return array_map(function ($step) {
            return array(
                'section_key' => $step['section_key'],
                'title' => $step['title'],
                'description' => $step['description'],
                'informs' => self::informs_copy($step['section_key']),
            );
        }, QN_Questionnaire::default_steps());
    }

    private static function update_organization_onboarding_columns($organization_id, $status)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        $data = array();
        if (QN_DB::column_exists($table, 'onboarding_status')) {
            $data['onboarding_status'] = sanitize_key($status);
        }
        if (QN_DB::column_exists($table, 'onboarding_percent')) {
            $data['onboarding_percent'] = QN_Questionnaire::calculate_total_progress($organization_id);
        }
        if ($data) {
            $wpdb->update($table, $data, array('id' => absint($organization_id)));
        }
    }

    private static function informs_copy($section_key)
    {
        $copy = array(
            'hospital_director_info' => __('This informs your hospital profile and default Scout timeline.', 'qualinav-admin-console'),
            'accreditation_survey_readiness' => __('This helps Scout build your readiness timeline and reminder schedule.', 'qualinav-admin-console'),
            'services_clinical_model' => __('This helps Scout decide which monitoring areas apply to your hospital.', 'qualinav-admin-console'),
            'committees_reporting' => __('This helps Scout prepare reports before committee and board meetings.', 'qualinav-admin-console'),
            'plans_policies_monitoring' => __('This informs plan, policy, and monitoring workflows without collecting PHI.', 'qualinav-admin-console'),
            'measures_qi_projects' => __('This helps Scout understand active measures and QI priorities.', 'qualinav-admin-console'),
            'goals_learning_contacts' => __('This informs learning tracks and contact reminders.', 'qualinav-admin-console'),
            'regulatory_tools_preferences' => __('This informs regulatory monitoring and reminder preferences.', 'qualinav-admin-console'),
        );

        return isset($copy[$section_key]) ? $copy[$section_key] : '';
    }
}
