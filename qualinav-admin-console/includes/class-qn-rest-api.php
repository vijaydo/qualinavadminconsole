<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_REST_API
{
    const NAMESPACE = 'qualinav/v1';

    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes()
    {
        register_rest_route(self::NAMESPACE, '/me', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_me'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/brand', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_brand'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/my-organizations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_my_organizations'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/switch-organization', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'switch_organization'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/admin/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_admin_dashboard'),
            'permission_callback' => function () {
                return self::permission_check('access_super_admin');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/system-check', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_admin_system_check'),
            'permission_callback' => function () {
                return self::permission_check('access_super_admin');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/hospitals', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_admin_hospitals'),
                'permission_callback' => function () {
                    return self::permission_check('access_super_admin');
                },
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'create_admin_hospital'),
                'permission_callback' => function () {
                    return self::permission_check('manage_all_hospitals');
                },
            ),
        ));

        register_rest_route(self::NAMESPACE, '/admin/hospitals/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_admin_hospital'),
                'permission_callback' => function () {
                    return self::permission_check('access_super_admin');
                },
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(__CLASS__, 'update_admin_hospital'),
                'permission_callback' => function () {
                    return self::permission_check('manage_all_hospitals');
                },
            ),
        ));

        register_rest_route(self::NAMESPACE, '/admin/states', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_admin_states'),
            'permission_callback' => function () {
                return self::permission_check('access_super_admin');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/brand/(?P<organization_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_admin_brand'),
                'permission_callback' => function () {
                    return self::permission_check('manage_branding');
                },
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(__CLASS__, 'update_admin_brand'),
                'permission_callback' => function () {
                    return self::permission_check('manage_branding');
                },
            ),
        ));

        register_rest_route(self::NAMESPACE, '/admin/health-systems', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_admin_health_systems'),
                'permission_callback' => function () {
                    return self::permission_check('access_super_admin');
                },
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'create_admin_health_system'),
                'permission_callback' => function () {
                    return self::permission_check('manage_all_hospitals');
                },
            ),
        ));

        register_rest_route(self::NAMESPACE, '/admin/health-systems/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_admin_health_system'),
                'permission_callback' => function () {
                    return self::permission_check('access_super_admin');
                },
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array(__CLASS__, 'update_admin_health_system'),
                'permission_callback' => function () {
                    return self::permission_check('manage_all_hospitals');
                },
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array(__CLASS__, 'deactivate_admin_health_system'),
                'permission_callback' => function () {
                    return self::permission_check('manage_all_hospitals');
                },
            ),
        ));

        register_rest_route(self::NAMESPACE, '/admin/health-systems/(?P<id>\d+)/hospitals', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_admin_health_system_hospitals'),
            'permission_callback' => function () {
                return self::permission_check('access_super_admin');
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital-types', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_hospital_types'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/service-models', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_service_models'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/admin/users', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_admin_users'),
            'permission_callback' => function () {
                return self::any_permission_check(array('access_super_admin', 'manage_all_users'));
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/users/invite', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'admin_invite_user'),
            'permission_callback' => function () {
                return self::permission_check('manage_all_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital/users/invite', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'hospital_invite_user'),
            'permission_callback' => function () {
                return self::any_permission_check(array('invite_hospital_users', 'invite_limited_users'));
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital/users', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_hospital_users'),
            'permission_callback' => function () {
                return self::any_permission_check(array('manage_hospital_users', 'access_hospital_console'));
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital/invitations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_hospital_invitations'),
            'permission_callback' => function () {
                return self::permission_check('manage_hospital_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/users/(?P<id>\d+)/role', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array(__CLASS__, 'admin_update_user_role'),
            'permission_callback' => function () {
                return self::permission_check('manage_all_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital/users/(?P<id>\d+)/role', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array(__CLASS__, 'hospital_update_user_role'),
            'permission_callback' => function () {
                return self::permission_check('manage_hospital_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/users/(?P<id>\d+)/status', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array(__CLASS__, 'admin_update_user_status'),
            'permission_callback' => function () {
                return self::permission_check('manage_all_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital/users/(?P<id>\d+)/status', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array(__CLASS__, 'hospital_update_user_status'),
            'permission_callback' => function () {
                return self::permission_check('manage_hospital_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/invitations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_admin_invitations'),
            'permission_callback' => function () {
                return self::permission_check('access_super_admin');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/invitations/(?P<id>\d+)/resend', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'admin_resend_invitation'),
            'permission_callback' => function () {
                return self::permission_check('manage_all_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/admin/invitations/(?P<id>\d+)/revoke', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'admin_revoke_invitation'),
            'permission_callback' => function () {
                return self::permission_check('manage_all_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital/invitations/(?P<id>\d+)/resend', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'hospital_resend_invitation'),
            'permission_callback' => function () {
                return self::permission_check('manage_hospital_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/hospital/invitations/(?P<id>\d+)/revoke', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'hospital_revoke_invitation'),
            'permission_callback' => function () {
                return self::permission_check('manage_hospital_users');
            },
        ));

        register_rest_route(self::NAMESPACE, '/onboarding', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_onboarding'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/onboarding/save', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'save_onboarding'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/onboarding/progress', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_onboarding_progress'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/onboarding/submit', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'submit_onboarding'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/scout/generate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'generate_scout_preview'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/scout/runs', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_scout_runs'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/scout/runs/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_scout_run'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));

        register_rest_route(self::NAMESPACE, '/scout/runs/(?P<id>\d+)/retry', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'retry_scout_run'),
            'permission_callback' => array(__CLASS__, 'can_read_me'),
        ));
    }

    public static function can_read_me()
    {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('You must be logged in.', 'qualinav-admin-console'), array('status' => 401));
        }

        $row = QN_Users::get_current_user_row();
        if (!$row || $row->qualinav_status !== 'active') {
            return new WP_Error('qn_inactive_user', __('Your QualiNav account is not active.', 'qualinav-admin-console'), array('status' => 403));
        }

        return true;
    }

    public static function permission_check($permission, $organization_id = null)
    {
        $base = self::can_read_me();
        if (is_wp_error($base)) {
            return $base;
        }

        if (!QN_Permissions::user_can(get_current_user_id(), $permission, $organization_id)) {
            return new WP_Error('qn_forbidden', __('You do not have permission for this QualiNav action.', 'qualinav-admin-console'), array('status' => 403));
        }

        return true;
    }

    public static function any_permission_check($permissions)
    {
        $base = self::can_read_me();
        if (is_wp_error($base)) {
            return $base;
        }

        foreach ($permissions as $permission) {
            if (QN_Permissions::user_can(get_current_user_id(), $permission)) {
                return true;
            }
        }

        return new WP_Error('qn_forbidden', __('You do not have permission for this QualiNav action.', 'qualinav-admin-console'), array('status' => 403));
    }

    public static function get_me()
    {
        $row = QN_Users::get_current_user_row();
        $effective_role = QN_Users::get_role_for_organization($row->ID, QN_Users::get_current_organization_id($row->ID)) ?: QN_Users::get_user_qualinav_role($row->ID);
        $permissions = QN_Permissions::role_permissions($effective_role);

        return rest_ensure_response(array(
            'user_id' => absint($row->ID),
            'display_name' => $row->display_name,
            'user_email' => $row->user_email,
            'organization_id' => $row->organization_id !== null ? absint($row->organization_id) : null,
            'state_id' => $row->state_id !== null ? absint($row->state_id) : null,
            'qualinav_role' => $effective_role,
            'qualinav_status' => $row->qualinav_status,
            'permissions' => $permissions,
        ));
    }

    public static function get_my_organizations()
    {
        $organizations = array_values(array_filter(QN_Users::get_user_organizations(get_current_user_id()), function ($organization) {
            return isset($organization['status']) && $organization['status'] === 'active';
        }));

        return rest_ensure_response($organizations);
    }

    public static function switch_organization(WP_REST_Request $request)
    {
        $organization_id = absint($request->get_param('organization_id'));
        if (!QN_Users::set_current_organization(get_current_user_id(), $organization_id)) {
            return new WP_Error('qn_invalid_organization_switch', __('You do not have active access to that hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $access = QN_Users::get_user_organization_access(get_current_user_id(), $organization_id);

        return rest_ensure_response($access);
    }

    public static function get_brand()
    {
        $user_id = get_current_user_id();
        $organization_id = QN_Users::is_hospital_user($user_id) ? QN_Users::get_user_organization_id($user_id) : null;

        return rest_ensure_response($organization_id ? QN_Branding::get_brand_for_organization($organization_id) : QN_Branding::get_default_brand());
    }

    public static function get_admin_dashboard()
    {
        return rest_ensure_response(QN_Organizations::dashboard_metrics());
    }

    public static function get_admin_system_check()
    {
        global $wpdb;

        $user = QN_Users::get_current_user_row();
        $required_user_columns = array('organization_id', 'state_id', 'qualinav_role', 'qualinav_status');
        $required_org_columns = array('parent_system_id', 'hospital_type', 'service_model');
        $required_tables = array(
            QN_DB::audit_logs_table(),
            QN_DB::invitations_table(),
            QN_DB::user_organizations_table(),
            QN_DB::health_systems_table(),
            QN_DB::questionnaire_sections_table(),
            QN_DB::questionnaire_questions_table(),
            QN_DB::questionnaire_answers_table(),
            QN_DB::onboarding_progress_table(),
            QN_DB::scout_runs_table(),
        );

        $missing_user_columns = self::missing_columns(QN_DB::users_table(), $required_user_columns);
        $missing_org_columns = self::missing_columns(QN_DB::organizations_table(), $required_org_columns);
        $missing_tables = array();
        foreach ($required_tables as $table) {
            if (!QN_DB::table_exists($table)) {
                $missing_tables[] = $table;
            }
        }

        return rest_ensure_response(array(
            'plugin_version' => defined('QN_ADMIN_CONSOLE_VERSION') ? QN_ADMIN_CONSOLE_VERSION : '',
            'environment' => 'Local',
            'db_prefix' => $wpdb->prefix,
            'required_user_columns' => array(
                'present' => array_values(array_diff($required_user_columns, $missing_user_columns)),
                'missing' => $missing_user_columns,
            ),
            'required_plugin_tables' => array(
                'present' => array_values(array_diff($required_tables, $missing_tables)),
                'missing' => $missing_tables,
            ),
            'organization_classification_columns' => array(
                'present' => array_values(array_diff($required_org_columns, $missing_org_columns)),
                'missing' => $missing_org_columns,
            ),
            'questionnaire_sections' => QN_DB::table_exists(QN_DB::questionnaire_sections_table()) ? absint($wpdb->get_var('SELECT COUNT(*) FROM ' . QN_DB::questionnaire_sections_table())) : 0,
            'questionnaire_questions' => QN_DB::table_exists(QN_DB::questionnaire_questions_table()) ? absint($wpdb->get_var('SELECT COUNT(*) FROM ' . QN_DB::questionnaire_questions_table())) : 0,
            'scout_bridge_available' => class_exists('QN_Scout') ? QN_Scout::is_bridge_available() : false,
            'scout_run_count' => QN_DB::table_exists(QN_DB::scout_runs_table()) ? absint($wpdb->get_var('SELECT COUNT(*) FROM ' . QN_DB::scout_runs_table())) : 0,
            'current_user' => array(
                'id' => $user ? absint($user->ID) : null,
                'qualinav_role' => $user ? $user->qualinav_role : '',
                'qualinav_status' => $user ? $user->qualinav_status : '',
            ),
        ));
    }

    public static function get_admin_hospitals(WP_REST_Request $request)
    {
        return rest_ensure_response(QN_Organizations::get_hospitals(array(
            'limit' => $request->get_param('limit') ? absint($request->get_param('limit')) : 100,
        )));
    }

    public static function create_admin_hospital(WP_REST_Request $request)
    {
        $hospital = QN_Organizations::create_hospital(self::hospital_payload($request));

        return is_wp_error($hospital) ? $hospital : rest_ensure_response($hospital);
    }

    public static function get_admin_hospital(WP_REST_Request $request)
    {
        $hospital = QN_Organizations::get_hospital(absint($request['id']));
        if (!$hospital) {
            return new WP_Error('qn_hospital_not_found', __('Hospital not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        return rest_ensure_response($hospital);
    }

    public static function update_admin_hospital(WP_REST_Request $request)
    {
        $hospital = QN_Organizations::update_hospital(absint($request['id']), self::hospital_payload($request));

        return is_wp_error($hospital) ? $hospital : rest_ensure_response($hospital);
    }

    public static function get_admin_states()
    {
        return rest_ensure_response(QN_Organizations::get_states());
    }

    public static function get_admin_brand(WP_REST_Request $request)
    {
        return rest_ensure_response(QN_Branding::get_brand_for_organization(absint($request['organization_id'])));
    }

    public static function update_admin_brand(WP_REST_Request $request)
    {
        $brand = QN_Branding::update_brand_for_organization(absint($request['organization_id']), $request->get_json_params());

        return is_wp_error($brand) ? $brand : rest_ensure_response($brand);
    }

    public static function get_admin_health_systems()
    {
        return rest_ensure_response(QN_Health_Systems::get_systems());
    }

    public static function create_admin_health_system(WP_REST_Request $request)
    {
        $system = QN_Health_Systems::create_system(self::health_system_payload($request));

        return is_wp_error($system) ? $system : rest_ensure_response($system);
    }

    public static function get_admin_health_system(WP_REST_Request $request)
    {
        $system = QN_Health_Systems::get_system(absint($request['id']));
        if (!$system) {
            return new WP_Error('qn_system_not_found', __('Health system not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        return rest_ensure_response($system);
    }

    public static function update_admin_health_system(WP_REST_Request $request)
    {
        $system = QN_Health_Systems::update_system(absint($request['id']), self::health_system_payload($request));

        return is_wp_error($system) ? $system : rest_ensure_response($system);
    }

    public static function deactivate_admin_health_system(WP_REST_Request $request)
    {
        $system = QN_Health_Systems::delete_or_deactivate_system(absint($request['id']));

        return is_wp_error($system) ? $system : rest_ensure_response($system);
    }

    public static function get_admin_health_system_hospitals(WP_REST_Request $request)
    {
        return rest_ensure_response(QN_Health_Systems::get_system_hospitals(absint($request['id'])));
    }

    public static function get_hospital_types()
    {
        return rest_ensure_response(self::options_to_list(QN_Organizations::get_hospital_type_options()));
    }

    public static function get_service_models()
    {
        return rest_ensure_response(self::options_to_list(QN_Organizations::get_service_model_options()));
    }

    public static function get_admin_users()
    {
        return rest_ensure_response(QN_Invitations::get_users());
    }

    public static function admin_invite_user(WP_REST_Request $request)
    {
        $invitation = QN_Invitations::invite_user(self::invite_payload($request, true));

        return is_wp_error($invitation) ? $invitation : rest_ensure_response($invitation);
    }

    public static function hospital_invite_user(WP_REST_Request $request)
    {
        $payload = self::invite_payload($request, false);
        unset($payload['organization_id']);
        $invitation = QN_Invitations::invite_user($payload);

        return is_wp_error($invitation) ? $invitation : rest_ensure_response($invitation);
    }

    public static function get_hospital_users()
    {
        $organization_id = QN_Users::get_user_organization_id(get_current_user_id());

        return rest_ensure_response(QN_Invitations::get_users(array('organization_id' => $organization_id)));
    }

    public static function get_hospital_invitations()
    {
        $organization_id = QN_Users::get_user_organization_id(get_current_user_id());

        return rest_ensure_response(QN_Invitations::get_invitations(array('organization_id' => $organization_id)));
    }

    public static function admin_update_user_role(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        $new_role = sanitize_key($request->get_param('qualinav_role'));

        if ($user_id === get_current_user_id() && !in_array($new_role, array('qualinav_super_admin', 'qualinav_admin'), true)) {
            return new WP_Error('qn_self_role_lower', __('You cannot lower your own admin role.', 'qualinav-admin-console'), array('status' => 400));
        }

        $updated = QN_Invitations::update_user_role($user_id, $new_role);

        return is_wp_error($updated) ? $updated : rest_ensure_response($updated);
    }

    public static function hospital_update_user_role(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        $new_role = sanitize_key($request->get_param('qualinav_role'));
        $access = self::validate_hospital_target_user($user_id);
        if (is_wp_error($access)) {
            return $access;
        }

        $current_org = QN_Users::get_user_organization_id(get_current_user_id());
        $current_role = QN_Users::get_role_for_organization(get_current_user_id(), $current_org);
        $target_role = $access['qualinav_role'];
        if ($target_role === 'quality_director') {
            return new WP_Error('qn_no_qd_role_change', __('Hospital users cannot modify a Quality Director role.', 'qualinav-admin-console'), array('status' => 403));
        }

        if (!QN_Invitations::can_invite_role($current_role, $new_role) || in_array($new_role, array('qualinav_super_admin', 'qualinav_admin', 'quality_director'), true)) {
            return new WP_Error('qn_invalid_hospital_role', __('You cannot assign that role.', 'qualinav-admin-console'), array('status' => 403));
        }

        $updated = QN_Users::update_user_organization_role($user_id, $current_org, $new_role);
        QN_Audit_Log::log('user_role_changed', 'user', $user_id, $access, $updated, $current_org);

        return is_wp_error($updated) ? $updated : rest_ensure_response($updated);
    }

    public static function admin_update_user_status(WP_REST_Request $request)
    {
        $updated = QN_Invitations::update_user_status(absint($request['id']), sanitize_key($request->get_param('qualinav_status')));

        return is_wp_error($updated) ? $updated : rest_ensure_response($updated);
    }

    public static function hospital_update_user_status(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        if ($user_id === get_current_user_id()) {
            return new WP_Error('qn_no_self_status', __('You cannot change your own status.', 'qualinav-admin-console'), array('status' => 400));
        }

        $access = self::validate_hospital_target_user($user_id);
        if (is_wp_error($access)) {
            return $access;
        }

        $target_role = $access['qualinav_role'];
        if ($target_role === 'quality_director') {
            return new WP_Error('qn_no_qd_status_change', __('Hospital users cannot modify a Quality Director status.', 'qualinav-admin-console'), array('status' => 403));
        }

        $new_status = sanitize_key($request->get_param('qualinav_status'));
        if (!in_array($new_status, array('invited', 'active', 'disabled', 'archived'), true)) {
            return new WP_Error('qn_invalid_status', __('Invalid QualiNav status.', 'qualinav-admin-console'), array('status' => 400));
        }

        $current_org = QN_Users::get_user_organization_id(get_current_user_id());
        $updated = QN_Users::update_user_organization_status($user_id, $current_org, $new_status);
        QN_Audit_Log::log('user_status_changed', 'user', $user_id, $access, $updated, $current_org);

        return is_wp_error($updated) ? $updated : rest_ensure_response($updated);
    }

    public static function get_admin_invitations()
    {
        return rest_ensure_response(QN_Invitations::get_invitations());
    }

    public static function admin_resend_invitation(WP_REST_Request $request)
    {
        $invitation = QN_Invitations::resend_invitation(absint($request['id']));

        return is_wp_error($invitation) ? $invitation : rest_ensure_response($invitation);
    }

    public static function admin_revoke_invitation(WP_REST_Request $request)
    {
        $invitation = QN_Invitations::revoke_invitation(absint($request['id']));

        return is_wp_error($invitation) ? $invitation : rest_ensure_response($invitation);
    }

    public static function hospital_resend_invitation(WP_REST_Request $request)
    {
        $invitation = self::same_org_invitation(absint($request['id']));
        if (is_wp_error($invitation)) {
            return $invitation;
        }

        $resent = QN_Invitations::resend_invitation(absint($request['id']));

        return is_wp_error($resent) ? $resent : rest_ensure_response($resent);
    }

    public static function hospital_revoke_invitation(WP_REST_Request $request)
    {
        $invitation = self::same_org_invitation(absint($request['id']));
        if (is_wp_error($invitation)) {
            return $invitation;
        }

        $revoked = QN_Invitations::revoke_invitation(absint($request['id']));

        return is_wp_error($revoked) ? $revoked : rest_ensure_response($revoked);
    }

    public static function get_onboarding(WP_REST_Request $request)
    {
        $organization_id = self::resolve_onboarding_organization($request);
        if (is_wp_error($organization_id)) {
            return $organization_id;
        }

        if (!QN_Permissions::user_can(get_current_user_id(), 'access_hospital_console', $organization_id) && !QN_Users::is_qualinav_admin(get_current_user_id())) {
            return new WP_Error('qn_onboarding_forbidden', __('You cannot view onboarding for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        return rest_ensure_response(QN_Onboarding::get_onboarding_payload($organization_id));
    }

    public static function save_onboarding(WP_REST_Request $request)
    {
        $organization_id = self::resolve_onboarding_organization($request);
        if (is_wp_error($organization_id)) {
            return $organization_id;
        }

        $payload = $request->get_json_params();
        $step_key = isset($payload['step_key']) ? sanitize_key($payload['step_key']) : '';
        $answers = isset($payload['answers']) && is_array($payload['answers']) ? $payload['answers'] : array();
        $saved = QN_Onboarding::save_step($organization_id, $step_key, $answers, get_current_user_id());

        return is_wp_error($saved) ? $saved : rest_ensure_response($saved);
    }

    public static function get_onboarding_progress(WP_REST_Request $request)
    {
        $organization_id = self::resolve_onboarding_organization($request);
        if (is_wp_error($organization_id)) {
            return $organization_id;
        }

        return rest_ensure_response(QN_Onboarding::get_progress($organization_id));
    }

    public static function submit_onboarding(WP_REST_Request $request)
    {
        $organization_id = self::resolve_onboarding_organization($request);
        if (is_wp_error($organization_id)) {
            return $organization_id;
        }

        try {
            $submitted = QN_Onboarding::submit_onboarding($organization_id, get_current_user_id());
        } catch (Throwable $error) {
            error_log('QualiNav onboarding submit failed: ' . $error->getMessage());
            return new WP_Error('qn_onboarding_submit_failed', __('Final setup could not be submitted. Please try again or contact support.', 'qualinav-admin-console'), array('status' => 500));
        }

        return is_wp_error($submitted) ? $submitted : rest_ensure_response($submitted);
    }

    public static function generate_scout_preview(WP_REST_Request $request)
    {
        $organization_id = QN_Scout::get_selected_organization_for_request($request);
        if (is_wp_error($organization_id)) {
            return $organization_id;
        }

        if (!QN_Scout::can_generate(get_current_user_id(), $organization_id)) {
            return new WP_Error('qn_scout_generate_forbidden', __('You cannot generate Scout preview for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $run = QN_Scout::generate_for_organization($organization_id, get_current_user_id());

        return is_wp_error($run) ? $run : rest_ensure_response(array(
            'run' => $run,
            'preview' => isset($run['preview']) ? $run['preview'] : null,
        ));
    }

    public static function get_scout_runs(WP_REST_Request $request)
    {
        $organization_id = QN_Scout::get_selected_organization_for_request($request);
        if (is_wp_error($organization_id)) {
            return $organization_id;
        }

        if (!QN_Scout::can_view(get_current_user_id(), $organization_id)) {
            return new WP_Error('qn_scout_view_forbidden', __('You cannot view Scout preview for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $runs = QN_Scout::get_runs($organization_id, $request->get_param('limit') ? absint($request->get_param('limit')) : 20);

        return rest_ensure_response(array(
            'organization_id' => absint($organization_id),
            'runs' => $runs,
            'latest_run' => $runs ? $runs[0] : null,
            'bridge_available' => QN_Scout::is_bridge_available(),
            'can_generate' => QN_Scout::can_generate(get_current_user_id(), $organization_id),
            'onboarding_submitted' => QN_Scout::is_onboarding_submitted($organization_id),
            'onboarding_status' => QN_Scout::is_onboarding_submitted($organization_id) ? 'submitted' : '',
        ));
    }

    public static function get_scout_run(WP_REST_Request $request)
    {
        $run = QN_Scout::get_run(absint($request['id']));
        if (!$run) {
            return new WP_Error('qn_scout_run_not_found', __('Scout run not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        if (!QN_Scout::can_view(get_current_user_id(), $run['organization_id'])) {
            return new WP_Error('qn_scout_view_forbidden', __('You cannot view Scout preview for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        return rest_ensure_response($run);
    }

    public static function retry_scout_run(WP_REST_Request $request)
    {
        $run = QN_Scout::get_run(absint($request['id']));
        if (!$run) {
            return new WP_Error('qn_scout_run_not_found', __('Scout run not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        if (!QN_Scout::can_generate(get_current_user_id(), $run['organization_id'])) {
            return new WP_Error('qn_scout_generate_forbidden', __('You cannot retry Scout preview for this hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        $retry = QN_Scout::retry_run($run['id'], get_current_user_id());

        return is_wp_error($retry) ? $retry : rest_ensure_response(array(
            'run' => $retry,
            'preview' => isset($retry['preview']) ? $retry['preview'] : null,
        ));
    }

    private static function hospital_payload(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $allowed = array('organization_name', 'city', 'zip', 'beds', 'state_id', 'status', 'timezone', 'ccn', 'brandsetting_id', 'parent_system_id', 'hospital_type', 'service_model', 'payment_model');
        $data = array();

        foreach ($allowed as $field) {
            if (isset($payload[$field])) {
                $data[$field] = $payload[$field];
            }
        }

        return $data;
    }

    private static function health_system_payload(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        return array(
            'name' => isset($payload['name']) ? sanitize_text_field($payload['name']) : '',
            'headquarters_state_id' => isset($payload['headquarters_state_id']) ? absint($payload['headquarters_state_id']) : null,
            'description' => isset($payload['description']) ? sanitize_textarea_field($payload['description']) : '',
            'is_active' => isset($payload['is_active']) ? absint($payload['is_active']) : 1,
        );
    }

    private static function options_to_list($options)
    {
        $list = array();
        foreach ($options as $value => $label) {
            $list[] = array('value' => $value, 'label' => $label);
        }

        return $list;
    }

    private static function missing_columns($table, $columns)
    {
        $missing = array();
        foreach ($columns as $column) {
            if (!QN_DB::column_exists($table, $column)) {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    private static function invite_payload(WP_REST_Request $request, $include_organization)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $data = array(
            'full_name' => isset($payload['full_name']) ? sanitize_text_field($payload['full_name']) : '',
            'email' => isset($payload['email']) ? sanitize_email($payload['email']) : '',
            'qualinav_role' => isset($payload['qualinav_role']) ? sanitize_key($payload['qualinav_role']) : '',
        );

        if ($include_organization && isset($payload['organization_id'])) {
            $data['organization_id'] = absint($payload['organization_id']);
        }

        return $data;
    }

    private static function validate_hospital_target_user($user_id)
    {
        $target = QN_Users::get_user_row($user_id);
        if (!$target) {
            return new WP_Error('qn_user_not_found', __('User not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $current_org = QN_Users::get_user_organization_id(get_current_user_id());
        $access = $current_org ? QN_Users::get_user_organization_access($user_id, $current_org) : null;
        if (!$current_org || !$access) {
            return new WP_Error('qn_cross_hospital_blocked', __('You cannot manage users in another hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        if (in_array($target->qualinav_role, array('qualinav_super_admin', 'qualinav_admin'), true) || in_array($access['qualinav_role'], array('qualinav_super_admin', 'qualinav_admin'), true)) {
            return new WP_Error('qn_admin_user_blocked', __('Hospital users cannot manage QualiNav admins.', 'qualinav-admin-console'), array('status' => 403));
        }

        return $access;
    }

    private static function same_org_invitation($invitation_id)
    {
        $invitation = QN_Invitations::get_invitation($invitation_id);
        if (!$invitation) {
            return new WP_Error('qn_invitation_not_found', __('Invitation not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $current_org = QN_Users::get_user_organization_id(get_current_user_id());
        if (!$current_org || absint($invitation['organization_id']) !== absint($current_org)) {
            return new WP_Error('qn_cross_hospital_blocked', __('You cannot manage invitations for another hospital.', 'qualinav-admin-console'), array('status' => 403));
        }

        return $invitation;
    }

    private static function resolve_onboarding_organization(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        $requested = $request->get_param('organization_id');
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
}
