<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Permissions
{
    public static function permission_map()
    {
        return array(
            'qualinav_super_admin' => array(
                'access_super_admin',
                'manage_all_hospitals',
                'manage_all_users',
                'manage_branding',
                'view_audit_logs',
                'invite_qualinav_admin',
                'invite_quality_director',
            ),
            'qualinav_admin' => array(
                'access_super_admin',
                'manage_all_hospitals',
                'manage_all_users',
                'manage_branding',
                'invite_quality_director',
            ),
            'quality_director' => array(
                'access_hospital_console',
                'complete_onboarding',
                'manage_hospital_users',
                'invite_hospital_users',
                'edit_hospital_profile',
                'edit_quality_program',
                'view_reports',
            ),
            'hospital_admin' => array(
                'access_hospital_console',
                'manage_hospital_users',
                'invite_limited_users',
                'edit_hospital_profile',
                'view_reports',
            ),
            'backup_quality_user' => array(
                'access_hospital_console',
                'edit_quality_program',
                'view_reports',
            ),
            'reporting_user' => array(
                'access_hospital_console',
                'edit_reporting',
                'view_reports',
            ),
            'policy_owner' => array(
                'access_hospital_console',
                'edit_policies',
                'view_reports',
            ),
            'committee_user' => array(
                'access_hospital_console',
                'edit_committee_items',
                'view_reports',
            ),
            'viewer' => array(
                'access_hospital_console',
                'view_reports',
            ),
        );
    }

    public static function role_permissions($role)
    {
        $map = self::permission_map();

        return isset($map[$role]) ? $map[$role] : array();
    }

    public static function user_can($user_id, $permission, $organization_id = null)
    {
        $user_id = absint($user_id);
        if (!$user_id || !is_user_logged_in()) {
            return false;
        }

        $row = QN_Users::get_user_row($user_id);
        if (!$row || $row->qualinav_status !== 'active') {
            return false;
        }

        $effective_role = QN_Users::get_user_qualinav_role($user_id);
        if (QN_Users::is_qualinav_admin($user_id)) {
            return in_array($permission, self::role_permissions($effective_role), true);
        }

        $organization_id = $organization_id !== null ? absint($organization_id) : QN_Users::get_current_organization_id($user_id);
        if (!$organization_id) {
            return false;
        }

        $access = QN_Users::get_user_organization_access($user_id, $organization_id);
        if (!$access || $access['status'] !== 'active') {
            return false;
        }

        return in_array($permission, self::role_permissions($access['qualinav_role']), true);
    }

    public static function require_permission($permission, $organization_id = null)
    {
        if (!is_user_logged_in()) {
            auth_redirect();
        }

        if (!self::user_can(get_current_user_id(), $permission, $organization_id)) {
            status_header(403);
            nocache_headers();
            wp_die(
                esc_html__('You do not have permission to access this QualiNav area.', 'qualinav-admin-console'),
                esc_html__('Access denied', 'qualinav-admin-console'),
                array('response' => 403)
            );
        }

        return true;
    }
}
