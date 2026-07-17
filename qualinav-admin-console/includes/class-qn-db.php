<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_DB
{
    private static $table_columns = array();

    public static function users_table()
    {
        global $wpdb;

        return $wpdb->users;
    }

    public static function audit_logs_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_audit_logs';
    }

    public static function invitations_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_invitations';
    }

    public static function user_organizations_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_user_organizations';
    }

    public static function questionnaire_sections_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_questionnaire_sections';
    }

    public static function questionnaire_questions_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_questionnaire_questions';
    }

    public static function questionnaire_answers_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_questionnaire_answers';
    }

    public static function onboarding_progress_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_onboarding_progress';
    }

    public static function org_profile_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_profile';
    }

    public static function org_contacts_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_contacts';
    }

    public static function org_accreditation_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_accreditation';
    }

    public static function org_survey_history_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_survey_history';
    }

    public static function org_services_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_services';
    }

    public static function org_committees_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_committees';
    }

    public static function org_reporting_requirements_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_reporting_requirements';
    }

    public static function org_plans_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_plans';
    }

    public static function org_policy_reviews_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_policy_reviews';
    }

    public static function org_monitoring_areas_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_monitoring_areas';
    }

    public static function org_goals_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_goals';
    }

    public static function org_learning_items_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_learning_items';
    }

    public static function org_regulatory_sources_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_regulatory_sources';
    }

    public static function org_tools_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_tools';
    }

    public static function org_reminder_preferences_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_reminder_preferences';
    }

    public static function org_milestones_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_milestones';
    }

    public static function org_milestone_updates_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_org_milestone_updates';
    }

    public static function health_systems_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_health_systems';
    }

    public static function scout_runs_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'qualinav_scout_runs';
    }

    public static function organizations_table()
    {
        global $wpdb;

        return self::first_existing_table(array(
            $wpdb->prefix . 'organizations',
            'wp_organizations',
        ));
    }

    public static function states_table()
    {
        global $wpdb;

        return self::first_existing_table(array(
            $wpdb->prefix . 'states',
            'wp_states',
        ));
    }

    public static function brand_settings_table()
    {
        global $wpdb;

        return self::first_existing_table(array(
            $wpdb->prefix . 'dt_brand_settings',
            'wp_dt_brand_settings',
            $wpdb->prefix . 'brandsettings',
            'wp_brandsettings',
        ));
    }

    private static function first_existing_table($tables)
    {
        foreach ($tables as $table) {
            if (self::table_exists($table)) {
                return $table;
            }
        }

        return $tables[0];
    }

    public static function table_exists($table)
    {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return $found === $table;
    }

    public static function column_exists($table, $column)
    {
        return in_array($column, self::get_table_columns($table), true);
    }

    public static function get_table_columns($table)
    {
        global $wpdb;

        if (isset(self::$table_columns[$table])) {
            return self::$table_columns[$table];
        }

        if (!self::table_exists($table)) {
            self::$table_columns[$table] = array();

            return self::$table_columns[$table];
        }

        self::$table_columns[$table] = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);

        return self::$table_columns[$table];
    }

    public static function clear_table_columns_cache($table = null)
    {
        if ($table === null) {
            self::$table_columns = array();
            return;
        }

        unset(self::$table_columns[$table]);
    }

    public static function filter_existing_columns($table, $data)
    {
        $columns = self::get_table_columns($table);
        $filtered = array();

        foreach ($data as $key => $value) {
            if (in_array($key, $columns, true)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    public static function index_exists($table, $index)
    {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s LIMIT 1',
                $table,
                $index
            )
        );

        return $found === $index;
    }
}
