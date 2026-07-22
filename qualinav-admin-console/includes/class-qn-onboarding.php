<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Onboarding
{
    public static function get_onboarding_payload($organization_id)
    {
        $hospital = QN_Organizations::get_hospital($organization_id);

        $answers = QN_Questionnaire::get_answer_map($organization_id);
        $answers = self::hydrate_canonical_references($organization_id, $hospital, $answers);
        $grapevine_prefill = self::get_grapevine_onboarding_prefill($organization_id);
        $answers = self::merge_prefill_answers($answers, $grapevine_prefill);

        return array(
            'current_organization_id' => absint($organization_id),
            'current_organization_name' => $hospital ? $hospital['name'] : '',
            'city' => $hospital ? $hospital['city'] : '',
            'zip' => $hospital && isset($hospital['zip']) ? $hospital['zip'] : '',
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
            'answers' => $answers,
            'grapevine_onboarding_prefill' => $grapevine_prefill,
            'progress' => self::get_progress($organization_id),
            'onboarding_status' => $hospital && !empty($hospital['onboarding_status']) ? sanitize_key($hospital['onboarding_status']) : '',
            'onboarding_submitted' => $hospital && isset($hospital['onboarding_status']) && $hospital['onboarding_status'] === 'submitted',
            'can_edit' => self::can_edit_onboarding(get_current_user_id(), $organization_id),
        );
    }

    public static function save_step($organization_id, $step_key, $answers, $user_id, $mark_reviewed = false)
    {
        if (!self::can_edit_onboarding($user_id, $organization_id)) {
            return new WP_Error('qn_onboarding_forbidden', __('You cannot edit onboarding for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $step_key = sanitize_key($step_key);
        $role = QN_Users::get_role_for_organization($user_id, $organization_id);
        if ($role === 'hospital_admin' && !in_array($step_key, array('hospital_director_info'), true)) {
            return new WP_Error('qn_onboarding_step_forbidden', __('Hospital Admins can edit hospital profile setup only.', 'qualinav-admin-console'), array('status' => 403));
        }

        $answers = self::remove_retired_compatibility_answers($answers);
        $contract = self::validate_step_answer_keys($step_key, $answers);
        if (is_wp_error($contract)) {
            return $contract;
        }

        $existing_answers = QN_Questionnaire::get_answer_map($organization_id, false);
        $result = QN_Questionnaire::save_answers($organization_id, $answers, $user_id);
        if (is_wp_error($result)) {
            return $result;
        }
        $saved_answers = QN_Questionnaire::get_answer_map($organization_id, false);
        $answers_changed = false;
        $changed_answer_keys = array();
        foreach (array_keys((array) $answers) as $question_key) {
            $before = array_key_exists($question_key, $existing_answers) ? $existing_answers[$question_key] : null;
            $after = array_key_exists($question_key, $saved_answers) ? $saved_answers[$question_key] : null;
            if ($before !== $after) {
                $answers_changed = true;
                $changed_answer_keys[] = $question_key;
            }
        }

        $final_confirmation_was_checked = !empty($existing_answers['final_review_confirmation']);
        $final_confirmation_is_checked = !empty($saved_answers['final_review_confirmation']);
        $final_confirmation_just_checked = !$final_confirmation_was_checked && $final_confirmation_is_checked;
        if ($step_key === 'regulatory_tools_preferences' && $final_confirmation_is_checked) {
            // Checking the final acknowledgement is itself the review action for
            // this step; a separate Save & Continue click must not be required.
            $mark_reviewed = true;
        }

        if (in_array($step_key, array('measures_qi_projects', 'committees_reporting'), true) && class_exists('QN_Data_Hub_Integration')) {
            QN_Data_Hub_Integration::sync_from_answers($organization_id, $answers, $user_id);
        }

        $step_progress = QN_Questionnaire::update_section_progress($organization_id, $step_key, $user_id, (bool) $mark_reviewed);
        $hospital = QN_Organizations::get_hospital($organization_id);
        $was_submitted = $hospital && $hospital['onboarding_status'] === 'submitted';
        $non_confirmation_changes = array_diff($changed_answer_keys, array('final_review_confirmation'));
        $changed_after_confirmation = $final_confirmation_was_checked && !empty($non_confirmation_changes);
        if ($changed_after_confirmation) {
            // A genuine edit after the whole-setup acknowledgement reopens the
            // final review, whether or not Scout has already been started.
            QN_Questionnaire::save_answers($organization_id, array('final_review_confirmation' => false), $user_id);
            $final_confirmation_is_checked = false;
        }
        if ($was_submitted && $answers_changed && !$final_confirmation_just_checked) {
            self::update_organization_onboarding_columns($organization_id, 'in_progress');
        } else {
            self::update_organization_onboarding_columns($organization_id, $was_submitted ? 'submitted' : 'in_progress');
        }
        QN_Audit_Log::log('onboarding_saved', 'organization', $organization_id, null, array('step_key' => $step_key, 'answers' => array_keys($answers)), $organization_id);

        return array(
            'saved' => $result,
            'step_progress' => $step_progress,
            'progress' => self::get_progress($organization_id),
        );
    }

    private static function remove_retired_compatibility_answers($answers)
    {
        $retired_keys = array(
            'is_critical_access_hospital',
            'acute_beds',
            'licensed_for_swing_beds',
            'quality_director_name',
        );

        return array_diff_key((array) $answers, array_fill_keys($retired_keys, true));
    }

    private static function validate_step_answer_keys($step_key, $answers)
    {
        $allowed = array();
        foreach (QN_Questionnaire::get_questions($step_key) as $question) {
            $allowed[$question['question_key']] = true;
        }

        $invalid = array_values(array_diff(array_keys((array) $answers), array_keys($allowed)));
        if ($invalid) {
            return new WP_Error(
                'qn_onboarding_invalid_question',
                __('Hospital Setup changed while this page was open. Refresh the page and save again.', 'qualinav-admin-console'),
                array(
                    'status' => 400,
                    'question_keys' => $invalid,
                )
            );
        }

        return true;
    }

    public static function submit_onboarding($organization_id, $user_id)
    {
        $hospital = QN_Organizations::get_hospital($organization_id);
        if (!$hospital) {
            return new WP_Error('qn_onboarding_invalid_organization', __('Select a valid hospital before starting Scout.', 'qualinav-admin-console'), array('status' => 404));
        }

        if (!self::can_edit_onboarding($user_id, $organization_id)) {
            return new WP_Error('qn_onboarding_forbidden', __('You cannot submit onboarding for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $role = QN_Users::get_role_for_organization($user_id, $organization_id);
        if (!QN_Users::is_qualinav_admin($user_id) && $role !== 'quality_director') {
            return new WP_Error('qn_onboarding_submit_forbidden', __('Only Hospital Quality Directors and QualiNav admins can submit Hospital Setup.', 'qualinav-admin-console'), array('status' => 403));
        }

        $answers = QN_Questionnaire::get_answer_map($organization_id);
        foreach (QN_Questionnaire::get_questions() as $question) {
            if (empty($question['is_required'])) {
                continue;
            }
            $answer = array_key_exists($question['question_key'], $answers) ? $answers[$question['question_key']] : null;
            $required = QN_Questionnaire::validate_required_answer($question, $answer);
            if (is_wp_error($required)) {
                return $required;
            }
        }

        $offers_swing_bed_services = in_array('swing_bed_services', (array) ($answers['service_lines_core'] ?? array()), true);
        $swing_bed_count = $hospital && isset($hospital['swing_beds']) ? absint($hospital['swing_beds']) : 0;
        if ($offers_swing_bed_services && !$swing_bed_count) {
            return new WP_Error(
                'qn_swing_bed_count_required',
                __('Add the number of licensed beds approved for swing-bed use, or remove Swing Bed Services before starting Scout.', 'qualinav-admin-console'),
                array('status' => 422, 'question_key' => 'swing_beds')
            );
        }
        if ($swing_bed_count && !$offers_swing_bed_services) {
            return new WP_Error(
                'qn_swing_bed_service_required',
                __('Confirm that your hospital offers Swing Bed Services, or clear the swing-bed count before starting Scout.', 'qualinav-admin-console'),
                array('status' => 422, 'question_key' => 'service_lines_core')
            );
        }

        self::mark_submitted($organization_id, $user_id);
        QN_Audit_Log::log('onboarding_submitted', 'organization', $organization_id, null, self::get_progress($organization_id), $organization_id);

        $response = array(
            'progress' => self::get_progress($organization_id),
            'scout_run' => null,
            'warning' => __('Hospital Setup is ready. Scout is now building the initial workspace preview automatically.', 'qualinav-admin-console'),
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

    private static function get_grapevine_onboarding_prefill($organization_id)
    {
        global $wpdb;

        $quality_director_id = self::get_primary_quality_director_user_id($organization_id);
        if (!$quality_director_id || !QN_DB::table_exists($wpdb->prefix . 'gv_user_profile_meta')) {
            return array();
        }

        $meta_table = $wpdb->prefix . 'gv_user_profile_meta';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$meta_table} WHERE user_id = %d AND meta_key IN ('q_13', 'q_17')",
                absint($quality_director_id)
            ),
            ARRAY_A
        );

        if (!$rows) {
            return array();
        }

        $raw = array();
        foreach ($rows as $row) {
            $raw[$row['meta_key']] = self::normalize_grapevine_meta_value($row['meta_value']);
        }

        $answers = array();
        if (!empty($raw['q_13'])) {
            $answers['department_goals_this_year'] = $raw['q_13'];
        }
        if (!empty($raw['q_17'])) {
            $answers['department_goals_two_three_years'] = $raw['q_17'];
        }

        return array(
            'source' => 'grapevine_onboarding',
            'quality_director_user_id' => absint($quality_director_id),
            'answers' => $answers,
        );
    }

    public static function hydrate_canonical_references($organization_id, $hospital, $answers)
    {
        $answers = self::hydrate_hospital_references($hospital, $answers);

        $projects = self::canonical_qi_projects($organization_id);
        $answers['qi_project_references'] = $projects;
        $answers['active_qi_projects'] = $projects;
        return $answers;
    }

    public static function hydrate_hospital_references($hospital, $answers)
    {
        if ($hospital) {
            $answers['hospital_name'] = (string) $hospital['name'];
            $answers['ccn'] = (string) $hospital['ccn'];
            $answers['hospital_city'] = (string) $hospital['city'];
            $answers['hospital_state'] = (string) $hospital['state_code'];
            $answers['hospital_zip'] = isset($hospital['zip']) ? (string) $hospital['zip'] : '';
            $answers['licensed_beds'] = $hospital['licensed_beds'];
            $answers['acute_beds'] = $hospital['acute_beds'];
            $answers['swing_beds'] = $hospital['swing_beds'];
            $answers['hospital_type'] = (string) $hospital['hospital_type'];
            $answers['is_critical_access_hospital'] = $hospital['hospital_type'] === 'critical_access_hospital' ? 'yes' : 'no';
            $answers['independent_or_system'] = !empty($hospital['parent_system_id']) ? 'system_owned' : 'independent';
            $answers['system_network_name'] = (string) $hospital['parent_system_name'];

            $quality_director = isset($hospital['primary_quality_director']) ? $hospital['primary_quality_director'] : null;
            $answers['quality_leader_user_id'] = $quality_director ? absint($quality_director['user_id']) : 0;
            $answers['quality_leader_name'] = $quality_director ? (string) $quality_director['display_name'] : '';
            $answers['quality_director_name'] = $answers['quality_leader_name'];
            $answers['quality_leader_email'] = $quality_director ? (string) $quality_director['user_email'] : '';
        }
        return $answers;
    }

    private static function canonical_qi_projects($organization_id)
    {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'qi_projects';
        if (!QN_DB::table_exists($projects_table)) {
            return array();
        }
        $measures_table = $wpdb->prefix . 'qi_project_measures';
        $members_table = $wpdb->prefix . 'qi_project_members';
        $measure_join = QN_DB::table_exists($measures_table)
            ? " LEFT JOIN (SELECT project_id, COUNT(*) AS measure_count FROM {$measures_table} GROUP BY project_id) qm ON qm.project_id = p.id"
            : '';
        $member_join = QN_DB::table_exists($members_table)
            ? " LEFT JOIN (SELECT project_id, COUNT(*) AS member_count FROM {$members_table} GROUP BY project_id) qmem ON qmem.project_id = p.id"
            : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.title, p.status, p.pillar, p.focus_area, p.owner_user_id, p.updated_at, u.display_name AS owner_name" .
            ($measure_join ? ', COALESCE(qm.measure_count, 0) AS measure_count' : ', 0 AS measure_count') .
            ($member_join ? ', COALESCE(qmem.member_count, 0) AS member_count' : ', 0 AS member_count') .
            " FROM {$projects_table} p LEFT JOIN {$wpdb->users} u ON u.ID = p.owner_user_id{$measure_join}{$member_join} WHERE p.org_id = %d AND p.status NOT IN ('completed', 'archived', 'cancelled') ORDER BY p.updated_at DESC, p.id DESC",
            absint($organization_id)
        ), ARRAY_A);

        return array_map(function ($row) {
            return array(
                'project_id' => absint($row['id']),
                'title' => (string) $row['title'],
                'status' => sanitize_key($row['status']),
                'pillar' => (string) $row['pillar'],
                'focus_area' => (string) $row['focus_area'],
                'owner_user_id' => absint($row['owner_user_id']),
                'owner_name' => (string) $row['owner_name'],
                'measure_count' => absint($row['measure_count']),
                'member_count' => absint($row['member_count']),
                'updated_at' => (string) $row['updated_at'],
                'canonical_source' => 'qi_projects',
            );
        }, (array) $rows);
    }

    private static function get_primary_quality_director_user_id($organization_id)
    {
        global $wpdb;

        $mapping_table = QN_DB::user_organizations_table();
        if (QN_DB::table_exists($mapping_table)) {
            $mapped = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM {$mapping_table} WHERE organization_id = %d AND qualinav_role = %s AND status = %s ORDER BY is_default DESC, accepted_at DESC, id ASC LIMIT 1",
                    absint($organization_id),
                    'quality_director',
                    'active'
                )
            );
            if ($mapped) {
                return absint($mapped);
            }
        }

        $legacy = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE organization_id = %d AND qualinav_role = %s AND qualinav_status = %s ORDER BY ID ASC LIMIT 1",
                absint($organization_id),
                'quality_director',
                'active'
            )
        );

        return $legacy ? absint($legacy) : 0;
    }

    private static function normalize_grapevine_meta_value($value)
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            return array();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('sanitize_text_field', $decoded)));
            }

            return array(sanitize_text_field($value));
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('sanitize_text_field', $value)));
        }

        return array();
    }

    private static function merge_prefill_answers($answers, $prefill)
    {
        if (empty($prefill['answers']) || !is_array($prefill['answers'])) {
            return $answers;
        }

        foreach ($prefill['answers'] as $key => $value) {
            if (!array_key_exists($key, $answers) || $answers[$key] === '' || $answers[$key] === null || $answers[$key] === array()) {
                $answers[$key] = $value;
            }
        }

        return $answers;
    }

    private static function informs_copy($section_key)
    {
        $copy = array(
            'hospital_director_info' => __('This informs your hospital profile and default Scout timeline.', 'qualinav-admin-console'),
            'accreditation_survey_readiness' => __('This helps Scout build your survey readiness roadmap.', 'qualinav-admin-console'),
            'services_clinical_model' => __('This helps Scout understand which requirements and guidance may apply to your hospital.', 'qualinav-admin-console'),
            'committees_reporting' => __('This helps Scout prepare information before the meetings where quality is reviewed.', 'qualinav-admin-console'),
            'plans_policies_monitoring' => __('This gives Scout authorized plan and policy context without collecting case-level details.', 'qualinav-admin-console'),
            'measures_qi_projects' => __('Data Hub remains the source of truth for measures, submissions, deadlines, and performance.', 'qualinav-admin-console'),
            'regulatory_tools_preferences' => __('This confirms setup readiness and who can provide backup coverage.', 'qualinav-admin-console'),
        );

        return isset($copy[$section_key]) ? $copy[$section_key] : '';
    }
}
