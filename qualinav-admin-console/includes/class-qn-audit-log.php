<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Audit_Log
{
    public static function log($action, $entity_type = null, $entity_id = null, $before = null, $after = null, $organization_id = null)
    {
        global $wpdb;

        $actor_user_id = get_current_user_id();
        $actor_user_id = $actor_user_id ? absint($actor_user_id) : null;

        if ($organization_id === null && $actor_user_id) {
            $organization_id = QN_Users::get_user_organization_id($actor_user_id);
        }

        $wpdb->insert(
            QN_DB::audit_logs_table(),
            array(
                'actor_user_id' => $actor_user_id,
                'organization_id' => $organization_id !== null ? absint($organization_id) : null,
                'action' => sanitize_text_field($action),
                'entity_type' => $entity_type !== null ? sanitize_text_field($entity_type) : null,
                'entity_id' => $entity_id !== null ? absint($entity_id) : null,
                'before_json' => self::encode_json($before),
                'after_json' => self::encode_json($after),
                'ip_address' => self::get_ip_address(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        return $wpdb->insert_id;
    }

    private static function encode_json($value)
    {
        if ($value === null) {
            return null;
        }

        return wp_json_encode($value);
    }

    private static function get_ip_address()
    {
        $keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $raw = sanitize_text_field(wp_unslash($_SERVER[$key]));
            $parts = explode(',', $raw);

            return trim($parts[0]);
        }

        return null;
    }
}
