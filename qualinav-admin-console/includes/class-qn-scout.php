<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Scout
{
    const REQUEST_TYPE = 'scout_day0_workflow_generation';
    const INPUT_DATA_TYPE = 'qualinav-day0-workflow-generation-v1';
    const PAYLOAD_LIMIT_BYTES = 40000;

    public static function is_bridge_available()
    {
        return function_exists('grapevine_ai_run_scout_task');
    }

    public static function get_selected_organization_for_request($request)
    {
        $payload = $request instanceof WP_REST_Request ? $request->get_json_params() : array();
        $requested = $request instanceof WP_REST_Request ? $request->get_param('organization_id') : null;
        if (is_array($payload) && isset($payload['organization_id'])) {
            $requested = $payload['organization_id'];
        }

        if (QN_Users::is_qualinav_admin(get_current_user_id()) && $requested) {
            return absint($requested);
        }

        $organization_id = QN_Users::get_current_organization_id(get_current_user_id());
        if (!$organization_id || !QN_Users::user_has_organization(get_current_user_id(), $organization_id)) {
            return new WP_Error('qn_no_current_organization', __('Select a hospital first.', 'qualinav-admin-console'), array('status' => 403));
        }

        return absint($organization_id);
    }

    public static function can_generate($user_id, $organization_id)
    {
        $user_id = absint($user_id);
        $organization_id = absint($organization_id);
        if (!$user_id || !$organization_id) {
            return false;
        }

        if (QN_Users::is_qualinav_admin($user_id)) {
            return QN_Permissions::user_can($user_id, 'access_super_admin');
        }

        return QN_Users::user_has_organization($user_id, $organization_id)
            && QN_Permissions::user_can($user_id, 'complete_onboarding', $organization_id);
    }

    public static function can_view($user_id, $organization_id)
    {
        $user_id = absint($user_id);
        $organization_id = absint($organization_id);
        if (!$user_id || !$organization_id) {
            return false;
        }

        if (QN_Users::is_qualinav_admin($user_id)) {
            return QN_Permissions::user_can($user_id, 'access_super_admin');
        }

        return QN_Users::user_has_organization($user_id, $organization_id)
            && QN_Permissions::user_can($user_id, 'access_hospital_console', $organization_id);
    }

    public static function build_day0_payload($organization_id, $user_id)
    {
        $organization_id = absint($organization_id);
        $user_id = absint($user_id);
        $hospital = QN_Organizations::get_hospital($organization_id);
        if (!$hospital) {
            return new WP_Error('qn_scout_hospital_not_found', __('Hospital not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $state = !empty($hospital['state_id']) ? QN_Organizations::get_state($hospital['state_id']) : null;
        $current_role = QN_Users::get_role_for_organization($user_id, $organization_id);
        $user = QN_Users::get_user_row($user_id);
        if (!$current_role && $user) {
            $current_role = $user->qualinav_role;
        }

        $answer_map = QN_Questionnaire::get_answer_map($organization_id);
        $persona_context = self::build_persona_context($organization_id, $answer_map, $user_id);

        $payload = array(
            'source' => 'qualinav_admin_console',
            'request_type' => self::REQUEST_TYPE,
            'questionnaire_version' => 'day0_v1',
            'organization_id' => $organization_id,
            'organization_name' => isset($hospital['name']) ? $hospital['name'] : '',
            'parent_system_id' => isset($hospital['parent_system_id']) ? $hospital['parent_system_id'] : null,
            'parent_system_name' => isset($hospital['parent_system_name']) ? $hospital['parent_system_name'] : '',
            'hospital_type' => isset($hospital['hospital_type']) ? $hospital['hospital_type'] : '',
            'hospital_type_label' => isset($hospital['hospital_type_label']) ? $hospital['hospital_type_label'] : '',
            'service_model' => isset($hospital['service_model']) ? $hospital['service_model'] : '',
            'service_model_label' => isset($hospital['service_model_label']) ? $hospital['service_model_label'] : '',
            'state_id' => isset($hospital['state_id']) ? $hospital['state_id'] : null,
            'state_code' => $state && isset($state['abbreviation']) ? $state['abbreviation'] : ($state && isset($state['code']) ? $state['code'] : ''),
            'current_user_id' => $user_id,
            'current_user_role' => $current_role,
            'persona_context' => $persona_context,
            'persona_summary' => self::build_persona_summary($persona_context),
            'onboarding_answers' => self::group_onboarding_answers($organization_id, $answer_map),
            'constraints' => array(
                'no_phi' => true,
                'do_not_include_patient_names' => true,
                'do_not_include_mrns' => true,
                'do_not_include_provider_case_details' => true,
                'do_not_include_incident_narratives' => true,
                'do_not_include_peer_review_details' => true,
            ),
            'requested_outputs' => array(
                'reporting_schedule',
                'committee_flow_map',
                'survey_readiness_timeline',
                'plan_policy_tasks',
                'clinical_monitoring_tasks',
                'qi_project_milestones',
                'external_contact_directory',
                'regulatory_monitoring_preferences',
                'learning_journey',
                'reminder_rules',
                'master_reporting_schedule',
                'meeting_report_flow_map',
                'active_monitoring_improvement_tasks',
                'recurring_clinical_monitoring',
                'aggregate_data_uploads',
                'routine_task_rhythm',
                'active_improvement_projects',
                'priority_queue',
                'first_30_days_learning_journey',
                'persona_experience_summary',
            ),
        );

        return self::sanitize_payload_for_scout($payload);
    }

    public static function build_persona_context($organization_id, $answer_map, $user_id)
    {
        $hospital = QN_Organizations::get_hospital($organization_id);
        $hospital_type = $hospital && !empty($hospital['hospital_type']) ? sanitize_key($hospital['hospital_type']) : '';
        $org_payment_model = $hospital && !empty($hospital['payment_model']) ? sanitize_key($hospital['payment_model']) : '';
        $is_cah = in_array($hospital_type, array('critical_access_hospital', 'cah'), true) || self::answer_is_yes(self::answer_value($answer_map, 'is_critical_access_hospital'));
        $haystack = self::answers_haystack($answer_map);

        $payment_model = self::derive_payment_model($hospital_type, $org_payment_model, $is_cah, $haystack);
        $hospital_category = self::derive_hospital_category($hospital_type, $payment_model, $is_cah);
        $accreditation_body = self::normalize_accreditation_body(self::answer_value($answer_map, 'accrediting_body'));
        $survey_pathway = self::derive_survey_pathway($answer_map, $accreditation_body);
        $accreditation_pathway = self::derive_accreditation_pathway($answer_map, $accreditation_body, $survey_pathway, $haystack);
        $quality_director_experience = self::derive_quality_director_experience($answer_map);
        $new_director = $quality_director_experience === 'new';
        $first_30_days_track = self::derive_first_30_days_track($answer_map, $new_director);
        $learning_journey_enabled = self::derive_learning_journey_enabled($answer_map, $new_director);
        $program_maturity = self::derive_program_maturity($answer_map);
        $preferred_guidance_level = self::derive_guidance_level($quality_director_experience, $new_director, $first_30_days_track, $program_maturity);

        return array(
            'hospital_category' => $hospital_category,
            'payment_model' => $payment_model,
            'accreditation_pathway' => $accreditation_pathway,
            'survey_pathway' => $survey_pathway,
            'accreditation_body' => $accreditation_body,
            'quality_director_experience' => $quality_director_experience,
            'new_director' => $new_director,
            'first_30_days_track' => $first_30_days_track,
            'learning_journey_enabled' => $learning_journey_enabled,
            'program_maturity' => $program_maturity,
            'preferred_guidance_level' => $preferred_guidance_level,
        );
    }

    public static function sanitize_payload_for_scout($payload)
    {
        return self::sanitize_deep($payload);
    }

    public static function call_scout_api($payload, $user_id)
    {
        if (!self::is_bridge_available()) {
            return new WP_Error('qn_scout_bridge_unavailable', __('GrapevineAI Scout bridge is not available. Activate/configure the GrapevineAI plugin.', 'qualinav-admin-console'), array('status' => 503));
        }

        $json = wp_json_encode($payload);
        if ($json === false || strlen($json) > self::PAYLOAD_LIMIT_BYTES) {
            return new WP_Error('qn_scout_payload_too_large', __('Hospital Setup payload is too large for Scout. Please shorten long free-text fields or contact QualiNav support.', 'qualinav-admin-console'), array('status' => 413));
        }

        return grapevine_ai_run_scout_task(array(
            'task_type' => 'generate_action_plan',
            'caller_plugin' => 'qualinav-admin-console',
            'input_data_type' => self::INPUT_DATA_TYPE,
            'input_data' => $payload,
            'output_format' => 'structured_json',
            'include_sources' => true,
            'persist_result' => true,
            'max_goals' => 12,
            'correlation_id' => wp_generate_uuid4(),
        ));
    }

    public static function generate_for_organization($organization_id, $user_id)
    {
        $organization_id = absint($organization_id);
        $user_id = absint($user_id);
        $payload = self::build_day0_payload($organization_id, $user_id);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $run_id = self::create_run($organization_id, self::REQUEST_TYPE, self::INPUT_DATA_TYPE, $payload, $user_id);
        $response = self::call_scout_api($payload, $user_id);

        if (is_wp_error($response)) {
            self::update_run($run_id, array(
                'status' => 'failed',
                'error_message' => $response->get_error_message(),
                'response_json' => array(
                    'success' => false,
                    'error' => $response->get_error_message(),
                    'code' => $response->get_error_code(),
                ),
            ));
            QN_Audit_Log::log('scout_generation_failed', 'organization', $organization_id, null, array('run_id' => $run_id, 'error' => $response->get_error_message()), $organization_id);

            return self::get_run($run_id);
        }

        $normalized = self::normalize_scout_response($response);
        $status = !empty($normalized['error']) ? 'failed' : 'completed';
        self::update_run($run_id, array(
            'status' => $status,
            'response_json' => $response,
            'api_request_id' => isset($normalized['api_request_id']) ? $normalized['api_request_id'] : '',
            'source_count' => isset($normalized['source_count']) ? absint($normalized['source_count']) : null,
            'error_message' => !empty($normalized['error']) ? $normalized['error'] : '',
        ));

        QN_Audit_Log::log($status === 'completed' ? 'scout_generation_completed' : 'scout_generation_failed', 'organization', $organization_id, null, array('run_id' => $run_id, 'status' => $status), $organization_id);

        return self::get_run($run_id);
    }

    public static function create_run($organization_id, $request_type, $input_data_type, $payload, $user_id)
    {
        global $wpdb;

        $wpdb->insert(QN_DB::scout_runs_table(), array(
            'organization_id' => absint($organization_id),
            'request_type' => sanitize_key($request_type),
            'input_data_type' => sanitize_text_field($input_data_type),
            'request_payload_json' => wp_json_encode($payload),
            'response_json' => null,
            'status' => 'running',
            'api_request_id' => null,
            'source_count' => null,
            'error_message' => null,
            'generated_by' => absint($user_id),
            'created_at' => current_time('mysql'),
            'updated_at' => null,
        ));

        $run_id = absint($wpdb->insert_id);
        QN_Audit_Log::log('scout_generation_requested', 'organization', absint($organization_id), null, array('run_id' => $run_id, 'request_type' => $request_type), absint($organization_id));

        return $run_id;
    }

    public static function update_run($run_id, $data)
    {
        global $wpdb;

        $update = array('updated_at' => current_time('mysql'));
        $allowed = array('status', 'api_request_id', 'source_count', 'error_message');
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = is_string($data[$field]) ? sanitize_text_field($data[$field]) : $data[$field];
            }
        }
        if (array_key_exists('response_json', $data)) {
            $update['response_json'] = wp_json_encode($data['response_json']);
        }

        return false !== $wpdb->update(QN_DB::scout_runs_table(), $update, array('id' => absint($run_id)));
    }

    public static function get_runs($organization_id, $limit = 20)
    {
        global $wpdb;

        $limit = max(1, min(100, absint($limit)));
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . QN_DB::scout_runs_table() . ' WHERE organization_id = %d ORDER BY created_at DESC, id DESC LIMIT %d', absint($organization_id), $limit),
            ARRAY_A
        );

        return array_map(array(__CLASS__, 'normalize_run_row'), $rows);
    }

    public static function get_latest_run($organization_id)
    {
        $runs = self::get_runs($organization_id, 1);

        return $runs ? $runs[0] : null;
    }

    public static function get_run($run_id)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . QN_DB::scout_runs_table() . ' WHERE id = %d', absint($run_id)),
            ARRAY_A
        );

        return $row ? self::normalize_run_row($row) : null;
    }

    public static function retry_run($run_id, $user_id)
    {
        $run = self::get_run($run_id);
        if (!$run) {
            return new WP_Error('qn_scout_run_not_found', __('Scout run not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        return self::generate_for_organization($run['organization_id'], $user_id);
    }

    public static function extract_preview_summary($response)
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            $response = json_last_error() === JSON_ERROR_NONE ? $decoded : array('markdown_summary' => $response);
        }
        $response = is_array($response) ? $response : array();
        $warnings = self::listify(isset($response['warnings']) ? $response['warnings'] : array());
        $missing = self::listify(isset($response['missing_inputs']) ? $response['missing_inputs'] : array());
        $groups = array();

        if (!empty($response['workflows']) && is_array($response['workflows'])) {
            foreach ($response['workflows'] as $key => $items) {
                $groups[] = self::preview_group($key, self::listify($items));
            }
        } elseif (self::has_any($response, self::workflow_keys())) {
            foreach (self::workflow_keys() as $key) {
                if (!empty($response[$key])) {
                    $groups[] = self::preview_group($key, self::listify($response[$key]));
                }
            }
        } elseif (self::has_any($response, array('action_items', 'goals', 'recommendations', 'commitments'))) {
            $items = array();
            foreach (array('action_items', 'goals', 'recommendations', 'commitments') as $key) {
                if (!empty($response[$key])) {
                    $items[] = array('label' => self::labelize($key), 'value' => $response[$key]);
                }
            }
            $warnings[] = __('Detailed Hospital Setup workflow contract is not yet available; showing generic Scout recommendations.', 'qualinav-admin-console');
            $groups[] = self::preview_group('scout_recommendations', $items);
        } elseif (!empty($response['markdown_summary']) || !empty($response['summary'])) {
            $groups[] = self::preview_group('scout_summary', array(!empty($response['markdown_summary']) ? $response['markdown_summary'] : $response['summary']));
        }

        return array(
            'groups' => $groups,
            'warnings' => $warnings,
            'missing_inputs' => $missing,
            'source_count' => self::source_count($response),
            'sources' => self::safe_sources(isset($response['sources']) ? $response['sources'] : array()),
        );
    }

    public static function normalize_scout_response($response)
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            $response = json_last_error() === JSON_ERROR_NONE ? $decoded : array('markdown_summary' => $response);
        }
        $response = is_array($response) ? $response : array();
        $error = '';
        if (!empty($response['error'])) {
            $error = is_string($response['error']) ? $response['error'] : wp_json_encode($response['error']);
        } elseif (isset($response['success']) && !$response['success']) {
            $error = __('Scout returned an unsuccessful response.', 'qualinav-admin-console');
        }

        return array(
            'success' => $error === '',
            'api_request_id' => isset($response['request_id']) ? sanitize_text_field($response['request_id']) : (isset($response['api_request_id']) ? sanitize_text_field($response['api_request_id']) : ''),
            'source_count' => self::source_count($response),
            'sources' => self::safe_sources(isset($response['sources']) ? $response['sources'] : array()),
            'warnings' => self::listify(isset($response['warnings']) ? $response['warnings'] : array()),
            'missing_inputs' => self::listify(isset($response['missing_inputs']) ? $response['missing_inputs'] : array()),
            'preview' => self::extract_preview_summary($response),
            'error' => $error,
        );
    }

    public static function is_onboarding_submitted($organization_id)
    {
        $hospital = QN_Organizations::get_hospital($organization_id);
        if ($hospital && isset($hospital['onboarding_status']) && $hospital['onboarding_status'] === 'submitted') {
            return true;
        }

        $latest_run = self::get_latest_run($organization_id);
        if ($latest_run && !empty($latest_run['id'])) {
            return true;
        }

        $progress = QN_Onboarding::get_progress($organization_id);

        return !empty($progress['total_percent']) && absint($progress['total_percent']) >= 100;
    }

    private static function build_persona_summary($context)
    {
        $experience = isset($context['quality_director_experience']) ? $context['quality_director_experience'] : 'unknown';
        $hospital = isset($context['hospital_category']) ? $context['hospital_category'] : 'unknown';
        $pathway = isset($context['accreditation_pathway']) ? $context['accreditation_pathway'] : 'unknown';

        $experience_label = $experience === 'new' ? __('New Quality Director', 'qualinav-admin-console') : ($experience === 'experienced' ? __('Experienced Quality Director', 'qualinav-admin-console') : __('Quality Director', 'qualinav-admin-console'));
        $hospital_label = self::persona_label($hospital);
        $pathway_label = self::persona_label($pathway);

        if ($pathway === 'unknown') {
            return sprintf(__('%1$s at a %2$s.', 'qualinav-admin-console'), $experience_label, $hospital_label);
        }

        return sprintf(__('%1$s at a %2$s on a %3$s pathway.', 'qualinav-admin-console'), $experience_label, $hospital_label, $pathway_label);
    }

    private static function derive_payment_model($hospital_type, $org_payment_model, $is_cah, $haystack)
    {
        if (in_array($org_payment_model, array('cah', 'pps', 'other'), true)) {
            return $org_payment_model;
        }
        if ($is_cah || in_array($hospital_type, array('critical_access_hospital', 'cah'), true)) {
            return 'cah';
        }
        if ($hospital_type === 'rural_pps') {
            return 'pps';
        }
        if (preg_match('/\b(pps|iqr|oqr|promoting interoperability|value[- ]based|hcahps|ecqm)\b/i', $haystack)) {
            return 'pps';
        }

        return 'unknown';
    }

    private static function derive_hospital_category($hospital_type, $payment_model, $is_cah)
    {
        if ($is_cah || in_array($hospital_type, array('critical_access_hospital', 'cah'), true)) {
            return 'critical_access_hospital';
        }
        if ($hospital_type === 'rural_pps' || ($hospital_type === 'rural_hospital' && $payment_model === 'pps')) {
            return 'rural_pps_hospital';
        }
        if (in_array($hospital_type, array('acute_care_hospital', 'ipps_general_acute'), true)) {
            return 'acute_care_hospital';
        }
        if ($hospital_type !== '') {
            return $hospital_type;
        }

        return 'unknown';
    }

    private static function normalize_accreditation_body($value)
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return 'unknown';
        }
        if (strpos($value, 'joint') !== false || strpos($value, 'tjc') !== false) {
            return 'joint_commission';
        }
        if (strpos($value, 'dnv') !== false) {
            return 'dnv';
        }
        if (strpos($value, 'hfap') !== false) {
            return 'hfap';
        }
        if (strpos($value, 'cihq') !== false) {
            return 'cihq';
        }
        if (strpos($value, 'state') !== false || strpos($value, 'cms') !== false || strpos($value, 'none') !== false) {
            return 'none';
        }

        return 'unknown';
    }

    private static function derive_survey_pathway($answer_map, $accreditation_body)
    {
        $status = strtolower((string) self::answer_value($answer_map, 'accreditation_status'));
        $cms_pathway = strtolower((string) self::answer_value($answer_map, 'cms_certification_pathway'));
        $state_agency = strtolower((string) self::answer_value($answer_map, 'state_survey_agency'));

        if ($status === 'accredited' && !in_array($accreditation_body, array('none', 'unknown'), true)) {
            return 'accreditation';
        }
        if ($cms_pathway !== '' || $state_agency !== '' || $status === 'cms_certified') {
            return 'cms_state_survey';
        }

        return 'unknown';
    }

    private static function derive_accreditation_pathway($answer_map, $accreditation_body, $survey_pathway, $haystack)
    {
        if ($accreditation_body === 'joint_commission') {
            return stripos($haystack, 'accreditation 360') !== false ? 'joint_commission_accreditation_360' : 'joint_commission';
        }
        if (in_array($accreditation_body, array('dnv', 'hfap', 'cihq'), true)) {
            return $accreditation_body;
        }
        if ($survey_pathway === 'cms_state_survey') {
            return 'cms_state_survey_only';
        }

        return 'unknown';
    }

    private static function derive_quality_director_experience($answer_map)
    {
        if (self::answer_is_yes(self::answer_value($answer_map, 'new_to_quality_director_role'))) {
            return 'new';
        }

        $start_date = self::answer_value($answer_map, 'quality_director_role_start_date');
        if ($start_date && self::date_is_within_year($start_date)) {
            return 'new';
        }

        $time_in_role = strtolower((string) self::answer_value($answer_map, 'time_in_current_role'));
        if ($time_in_role !== '') {
            if (preg_match('/(under|less than|<)\s*1|(\b0\b)|month/i', $time_in_role)) {
                return 'new';
            }
            if (preg_match('/([1-2](\.\d+)?)\s*(year|yr)|1\s*-\s*3|one|two/i', $time_in_role)) {
                return 'developing';
            }
            if (preg_match('/([3-9]|\d{2,})\+?\s*(year|yr)|3\+|three|four|five/i', $time_in_role)) {
                return 'experienced';
            }
        }

        $confidence = self::average_confidence($answer_map);
        if ($confidence !== null) {
            if ($confidence <= 2.5) {
                return 'new';
            }
            if ($confidence < 4) {
                return 'developing';
            }
            return 'experienced';
        }

        return 'unknown';
    }

    private static function derive_first_30_days_track($answer_map, $new_director)
    {
        $preference = strtolower((string) self::answer_value($answer_map, 'activate_first_30_days_track'));
        if (in_array($preference, array('yes', 'true', '1'), true)) {
            return true;
        }
        if (in_array($preference, array('no', 'false', '0'), true)) {
            return false;
        }

        return (bool) $new_director;
    }

    private static function derive_learning_journey_enabled($answer_map, $new_director)
    {
        if ($new_director) {
            return true;
        }
        $confidence = self::average_confidence($answer_map);
        if ($confidence !== null && $confidence < 4) {
            return true;
        }

        return trim((string) self::answer_value($answer_map, 'learning_format_preference')) !== '';
    }

    private static function derive_program_maturity($answer_map)
    {
        $required_plans = array('qapi_plan_status', 'patient_safety_plan_status', 'infection_prevention_plan_status', 'emergency_preparedness_plan_status', 'risk_management_plan_status');
        $missing = 0;
        $present = 0;
        foreach ($required_plans as $key) {
            $exists = self::plan_exists(self::answer_value($answer_map, $key));
            if ($exists === true) {
                $present++;
            } elseif ($exists === false) {
                $missing++;
            }
        }

        $has_policy_cycle = self::has_answer($answer_map, 'annual_policy_review_cycle');
        $has_dashboard = self::has_answer($answer_map, 'current_quality_dashboard');
        $has_readiness = self::has_answer($answer_map, 'current_readiness_activities');
        $has_monitoring = self::has_answer($answer_map, 'weakest_monitoring_areas') || self::has_answer($answer_map, 'contracted_service_quality_data_flow');
        $has_qi_projects = self::has_answer($answer_map, 'active_qi_projects');
        $has_gaps = self::has_answer($answer_map, 'program_gaps') || self::has_answer($answer_map, 'templates_needed');

        if ($missing >= 2 || (!$has_policy_cycle && !$has_dashboard && !$has_readiness)) {
            return 'thin';
        }
        if ($present >= 4 && $has_policy_cycle && $has_dashboard && $has_monitoring && $has_qi_projects && !$has_gaps) {
            return 'mature';
        }
        if ($present > 0 || $has_policy_cycle || $has_dashboard || $has_readiness || $has_qi_projects || $has_gaps) {
            return 'partial';
        }

        return 'unknown';
    }

    private static function derive_guidance_level($experience, $new_director, $first_30_days_track, $program_maturity)
    {
        if ($new_director || $first_30_days_track) {
            return 'guided';
        }
        if ($experience === 'experienced' && !$first_30_days_track) {
            return 'light';
        }
        if ($experience === 'developing' || $program_maturity === 'partial') {
            return 'standard';
        }

        return 'standard';
    }

    private static function group_onboarding_answers($organization_id, $answers = null)
    {
        if ($answers === null) {
            $answers = QN_Questionnaire::get_answer_map($organization_id);
        }
        $sections = QN_Questionnaire::get_sections();
        $grouped = array();

        foreach ($sections as $section) {
            $section_key = $section['section_key'];
            $grouped[$section_key] = array(
                'title' => $section['title'],
                'answers' => array(),
            );
            foreach (QN_Questionnaire::get_questions($section_key) as $question) {
                $question_key = $question['question_key'];
                if (array_key_exists($question_key, $answers)) {
                    $grouped[$section_key]['answers'][$question_key] = array(
                        'label' => $question['label'],
                        'value' => $answers[$question_key],
                    );
                }
            }
        }

        return $grouped;
    }

    private static function normalize_run_row($row)
    {
        $response = !empty($row['response_json']) ? json_decode($row['response_json'], true) : null;
        $request_payload = !empty($row['request_payload_json']) ? json_decode($row['request_payload_json'], true) : array();
        $normalized = $response ? self::normalize_scout_response($response) : array(
            'preview' => self::extract_preview_summary(array()),
            'warnings' => array(),
            'missing_inputs' => array(),
            'source_count' => null,
            'sources' => array(),
            'error' => '',
        );

        return array(
            'id' => absint($row['id']),
            'organization_id' => absint($row['organization_id']),
            'request_type' => $row['request_type'],
            'input_data_type' => $row['input_data_type'],
            'status' => $row['status'],
            'api_request_id' => $row['api_request_id'],
            'source_count' => $row['source_count'] !== null ? absint($row['source_count']) : $normalized['source_count'],
            'error_message' => $row['error_message'],
            'generated_by' => $row['generated_by'] !== null ? absint($row['generated_by']) : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'response' => $response,
            'preview' => isset($normalized['preview']) ? $normalized['preview'] : self::extract_preview_summary($response),
            'warnings' => isset($normalized['warnings']) ? $normalized['warnings'] : array(),
            'missing_inputs' => isset($normalized['missing_inputs']) ? $normalized['missing_inputs'] : array(),
            'sources' => isset($normalized['sources']) ? $normalized['sources'] : array(),
            'persona_context' => isset($request_payload['persona_context']) && is_array($request_payload['persona_context']) ? self::sanitize_deep($request_payload['persona_context']) : array(),
            'persona_summary' => isset($request_payload['persona_summary']) ? sanitize_text_field($request_payload['persona_summary']) : '',
            'request_meta' => array(
                'request_type' => isset($request_payload['request_type']) ? sanitize_key($request_payload['request_type']) : $row['request_type'],
                'input_data_type' => $row['input_data_type'],
                'questionnaire_version' => isset($request_payload['questionnaire_version']) ? sanitize_text_field($request_payload['questionnaire_version']) : '',
            ),
        );
    }

    private static function answer_value($answer_map, $key)
    {
        return isset($answer_map[$key]) ? $answer_map[$key] : null;
    }

    private static function answer_is_yes($value)
    {
        return in_array(strtolower(trim((string) $value)), array('yes', 'true', '1'), true);
    }

    private static function has_answer($answer_map, $key)
    {
        if (!array_key_exists($key, $answer_map)) {
            return false;
        }

        return self::value_has_content($answer_map[$key]);
    }

    private static function value_has_content($value)
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                if (self::value_has_content($child)) {
                    return true;
                }
            }
            return false;
        }

        return trim((string) $value) !== '';
    }

    private static function answers_haystack($answer_map)
    {
        $parts = array();
        array_walk_recursive($answer_map, function ($value) use (&$parts) {
            if (is_scalar($value)) {
                $parts[] = (string) $value;
            }
        });

        return strtolower(implode(' ', $parts));
    }

    private static function date_is_within_year($value)
    {
        $timestamp = strtotime((string) $value);
        if (!$timestamp) {
            return false;
        }

        return $timestamp >= strtotime('-1 year');
    }

    private static function average_confidence($answer_map)
    {
        $keys = array('confidence_foundational', 'confidence_qi_patient_safety', 'confidence_specialized_areas', 'confidence_professional_development');
        $values = array();
        foreach ($keys as $key) {
            $value = self::answer_value($answer_map, $key);
            if (is_numeric($value)) {
                $values[] = (float) $value;
            }
        }
        if (!$values) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    private static function plan_exists($value)
    {
        if (!is_array($value)) {
            return null;
        }

        $exists = isset($value['exists']) ? strtolower((string) $value['exists']) : '';
        if (in_array($exists, array('yes', 'true', '1', 'exists', 'current'), true)) {
            return true;
        }
        if (in_array($exists, array('no', 'false', '0', 'missing', 'not_exists', 'none'), true)) {
            return false;
        }

        return null;
    }

    private static function persona_label($value)
    {
        $labels = array(
            'critical_access_hospital' => __('Critical Access Hospital', 'qualinav-admin-console'),
            'rural_pps_hospital' => __('Rural PPS Hospital', 'qualinav-admin-console'),
            'rural_hospital' => __('Rural Hospital', 'qualinav-admin-console'),
            'acute_care_hospital' => __('Acute Care Hospital', 'qualinav-admin-console'),
            'joint_commission_accreditation_360' => __('Joint Commission Accreditation 360', 'qualinav-admin-console'),
            'joint_commission' => __('Joint Commission', 'qualinav-admin-console'),
            'cms_state_survey_only' => __('CMS/state survey', 'qualinav-admin-console'),
            'cms_state_survey' => __('CMS/state survey', 'qualinav-admin-console'),
            'dnv' => __('DNV', 'qualinav-admin-console'),
            'hfap' => __('HFAP', 'qualinav-admin-console'),
            'cihq' => __('CIHQ', 'qualinav-admin-console'),
            'unknown' => __('hospital', 'qualinav-admin-console'),
        );

        return isset($labels[$value]) ? $labels[$value] : ucwords(str_replace('_', ' ', (string) $value));
    }

    private static function sanitize_deep($value, $key = '')
    {
        $blocked_keys = array('password', 'pass', 'token', 'token_hash', 'api_key', 'secret', 'authorization', 'cookie');
        $lower_key = strtolower((string) $key);
        foreach ($blocked_keys as $blocked) {
            if ($lower_key !== '' && strpos($lower_key, $blocked) !== false) {
                return null;
            }
        }

        if (is_array($value)) {
            $clean = array();
            foreach ($value as $child_key => $child_value) {
                $clean[$child_key] = self::sanitize_deep($child_value, $child_key);
            }
            return $clean;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return sanitize_textarea_field((string) $value);
    }

    private static function listify($items)
    {
        if ($items === null || $items === '') {
            return array();
        }
        if (!is_array($items)) {
            return array($items);
        }
        if ($items === array()) {
            return array();
        }
        if (array_keys($items) !== range(0, count($items) - 1)) {
            return array($items);
        }

        return array_values(array_filter($items, function ($item) {
            return !($item === null || $item === '' || $item === array());
        }));
    }

    private static function preview_group($key, $items)
    {
        return array(
            'key' => sanitize_key($key),
            'title' => self::labelize($key),
            'items' => $items,
            'item_count' => count($items),
        );
    }

    private static function labelize($key)
    {
        $labels = array(
            'master_reporting_schedule' => __('Master Reporting Schedule', 'qualinav-admin-console'),
            'reporting_schedule' => __('Reporting Schedule', 'qualinav-admin-console'),
            'meeting_report_flow_map' => __('Meeting & Report Flow Map', 'qualinav-admin-console'),
            'committee_flow_map' => __('Committee Flow Map', 'qualinav-admin-console'),
            'survey_readiness_timeline' => __('Survey Readiness Timeline', 'qualinav-admin-console'),
            'active_monitoring_improvement_tasks' => __('Active Monitoring & Improvement Tasks', 'qualinav-admin-console'),
            'recurring_clinical_monitoring' => __('Recurring Clinical Monitoring', 'qualinav-admin-console'),
            'plan_policy_tasks' => __('Plans & Policies', 'qualinav-admin-console'),
            'clinical_monitoring_tasks' => __('Clinical Monitoring', 'qualinav-admin-console'),
            'aggregate_data_uploads' => __('Aggregate Data Uploads', 'qualinav-admin-console'),
            'routine_task_rhythm' => __('Routine Task Rhythm', 'qualinav-admin-console'),
            'active_improvement_projects' => __('Active Improvement Projects', 'qualinav-admin-console'),
            'qi_project_milestones' => __('QI Project Milestones', 'qualinav-admin-console'),
            'priority_queue' => __('Priority Queue', 'qualinav-admin-console'),
            'first_30_days_learning_journey' => __('First 30 Days & Learning Journey', 'qualinav-admin-console'),
            'external_contact_directory' => __('External Contacts', 'qualinav-admin-console'),
            'regulatory_monitoring_preferences' => __('Regulatory Monitoring', 'qualinav-admin-console'),
            'learning_journey' => __('Learning Journey', 'qualinav-admin-console'),
            'reminder_rules' => __('Reminder Rules', 'qualinav-admin-console'),
            'persona_experience_summary' => __('Scout Experience Summary', 'qualinav-admin-console'),
            'scout_recommendations' => __('Scout Recommendations', 'qualinav-admin-console'),
            'scout_summary' => __('Scout Summary', 'qualinav-admin-console'),
        );

        return isset($labels[$key]) ? $labels[$key] : ucwords(str_replace('_', ' ', $key));
    }

    private static function workflow_keys()
    {
        return array(
            'persona_experience_summary',
            'master_reporting_schedule',
            'reporting_schedule',
            'meeting_report_flow_map',
            'committee_flow_map',
            'survey_readiness_timeline',
            'active_monitoring_improvement_tasks',
            'recurring_clinical_monitoring',
            'clinical_monitoring_tasks',
            'aggregate_data_uploads',
            'routine_task_rhythm',
            'active_improvement_projects',
            'qi_project_milestones',
            'priority_queue',
            'plan_policy_tasks',
            'regulatory_monitoring_preferences',
            'external_contact_directory',
            'first_30_days_learning_journey',
            'learning_journey',
            'reminder_rules',
        );
    }

    private static function has_any($array, $keys)
    {
        foreach ($keys as $key) {
            if (!empty($array[$key])) {
                return true;
            }
        }

        return false;
    }

    private static function source_count($response)
    {
        if (isset($response['source_count'])) {
            return absint($response['source_count']);
        }
        if (!empty($response['sources']) && is_array($response['sources'])) {
            return count($response['sources']);
        }

        return null;
    }

    private static function safe_sources($sources)
    {
        $safe = array();
        foreach (self::listify($sources) as $source) {
            if (is_array($source)) {
                $safe[] = self::sanitize_deep($source);
            } else {
                $safe[] = sanitize_text_field((string) $source);
            }
        }

        return $safe;
    }
}
