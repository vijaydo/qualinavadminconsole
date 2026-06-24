<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Organizations
{
    public static function get_hospitals($args = array())
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        if (!QN_DB::table_exists($table)) {
            return array();
        }

        $limit = isset($args['limit']) ? absint($args['limit']) : 100;
        $limit = $limit > 0 ? min($limit, 500) : 100;

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit)
        );

        return self::normalize_hospital_rows($rows);
    }

    public static function get_hospital($organization_id)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        $organization_id = absint($organization_id);
        if (!$organization_id || !QN_DB::table_exists($table) || !QN_DB::column_exists($table, 'id')) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $organization_id)
        );

        return $row ? self::normalize_hospital_row($row) : null;
    }

    public static function create_hospital($data)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        if (!QN_DB::table_exists($table)) {
            return new WP_Error('qn_missing_organizations_table', __('The organizations table does not exist.', 'qualinav-admin-console'), array('status' => 500));
        }

        $insert = self::prepare_hospital_data($data, true);
        if (empty($insert)) {
            return new WP_Error('qn_no_valid_hospital_fields', __('No supported hospital fields were provided.', 'qualinav-admin-console'), array('status' => 400));
        }

        $inserted = $wpdb->insert($table, $insert);
        if (!$inserted) {
            return new WP_Error('qn_hospital_create_failed', __('Unable to create hospital.', 'qualinav-admin-console'), array('status' => 500));
        }

        $hospital = self::get_hospital($wpdb->insert_id);
        QN_Audit_Log::log('hospital_created', 'organization', $wpdb->insert_id, null, $hospital, isset($hospital['id']) ? $hospital['id'] : null);

        return $hospital;
    }

    public static function update_hospital($organization_id, $data)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        $organization_id = absint($organization_id);
        if (!$organization_id || !QN_DB::table_exists($table)) {
            return new WP_Error('qn_invalid_hospital', __('Hospital not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $before = self::get_hospital($organization_id);
        if (!$before) {
            return new WP_Error('qn_invalid_hospital', __('Hospital not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $update = self::prepare_hospital_data($data, false);
        if (empty($update)) {
            return new WP_Error('qn_no_valid_hospital_fields', __('No supported hospital fields were provided.', 'qualinav-admin-console'), array('status' => 400));
        }

        $wpdb->update($table, $update, array('id' => $organization_id));

        $after = self::get_hospital($organization_id);
        QN_Audit_Log::log('hospital_updated', 'organization', $organization_id, $before, $after, $organization_id);
        if ($before['parent_system_id'] !== $after['parent_system_id']) {
            QN_Audit_Log::log('hospital_system_changed', 'organization', $organization_id, $before, $after, $organization_id);
        }
        if ($before['hospital_type'] !== $after['hospital_type']) {
            QN_Audit_Log::log('hospital_type_changed', 'organization', $organization_id, $before, $after, $organization_id);
        }
        if ($before['service_model'] !== $after['service_model']) {
            QN_Audit_Log::log('hospital_service_model_changed', 'organization', $organization_id, $before, $after, $organization_id);
        }

        return $after;
    }

    public static function get_hospital_type_options()
    {
        return array(
            'cah' => __('Critical Access Hospital', 'qualinav-admin-console'),
            'rural_pps' => __('Rural PPS Hospital', 'qualinav-admin-console'),
            'ipps_general_acute' => __('IPPS General Acute Hospital', 'qualinav-admin-console'),
            'critical_access_hospital' => __('Critical Access Hospital', 'qualinav-admin-console'),
            'rural_hospital' => __('Rural Hospital', 'qualinav-admin-console'),
            'acute_care_hospital' => __('Acute Care Hospital', 'qualinav-admin-console'),
            'swing_bed_hospital' => __('Swing Bed Hospital', 'qualinav-admin-console'),
            'specialty_hospital' => __('Specialty Hospital', 'qualinav-admin-console'),
            'outpatient_facility' => __('Outpatient Facility', 'qualinav-admin-console'),
            'other' => __('Other', 'qualinav-admin-console'),
        );
    }

    public static function get_service_model_options()
    {
        return array(
            'independent' => __('Independent', 'qualinav-admin-console'),
            'system_owned' => __('System-Owned', 'qualinav-admin-console'),
            'network_affiliated' => __('Network-Affiliated', 'qualinav-admin-console'),
            'managed_services' => __('Managed Services', 'qualinav-admin-console'),
            'other' => __('Other', 'qualinav-admin-console'),
        );
    }

    public static function get_payment_model_options()
    {
        return array(
            'cah' => __('Critical Access Hospital', 'qualinav-admin-console'),
            'pps' => __('Prospective Payment System', 'qualinav-admin-console'),
            'other' => __('Other', 'qualinav-admin-console'),
            'unknown' => __('Unknown', 'qualinav-admin-console'),
        );
    }

    public static function get_hospitals_by_system($system_id)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        if (!QN_DB::table_exists($table) || !QN_DB::column_exists($table, 'parent_system_id')) {
            return array();
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE parent_system_id = %d ORDER BY name ASC", absint($system_id))
        );

        return self::normalize_hospital_rows($rows);
    }

    public static function get_hospital_system($organization_id)
    {
        $hospital = self::get_hospital($organization_id);

        return $hospital && !empty($hospital['parent_system_id']) ? QN_Health_Systems::get_system($hospital['parent_system_id']) : null;
    }

    public static function get_states()
    {
        global $wpdb;

        $table = QN_DB::states_table();
        if (!QN_DB::table_exists($table)) {
            return array();
        }

        $order_column = QN_DB::column_exists($table, 'state_name') ? 'state_name' : (QN_DB::column_exists($table, 'name') ? 'name' : 'id');
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY {$order_column} ASC");

        return array_map(array(__CLASS__, 'normalize_state_row'), $rows);
    }

    public static function get_state($state_id)
    {
        global $wpdb;

        $table = QN_DB::states_table();
        $state_id = absint($state_id);
        if (!$state_id || !QN_DB::table_exists($table) || !QN_DB::column_exists($table, 'id')) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $state_id)
        );

        return $row ? self::normalize_state_row($row) : null;
    }

    public static function get_hospital_primary_quality_director($organization_id)
    {
        global $wpdb;

        $organization_id = absint($organization_id);
        if (!$organization_id) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID, display_name, user_email FROM {$wpdb->users} WHERE organization_id = %d AND qualinav_role = %s AND qualinav_status = %s ORDER BY ID ASC LIMIT 1",
                $organization_id,
                'quality_director',
                'active'
            )
        );

        if (!$row) {
            return null;
        }

        return array(
            'user_id' => absint($row->ID),
            'display_name' => $row->display_name,
            'user_email' => $row->user_email,
        );
    }

    public static function calculate_onboarding_percent($organization_id)
    {
        $hospital = self::get_raw_hospital($organization_id);
        if ($hospital && property_exists($hospital, 'onboarding_percent')) {
            return max(0, min(100, absint($hospital->onboarding_percent)));
        }

        return 0;
    }

    public static function dashboard_metrics()
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        $total_hospitals = 0;
        $active = 0;
        $inactive = 0;
        $pending = 0;

        if (QN_DB::table_exists($table)) {
            $total_hospitals = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

            if (QN_DB::column_exists($table, 'is_active')) {
                $active = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1");
                $inactive = max(0, $total_hospitals - $active);
            } elseif (QN_DB::column_exists($table, 'status')) {
                $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'active'));
                $inactive = max(0, $total_hospitals - $active);
            } else {
                $active = $total_hospitals;
                $inactive = 0;
            }

            if (QN_DB::column_exists($table, 'onboarding_percent')) {
                $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE onboarding_percent < 100 OR onboarding_percent IS NULL");
            } else {
                $pending = $total_hospitals;
            }
        }

        $quality_directors = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE qualinav_role = %s AND qualinav_status = %s", 'quality_director', 'active')
        );

        $hospital_roles = array('quality_director', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer');
        $placeholders = implode(',', array_fill(0, count($hospital_roles), '%s'));
        $hospital_users = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->users} WHERE qualinav_status = %s AND qualinav_role IN ({$placeholders})",
                array_merge(array('active'), $hospital_roles)
            )
        );

        return array(
            'total_hospitals' => $total_hospitals,
            'active_hospitals' => $active,
            'inactive_hospitals' => $inactive,
            'total_quality_directors' => $quality_directors,
            'total_hospital_users' => $hospital_users,
            'onboarding_pending' => $pending,
            'recent_hospitals' => self::get_hospitals(array('limit' => 5)),
        );
    }

    private static function prepare_hospital_data($data, $creating)
    {
        $table = QN_DB::organizations_table();
        $columns = QN_DB::get_table_columns($table);

        $prepared = array();
        $name = isset($data['organization_name']) ? sanitize_text_field($data['organization_name']) : '';
        if ($name !== '') {
            if (in_array('organization_name', $columns, true)) {
                $prepared['organization_name'] = $name;
            } elseif (in_array('name', $columns, true)) {
                $prepared['name'] = $name;
            }

            if ($creating && in_array('slug', $columns, true)) {
                $prepared['slug'] = self::generate_unique_slug($name);
            }
        }

        $field_sanitizers = array(
            'city' => 'sanitize_text_field',
            'zip' => 'sanitize_text_field',
            'status' => 'sanitize_key',
            'timezone' => 'sanitize_text_field',
            'ccn' => 'sanitize_text_field',
        );

        foreach ($field_sanitizers as $field => $sanitizer) {
            if (isset($data[$field]) && in_array($field, $columns, true)) {
                $prepared[$field] = call_user_func($sanitizer, $data[$field]);
            }
        }

        foreach (array('state_id', 'brandsetting_id') as $field) {
            if (isset($data[$field]) && in_array($field, $columns, true)) {
                $prepared[$field] = absint($data[$field]);
            }
        }

        if (isset($data['beds']) && in_array('beds', $columns, true)) {
            $prepared['beds'] = $data['beds'] === '' ? null : absint($data['beds']);
        }

        if (isset($data['parent_system_id']) && in_array('parent_system_id', $columns, true)) {
            $prepared['parent_system_id'] = absint($data['parent_system_id']);
        }

        $hospital_type_options = self::get_hospital_type_options();
        if (isset($data['hospital_type']) && in_array('hospital_type', $columns, true)) {
            $hospital_type = sanitize_key($data['hospital_type']);
            $prepared['hospital_type'] = isset($hospital_type_options[$hospital_type]) ? $hospital_type : null;
        }

        $service_model_options = self::get_service_model_options();
        if (isset($data['service_model']) && in_array('service_model', $columns, true)) {
            $service_model = sanitize_key($data['service_model']);
            $prepared['service_model'] = isset($service_model_options[$service_model]) ? $service_model : null;
        }

        $payment_model_options = self::get_payment_model_options();
        if (isset($data['payment_model']) && in_array('payment_model', $columns, true)) {
            $payment_model = sanitize_key($data['payment_model']);
            $prepared['payment_model'] = isset($payment_model_options[$payment_model]) ? $payment_model : null;
        }

        if ($creating && in_array('status', $columns, true) && empty($prepared['status'])) {
            $prepared['status'] = 'active';
        }

        if (isset($data['status']) && !in_array('status', $columns, true) && in_array('is_active', $columns, true)) {
            $prepared['is_active'] = sanitize_key($data['status']) === 'active' ? 1 : 0;
        } elseif ($creating && in_array('is_active', $columns, true) && !isset($prepared['is_active'])) {
            $prepared['is_active'] = 1;
        }

        if ($creating && in_array('created_at', $columns, true)) {
            $prepared['created_at'] = current_time('mysql');
        }

        if (in_array('updated_at', $columns, true)) {
            $prepared['updated_at'] = current_time('mysql');
        }

        return $prepared;
    }

    private static function generate_unique_slug($name)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        $base = sanitize_title($name);
        if ($base === '') {
            $base = 'hospital';
        }

        $slug = $base;
        $suffix = 2;

        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug))) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function get_raw_hospital($organization_id)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        $organization_id = absint($organization_id);
        if (!$organization_id || !QN_DB::table_exists($table) || !QN_DB::column_exists($table, 'id')) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $organization_id)
        );
    }

    private static function normalize_hospital_rows($rows)
    {
        if (empty($rows)) {
            return array();
        }

        $state_ids = array();
        $system_ids = array();
        $organization_ids = array();

        foreach ($rows as $row) {
            if (!empty($row->state_id)) {
                $state_ids[] = absint($row->state_id);
            }
            if (!empty($row->parent_system_id)) {
                $system_ids[] = absint($row->parent_system_id);
            }
            if (!empty($row->id)) {
                $organization_ids[] = absint($row->id);
            }
        }

        $context = array(
            'states' => self::get_state_map(array_unique($state_ids)),
            'systems' => self::get_system_map(array_unique($system_ids)),
            'primary_quality_directors' => self::get_primary_quality_director_map(array_unique($organization_ids)),
            'onboarding_section_progress' => self::get_onboarding_progress_summary_map(array_unique($organization_ids)),
        );

        return array_map(function ($row) use ($context) {
            return self::normalize_hospital_row($row, $context);
        }, $rows);
    }

    private static function get_state_map($state_ids)
    {
        $map = array();
        foreach ($state_ids as $state_id) {
            $state = self::get_state($state_id);
            if ($state) {
                $map[absint($state_id)] = $state;
            }
        }

        return $map;
    }

    private static function get_system_map($system_ids)
    {
        $map = array();
        foreach ($system_ids as $system_id) {
            $system = QN_Health_Systems::get_system($system_id);
            if ($system) {
                $map[absint($system_id)] = $system;
            }
        }

        return $map;
    }

    private static function get_primary_quality_director_map($organization_ids)
    {
        global $wpdb;

        $map = array();
        $organization_ids = array_values(array_filter(array_map('absint', $organization_ids)));
        if (empty($organization_ids)) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($organization_ids), '%d'));
        $user_org_table = QN_DB::user_organizations_table();

        if (QN_DB::table_exists($user_org_table)) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT uo.organization_id, u.ID, u.display_name, u.user_email
                     FROM {$user_org_table} uo
                     INNER JOIN {$wpdb->users} u ON u.ID = uo.user_id
                     WHERE uo.organization_id IN ({$placeholders})
                       AND uo.qualinav_role = %s
                       AND uo.status = %s
                       AND u.qualinav_status = %s
                     ORDER BY uo.is_default DESC, uo.id ASC",
                    array_merge($organization_ids, array('quality_director', 'active', 'active'))
                )
            );

            foreach ($rows as $row) {
                $organization_id = absint($row->organization_id);
                if (!isset($map[$organization_id])) {
                    $map[$organization_id] = array(
                        'ID' => absint($row->ID),
                        'display_name' => $row->display_name,
                        'user_email' => $row->user_email,
                    );
                }
            }
        }

        $missing_ids = array_values(array_diff($organization_ids, array_keys($map)));
        if (!empty($missing_ids)) {
            $missing_placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
            $legacy_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT organization_id, ID, display_name, user_email
                     FROM {$wpdb->users}
                     WHERE organization_id IN ({$missing_placeholders})
                       AND qualinav_role = %s
                       AND qualinav_status = %s
                     ORDER BY ID ASC",
                    array_merge($missing_ids, array('quality_director', 'active'))
                )
            );

            foreach ($legacy_rows as $row) {
                $organization_id = absint($row->organization_id);
                if (!isset($map[$organization_id])) {
                    $map[$organization_id] = array(
                        'ID' => absint($row->ID),
                        'display_name' => $row->display_name,
                        'user_email' => $row->user_email,
                    );
                }
            }
        }

        return $map;
    }

    private static function get_onboarding_progress_summary_map($organization_ids)
    {
        global $wpdb;

        $map = array();
        $organization_ids = array_values(array_filter(array_map('absint', $organization_ids)));
        $steps = class_exists('QN_Onboarding') ? QN_Onboarding::get_step_definitions() : array();
        if (empty($organization_ids) || empty($steps)) {
            return $map;
        }

        $progress_table = QN_DB::onboarding_progress_table();
        if (!QN_DB::table_exists($progress_table)) {
            return $map;
        }

        $step_titles = array();
        foreach ($steps as $step) {
            $step_titles[$step['section_key']] = $step['title'];
        }

        $placeholders = implode(',', array_fill(0, count($organization_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT organization_id, section_key, status, percent_complete
                 FROM {$progress_table}
                 WHERE organization_id IN ({$placeholders})",
                $organization_ids
            )
        );

        foreach ($organization_ids as $organization_id) {
            $map[$organization_id] = array(
                'total' => count($steps),
                'complete' => 0,
                'in_progress' => 0,
                'not_started' => count($steps),
                'sections' => array(),
            );
            foreach ($steps as $step) {
                $map[$organization_id]['sections'][$step['section_key']] = array(
                    'section_key' => $step['section_key'],
                    'title' => $step['title'],
                    'status' => 'not_started',
                    'percent_complete' => 0,
                );
            }
        }

        foreach ($rows as $row) {
            $organization_id = absint($row->organization_id);
            $section_key = sanitize_key($row->section_key);
            if (!isset($map[$organization_id]) || !isset($step_titles[$section_key])) {
                continue;
            }
            $percent = max(0, min(100, absint($row->percent_complete)));
            $status = $percent >= 100 ? 'complete' : ($percent > 0 ? 'in_progress' : 'not_started');
            $map[$organization_id]['sections'][$section_key] = array(
                'section_key' => $section_key,
                'title' => $step_titles[$section_key],
                'status' => $status,
                'percent_complete' => $percent,
            );
        }

        foreach ($map as $organization_id => $summary) {
            $complete = 0;
            $in_progress = 0;
            foreach ($summary['sections'] as $section) {
                if ($section['status'] === 'complete') {
                    $complete++;
                } elseif ($section['status'] === 'in_progress') {
                    $in_progress++;
                }
            }
            $map[$organization_id]['sections'] = array_values($summary['sections']);
            $map[$organization_id]['complete'] = $complete;
            $map[$organization_id]['in_progress'] = $in_progress;
            $map[$organization_id]['not_started'] = max(0, $summary['total'] - $complete - $in_progress);
        }

        return $map;
    }

    private static function normalize_hospital_row($row, $context = array())
    {
        $state_id = !empty($row->state_id) ? absint($row->state_id) : null;
        $system_id = !empty($row->parent_system_id) ? absint($row->parent_system_id) : null;
        $organization_id = !empty($row->id) ? absint($row->id) : 0;
        $state = $state_id && isset($context['states'][$state_id]) ? $context['states'][$state_id] : ($state_id ? self::get_state($state_id) : null);
        $system = $system_id && isset($context['systems'][$system_id]) ? $context['systems'][$system_id] : ($system_id ? QN_Health_Systems::get_system($system_id) : null);
        $primary_quality_director = $organization_id && isset($context['primary_quality_directors'][$organization_id]) ? $context['primary_quality_directors'][$organization_id] : ($organization_id ? self::get_hospital_primary_quality_director($organization_id) : null);
        $hospital_type = isset($row->hospital_type) ? $row->hospital_type : '';
        $service_model = isset($row->service_model) ? $row->service_model : '';
        $payment_model = isset($row->payment_model) ? $row->payment_model : '';
        $hospital_types = self::get_hospital_type_options();
        $service_models = self::get_service_model_options();
        $payment_models = self::get_payment_model_options();
        $name = '';
        if (isset($row->organization_name) && $row->organization_name !== '') {
            $name = $row->organization_name;
        } elseif (isset($row->name)) {
            $name = $row->name;
        }

        $status = 'active';
        if (isset($row->status) && $row->status !== '') {
            $status = $row->status;
        } elseif (isset($row->is_active)) {
            $status = absint($row->is_active) ? 'active' : 'inactive';
        }

        return array(
            'id' => $organization_id,
            'name' => $name,
            'organization_name' => $name,
            'city' => isset($row->city) ? $row->city : '',
            'zip' => isset($row->zip) ? $row->zip : '',
            'state_id' => isset($row->state_id) ? absint($row->state_id) : null,
            'state_name' => $state ? $state['name'] : '',
            'state_code' => $state ? $state['abbreviation'] : '',
            'parent_system_id' => isset($row->parent_system_id) ? absint($row->parent_system_id) : null,
            'parent_system_name' => $system ? $system['name'] : '',
            'hospital_type' => $hospital_type,
            'hospital_type_label' => isset($hospital_types[$hospital_type]) ? $hospital_types[$hospital_type] : __('Not specified.', 'qualinav-admin-console'),
            'service_model' => $service_model,
            'service_model_label' => isset($service_models[$service_model]) ? $service_models[$service_model] : __('Not specified.', 'qualinav-admin-console'),
            'payment_model' => $payment_model,
            'payment_model_label' => isset($payment_models[$payment_model]) ? $payment_models[$payment_model] : __('Unknown', 'qualinav-admin-console'),
            'beds' => isset($row->beds) ? absint($row->beds) : null,
            'licensed_beds' => isset($row->licensed_beds) ? absint($row->licensed_beds) : (isset($row->beds) ? absint($row->beds) : null),
            'acute_beds' => isset($row->acute_beds) ? absint($row->acute_beds) : null,
            'swing_beds' => isset($row->swing_beds) ? absint($row->swing_beds) : null,
            'status' => $status,
            'is_active' => $status === 'active',
            'timezone' => isset($row->timezone) ? $row->timezone : '',
            'ccn' => isset($row->ccn) ? $row->ccn : '',
            'brandsetting_id' => isset($row->brandsetting_id) ? absint($row->brandsetting_id) : null,
            'onboarding_status' => isset($row->onboarding_status) ? $row->onboarding_status : '',
            'onboarding_percent' => isset($row->onboarding_percent) ? max(0, min(100, absint($row->onboarding_percent))) : 0,
            'onboarding_section_progress' => isset($context['onboarding_section_progress'][$organization_id]) ? $context['onboarding_section_progress'][$organization_id] : null,
            'primary_quality_director' => $primary_quality_director,
            'created_at' => isset($row->created_at) ? $row->created_at : null,
            'updated_at' => isset($row->updated_at) ? $row->updated_at : null,
        );
    }

    private static function normalize_state_row($row)
    {
        $name = '';
        if (isset($row->state_name) && $row->state_name !== '') {
            $name = $row->state_name;
        } elseif (isset($row->name) && $row->name !== '') {
            $name = $row->name;
        } elseif (isset($row->abbreviation)) {
            $name = $row->abbreviation;
        } elseif (isset($row->code)) {
            $name = $row->code;
        }

        return array(
            'id' => isset($row->id) ? absint($row->id) : 0,
            'name' => $name,
            'abbreviation' => isset($row->abbreviation) ? $row->abbreviation : (isset($row->code) ? $row->code : ''),
        );
    }
}
