<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Health_Systems
{
    public static function get_systems($args = array())
    {
        global $wpdb;

        $table = QN_DB::health_systems_table();
        if (!QN_DB::table_exists($table)) {
            return array();
        }

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC");

        return array_map(array(__CLASS__, 'normalize_system_row'), $rows);
    }

    public static function get_system($system_id)
    {
        global $wpdb;

        $system_id = absint($system_id);
        if (!$system_id || !QN_DB::table_exists(QN_DB::health_systems_table())) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . QN_DB::health_systems_table() . ' WHERE id = %d', $system_id));

        return $row ? self::normalize_system_row($row) : null;
    }

    public static function create_system($data)
    {
        global $wpdb;

        $table = QN_DB::health_systems_table();
        $name = sanitize_text_field(isset($data['name']) ? $data['name'] : '');
        if ($name === '') {
            return new WP_Error('qn_system_name_required', __('System name is required.', 'qualinav-admin-console'), array('status' => 400));
        }

        $insert = array(
            'name' => $name,
            'slug' => self::generate_unique_slug($name),
            'headquarters_state_id' => !empty($data['headquarters_state_id']) ? absint($data['headquarters_state_id']) : null,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'is_active' => isset($data['is_active']) ? (absint($data['is_active']) ? 1 : 0) : 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $wpdb->insert($table, $insert);
        $system = self::get_system($wpdb->insert_id);
        QN_Audit_Log::log('health_system_created', 'health_system', $wpdb->insert_id, null, $system, null);

        return $system;
    }

    public static function update_system($system_id, $data)
    {
        global $wpdb;

        $before = self::get_system($system_id);
        if (!$before) {
            return new WP_Error('qn_system_not_found', __('Health system not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $update = array('updated_at' => current_time('mysql'));
        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $update['slug'] = self::generate_unique_slug($update['name'], $system_id);
        }
        if (isset($data['headquarters_state_id'])) {
            $update['headquarters_state_id'] = absint($data['headquarters_state_id']);
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = absint($data['is_active']) ? 1 : 0;
        }

        $wpdb->update(QN_DB::health_systems_table(), $update, array('id' => absint($system_id)));
        $after = self::get_system($system_id);
        QN_Audit_Log::log('health_system_updated', 'health_system', $system_id, $before, $after, null);

        return $after;
    }

    public static function delete_or_deactivate_system($system_id)
    {
        $before = self::get_system($system_id);
        if (!$before) {
            return new WP_Error('qn_system_not_found', __('Health system not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $after = self::update_system($system_id, array('is_active' => 0));
        QN_Audit_Log::log('health_system_deactivated', 'health_system', $system_id, $before, $after, null);

        return $after;
    }

    public static function generate_unique_slug($name, $exclude_id = null)
    {
        global $wpdb;

        $base = sanitize_title($name);
        if ($base === '') {
            $base = 'health-system';
        }

        $slug = $base;
        $suffix = 2;
        while (true) {
            $sql = 'SELECT id FROM ' . QN_DB::health_systems_table() . ' WHERE slug = %s';
            $values = array($slug);
            if ($exclude_id) {
                $sql .= ' AND id <> %d';
                $values[] = absint($exclude_id);
            }
            if (!$wpdb->get_var($wpdb->prepare($sql, $values))) {
                return $slug;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    public static function get_system_hospitals($system_id)
    {
        return QN_Organizations::get_hospitals_by_system($system_id);
    }

    public static function get_system_options()
    {
        return array_map(function ($system) {
            return array(
                'id' => $system['id'],
                'name' => $system['name'],
                'is_active' => $system['is_active'],
            );
        }, self::get_systems());
    }

    private static function normalize_system_row($row)
    {
        $state = !empty($row->headquarters_state_id) ? QN_Organizations::get_state($row->headquarters_state_id) : null;
        return array(
            'id' => absint($row->id),
            'name' => $row->name,
            'slug' => $row->slug,
            'headquarters_state_id' => $row->headquarters_state_id !== null ? absint($row->headquarters_state_id) : null,
            'headquarters_state_name' => $state ? $state['name'] : '',
            'description' => $row->description,
            'is_active' => (bool) $row->is_active,
            'hospital_count' => self::count_system_hospitals($row->id),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        );
    }

    private static function count_system_hospitals($system_id)
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        if (!QN_DB::table_exists($table) || !QN_DB::column_exists($table, 'parent_system_id')) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE parent_system_id = %d", absint($system_id))
        );
    }
}
