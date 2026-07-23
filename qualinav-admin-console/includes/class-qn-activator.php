<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Activator
{
    public static function maybe_upgrade()
    {
        $installed_version = get_option('qn_admin_console_version', '');
        if ($installed_version === QN_ADMIN_CONSOLE_VERSION) {
            return;
        }

        self::create_org_setup_tables();
        self::create_scout_runs_table();
        QN_Questionnaire::seed_default_questionnaire();
        update_option('qn_admin_console_version', QN_ADMIN_CONSOLE_VERSION, false);
    }

    public static function activate()
    {
        self::migrate_users_table();
        self::migrate_organizations_table();
        self::create_audit_log_table();
        self::create_invitations_table();
        self::create_user_organizations_table();
        self::create_health_systems_table();
        self::create_questionnaire_tables();
        self::create_org_setup_tables();
        self::create_scout_runs_table();
        self::backfill_user_organizations();
        QN_Questionnaire::seed_default_questionnaire();
        update_option('qn_admin_console_version', QN_ADMIN_CONSOLE_VERSION, false);
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

        $roles = array('quality_director', 'executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer');
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
            is_progress_tracked TINYINT(1) NOT NULL DEFAULT 0,
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

    private static function create_org_setup_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE " . QN_DB::org_profile_table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            hospital_name VARCHAR(255) NULL,
            city VARCHAR(160) NULL,
            state_code VARCHAR(20) NULL,
            state_id BIGINT UNSIGNED NULL,
            licensed_beds INT NULL,
            acute_beds INT NULL,
            swing_beds INT NULL,
            is_critical_access_hospital VARCHAR(40) NULL,
            independent_or_system VARCHAR(120) NULL,
            quality_director_name VARCHAR(255) NULL,
            quality_director_role_start_date VARCHAR(80) NULL,
            quality_director_background TEXT NULL,
            source_answers_json LONGTEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_unique (organization_id),
            KEY idx_state_id (state_id),
            KEY idx_updated_by (updated_by)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE " . QN_DB::org_accreditation_table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            accreditation_status VARCHAR(120) NULL,
            accrediting_body VARCHAR(160) NULL,
            cms_certification_pathway VARCHAR(160) NULL,
            state_survey_agency VARCHAR(255) NULL,
            life_safety_survey_agency VARCHAR(255) NULL,
            open_plans_of_correction LONGTEXT NULL,
            projected_next_survey_window VARCHAR(160) NULL,
            historical_deficiency_areas LONGTEXT NULL,
            current_readiness_activities LONGTEXT NULL,
            source_answers_json LONGTEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_unique (organization_id),
            KEY idx_accrediting_body (accrediting_body),
            KEY idx_updated_by (updated_by)
        ) {$charset_collate};");

        self::create_org_collection_table(QN_DB::org_survey_history_table(), 'survey_key', 'survey_title');
        self::create_org_single_json_table(QN_DB::org_services_table());
        self::create_org_collection_table(QN_DB::org_committees_table(), 'committee_key', 'committee_name');
        self::create_org_collection_table(QN_DB::org_reporting_requirements_table(), 'requirement_key', 'requirement_name');
        self::create_org_collection_table(QN_DB::org_plans_table(), 'plan_key', 'plan_name');
        self::create_org_collection_table(QN_DB::org_policy_reviews_table(), 'review_key', 'review_name');
        self::create_org_collection_table(QN_DB::org_monitoring_areas_table(), 'area_key', 'area_name');
        self::create_org_collection_table(QN_DB::org_goals_table(), 'goal_key', 'goal_name');
        self::create_org_collection_table(QN_DB::org_learning_items_table(), 'learning_key', 'learning_name');
        self::create_org_collection_table(QN_DB::org_contacts_table(), 'contact_key', 'contact_name');
        self::create_org_collection_table(QN_DB::org_regulatory_sources_table(), 'source_key', 'source_name');
        self::create_org_collection_table(QN_DB::org_tools_table(), 'tool_key', 'tool_name');

        dbDelta("CREATE TABLE " . QN_DB::org_reminder_preferences_table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            update_preference VARCHAR(160) NULL,
            auto_propose_task_adjustments VARCHAR(80) NULL,
            reminder_lead_time VARCHAR(160) NULL,
            reminder_buffer_time VARCHAR(160) NULL,
            backup_visibility_users LONGTEXT NULL,
            final_review_confirmation VARCHAR(80) NULL,
            source_answers_json LONGTEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_unique (organization_id),
            KEY idx_updated_by (updated_by)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE " . QN_DB::org_milestones_table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            milestone_key VARCHAR(160) NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(120) NULL,
            status VARCHAR(80) NULL,
            cadence VARCHAR(120) NULL,
            due_window VARCHAR(160) NULL,
            linked_object_type VARCHAR(120) NULL,
            linked_object_id BIGINT UNSIGNED NULL,
            source_answers_json LONGTEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_milestone (organization_id, milestone_key),
            KEY idx_org (organization_id),
            KEY idx_category (category),
            KEY idx_status (status),
            KEY idx_linked_object (linked_object_type, linked_object_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE " . QN_DB::org_milestone_updates_table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            milestone_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(80) NULL,
            note TEXT NULL,
            update_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_milestone (milestone_id),
            KEY idx_org (organization_id),
            KEY idx_status (status),
            KEY idx_created_by (created_by)
        ) {$charset_collate};");
    }

    private static function create_org_single_json_table($table)
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            data_json LONGTEXT NULL,
            source_answers_json LONGTEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_unique (organization_id),
            KEY idx_updated_by (updated_by)
        ) {$charset_collate};");
    }

    private static function create_org_collection_table($table, $key_column, $name_column)
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            {$key_column} VARCHAR(160) NOT NULL,
            {$name_column} VARCHAR(255) NULL,
            status VARCHAR(80) NULL,
            cadence VARCHAR(120) NULL,
            owner VARCHAR(255) NULL,
            details_json LONGTEXT NULL,
            source_answer_json LONGTEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY org_item (organization_id, {$key_column}),
            KEY idx_org (organization_id),
            KEY idx_status (status),
            KEY idx_updated_by (updated_by)
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
            review_json LONGTEXT NULL,
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
