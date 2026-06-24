<?php
$organization_id = 1382;
global $wpdb;
$table = $wpdb->prefix . 'qualinav_scout_runs';
$row = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id, organization_id, request_type, input_data_type, request_payload_json, response_json, status, error_message, source_count, api_request_id, created_at, updated_at FROM {$table} WHERE organization_id = %d ORDER BY created_at DESC, id DESC LIMIT 1",
        $organization_id
    ),
    ARRAY_A
);

if (!$row) {
    echo wp_json_encode(array('found' => false), JSON_PRETTY_PRINT);
    return;
}

$request = json_decode($row['request_payload_json'], true);
$response = json_decode($row['response_json'], true);

$workflow_keys = array(
    'persona_experience_summary',
    'master_reporting_schedule',
    'meeting_report_flow_map',
    'survey_readiness_timeline',
    'plan_policy_tasks',
    'recurring_clinical_monitoring',
    'aggregate_data_uploads',
    'routine_task_rhythm',
    'active_improvement_projects',
    'priority_queue',
    'first_30_days_learning_journey',
    'learning_journey',
    'reminder_rules',
);

$response_top_keys = is_array($response) ? array_keys($response) : array();
$response_workflows = isset($response['workflows']) && is_array($response['workflows']) ? $response['workflows'] : array();
$preview = isset($response['preview']) && is_array($response['preview']) ? $response['preview'] : array();
$normalized = class_exists('QN_Scout') && is_array($response) ? QN_Scout::normalize_scout_response($response) : array();
$normalized_preview = isset($normalized['preview']) && is_array($normalized['preview']) ? $normalized['preview'] : array();

$summary = array(
    'found' => true,
    'row' => array(
        'id' => absint($row['id']),
        'organization_id' => absint($row['organization_id']),
        'request_type' => $row['request_type'],
        'input_data_type' => $row['input_data_type'],
        'status' => $row['status'],
        'error_message' => $row['error_message'],
        'source_count' => is_null($row['source_count']) ? null : absint($row['source_count']),
        'api_request_id_present' => $row['api_request_id'] !== '',
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ),
    'request_diagnosis' => array(
        'top_keys' => is_array($request) ? array_keys($request) : array(),
        'request_type' => isset($request['request_type']) ? $request['request_type'] : null,
        'input_data_type_expected' => $row['input_data_type'] === 'qualinav-day0-workflow-generation-v1',
        'has_organization_context' => isset($request['organization_id'], $request['organization_name'], $request['hospital_type']),
        'has_onboarding_answers' => isset($request['onboarding_answers']) && is_array($request['onboarding_answers']),
        'onboarding_sections' => isset($request['onboarding_answers']) && is_array($request['onboarding_answers']) ? array_keys($request['onboarding_answers']) : array(),
        'has_persona_context' => isset($request['persona_context']) && is_array($request['persona_context']),
        'persona_context' => isset($request['persona_context']) && is_array($request['persona_context']) ? $request['persona_context'] : null,
        'persona_summary' => isset($request['persona_summary']) ? $request['persona_summary'] : null,
        'has_no_phi_constraints' => !empty($request['constraints']['no_phi']),
        'requested_outputs' => isset($request['requested_outputs']) && is_array($request['requested_outputs']) ? $request['requested_outputs'] : array(),
        'missing_requested_outputs' => array_values(array_diff($workflow_keys, isset($request['requested_outputs']) && is_array($request['requested_outputs']) ? $request['requested_outputs'] : array())),
    ),
    'response_diagnosis' => array(
        'top_keys' => $response_top_keys,
        'has_workflows_object' => !empty($response_workflows),
        'workflow_keys_present' => array_values(array_intersect($workflow_keys, array_keys($response_workflows))),
        'top_level_workflow_keys_present' => array_values(array_intersect($workflow_keys, $response_top_keys)),
        'has_preview_object' => !empty($preview),
        'preview_keys' => array_keys($preview),
        'warnings' => isset($response['warnings']) ? $response['warnings'] : array(),
        'missing_inputs' => isset($response['missing_inputs']) ? $response['missing_inputs'] : array(),
        'sources_count' => isset($response['sources']) && is_array($response['sources']) ? count($response['sources']) : 0,
        'normalized_preview_keys' => array_keys($normalized_preview),
        'normalized_warnings' => isset($normalized['warnings']) ? $normalized['warnings'] : array(),
        'normalized_error' => isset($normalized['error']) ? $normalized['error'] : '',
    ),
);

echo wp_json_encode($summary, JSON_PRETTY_PRINT);
