<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Activator
{
    public static function activate()
    {
        self::migrate_users_table();
        self::migrate_organizations_table();
        self::create_audit_log_table();
        self::create_invitations_table();
        self::create_user_organizations_table();
        self::create_health_systems_table();
        self::create_questionnaire_tables();
        self::create_scout_runs_table();
        self::backfill_user_organizations();
        QN_Questionnaire::seed_default_questionnaire();
        QN_Router::add_rewrite_rules();
        flush_rewrite_rules();
    }

    private static function migrate_users_table()
    {
        global $wpdb;

        $table = QN_DB::users_table();

        $columns = array(
            'organization_id' => 'ADD COLUMN organization_id BIGINT UNSIGNED NULL',
            'state_id' => 'ADD COLUMN state_id BIGINT UNSIGNED NULL',
            'qualinav_role' => 'ADD COLUMN qualinav_role VARCHAR(80) NULL',
            'qualinav_status' => "ADD COLUMN qualinav_status VARCHAR(30) DEFAULT 'active'",
        );

        foreach ($columns as $column => $sql) {
            if (!QN_DB::column_exists($table, $column)) {
                $wpdb->query("ALTER TABLE {$table} {$sql}");
                QN_DB::clear_table_columns_cache($table);
            }
        }

        $indexes = array(
            'idx_qn_org' => 'organization_id',
            'idx_qn_state' => 'state_id',
            'idx_qn_role' => 'qualinav_role',
            'idx_qn_status' => 'qualinav_status',
        );

        foreach ($indexes as $index => $column) {
            if (!QN_DB::index_exists($table, $index)) {
                $wpdb->query("ALTER TABLE {$table} ADD INDEX {$index} ({$column})");
            }
        }
    }

    private static function migrate_organizations_table()
    {
        global $wpdb;

        $table = QN_DB::organizations_table();
        if (!QN_DB::table_exists($table)) {
            return;
        }

        $columns = array(
            'parent_system_id' => 'ADD COLUMN parent_system_id BIGINT UNSIGNED NULL',
            'hospital_type' => 'ADD COLUMN hospital_type VARCHAR(80) NULL',
            'service_model' => 'ADD COLUMN service_model VARCHAR(80) NULL',
            'payment_model' => 'ADD COLUMN payment_model VARCHAR(80) NULL',
        );

        foreach ($columns as $column => $sql) {
            if (!QN_DB::column_exists($table, $column)) {
                $wpdb->query("ALTER TABLE {$table} {$sql}");
                QN_DB::clear_table_columns_cache($table);
            }
        }

        $indexes = array(
            'idx_qn_parent_system' => 'parent_system_id',
            'idx_qn_hospital_type' => 'hospital_type',
            'idx_qn_service_model' => 'service_model',
            'idx_qn_payment_model' => 'payment_model',
        );

        foreach ($indexes as $index => $column) {
            if (!QN_DB::index_exists($table, $index) && QN_DB::column_exists($table, $column)) {
                $wpdb->query("ALTER TABLE {$table} ADD INDEX {$index} ({$column})");
            }
        }
    }

    private static function create_audit_log_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = QN_DB::audit_logs_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id BIGINT UNSIGNED NULL,
            organization_id BIGINT UNSIGNED NULL,
            action VARCHAR(160) NOT NULL,
            entity_type VARCHAR(120) NULL,
            entity_id BIGINT UNSIGNED NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            ip_address VARCHAR(80) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_qn_audit_actor (actor_user_id),
            KEY idx_qn_audit_org (organization_id),
            KEY idx_qn_audit_action (action),
            KEY idx_qn_audit_created (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_user_organizations_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = QN_DB::user_organizations_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            state_id BIGINT UNSIGNED NULL,
            qualinav_role VARCHAR(80) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            invited_by BIGINT UNSIGNED NULL,
            invited_at DATETIME NULL,
            accepted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_org_unique (user_id, organization_id),
            KEY idx_user_id (user_id),
            KEY idx_organization_id (organization_id),
            KEY idx_state_id (state_id),
            KEY idx_role (qualinav_role),
            KEY idx_status (status),
            KEY idx_default (is_default)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_health_systems_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = QN_DB::health_systems_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            headquarters_state_id BIGINT UNSIGNED NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY idx_health_system_state (headquarters_state_id),
            KEY idx_health_system_active (is_active)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function backfill_user_organizations()
    {
        global $wpdb;

        $roles = array('quality_director', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer');
        $placeholders = implode(',', array_fill(0, count($roles), '%s'));
        $users = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, organization_id, state_id, qualinav_role, qualinav_status FROM {$wpdb->users} WHERE organization_id IS NOT NULL AND organization_id > 0 AND qualinav_role IN ({$placeholders})",
                $roles
            )
        );

        foreach ($users as $user) {
            QN_Users::add_user_to_organization(
                $user->ID,
                $user->organization_id,
                $user->state_id,
                $user->qualinav_role,
                $user->qualinav_status ? $user->qualinav_status : 'active',
                true
            );
        }
    }

    private static function create_questionnaire_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sections = QN_DB::questionnaire_sections_table();
        $questions = QN_DB::questionnaire_questions_table();
        $answers = QN_DB::questionnaire_answers_table();
        $progress = QN_DB::onboarding_progress_table();

        dbDelta("CREATE TABLE {$sections} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            section_key VARCHAR(120) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY section_key (section_key)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$questions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            section_id BIGINT UNSIGNED NOT NULL,
            question_key VARCHAR(160) NOT NULL,
            label TEXT NOT NULL,
            help_text TEXT NULL,
            field_type VARCHAR(80) NOT NULL,
            options_json LONGTEXT NULL,
            validation_json LONGTEXT NULL,
            conditional_logic_json LONGTEXT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY question_key (question_key),
            KEY idx_section_id (section_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$answers} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            answer_json LONGTEXT NULL,
            completed_by BIGINT UNSIGNED NULL,
            completed_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_question (organization_id, question_id),
            KEY idx_org (organization_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$progress} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            section_key VARCHAR(120) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'not_started',
            percent_complete INT NOT NULL DEFAULT 0,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            completed_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_section (organization_id, section_key),
            KEY idx_org (organization_id)
        ) {$charset_collate};");
    }

    private static function create_invitations_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = QN_DB::invitations_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NULL,
            organization_id BIGINT UNSIGNED NULL,
            state_id BIGINT UNSIGNED NULL,
            qualinav_role VARCHAR(80) NOT NULL,
            token_hash VARCHAR(128) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            email_status VARCHAR(30) NULL,
            email_error TEXT NULL,
            invited_by BIGINT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY idx_invite_email (email),
            KEY idx_invite_token (token_hash),
            KEY idx_invite_org (organization_id),
            KEY idx_invite_status (status),
            KEY idx_invite_user (user_id)
        ) {$charset_collate};";

        dbDelta($sql);

        $columns = array(
            'email_status' => 'ADD COLUMN email_status VARCHAR(30) NULL',
            'email_error' => 'ADD COLUMN email_error TEXT NULL',
        );

        foreach ($columns as $column => $definition) {
            if (!QN_DB::column_exists($table, $column)) {
                $wpdb->query("ALTER TABLE {$table} {$definition}");
                QN_DB::clear_table_columns_cache($table);
            }
        }
    }

    private static function create_scout_runs_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = QN_DB::scout_runs_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            request_type VARCHAR(120) NOT NULL,
            input_data_type VARCHAR(160) NULL,
            request_payload_json LONGTEXT NULL,
            response_json LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            api_request_id VARCHAR(160) NULL,
            source_count INT NULL,
            error_message TEXT NULL,
            generated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY idx_org (organization_id),
            KEY idx_request_type (request_type),
            KEY idx_status (status),
            KEY idx_generated_by (generated_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
