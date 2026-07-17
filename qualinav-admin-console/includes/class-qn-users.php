<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Users
{
    const ROLE_SUPER_ADMIN = 'qualinav_super_admin';
    const ROLE_ADMIN = 'qualinav_admin';

    public static function get_user_row($user_id)
    {
        global $wpdb;

        $user_id = absint($user_id);
        if (!$user_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID, user_login, user_email, display_name, organization_id, state_id, qualinav_role, qualinav_status FROM {$wpdb->users} WHERE ID = %d",
                $user_id
            )
        );
    }

    public static function get_current_user_row()
    {
        return self::get_user_row(get_current_user_id());
    }

    public static function get_user_organization_id($user_id)
    {
        return self::get_current_organization_id($user_id);
    }

    public static function get_user_state_id($user_id)
    {
        $row = self::get_user_row($user_id);

        return $row && $row->state_id !== null ? absint($row->state_id) : null;
    }

    public static function get_user_qualinav_role($user_id)
    {
        if (self::is_wordpress_administrator($user_id)) {
            return self::ROLE_SUPER_ADMIN;
        }

        $row = self::get_user_row($user_id);

        if ($row && !empty($row->qualinav_role)) {
            return (string) $row->qualinav_role;
        }

        return null;
    }

    public static function role_labels()
    {
        return array(
            'qualinav_super_admin' => __('QualiNav Super Admin', 'qualinav-admin-console'),
            'qualinav_admin' => __('QualiNav Admin', 'qualinav-admin-console'),
            'quality_director' => __('Hospital Quality Director', 'qualinav-admin-console'),
            'executive_leader' => __('Executive Leader (CEO or CFO)', 'qualinav-admin-console'),
            'clinical_ancillary_services_leader' => __('Clinical or Ancillary Services Leader or Director', 'qualinav-admin-console'),
            'hospital_admin' => __('Hospital Admin', 'qualinav-admin-console'),
            'backup_quality_user' => __('Backup Quality User', 'qualinav-admin-console'),
            'reporting_user' => __('Reporting User', 'qualinav-admin-console'),
            'policy_owner' => __('Policy Owner', 'qualinav-admin-console'),
            'committee_user' => __('Committee User', 'qualinav-admin-console'),
            'viewer' => __('Viewer', 'qualinav-admin-console'),
        );
    }

    public static function role_label($role)
    {
        $role = sanitize_key($role);
        $labels = self::role_labels();

        return isset($labels[$role]) ? $labels[$role] : ucwords(str_replace('_', ' ', $role));
    }

    public static function get_user_organizations($user_id)
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . QN_DB::user_organizations_table() . ' WHERE user_id = %d ORDER BY is_default DESC, id ASC',
                absint($user_id)
            )
        );

        return array_map(array(__CLASS__, 'normalize_user_organization_row'), $rows);
    }

    public static function get_user_organization_access($user_id, $organization_id)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . QN_DB::user_organizations_table() . ' WHERE user_id = %d AND organization_id = %d LIMIT 1',
                absint($user_id),
                absint($organization_id)
            )
        );

        return $row ? self::normalize_user_organization_row($row) : null;
    }

    public static function get_current_organization_id($user_id)
    {
        $row = self::get_user_row($user_id);

        if ($row && $row->organization_id !== null) {
            return absint($row->organization_id);
        }

        $organizations = self::get_user_organizations($user_id);

        return $organizations ? absint($organizations[0]['organization_id']) : null;
    }

    public static function set_current_organization($user_id, $organization_id)
    {
        global $wpdb;

        $access = self::get_user_organization_access($user_id, $organization_id);
        if (!$access || $access['status'] !== 'active') {
            return false;
        }

        $wpdb->update(
            $wpdb->users,
            array(
                'organization_id' => absint($access['organization_id']),
                'state_id' => $access['state_id'] !== null ? absint($access['state_id']) : null,
                'qualinav_role' => sanitize_key($access['qualinav_role']),
            ),
            array('ID' => absint($user_id))
        );

        return true;
    }

    public static function get_role_for_organization($user_id, $organization_id)
    {
        if (self::is_qualinav_admin($user_id)) {
            return self::get_user_qualinav_role($user_id);
        }

        $access = self::get_user_organization_access($user_id, $organization_id);

        return $access ? $access['qualinav_role'] : null;
    }

    public static function user_has_organization($user_id, $organization_id)
    {
        $access = self::get_user_organization_access($user_id, $organization_id);

        return $access && $access['status'] === 'active';
    }

    public static function add_user_to_organization($user_id, $organization_id, $state_id, $role, $status = 'active', $is_default = false)
    {
        global $wpdb;

        $table = QN_DB::user_organizations_table();
        $existing = self::get_user_organization_access($user_id, $organization_id);
        $data = array(
            'user_id' => absint($user_id),
            'organization_id' => absint($organization_id),
            'state_id' => $state_id !== null ? absint($state_id) : null,
            'qualinav_role' => sanitize_key($role),
            'status' => sanitize_key($status),
            'is_default' => $is_default ? 1 : 0,
            'updated_at' => current_time('mysql'),
        );

        if ($is_default) {
            $wpdb->update($table, array('is_default' => 0), array('user_id' => absint($user_id)));
        }

        if ($existing) {
            $wpdb->update($table, $data, array('user_id' => absint($user_id), 'organization_id' => absint($organization_id)));
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        $row = self::get_user_row($user_id);
        if ($is_default || !$row || !$row->organization_id) {
            self::set_current_organization($user_id, $organization_id);
        }

        return self::get_user_organization_access($user_id, $organization_id);
    }

    public static function update_user_organization_role($user_id, $organization_id, $role)
    {
        global $wpdb;

        $wpdb->update(
            QN_DB::user_organizations_table(),
            array('qualinav_role' => sanitize_key($role), 'updated_at' => current_time('mysql')),
            array('user_id' => absint($user_id), 'organization_id' => absint($organization_id))
        );

        if (self::get_current_organization_id($user_id) === absint($organization_id)) {
            self::set_current_organization($user_id, $organization_id);
        }

        return self::get_user_organization_access($user_id, $organization_id);
    }

    public static function update_user_organization_status($user_id, $organization_id, $status)
    {
        global $wpdb;

        $wpdb->update(
            QN_DB::user_organizations_table(),
            array('status' => sanitize_key($status), 'updated_at' => current_time('mysql')),
            array('user_id' => absint($user_id), 'organization_id' => absint($organization_id))
        );

        return self::get_user_organization_access($user_id, $organization_id);
    }

    public static function remove_user_from_organization($user_id, $organization_id)
    {
        global $wpdb;

        return $wpdb->delete(QN_DB::user_organizations_table(), array('user_id' => absint($user_id), 'organization_id' => absint($organization_id)));
    }

    public static function get_user_qualinav_status($user_id)
    {
        $row = self::get_user_row($user_id);

        return $row ? (string) $row->qualinav_status : null;
    }

    public static function is_qualinav_user($user_id)
    {
        $role = self::get_user_qualinav_role($user_id);

        return !empty($role) && array_key_exists($role, QN_Permissions::permission_map());
    }

    public static function is_super_admin($user_id)
    {
        return self::get_user_qualinav_role($user_id) === self::ROLE_SUPER_ADMIN;
    }

    public static function is_qualinav_admin($user_id)
    {
        return in_array(self::get_user_qualinav_role($user_id), array(self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN), true);
    }

    public static function is_wordpress_administrator($user_id)
    {
        $user = get_userdata(absint($user_id));

        return $user instanceof WP_User && in_array('administrator', (array) $user->roles, true);
    }

    public static function is_hospital_user($user_id)
    {
        $role = self::get_user_qualinav_role($user_id);

        return self::is_qualinav_user($user_id) && !in_array($role, array(self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN), true);
    }

    private static function normalize_user_organization_row($row)
    {
        $hospital = QN_Organizations::get_hospital($row->organization_id);
        $state = $row->state_id ? QN_Organizations::get_state($row->state_id) : null;

        return array(
            'id' => absint($row->id),
            'user_id' => absint($row->user_id),
            'organization_id' => absint($row->organization_id),
            'organization_name' => $hospital ? $hospital['name'] : '',
            'parent_system_id' => $hospital ? $hospital['parent_system_id'] : null,
            'parent_system_name' => $hospital ? $hospital['parent_system_name'] : '',
            'hospital_type' => $hospital ? $hospital['hospital_type'] : '',
            'hospital_type_label' => $hospital ? $hospital['hospital_type_label'] : __('Not specified.', 'qualinav-admin-console'),
            'service_model' => $hospital ? $hospital['service_model'] : '',
            'service_model_label' => $hospital ? $hospital['service_model_label'] : __('Not specified.', 'qualinav-admin-console'),
            'state_id' => $row->state_id !== null ? absint($row->state_id) : null,
            'state_name' => $state ? $state['name'] : '',
            'state_code' => $state ? $state['abbreviation'] : '',
            'qualinav_role' => $row->qualinav_role,
            'status' => $row->status,
            'is_default' => (bool) $row->is_default,
        );
    }
}
