<?php
/**
 * QAPI Dashboard module — merged into data-hub.
 *
 * Originally the standalone qualinav-qapi-dashboard plugin (v1.1.0). The file
 * stays self-contained: constants resolve via plugin_dir_path(__FILE__) so the
 * `templates/` and `assets/` paths inside this plugin's folder are picked up
 * automatically. The data-hub main file requires this module after a
 * class_exists() guard so it can coexist (during migration) with the legacy
 * standalone plugin folder.
 *
 * @package Qualinav_Data_Hub
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants UNCONDITIONALLY at the top so they're available even if
// the class-redeclare guard further down causes this file to return early.
// (The class is only declared once but the constants must always be set
// because class methods reference them at runtime.)
if (!defined('QUAINAV_QAPI_DASBOARD_VERSION')) {
    define('QUAINAV_QAPI_DASBOARD_VERSION', '1.1.1');
}
if (!defined('QUAINAV_QAPI_DASBOARD_DB_VERSION')) {
    define('QUAINAV_QAPI_DASBOARD_DB_VERSION', '1.4.2');
}
if (!defined('QUAINAV_QAPI_DASBOARD_PLUGIN_FILE')) {
    define('QUAINAV_QAPI_DASBOARD_PLUGIN_FILE', __FILE__);
}
if (!defined('QUAINAV_QAPI_DASBOARD_PLUGIN_DIR')) {
    define('QUAINAV_QAPI_DASBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('QUAINAV_QAPI_DASBOARD_PLUGIN_URL')) {
    define('QUAINAV_QAPI_DASBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Guard against double-load if the legacy standalone qualinav-qapi-dashboard
// plugin is still activated. Activating data-hub alone is the supported state.
if (class_exists('Quainav_Qapi_Dasboard')) {
    return;
}

final class Quainav_Qapi_Dasboard {
    private static $instance = null;
    private $template_file = 'page-qualinav-qapi-dashboard.php';
    private $max_parsed_rows = 10000;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        $instance = self::instance();
        $instance->ensure_metric_data_table();
        $instance->sync_metric_data_table();
    }

    private function __construct() {
        add_action('init', array($this, 'ensure_metric_data_table'), 5);
        add_filter('theme_page_templates', array($this, 'add_page_template'));
        add_filter('template_include', array($this, 'load_page_template'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('qaqd_dashboard', array($this, 'render_dashboard_shortcode'));
        add_shortcode('qualinav_qapi_data_management', array($this, 'render_data_management_shortcode'));
        if (!shortcode_exists('qualinav_data_management')) {
            add_shortcode('qualinav_data_management', array($this, 'render_data_management_shortcode'));
        }
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_init', array($this, 'register_settings'));

        add_action('init', array($this, 'register_report_post_type'));
        add_action('wp_ajax_qaqd_save_report', array($this, 'save_report_handler'));
        add_action('wp_ajax_qaqd_save_org_metrics', array($this, 'save_org_metrics_handler'));
        add_action('wp_ajax_qaqd_live_metrics', array($this, 'live_metrics_handler'));
        add_action('wp_ajax_mydata_delete_report', array($this, 'mydata_delete_report_handler'));
        add_action('wp_ajax_dm_update_report_metrics', array($this, 'dm_update_report_metrics_handler'));
        add_action('wp_ajax_dm_upload_folder_file', array($this, 'dm_upload_folder_file_handler'));
        add_action('updated_option', array($this, 'invalidate_live_metrics_cache_on_option_update'), 10, 3);
        add_action('added_option', array($this, 'invalidate_live_metrics_cache_on_option_add'), 10, 2);
    }

    private function get_current_org_context() {
        $user_id = get_current_user_id();
        if (function_exists('qualinav_data_hub_get_org_context')) {
            $context = qualinav_data_hub_get_org_context($user_id);
            $org_key = sanitize_title((string) ($context['org_key'] ?? ''));
            $org_label = trim((string) ($context['org_name'] ?? ''));
            if ($org_key !== '') {
                return array($org_key, $org_label !== '' ? $org_label : 'My Organization');
            }
        }
        $org_raw = $user_id ? (string) get_user_meta($user_id, 'organization', true) : '';
        $org_label = trim($org_raw) !== '' ? trim($org_raw) : 'My Organization';
        $org_key = sanitize_title($org_label);
        if ($org_key === '') {
            $org_key = $user_id ? ('user-' . (int) $user_id) : 'anonymous';
        }
        return array($org_key, $org_label);
    }

    private function get_metric_data_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'qapi_metric_data';
    }

    private function get_dashboard_reports_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'data_dashboard_reports';
    }

    public function ensure_metric_data_table() {
        global $wpdb;

        $installed_version = (string) get_option('qaqd_metric_data_db_version', '');
        $table_name = $this->get_metric_data_table_name();
        $reports_table = $this->get_dashboard_reports_table_name();

        if (
            $installed_version === QUAINAV_QAPI_DASBOARD_DB_VERSION
            && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name
            && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $reports_table)) === $reports_table
        ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            metric_key VARCHAR(190) NOT NULL,
            metric_label VARCHAR(190) NOT NULL,
            folder_key VARCHAR(190) NOT NULL DEFAULT '',
            source_name VARCHAR(255) NOT NULL DEFAULT '',
            source_url TEXT NULL,
            benchmark_label VARCHAR(100) NOT NULL DEFAULT '',
            lower_is_better TINYINT(1) NOT NULL DEFAULT 0,
            minutes_mode TINYINT(1) NOT NULL DEFAULT 0,
            period_ts BIGINT NULL,
            period_date DATETIME NULL,
            year_num SMALLINT NULL,
            month_num TINYINT NULL,
            quarter_num TINYINT NULL,
            row_index INT NOT NULL DEFAULT 0,
            num_value DECIMAL(18,6) NULL,
            denom_value DECIMAL(18,6) NULL,
            raw_value DECIMAL(18,6) NULL,
            uploaded_at DATETIME NOT NULL,
            row_hash VARCHAR(64) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY row_hash (row_hash),
            KEY org_id_metric_period (org_id, metric_key, period_ts),
            KEY user_id (user_id),
            KEY metric_period (metric_key, period_ts)
        ) {$charset_collate};";

        $reports_sql = "CREATE TABLE {$reports_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            report_name VARCHAR(190) NOT NULL,
            report_key VARCHAR(190) NOT NULL DEFAULT '',
            report_type VARCHAR(80) NOT NULL DEFAULT '',
            config_json LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            archived_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY org_user_status (org_id, user_id, status),
            KEY user_status (user_id, status),
            KEY report_key (report_key)
        ) {$charset_collate};";

        dbDelta($sql);
        dbDelta($reports_sql);

        // dbDelta only adds columns/indexes — it never drops or reorders.
        // Drop the denormalized org_name/state_code columns + the now-unused
        // metric_state_period index. Both come from user_meta now (resolved
        // via user_id at read time).
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}", 0);
        if (in_array('org_name', (array) $existing_columns, true)) {
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN org_name");
        }
        if (in_array('state_code', (array) $existing_columns, true)) {
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN state_code");
        }
        $existing_indexes = $wpdb->get_col("SHOW INDEX FROM {$table_name}", 2);
        if (in_array('metric_state_period', (array) $existing_indexes, true)) {
            $wpdb->query("ALTER TABLE {$table_name} DROP INDEX metric_state_period");
        }
        if (in_array('org_metric_period', (array) $existing_indexes, true)) {
            $wpdb->query("ALTER TABLE {$table_name} DROP INDEX org_metric_period");
        }

        // org_key was the legacy denormalized slug; user_id + org_id cover the
        // identity now, so drop it. Has to happen after the index above so the
        // index reference is gone first.
        if (in_array('org_key', (array) $existing_columns, true)) {
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN org_key");
        }

        // Reorder org_id + user_id to the top of the table (right after id).
        // dbDelta places newly-added columns at the end; this MODIFY keeps the
        // data intact while moving them so the schema reads logically.
        $wpdb->query("ALTER TABLE {$table_name}
            MODIFY COLUMN org_id BIGINT UNSIGNED NULL AFTER id,
            MODIFY COLUMN user_id BIGINT UNSIGNED NULL AFTER org_id");

        update_option('qaqd_metric_data_db_version', QUAINAV_QAPI_DASBOARD_DB_VERSION, false);
    }

    /**
     * Resolve display fields (org label + state code) for a user_id by reading
     * user_meta. Cached per-request so we don't hit get_user_meta() N times for
     * the same user during a dashboard render.
     */
    private function resolve_user_org_meta($user_id) {
        static $cache = array();
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array('label' => '', 'state' => '');
        }
        if (isset($cache[$user_id])) {
            return $cache[$user_id];
        }
        $label = trim((string) get_user_meta($user_id, 'organization', true));
        $state = strtoupper(substr((string) get_user_meta($user_id, 'state', true), 0, 50));
        $cache[$user_id] = array('label' => $label, 'state' => $state);
        return $cache[$user_id];
    }

    public function register_settings() {
        register_setting(
            'qaqd_settings_group',
            'qaqd_run_chart_url',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_run_chart_url'),
                'default' => home_url('/run-chart/'),
            )
        );
        register_setting(
            'qaqd_settings_group',
            'qaqd_design_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_design_settings'),
                'default' => $this->get_default_design_settings(),
            )
        );
    }

    private function get_theme_css_variables() {
        static $vars = null;
        if ($vars !== null) {
            return $vars;
        }

        $vars = array();
        $stylesheet = trailingslashit(get_stylesheet_directory()) . 'style.css';
        if (!file_exists($stylesheet)) {
            return $vars;
        }

        $css = (string) @file_get_contents($stylesheet);
        if ($css === '') {
            return $vars;
        }

        if (preg_match_all('/--([a-z0-9\-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $vars[$match[1]] = trim($match[2]);
            }
        }

        return $vars;
    }

    private function get_theme_palette_defaults() {
        $vars = $this->get_theme_css_variables();
        $theme_defaults = array();

        $map = array(
            'qd_navy'         => array('gradient-color-1', 'brand-secondary'),
            'qd_cyan'         => array('brand-primary', 'brand-accent'),
            'qd_green'        => array('brand-accent', 'brand-secondary'),
            'qd_bg'           => array('brand-background'),
            'qd_card_bg'      => array('brand-surface'),
            'qd_muted'        => array('text-secondary'),
            'btn_bg'          => array('gradient-color-1', 'brand-secondary'),
            'btn_bg_hover'    => array('gradient-color-2', 'brand-accent'),
            'primary_text'    => array('text-primary', 'gradient-color-1'),
            'header_bg'       => array('brand-primary'),
            'header_text'     => array('gradient-color-1', 'text-primary'),
            'sidebar_bg'      => array('brand-surface', 'brand-background'),
            'search_bg'       => array('brand-surface'),
            'text_color'      => array('text-primary'),
            'heading_color'   => array('gradient-color-1', 'text-primary'),
            'badge_bg'        => array('brand-background', 'brand-primary'),
            'badge_text'      => array('text-primary'),
            'table_head_bg'   => array('brand-background', 'brand-surface'),
            'table_head_text' => array('text-primary'),
        );

        foreach ($map as $key => $choices) {
            foreach ($choices as $var_name) {
                if (empty($vars[$var_name])) {
                    continue;
                }
                $hex = sanitize_hex_color($vars[$var_name]);
                if ($hex) {
                    $theme_defaults[$key] = $hex;
                    break;
                }
            }
        }

        if (!empty($theme_defaults['qd_card_bg'])) {
            $theme_defaults['search_bg'] = $theme_defaults['qd_card_bg'];
            $theme_defaults['sidebar_bg'] = $theme_defaults['qd_card_bg'];
        }

        if (!empty($theme_defaults['qd_bg'])) {
            $theme_defaults['table_head_bg'] = $theme_defaults['qd_bg'];
            $theme_defaults['badge_bg'] = $theme_defaults['qd_bg'];
        }

        return $theme_defaults;
    }

    public function sanitize_run_chart_url($value) {
        $url = esc_url_raw((string) $value);
        if (empty($url)) {
            return home_url('/run-chart/');
        }
        return $url;
    }

    private function get_default_design_settings() {
        $defaults = array(
            'font_family'   => '',
            'base_font_size'=> '13',
            'qd_navy'       => '#03283e',
            'qd_cyan'       => '#7ccae2',
            'qd_green'      => '#53a661',
            'qd_bg'         => '#f3f5f8',
            'qd_card_bg'    => '#ffffff',
            'qd_muted'      => '#6b7280',
            'qd_line'       => '#e6eaf0',
            'btn_bg'        => '#03283e',
            'btn_bg_hover'  => '#0f4a71',
            'btn_text'      => '#ffffff',
            'primary_text'  => '#03283e',
            'header_bg'     => '#a8dbe6',
            'header_text'   => '#03283e',
            'sidebar_bg'    => '#f8fbff',
            'sidebar_border'=> '#e6eaf0',
            'search_bg'     => '#ffffff',
            'search_border' => '#dbe1e8',
            'text_color'    => '#334155',
            'heading_color' => '#0f2740',
            'card_border'   => '#dbe1e8',
            'badge_bg'      => '#e2e8f0',
            'badge_text'    => '#334155',
            'table_head_bg' => '#f6f9fc',
            'table_head_text'=> '#334155',
            'custom_css'    => '',
        );

        return array_merge($defaults, $this->get_theme_palette_defaults());
    }

    public function sanitize_design_settings($value) {
        $defaults = $this->get_default_design_settings();
        $input = is_array($value) ? $value : array();

        $color_keys = array(
            'qd_navy', 'qd_cyan', 'qd_green', 'qd_bg',
            'qd_card_bg', 'qd_muted', 'qd_line',
            'btn_bg', 'btn_bg_hover', 'btn_text', 'primary_text',
            'header_bg', 'header_text', 'sidebar_bg', 'sidebar_border',
            'search_bg', 'search_border', 'text_color', 'heading_color',
            'card_border', 'badge_bg', 'badge_text', 'table_head_bg', 'table_head_text'
        );

        $out = $defaults;
        foreach ($color_keys as $key) {
            if (isset($input[$key])) {
                $hex = sanitize_hex_color((string) $input[$key]);
                if ($hex) {
                    $out[$key] = $hex;
                }
            }
        }

        if (isset($input['font_family'])) {
            $font = sanitize_text_field((string) $input['font_family']);
            $font = trim($font);
            $out['font_family'] = substr($font, 0, 180);
        }

        if (isset($input['base_font_size'])) {
            $size = absint($input['base_font_size']);
            if ($size >= 11 && $size <= 18) {
                $out['base_font_size'] = (string) $size;
            }
        }

        if (isset($input['custom_css'])) {
            $custom_css = (string) $input['custom_css'];
            $custom_css = str_replace('</style', '<\\/style', $custom_css);
            $out['custom_css'] = substr(wp_strip_all_tags($custom_css), 0, 8000);
        }

        return $out;
    }

    private function get_design_settings() {
        $defaults = $this->get_default_design_settings();
        $saved = get_option('qaqd_design_settings', array());
        if (!is_array($saved)) {
            return $defaults;
        }
        return array_merge($defaults, $saved);
    }

    private function get_design_inline_css($design) {
        $font = trim((string) ($design['font_family'] ?? ''));
        $font_css = $font !== '' ? $font : 'var(--scout-font-sans, var(--wp--preset--font-family--system-font, inherit))';
        $base_font_size = absint($design['base_font_size'] ?? 13);
        if ($base_font_size < 11 || $base_font_size > 18) {
            $base_font_size = 13;
        }
        $custom_css = (string) ($design['custom_css'] ?? '');

        $css = "
            :root{
                --qd-navy: {$design['qd_navy']};
                --qd-cyan: {$design['qd_cyan']};
                --qd-green: {$design['qd_green']};
                --qd-bg: {$design['qd_bg']};
                --qd-card-bg: {$design['qd_card_bg']};
                --qd-muted: {$design['qd_muted']};
                --qd-line: {$design['qd_line']};
                --scout-primary: {$design['header_bg']};
                --scout-primary-text: {$design['header_text']};
                --qaqd-btn-bg: {$design['btn_bg']};
                --qaqd-btn-bg-hover: {$design['btn_bg_hover']};
                --qaqd-btn-text: {$design['btn_text']};
                --qaqd-sidebar-bg: {$design['sidebar_bg']};
                --qaqd-sidebar-border: {$design['sidebar_border']};
                --qaqd-search-bg: {$design['search_bg']};
                --qaqd-search-border: {$design['search_border']};
                --qaqd-text: {$design['text_color']};
                --qaqd-heading: {$design['heading_color']};
                --qaqd-card-border: {$design['card_border']};
                --qaqd-badge-bg: {$design['badge_bg']};
                --qaqd-badge-text: {$design['badge_text']};
                --qaqd-table-head-bg: {$design['table_head_bg']};
                --qaqd-table-head-text: {$design['table_head_text']};
            }
            .qd-wizard-wrap{
                font-family: {$font_css} !important;
                font-size: {$base_font_size}px !important;
                background: var(--qd-bg) !important;
                color: var(--qaqd-text) !important;
            }
            .qd-wizard-wrap h1,
            .qd-wizard-wrap h2,
            .qd-wizard-wrap h3,
            .qd-wizard-wrap h4,
            .qd-wizard-wrap h5,
            .qd-wizard-wrap h6,
            .qd-page-title,
            .qd-folder-head,
            .qd-folder-item,
            .qd-core-purpose-title,
            .qd-bench-head{
                color: var(--qaqd-heading) !important;
            }
            .qd-header{
                background: {$design['header_bg']} !important;
            }
            .qd-page-title,
            .qd-back-btn{
                color: {$design['header_text']} !important;
            }
            .qd-back-btn{
                background: rgba(255,255,255,0.24) !important;
            }
            .qd-folder-side{
                background: var(--qaqd-sidebar-bg) !important;
                border-color: var(--qaqd-sidebar-border) !important;
            }
            .qd-folder-item,
            .qd-service-card,
            .qd-analytics-card,
            .qd-core-section,
            .qd-bench-section,
            .qd-heat-section,
            .qd-heat-lane,
            .qd-controls-panel,
            .qd-services-row{
                border-color: var(--qaqd-card-border) !important;
            }
            .qd-select,
            .qd-multi-trigger,
            .qd-multi-menu{
                border-color: var(--qaqd-search-border) !important;
                background: var(--qaqd-search-bg) !important;
            }
            .qd-core-table th,
            .qd-heat-table th{
                background: var(--qaqd-table-head-bg) !important;
                color: var(--qaqd-table-head-text) !important;
            }
            .qd-summary-chip,
            .qd-bench-score,
            .qd-folder-pill,
            .qd-count-badge,
            .qd-pill{
                background: var(--qaqd-badge-bg) !important;
                color: var(--qaqd-badge-text) !important;
            }
            .qd-run-btn,
            .qd-folder-action,
            .qd-metric-editor-save,
            .qd-file-btn{
                background: var(--qaqd-btn-bg) !important;
                border-color: var(--qaqd-btn-bg) !important;
                color: var(--qaqd-btn-text) !important;
            }
            .qd-run-btn:hover,
            .qd-folder-action:hover,
            .qd-metric-editor-save:hover,
            .qd-file-btn:hover{
                filter: none !important;
                background: var(--qaqd-btn-bg-hover) !important;
                border-color: var(--qaqd-btn-bg-hover) !important;
            }
        ";

        if ($custom_css !== '') {
            $css .= "\n" . $custom_css;
        }

        return $css;
    }

    public function add_page_template($templates) {
        $templates[$this->template_file] = 'QualiNav QAPI Dashboard';
        return $templates;
    }

    public function load_page_template($template) {
        if (!is_singular('page')) {
            return $template;
        }

        global $post;
        if (!$post) {
            return $template;
        }

        $page_template = get_post_meta($post->ID, '_wp_page_template', true);
        if ($page_template !== $this->template_file) {
            return $template;
        }

        $plugin_template = QUAINAV_QAPI_DASBOARD_PLUGIN_DIR . 'templates/' . $this->template_file;
        return file_exists($plugin_template) ? $plugin_template : $template;
    }

    public function enqueue_assets() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $page_template = get_post_meta($post->ID, '_wp_page_template', true);
        if (empty($page_template) || $page_template === 'default') {
            $page_template = get_page_template_slug($post->ID);
        }

        $is_template_page = ($page_template === $this->template_file);
        $has_shortcode = false;
        if (is_a($post, 'WP_Post')) {
            $content = (string) $post->post_content;
            $shortcodes = array(
                'qaqd_dashboard',
                'qualinav_qapi_data_management',
            );
            foreach ($shortcodes as $shortcode_tag) {
                if (has_shortcode($content, $shortcode_tag)) {
                    $has_shortcode = true;
                    break;
                }
            }
        }

        if (!$is_template_page && !$has_shortcode) {
            return;
        }

        $this->enqueue_dashboard_assets();
    }

    private function enqueue_dashboard_assets() {
        // Chart.js + dttc styles for inline Run Chart view
        wp_enqueue_script( 'dttc-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
        if ( defined( 'DTTC_URL' ) && defined( 'DTTC_VERSION' ) ) {
            wp_enqueue_style( 'dttc-style', DTTC_URL . 'assets/css/dttc.css', array(), DTTC_VERSION );
        }
        $dm_css_path = QUAINAV_QAPI_DASBOARD_PLUGIN_DIR . 'assets/css/data-management.css';
        if (file_exists($dm_css_path)) {
            wp_enqueue_style(
                'qualinav-qapi-dashboard-data-management',
                QUAINAV_QAPI_DASBOARD_PLUGIN_URL . 'assets/css/data-management.css',
                array(),
                filemtime($dm_css_path)
            );
        }

        $css_path = QUAINAV_QAPI_DASBOARD_PLUGIN_DIR . 'assets/css/quality-dashboard.css';
        $design = $this->get_design_settings();
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'quainav-qapi-dasboard-style',
                QUAINAV_QAPI_DASBOARD_PLUGIN_URL . 'assets/css/quality-dashboard.css',
                array('qualinav-qapi-dashboard-data-management'),
                filemtime($css_path)
            );
            wp_add_inline_style('quainav-qapi-dasboard-style', $this->get_design_inline_css($design));
        }

        $js_path = QUAINAV_QAPI_DASBOARD_PLUGIN_DIR . 'assets/js/qapi-dashboard.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'quainav-qapi-dasboard-script',
                QUAINAV_QAPI_DASBOARD_PLUGIN_URL . 'assets/js/qapi-dashboard.js',
                array(),
                filemtime($js_path),
                true
            );

            $report_pdf_types = array('board', 'committee', 'dashboard', 'qapi');
            $available_pdfs = array();
            foreach ($report_pdf_types as $rtype) {
                $pdf_path = QUAINAV_QAPI_DASBOARD_PLUGIN_DIR . 'assets/reports/' . $rtype . '.pdf';
                if (file_exists($pdf_path)) {
                    $available_pdfs[$rtype] = QUAINAV_QAPI_DASBOARD_PLUGIN_URL . 'assets/reports/' . $rtype . '.pdf?v=' . filemtime($pdf_path);
                }
            }

            $configured_run_chart_url = get_option('qaqd_run_chart_url', home_url('/run-chart/'));
            if (empty($configured_run_chart_url)) {
                $configured_run_chart_url = home_url('/run-chart/');
            }
            list($org_key, $org_label) = $this->get_current_org_context();
            $org_metrics_option = get_option('qaqd_org_metrics_' . $org_key, array());
            $org_metrics = is_array($org_metrics_option) ? $org_metrics_option : array();

            wp_localize_script('quainav-qapi-dasboard-script', 'QD_CONFIG', array(
                'ajax' => array(
                    'url'   => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('qaqd_report_nonce'),
                ),
                'saveAction' => 'qaqd_save_report',
                'saveOrgMetricsAction' => 'qaqd_save_org_metrics',
                'runChartUrl' => esc_url_raw(apply_filters('qaqd_run_chart_url', $configured_run_chart_url)),
                'reportPdfs' => $available_pdfs,
                'metricsDataUrl' => add_query_arg(array(
                    'action' => 'qaqd_live_metrics',
                    'nonce'  => wp_create_nonce('qaqd_live_metrics'),
                ), admin_url('admin-ajax.php')),
                'organization' => array(
                    'key' => $org_key,
                    'label' => $org_label,
                ),
                'orgMetrics' => $org_metrics,
                'measureGoals' => $this->dashboard_measure_goals(),
                'qualityMeasures' => $this->dashboard_quality_measure_groups(),
            ));
        }
    }

    private function dashboard_measure_goals() {
        global $wpdb;

        if (
            ! function_exists('qualinav_data_hub_mbqip_measure_goals_table')
            || ! function_exists('qualinav_data_hub_mbqip_goal_user_context')
            || ! function_exists('qualinav_data_hub_mbqip_goal_payload')
        ) {
            return array(
                'byKey' => array(),
                'byName' => array(),
            );
        }

        if (function_exists('qualinav_data_hub_mbqip_maybe_install')) {
            qualinav_data_hub_mbqip_maybe_install();
        }

        $context = qualinav_data_hub_mbqip_goal_user_context();
        $org_key = (string) ($context['org_key'] ?? '');
        if ($org_key === '') {
            return array(
                'byKey' => array(),
                'byName' => array(),
            );
        }

        $table = qualinav_data_hub_mbqip_measure_goals_table();
        $today = current_time('Y-m-d');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                   FROM {$table}
                  WHERE org_key = %s
                    AND status = 'active'
                    AND (end_date IS NULL OR end_date = '0000-00-00' OR end_date >= %s)
                  ORDER BY COALESCE(end_date, '9999-12-31') DESC, updated_at DESC, id DESC",
                $org_key,
                $today
            ),
            ARRAY_A
        );

        $by_key = array();
        $by_name = array();
        foreach ((array) $rows as $row) {
            $payload = qualinav_data_hub_mbqip_goal_payload($row);
            if (!$payload) {
                continue;
            }
            $measure_key = sanitize_key((string) ($payload['measure_key'] ?? ''));
            $measure_name = trim((string) ($payload['measure_name'] ?? ''));
            $measure_key_variants = array_filter(array_unique(array(
                $measure_key,
                str_replace('-', '_', $measure_key),
                str_replace('_', '-', $measure_key),
            )));
            if (strpos($measure_key, 'hacs_hais_') === 0) {
                $hacs_measure_key = substr($measure_key, strlen('hacs_hais_'));
                $measure_key_variants[] = $hacs_measure_key;
                $measure_key_variants[] = str_replace('_', '-', $hacs_measure_key);
            }
            $measure_key_variants = array_filter(array_unique($measure_key_variants));
            foreach ($measure_key_variants as $measure_key_variant) {
                if (!isset($by_key[$measure_key_variant])) {
                    $by_key[$measure_key_variant] = $payload;
                }
            }
            if ($measure_name !== '') {
                $normalized_name = sanitize_title(str_replace(array('—', '/', ':'), '-', $measure_name));
                if ($normalized_name !== '' && !isset($by_name[$normalized_name])) {
                    $by_name[$normalized_name] = $payload;
                }
                $underscore_name = str_replace('-', '_', $normalized_name);
                if ($underscore_name !== '' && !isset($by_name[$underscore_name])) {
                    $by_name[$underscore_name] = $payload;
                }
            }
        }

        return array(
            'byKey' => $by_key,
            'byName' => $by_name,
        );
    }

    private function normalize_header($value) {
        $key = strtolower(trim((string) $value));
        $key = str_replace(array('(', ')', '/', '-', 'â€“', 'â€”'), ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        return trim((string) $key);
    }

    private function parse_numeric($value) {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(array('%', ','), '', $raw);
        if (!is_numeric($raw)) {
            return null;
        }
        return (float) $raw;
    }

    private function month_to_number($value) {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return null;
        }
        $raw = substr($raw, 0, 3);
        $map = array(
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        );
        return isset($map[$raw]) ? $map[$raw] : null;
    }

    private function parse_period_timestamp_from_row($row, $idx) {
        if (!is_array($row) || !is_array($idx)) {
            return null;
        }

        $year_idx = $idx['year'] ?? null;
        $month_idx = $idx['month'] ?? null;
        $quarter_idx = $idx['quarter'] ?? ($idx['period'] ?? null);
        $period_date_idx = $idx['period date'] ?? ($idx['period end'] ?? ($idx['date'] ?? null));

        if ($year_idx !== null && $month_idx !== null && isset($row[$year_idx], $row[$month_idx])) {
            $year = (int) trim((string) $row[$year_idx]);
            $month = $this->month_to_number($row[$month_idx]);
            if ($year > 1900 && $month !== null) {
                return strtotime(sprintf('%04d-%02d-01', $year, $month));
            }
        }

        if ($period_date_idx !== null && isset($row[$period_date_idx])) {
            $ts = strtotime(trim((string) $row[$period_date_idx]));
            if ($ts !== false) {
                return $ts;
            }
        }

        if ($quarter_idx !== null && isset($row[$quarter_idx])) {
            $raw = trim((string) $row[$quarter_idx]);
            if (preg_match('/q([1-4])\s*([12][0-9]{3})/i', $raw, $m)) {
                $quarter = (int) $m[1];
                $year = (int) $m[2];
                $month = (($quarter - 1) * 3) + 1;
                return strtotime(sprintf('%04d-%02d-01', $year, $month));
            }
            if ($year_idx !== null && isset($row[$year_idx]) && preg_match('/q([1-4])/i', $raw, $m)) {
                $quarter = (int) $m[1];
                $year = (int) trim((string) $row[$year_idx]);
                if ($year > 1900) {
                    $month = (($quarter - 1) * 3) + 1;
                    return strtotime(sprintf('%04d-%02d-01', $year, $month));
                }
            }
        }

        return null;
    }

    private function dashboard_quality_measure_groups() {
        $context = function_exists('qualinav_data_hub_get_org_context')
            ? qualinav_data_hub_get_org_context(get_current_user_id())
            : array();
        $coverage = function_exists('qualinav_data_hub_get_measure_coverage')
            ? qualinav_data_hub_get_measure_coverage($context)
            : array('saved' => false, 'mbqip' => array(), 'hacs_hais' => array());

        $coverage_saved = !empty($coverage['saved']);
        $mbqip_selected = array_flip(array_map('strval', (array) ($coverage['mbqip'] ?? array())));
        $hacs_selected = array_flip(array_map('strval', (array) ($coverage['hacs_hais'] ?? array())));

        $mbqip_groups = array();
        if (function_exists('qualinav_data_hub_mbqip_measure_definitions')) {
            foreach ((array) qualinav_data_hub_mbqip_measure_definitions() as $definition) {
                $measure_name = (string) ($definition['measure_name'] ?? '');
                if ($measure_name === '') {
                    continue;
                }
                if ($coverage_saved && !isset($mbqip_selected[$measure_name])) {
                    continue;
                }
                $event_type = (string) ($definition['event_type'] ?? 'MBQIP');
                if ($event_type === '') {
                    $event_type = 'MBQIP';
                }
                if (!isset($mbqip_groups[$event_type])) {
                    $mbqip_groups[$event_type] = array(
                        'id' => sanitize_title($event_type),
                        'cat' => $event_type,
                        'items' => array(),
                    );
                }
                $mbqip_groups[$event_type]['items'][] = $measure_name;
            }
        }

        $hacs_definitions = array(
            array('id' => 'c_diff', 'label' => 'C. Diff', 'group' => 'HAIs'),
            array('id' => 'mrsa', 'label' => 'MRSA', 'group' => 'HAIs'),
            array('id' => 'cauti', 'label' => 'CAUTI', 'group' => 'HAIs'),
            array('id' => 'clabsi', 'label' => 'CLABSI', 'group' => 'HAIs'),
            array('id' => 'pressure_ulcers_3_plus', 'label' => 'Pressure Ulcers 3+', 'group' => 'HACs'),
            array('id' => 'falls_with_injury', 'label' => 'Inpatient Falls with Injury', 'group' => 'HACs'),
            array('id' => 'sepsis_mortality', 'label' => 'Sepsis Mortality', 'group' => 'HACs'),
            array('id' => 'readmissions', 'label' => 'Readmissions', 'group' => 'HACs'),
        );
        $hacs_groups = array(
            'HAIs' => array(
                'id' => 'hais',
                'cat' => 'HAIs',
                'items' => array(),
            ),
            'HACs' => array(
                'id' => 'hacs',
                'cat' => 'HACs',
                'items' => array(),
            ),
        );
        foreach ($hacs_definitions as $definition) {
            $measure_id = (string) ($definition['id'] ?? '');
            if ($measure_id === '') {
                continue;
            }
            if ($coverage_saved && !isset($hacs_selected[$measure_id])) {
                continue;
            }
            $group_key = (string) ($definition['group'] ?? 'HACs');
            if (!isset($hacs_groups[$group_key])) {
                $hacs_groups[$group_key] = array(
                    'id' => sanitize_title($group_key),
                    'cat' => $group_key,
                    'items' => array(),
                );
            }
            $hacs_groups[$group_key]['items'][] = (string) ($definition['label'] ?? $measure_id);
        }

        $groups = array();
        if (!empty($mbqip_groups)) {
            $mbqip_children = array();
            foreach ($mbqip_groups as $group) {
                $group['items'] = array_values(array_unique($group['items']));
                if (!empty($group['items'])) {
                    $mbqip_children[] = $group;
                }
            }
            $groups[] = array(
                'id' => 'mbqip',
                'cat' => 'MBQIP',
                'items' => array(),
                'children' => $mbqip_children,
            );
        }
        $hacs_children = array();
        foreach ($hacs_groups as $group) {
            $group['items'] = array_values(array_unique($group['items']));
            if (!empty($group['items'])) {
                $hacs_children[] = $group;
            }
        }
        if (!empty($hacs_children)) {
            $groups[] = array(
                'id' => 'hacs-hais',
                'cat' => 'HACs & HAIs',
                'items' => array(),
                'children' => $hacs_children,
            );
        }

        return array(
            'saved' => $coverage_saved,
            'groups' => $groups,
        );
    }

    private function parse_period_parts_from_row($row, $idx) {
        $parts = array(
            'ts' => null,
            'year' => null,
            'month' => null,
            'quarter' => null,
        );

        if (!is_array($row) || !is_array($idx)) {
            return $parts;
        }

        $year_idx = $idx['year'] ?? null;
        $month_idx = $idx['month'] ?? null;
        $quarter_idx = $idx['quarter'] ?? ($idx['period'] ?? null);
        $period_date_idx = $idx['period date'] ?? ($idx['period end'] ?? ($idx['date'] ?? null));

        if ($year_idx !== null && isset($row[$year_idx])) {
            $year = (int) trim((string) $row[$year_idx]);
            if ($year > 1900) {
                $parts['year'] = $year;
            }
        }

        if ($month_idx !== null && isset($row[$month_idx])) {
            $month = $this->month_to_number($row[$month_idx]);
            if ($month !== null) {
                $parts['month'] = $month;
            }
        }

        if ($quarter_idx !== null && isset($row[$quarter_idx])) {
            $raw = trim((string) $row[$quarter_idx]);
            if (preg_match('/q([1-4])\s*([12][0-9]{3})/i', $raw, $m)) {
                $parts['quarter'] = (int) $m[1];
                $parts['year'] = (int) $m[2];
            } elseif (preg_match('/q([1-4])/i', $raw, $m)) {
                $parts['quarter'] = (int) $m[1];
            }
        }

        if ($period_date_idx !== null && isset($row[$period_date_idx])) {
            $ts = strtotime(trim((string) $row[$period_date_idx]));
            if ($ts !== false) {
                $parts['ts'] = $ts;
                if ($parts['year'] === null) {
                    $parts['year'] = (int) gmdate('Y', $ts);
                }
                if ($parts['month'] === null) {
                    $parts['month'] = (int) gmdate('n', $ts);
                }
            }
        }

        if ($parts['ts'] === null && $parts['year'] !== null && $parts['month'] !== null) {
            $parts['ts'] = strtotime(sprintf('%04d-%02d-01', $parts['year'], $parts['month']));
        }

        if ($parts['ts'] === null && $parts['year'] !== null && $parts['quarter'] !== null) {
            $parts['ts'] = strtotime(sprintf('%04d-%02d-01', $parts['year'], (($parts['quarter'] - 1) * 3) + 1));
        }

        if ($parts['ts'] === null && $parts['year'] !== null) {
            $parts['ts'] = strtotime(sprintf('%04d-12-31', $parts['year']));
        }

        return $parts;
    }

    private function metric_key_from_label($label) {
        $key = strtolower(trim((string) $label));
        $key = str_replace(array('Ã¢â‚¬â€œ', 'Ã¢â‚¬â€', 'â€“', 'â€”'), '-', $key);
        $key = preg_replace('/[^a-z0-9]+/', '-', $key);
        $key = trim((string) $key, '-');
        return $key;
    }

    private function extract_metric_observations_from_rows($rows, $lower_is_better = false, $minutes_mode = false, $metric_label = null) {
        if (!is_array($rows) || count($rows) < 2) {
            return array();
        }

        $headers = array_map(array($this, 'normalize_header'), (array) $rows[0]);
        $idx = array();
        foreach ($headers as $i => $h) {
            $idx[$h] = $i;
        }

        $i_rate = $idx['rate'] ?? null;
        $i_num = $idx['num'] ?? null;
        $i_denom = $idx['denom'] ?? null;
        $i_minutes = $idx['median minutes'] ?? ($idx['minutes'] ?? null);
        $i_met = $idx['elements met count'] ?? ($idx['core elements met count'] ?? ($idx['criteria met count'] ?? null));
        $i_selected = $idx['elements selected count'] ?? ($idx['core elements count'] ?? ($idx['core elements selected count'] ?? ($idx['criteria count'] ?? null)));

        // All metrics in a section share one folder bucket, so a file in that
        // bucket could be for any of them. The CSV's Metric column tells us
        // which — filter on it so a HCP Flu file doesn't get ingested as
        // observations for ASP, CAUTI, etc.
        $i_metric = $idx['metric'] ?? null;
        $expected_metric = is_string($metric_label) ? trim($metric_label) : '';

        $observations = array();
        for ($r = 1; $r < count($rows); $r++) {
            $row = (array) $rows[$r];

            if ($expected_metric !== '' && $i_metric !== null) {
                $row_metric = isset($row[$i_metric]) ? trim((string) $row[$i_metric]) : '';
                if ($row_metric !== '' && strcasecmp($row_metric, $expected_metric) !== 0) {
                    continue;
                }
            }

            $val = null;
            $num = null;
            $den = null;

            if ($i_rate !== null && isset($row[$i_rate])) {
                $val = $this->parse_numeric($row[$i_rate]);
            }
            if ($i_num !== null && isset($row[$i_num])) {
                $num = $this->parse_numeric($row[$i_num]);
            }
            if ($i_denom !== null && isset($row[$i_denom])) {
                $den = $this->parse_numeric($row[$i_denom]);
            }
            if ($val === null && $num !== null && $den !== null && $den > 0) {
                $val = ($num / $den) * 100.0;
            }
            if ($val === null && $minutes_mode && $i_minutes !== null && isset($row[$i_minutes])) {
                $val = $this->parse_numeric($row[$i_minutes]);
            }
            if ($val === null && $i_met !== null && $i_selected !== null && isset($row[$i_met], $row[$i_selected])) {
                $met = $this->parse_numeric($row[$i_met]);
                $sel = $this->parse_numeric($row[$i_selected]);
                if ($met !== null && $sel !== null && $sel > 0) {
                    $val = ($met / $sel) * 100.0;
                    $num = $met;
                    $den = $sel;
                }
            }

            if ($val === null) {
                continue;
            }

            $parts = $this->parse_period_parts_from_row($row, $idx);
            $observations[] = array(
                'row_index' => $r,
                'ts' => $parts['ts'],
                'year' => $parts['year'],
                'month' => $parts['month'],
                'quarter' => $parts['quarter'],
                'num_value' => $num,
                'denom_value' => $den,
                'raw_value' => (float) $val,
                'lower_is_better' => $lower_is_better,
                'minutes_mode' => $minutes_mode,
            );
        }

        return $observations;
    }

    private function csv_url_to_path($url) {
        $url = (string) $url;
        if ($url === '') return '';

        $uploads = wp_upload_dir();
        $baseurl = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';
        $basedir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';

        if ($baseurl !== '' && strpos($url, $baseurl) === 0) {
            $relative = ltrim(substr($url, strlen($baseurl)), '/');
            return trailingslashit($basedir) . $relative;
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') return '';

        $uploads_pos = strpos($path, '/wp-content/uploads/');
        if ($uploads_pos !== false && $basedir !== '') {
            $relative = ltrim(substr($path, $uploads_pos + strlen('/wp-content/uploads/')), '/');
            return trailingslashit($basedir) . $relative;
        }

        return ABSPATH . ltrim($path, '/');
    }

    private function read_xlsx_rows($path) {
        if (!class_exists('ZipArchive') || !file_exists($path) || !is_readable($path)) {
            return array();
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return array();
        }

        $shared_strings = array();
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($shared_xml) && $shared_xml !== '') {
            $sx = @simplexml_load_string($shared_xml, 'SimpleXMLElement', LIBXML_NONET);
            if ($sx && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    // Supports both simple <t> and rich text <r><t>.
                    if (isset($si->t)) {
                        $shared_strings[] = (string) $si->t;
                    } else {
                        $parts = array();
                        if (isset($si->r)) {
                            foreach ($si->r as $r) {
                                $parts[] = (string) $r->t;
                            }
                        }
                        $shared_strings[] = implode('', $parts);
                    }
                }
            }
        }

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!is_string($sheet_xml) || $sheet_xml === '') {
            // Fallback: first worksheet found.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/sheet') === 0 && substr($name, -4) === '.xml') {
                    $sheet_xml = (string) $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheet_xml === '') {
            return array();
        }

        $sx = @simplexml_load_string($sheet_xml, 'SimpleXMLElement', LIBXML_NONET);
        if (!$sx || !isset($sx->sheetData)) {
            return array();
        }

        $rows = array();
        $row_count = 0;
        foreach ($sx->sheetData->row as $row_node) {
            $row = array();
            foreach ($row_node->c as $c) {
                $type = (string) ($c['t'] ?? '');
                $val = '';
                if ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string) $c->is->t;
                } elseif (isset($c->v)) {
                    $raw = (string) $c->v;
                    if ($type === 's') {
                        $idx = (int) $raw;
                        $val = isset($shared_strings[$idx]) ? (string) $shared_strings[$idx] : '';
                    } else {
                        $val = $raw;
                    }
                }
                $row[] = trim((string) $val);
            }

            $has_content = false;
            foreach ($row as $cell) {
                if ($cell !== '') {
                    $has_content = true;
                    break;
                }
            }
            if ($has_content) {
                $rows[] = $row;
                $row_count++;
                if ($row_count >= $this->max_parsed_rows) {
                    break;
                }
            }
        }

        return $rows;
    }

    private function read_legacy_xls_rows($path) {
        if (!file_exists($path) || !is_readable($path)) {
            return array();
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return array();
        }

        // True legacy BIFF .xls (OLE Compound File) is not parsed here.
        // Signature: D0 CF 11 E0 A1 B1 1A E1
        $prefix = substr($raw, 0, 8);
        if ($prefix === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return array();
        }

        $trimmed = ltrim($raw);
        $rows = array();

        // 1) SpreadsheetML / generic XML-like rows.
        if (strpos($trimmed, '<?xml') === 0 || stripos($trimmed, '<Workbook') !== false || stripos($trimmed, '<Row') !== false) {
            $xml = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml) {
                $nodes = $xml->xpath('//*[local-name()="Row"]');
                if (is_array($nodes)) {
                    $row_count = 0;
                    foreach ($nodes as $rowNode) {
                        $row = array();
                        $cells = $rowNode->xpath('./*[local-name()="Cell"]');
                        if (is_array($cells)) {
                            foreach ($cells as $cell) {
                                $dataNodes = $cell->xpath('./*[local-name()="Data"]');
                                $val = '';
                                if (is_array($dataNodes) && isset($dataNodes[0])) {
                                    $val = trim((string) $dataNodes[0]);
                                } else {
                                    $val = trim((string) $cell);
                                }
                                $row[] = $val;
                            }
                        }
                        if (array_filter($row, static function($v) { return trim((string) $v) !== ''; })) {
                            $rows[] = $row;
                            $row_count++;
                            if ($row_count >= $this->max_parsed_rows) {
                                break;
                            }
                        }
                    }
                }
            }
            if ($rows) {
                return $rows;
            }
        }

        // 2) HTML table saved as .xls.
        if (stripos($trimmed, '<table') !== false || stripos($trimmed, '<tr') !== false) {
            if (class_exists('DOMDocument')) {
                $dom = new DOMDocument();
                @$dom->loadHTML($raw);
                $trs = $dom->getElementsByTagName('tr');
                $row_count = 0;
                foreach ($trs as $tr) {
                    $row = array();
                    foreach ($tr->childNodes as $cell) {
                        $name = strtolower((string) $cell->nodeName);
                        if ($name !== 'td' && $name !== 'th') continue;
                        $row[] = trim((string) $cell->textContent);
                    }
                    if (array_filter($row, static function($v) { return trim((string) $v) !== ''; })) {
                        $rows[] = $row;
                        $row_count++;
                        if ($row_count >= $this->max_parsed_rows) {
                            break;
                        }
                    }
                }
            }
            if ($rows) {
                return $rows;
            }
        }

        // 3) Text fallback: comma or tab delimited content with .xls extension.
        $lines = preg_split('/\r\n|\n|\r/', $raw);
        if (!is_array($lines)) {
            return array();
        }
        $row_count = 0;
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') continue;
            if (strpos($line, "\t") !== false) {
                $row = array_map('trim', explode("\t", $line));
            } else {
                $tmp = str_getcsv($line);
                $row = is_array($tmp) ? array_map('trim', $tmp) : array();
            }
            if (array_filter($row, static function($v) { return trim((string) $v) !== ''; })) {
                $rows[] = $row;
                $row_count++;
                if ($row_count >= $this->max_parsed_rows) {
                    break;
                }
            }
        }
        return $rows;
    }

    private function read_tabular_rows_from_record($record) {
        if (!is_array($record)) return array();
        if (!empty($record['archived'])) return array();
        if (!empty($record['raw_rows']) && is_array($record['raw_rows'])) {
            $headers = array();
            foreach ($record['raw_rows'] as $raw_row) {
                if (!is_array($raw_row)) {
                    continue;
                }
                foreach (array_keys($raw_row) as $key) {
                    $key = trim((string) $key);
                    if ($key !== '' && !in_array($key, $headers, true)) {
                        $headers[] = $key;
                    }
                }
            }
            if (!empty($headers)) {
                $rows = array($headers);
                foreach ($record['raw_rows'] as $raw_row) {
                    if (!is_array($raw_row)) {
                        continue;
                    }
                    $row = array();
                    foreach ($headers as $header) {
                        $row[] = isset($raw_row[$header]) ? (string) $raw_row[$header] : '';
                    }
                    if (array_filter($row, static function($value) { return trim((string) $value) !== ''; })) {
                        $rows[] = $row;
                    }
                }
                if (count($rows) > 1) {
                    return $rows;
                }
            }
        }
        $name = strtolower((string) ($record['name'] ?? ''));
        $type = strtolower((string) ($record['type'] ?? ''));
        $is_csv = substr($name, -4) === '.csv' || strpos($type, 'csv') !== false;
        $is_xlsx = substr($name, -5) === '.xlsx'
            || strpos($type, 'spreadsheetml') !== false
            || strpos($type, 'officedocument.spreadsheetml') !== false;
        // Legacy .xls binary parsing is not supported here.
        $is_xls = substr($name, -4) === '.xls' || $type === 'application/vnd.ms-excel';
        if (!$is_csv && !$is_xlsx && !$is_xls) return array();

        $path = $this->csv_url_to_path((string) ($record['url'] ?? ''));
        if ($path === '' || !file_exists($path) || !is_readable($path)) {
            return array();
        }

        if ($is_xlsx) {
            return $this->read_xlsx_rows($path);
        }
        if ($is_xls) {
            return $this->read_legacy_xls_rows($path);
        }

        $rows = array();
        $fh = fopen($path, 'r');
        if (!$fh) return array();
        while (($data = fgetcsv($fh)) !== false) {
            if (!is_array($data)) continue;
            $has_content = false;
            foreach ($data as $cell) {
                if (trim((string) $cell) !== '') {
                    $has_content = true;
                    break;
                }
            }
            if ($has_content) {
                $rows[] = $data;
            }
        }
        fclose($fh);
        return $rows;
    }

    private function get_live_metric_definitions() {
        return array(
            // MBQIP
            'CAH Quality Infrastructure Assessment' => array('folders' => array('mbqip'), 'benchmark' => '100%'),

            // 1. Patient Safety & Inpatient
            'HCP Flu (Staff Vaccination)'     => array('folders' => array('patient-safety'), 'benchmark' => '>=90%'),
            'ASP (Antibiotic Stewardship)'    => array('folders' => array('patient-safety'), 'benchmark' => '100%'),
            'Safe Use of Opioids'             => array('folders' => array('patient-safety'), 'benchmark' => '0 events', 'lower_is_better' => true),
            'IMM-3 (Vaccination Coverage)'    => array('folders' => array('patient-safety'), 'benchmark' => '>=90%'),
            'CAUTI Rate'                      => array('folders' => array('patient-safety'), 'benchmark' => '<=1.0', 'lower_is_better' => true),
            'Falls with Major Injury'         => array('folders' => array('patient-safety'), 'benchmark' => '0 events', 'lower_is_better' => true),

            // 2. Care Transitions (EDTC)
            'EDTC — Emergency Department Transfer Communication' => array('folders' => array('mbqip'), 'benchmark' => '>=90%'),
            'EDTC-All (Composite)'            => array('folders' => array('edtc'), 'benchmark' => '>=90%'),
            'EDTC-Med (Medications Sent)'     => array('folders' => array('edtc'), 'benchmark' => '100%'),
            'EDTC-Prov (Note/H&P Sent)'      => array('folders' => array('edtc'), 'benchmark' => '100%'),

            // 3. Outpatient & ED Efficiency
            'OP-18 (ED Arrival to Departure)' => array('folders' => array('outpatient-ed'), 'benchmark' => '<=240 min', 'minutes_mode' => true, 'lower_is_better' => true),
            'OP-3 (Time to Transfer)'         => array('folders' => array('outpatient-ed'), 'benchmark' => '<=60 min', 'minutes_mode' => true, 'lower_is_better' => true),
            'OP-22 (Left Without Being Seen)' => array('folders' => array('outpatient-ed'), 'benchmark' => '<=2%', 'lower_is_better' => true),
            'OP-2 (Fibrinolytic Therapy)'     => array('folders' => array('outpatient-ed'), 'benchmark' => '<=30 min', 'minutes_mode' => true, 'lower_is_better' => true),

            // 4. Patient Engagement (HCAHPS)
            'H-Comp-1 (Nurse Communication)'  => array('folders' => array('hcahps'), 'benchmark' => '>=80%'),
            'H-Comp-3 (Staff Style)'          => array('folders' => array('hcahps'), 'benchmark' => '>=80%'),
            'H-Global (Willingness to Recommend)' => array('folders' => array('hcahps'), 'benchmark' => '>=70%'),
            'H-Clean (Cleanliness)'           => array('folders' => array('hcahps'), 'benchmark' => '>=75%'),
            'SDOH 1+2 (Social Determinants)'  => array('folders' => array('hcahps'), 'benchmark' => '>=90%'),
            'HWR (Hospital-Wide Readmission)' => array('folders' => array('hcahps'), 'benchmark' => '<=15%', 'lower_is_better' => true),

            // 5. Swing Bed Quality
            'Functional Gains (Mobility/Self-care)' => array('folders' => array('swing-bed'), 'benchmark' => '>=70%'),
            'Discharge Disposition (Home/LTC/Acute)' => array('folders' => array('swing-bed'), 'benchmark' => '>=70%'),
            'Average Length of Stay (ALOS)'   => array('folders' => array('swing-bed'), 'benchmark' => '<=14 days', 'lower_is_better' => true),

            // 6. PIPs
            'Antibiotic Stewardship Program (PIP)' => array('folders' => array('pips'), 'benchmark' => '100%'),
            'Reduction of Patient Falls (PIP)' => array('folders' => array('pips'), 'benchmark' => '0 events', 'lower_is_better' => true),
            'ER: Throughput Efficiency (PIP)' => array('folders' => array('pips'), 'benchmark' => '<=120 min', 'minutes_mode' => true, 'lower_is_better' => true),
            'PDSA Cycle Status (Plan-Do-Study-Act)' => array('folders' => array('pips'), 'benchmark' => 'On Track'),
            'Monthly Interventions Summary'   => array('folders' => array('pips'), 'benchmark' => 'Completed'),

            // 7. Risk Management
            'Patient Grievances (Resolution Status)' => array('folders' => array('risk-management'), 'benchmark' => '100%'),
            'Incident Reports (Variance Summary)' => array('folders' => array('risk-management'), 'benchmark' => 'Trend Down', 'lower_is_better' => true),
            'Sentinel Events (Root Cause Analysis)' => array('folders' => array('risk-management'), 'benchmark' => '0 events', 'lower_is_better' => true),

            // 8. Infection Control
            'CLABSI Rate'                     => array('folders' => array('infection-control'), 'benchmark' => '<=1.0', 'lower_is_better' => true),
            'CAUTI Rate (Monthly)'           => array('folders' => array('infection-control'), 'benchmark' => '<=1.0', 'lower_is_better' => true),
            'Hand Hygiene Compliance'         => array('folders' => array('infection-control'), 'benchmark' => '>=95%'),

            // 9. Rural Health Clinics
            'Diabetes Control (A1c > 9)'      => array('folders' => array('rural-health'), 'benchmark' => '<=15%', 'lower_is_better' => true),
            'Hypertension Control'            => array('folders' => array('rural-health'), 'benchmark' => '>=60%'),
            'Depression Screening'            => array('folders' => array('rural-health'), 'benchmark' => '>=90%'),

            // 10. Utilization Review
            'Medical Necessity Denials'       => array('folders' => array('utilization-review'), 'benchmark' => '<=3%', 'lower_is_better' => true),
            'Peer-to-Peer Review Outcomes'    => array('folders' => array('utilization-review'), 'benchmark' => '>=80%'),

            // 11. Regulatory
            'Mock Survey Findings (Internal Audits)' => array('folders' => array('regulatory'), 'benchmark' => '0 findings', 'lower_is_better' => true),
            'Life Safety (Fire Doors/Generator)' => array('folders' => array('regulatory'), 'benchmark' => '100%'),
        );
    }

    private function get_org_directory() {
        $directory = array();
        $users = get_users(array(
            'fields' => array('ID'),
            'number' => 500,
        ));
        foreach ($users as $user) {
            $user_id = (int) ($user->ID ?? 0);
            if ($user_id <= 0) {
                continue;
            }
            $org_label = trim((string) get_user_meta($user_id, 'organization', true));
            if ($org_label === '') {
                continue;
            }
            $org_key = sanitize_title($org_label);
            if ($org_key === '') {
                continue;
            }

            $state = trim((string) get_user_meta($user_id, 'state', true));
            if ($state === '') {
                $state = trim((string) get_user_meta($user_id, 'states', true));
            }
            if ($state === '') {
                $state = trim((string) get_user_meta($user_id, 'user_state', true));
            }
            $state = strtoupper(substr(sanitize_text_field($state), 0, 50));

            if (!isset($directory[$org_key])) {
                $directory[$org_key] = array(
                    'label' => $org_label,
                    'state' => $state,
                );
                continue;
            }

            if ($directory[$org_key]['label'] === '' && $org_label !== '') {
                $directory[$org_key]['label'] = $org_label;
            }
            if ($directory[$org_key]['state'] === '' && $state !== '') {
                $directory[$org_key]['state'] = $state;
            }
        }

        return $directory;
    }

    private function get_all_org_folder_files() {
        global $wpdb;
        $like = $wpdb->esc_like('dm_org_folder_files_') . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            ),
            ARRAY_A
        );

        $all = array();
        foreach ((array) $rows as $row) {
            $option_name = (string) ($row['option_name'] ?? '');
            if ($option_name === '') {
                continue;
            }
            $org_key = substr($option_name, strlen('dm_org_folder_files_'));
            if ($org_key === '') {
                continue;
            }
            $value = maybe_unserialize($row['option_value'] ?? array());
            $all[$org_key] = is_array($value) ? $value : array();
        }

        return $all;
    }

    /**
     * Reconcile the metric cache against the source of truth (the
     * dm_org_folder_files_* options). Any row whose folder_key|source_name no
     * longer corresponds to an existing uploaded file is an orphan and is
     * deleted — including ALL rows when there are no files anywhere. This is
     * scope-agnostic (doesn't depend on the viewer's org_id/user_id), so it
     * cleans phantoms the scoped per-org resync can't reach. Returns rows removed.
     */
    public function reconcile_metric_data_orphans() {
        global $wpdb;
        $this->ensure_metric_data_table();
        $table = $this->get_metric_data_table_name();

        $valid = array();
        foreach ($this->get_all_org_folder_files() as $folder_files) {
            if (!is_array($folder_files)) {
                continue;
            }
            foreach ($folder_files as $folder_id => $list) {
                if (!is_array($list)) {
                    continue;
                }
                foreach ($list as $record) {
                    if (!is_array($record)) {
                        continue;
                    }
                    $name = sanitize_file_name((string) ($record['name'] ?? ''));
                    if ($name !== '') {
                        $valid[(string) $folder_id . '|' . $name] = true;
                    }
                }
            }
        }

        if (empty($valid)) {
            return (int) $wpdb->query("DELETE FROM {$table}");
        }

        $keys         = array_keys($valid);
        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $sql          = "DELETE FROM {$table} WHERE CONCAT(folder_key, '|', source_name) NOT IN ({$placeholders})";
        $prepared     = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(array($sql), $keys)
        );
        return (int) $wpdb->query($prepared);
    }

    private function sync_metric_data_table($target_org_key = null) {
        $this->ensure_metric_data_table();

        $definitions = $this->get_live_metric_definitions();
        $org_directory = $this->get_org_directory();
        $all_folder_files = $this->get_all_org_folder_files();

        if ($target_org_key !== null) {
            $target_org_key = (string) $target_org_key;
            $all_folder_files = array(
                $target_org_key => isset($all_folder_files[$target_org_key]) && is_array($all_folder_files[$target_org_key])
                    ? $all_folder_files[$target_org_key]
                    : array(),
            );
        }

        foreach ($all_folder_files as $org_key => $folder_files) {
            $this->sync_single_org_metric_data($org_key, is_array($folder_files) ? $folder_files : array(), $definitions, $org_directory);
        }
    }

    private function sync_single_org_metric_data($org_key, $folder_files, $definitions, $org_directory) {
        global $wpdb;

        $table_name = $this->get_metric_data_table_name();
        $org_key = sanitize_title((string) $org_key);
        if ($org_key === '') {
            return;
        }

        $org_meta = $org_directory[$org_key] ?? array();
        $org_label = (string) ($org_meta['label'] ?? $org_key);
        $state_code = strtoupper(substr((string) ($org_meta['state'] ?? ''), 0, 50));
        $uploaded_at = current_time('mysql', true);

        // Resolve FK references straight from wp_users. organization_id is a
        // first-class column on the user row now, so we don't need to look up
        // wp_qi_orgs by slug anymore — the FK is right there on the user.
        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            $user_id = null;
        }
        $org_id = null;
        if ($user_id !== null) {
            $org_id_lookup = $wpdb->get_var($wpdb->prepare(
                "SELECT organization_id FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
                $user_id
            ));
            if ($org_id_lookup !== null && (int) $org_id_lookup > 0) {
                $org_id = (int) $org_id_lookup;
            }
        }

        // Scope the delete by the strongest identity we have: prefer the real
        // org_id when known, otherwise the user_id so per-user re-syncs don't
        // wipe other users' rows.
        if ($org_id !== null) {
            $wpdb->delete($table_name, array('org_id' => $org_id), array('%d'));
        } elseif ($user_id !== null) {
            $wpdb->delete($table_name, array('user_id' => $user_id), array('%d'));
        }

        foreach ($definitions as $metric_label => $def) {
            $metric_key = $this->metric_key_from_label($metric_label);
            $folders = isset($def['folders']) && is_array($def['folders']) ? $def['folders'] : array();
            $lower_is_better = !empty($def['lower_is_better']);
            $minutes_mode = !empty($def['minutes_mode']);
            $benchmark_label = (string) ($def['benchmark'] ?? '');

            foreach ($folders as $folder_id) {
                $list = isset($folder_files[$folder_id]) && is_array($folder_files[$folder_id]) ? $folder_files[$folder_id] : array();
                foreach ($list as $record) {
                    $rows = $this->read_tabular_rows_from_record($record);
                    if (!$rows) {
                        continue;
                    }

                    $observations = $this->extract_metric_observations_from_rows($rows, $lower_is_better, $minutes_mode, $metric_label);
                    if (!$observations) {
                        continue;
                    }

                    $source_name = sanitize_file_name((string) ($record['name'] ?? ''));
                    $source_url = esc_url_raw((string) ($record['url'] ?? ''));

                    foreach ($observations as $observation) {
                        $period_ts = isset($observation['ts']) && $observation['ts'] !== null ? (int) $observation['ts'] : null;
                        $period_date = $period_ts ? gmdate('Y-m-d H:i:s', $period_ts) : null;
                        $row_hash = md5(wp_json_encode(array(
                            'org_id' => $org_id,
                            'user_id' => $user_id,
                            'metric_key' => $metric_key,
                            'folder_key' => $folder_id,
                            'source_name' => $source_name,
                            'row_index' => (int) ($observation['row_index'] ?? 0),
                            'period_ts' => $period_ts,
                            'raw_value' => (string) ($observation['raw_value'] ?? ''),
                            'num_value' => (string) ($observation['num_value'] ?? ''),
                            'denom_value' => (string) ($observation['denom_value'] ?? ''),
                        )));

                        $wpdb->insert(
                            $table_name,
                            array(
                                'org_id' => $org_id,
                                'user_id' => $user_id,
                                'metric_key' => $metric_key,
                                'metric_label' => $metric_label,
                                'folder_key' => (string) $folder_id,
                                'source_name' => $source_name,
                                'source_url' => $source_url,
                                'benchmark_label' => $benchmark_label,
                                'lower_is_better' => $lower_is_better ? 1 : 0,
                                'minutes_mode' => $minutes_mode ? 1 : 0,
                                'period_ts' => $period_ts,
                                'period_date' => $period_date,
                                'year_num' => isset($observation['year']) ? $observation['year'] : null,
                                'month_num' => isset($observation['month']) ? $observation['month'] : null,
                                'quarter_num' => isset($observation['quarter']) ? $observation['quarter'] : null,
                                'row_index' => (int) ($observation['row_index'] ?? 0),
                                'num_value' => $observation['num_value'],
                                'denom_value' => $observation['denom_value'],
                                'raw_value' => $observation['raw_value'],
                                'uploaded_at' => $uploaded_at,
                                'row_hash' => $row_hash,
                            ),
                            array(
                                '%d','%d',
                                '%s','%s','%s','%s','%s','%s',
                                '%d','%d','%d','%s','%d','%d','%d','%d',
                                '%f','%f','%f','%s','%s'
                            )
                        );
                    }
                }
            }
        }
    }

    private function observation_timestamp($observation) {
        if (!is_array($observation)) {
            return null;
        }
        foreach (array('ts', 'period_ts') as $key) {
            if (isset($observation[$key]) && $observation[$key] !== null && is_numeric($observation[$key])) {
                return (int) $observation[$key];
            }
        }
        if (!empty($observation['year'])) {
            $year = (int) $observation['year'];
            if (!empty($observation['month'])) {
                return strtotime(sprintf('%04d-%02d-01', $year, max(1, min(12, (int) $observation['month']))));
            }
            if (!empty($observation['quarter'])) {
                return strtotime(sprintf('%04d-%02d-01', $year, (((int) $observation['quarter'] - 1) * 3) + 1));
            }
            return strtotime(sprintf('%04d-12-31', $year));
        }
        return null;
    }

    private function ordered_current_observations($observations) {
        $observations = array_values(array_filter((array) $observations, static function($item) {
            return is_array($item) && isset($item['raw_value']) && is_numeric($item['raw_value']);
        }));
        usort($observations, function($a, $b) {
            $a_ts = $this->observation_timestamp($a);
            $b_ts = $this->observation_timestamp($b);
            $a_sort = $a_ts !== null ? $a_ts : 0;
            $b_sort = $b_ts !== null ? $b_ts : 0;
            if ($a_sort === $b_sort) {
                $a_row = (int) ($a['row_index'] ?? 0);
                $b_row = (int) ($b['row_index'] ?? 0);
                return $a_row <=> $b_row;
            }
            return $a_sort <=> $b_sort;
        });
        return $observations;
    }

    private function build_current_record_from_observations($observations, $lower_is_better = false, $minutes_mode = false, $benchmark = null, $display_year = 0) {
        $ordered = $this->ordered_current_observations($observations);
        if (!$ordered) {
            return array(
                'value' => '-',
                'status' => '',
                'trend' => '',
                'days_between' => null,
                'record_count' => 0,
                'numeric_value' => null,
                'direction' => $lower_is_better ? 'lower' : 'higher',
                'lower_is_better' => $lower_is_better,
                'series' => array(),
            );
        }

        $display_ordered = $ordered;
        $display_year = (int) $display_year;
        if ($display_year > 0) {
            $display_ordered = array_values(array_filter($ordered, static function($item) use ($display_year) {
                return isset($item['year']) && (int) $item['year'] === $display_year;
            }));
            if (!$display_ordered) {
                return array(
                    'value' => '-',
                    'status' => '',
                    'trend' => '',
                    'days_between' => null,
                    'record_count' => count($ordered),
                    'numeric_value' => null,
                    'direction' => $lower_is_better ? 'lower' : 'higher',
                    'lower_is_better' => $lower_is_better,
                    'series' => $this->dashboard_series_from_observations($ordered, $minutes_mode),
                );
            }
        }

        $display_latest = $display_ordered[count($display_ordered) - 1];
        $trend_ordered = $ordered;
        $display_latest_ts = $this->observation_timestamp($display_latest);
        if ($display_year > 0 && $display_latest_ts > 0) {
            $trend_ordered = array_values(array_filter($ordered, function($item) use ($display_latest_ts) {
                $item_ts = $this->observation_timestamp($item);
                return $item_ts !== null && $item_ts <= $display_latest_ts;
            }));
        }
        if (!$trend_ordered) {
            $trend_ordered = $display_ordered;
        }

        $first = (float) $trend_ordered[0]['raw_value'];
        $latest = $trend_ordered[count($trend_ordered) - 1];
        $last = (float) $latest['raw_value'];
        $delta = $last - $first;

        if (abs($delta) < 0.001) {
            $trend = 'stable';
        } else {
            $improving = $lower_is_better ? ($delta < 0) : ($delta > 0);
            $trend = $improving ? 'improving' : 'declining';
        }

        $current = isset($display_latest['raw_value']) && is_numeric($display_latest['raw_value'])
            ? (float) $display_latest['raw_value']
            : $this->aggregate_observations_value($display_ordered, $minutes_mode);

        if ($minutes_mode) {
            $value = number_format($current, 0) . ' min';
        } else {
            $value = number_format($current, 2) . '%';
        }
        $status = $benchmark !== null
            ? $this->dashboard_status_for_value($current, $benchmark, $lower_is_better, $minutes_mode)
            : $this->fallback_dashboard_status_for_value($current, $lower_is_better, $minutes_mode);

        $days_between = null;
        if (count($trend_ordered) >= 2) {
            $previous = $trend_ordered[count($trend_ordered) - 2];
            $latest_ts = $this->observation_timestamp($latest);
            $previous_ts = $this->observation_timestamp($previous);
            if ($latest_ts > 0 && $previous_ts > 0) {
                $days_between = (int) round(abs($latest_ts - $previous_ts) / DAY_IN_SECONDS);
            }
        }

        return array(
            'value' => $value,
            'status' => $status,
            'trend' => $trend,
            'days_between' => $days_between,
            'record_count' => count($trend_ordered),
            'numeric_value' => $current,
            'direction' => $lower_is_better ? 'lower' : 'higher',
            'lower_is_better' => $lower_is_better,
            'series' => $this->dashboard_series_from_observations($ordered, $minutes_mode),
        );
    }

    private function dashboard_series_from_observations($observations, $minutes_mode = false) {
        $ordered = $this->ordered_current_observations($observations);
        $month_names = array(
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        );
        $series = array();
        foreach ($ordered as $index => $observation) {
            if (!isset($observation['raw_value']) || !is_numeric($observation['raw_value'])) {
                continue;
            }
            $year = isset($observation['year']) ? (int) $observation['year'] : 0;
            $period = isset($observation['period']) ? trim((string) $observation['period']) : '';
            if ($period === '' && !empty($observation['month'])) {
                $month = max(1, min(12, (int) $observation['month']));
                $period = $month_names[$month] ?? '';
            } elseif ($period === '' && !empty($observation['quarter'])) {
                $period = 'Q' . max(1, min(4, (int) $observation['quarter']));
            }
            $label = trim($period . ' ' . ($year > 0 ? (string) $year : ''));
            if ($label === '') {
                $label = 'P' . ($index + 1);
            }
            $point = array(
                'label' => $label,
                'value' => (float) $observation['raw_value'],
                'year' => $year,
                'period' => $period,
            );
            if (isset($observation['num_value']) && is_numeric($observation['num_value'])) {
                $point['num'] = (float) $observation['num_value'];
            }
            if (isset($observation['denom_value']) && is_numeric($observation['denom_value'])) {
                $point['den'] = (float) $observation['denom_value'];
            }
            $series[] = $point;
        }
        return $series;
    }

    private function build_metric_record_from_observations($observations, $lower_is_better = false, $minutes_mode = false) {
        return $this->build_current_record_from_observations($observations, $lower_is_better, $minutes_mode);
    }

    private function aggregate_observations_value($observations, $minutes_mode = false) {
        if (!is_array($observations) || !$observations) {
            return 0.0;
        }

        if (!$minutes_mode) {
            $num_total   = 0.0;
            $denom_total = 0.0;
            $has_pair    = false;
            foreach ($observations as $obs) {
                $num = isset($obs['num_value']) ? $obs['num_value'] : null;
                $den = isset($obs['denom_value']) ? $obs['denom_value'] : null;
                if (is_numeric($num) && is_numeric($den) && (float) $den > 0) {
                    $num_total   += (float) $num;
                    $denom_total += (float) $den;
                    $has_pair = true;
                }
            }
            if ($has_pair && $denom_total > 0) {
                return ($num_total / $denom_total) * 100.0;
            }
        }

        $sum = 0.0;
        $count = 0;
        foreach ($observations as $obs) {
            if (isset($obs['raw_value']) && is_numeric($obs['raw_value'])) {
                $sum += (float) $obs['raw_value'];
                $count++;
            }
        }
        return $count > 0 ? $sum / $count : 0.0;
    }

    private function get_all_org_metric_records_from_table($definitions) {
        global $wpdb;

        $table_name = $this->get_metric_data_table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY org_id ASC, user_id ASC, metric_key ASC, period_ts ASC, row_index ASC", ARRAY_A);
        $records = array();

        foreach ($definitions as $metric_name => $def) {
            $metric_key = $this->metric_key_from_label($metric_name);
            foreach ((array) $rows as $row) {
                if (($row['metric_key'] ?? '') !== $metric_key) {
                    continue;
                }
                // Build a synthetic grouping key — prefer the real org_id when
                // present, otherwise fall back to the uploader's user_id so each
                // un-orged user stays in its own bucket.
                $org_id  = (int) ($row['org_id'] ?? 0);
                $user_id = (int) ($row['user_id'] ?? 0);
                if ($org_id > 0) {
                    $bucket = 'org-' . $org_id;
                } elseif ($user_id > 0) {
                    $bucket = 'user-' . $user_id;
                } else {
                    continue;
                }
                if (!isset($records[$bucket])) {
                    $resolved = $this->resolve_user_org_meta($user_id);
                    $records[$bucket] = array(
                        '__meta' => array(
                            'state' => $resolved['state'],
                            'label' => $resolved['label'],
                        ),
                    );
                }
                if (!isset($records[$bucket][$metric_name])) {
                    $records[$bucket][$metric_name] = array();
                }
                $records[$bucket][$metric_name][] = $row;
            }
        }

        foreach ($records as $org_key => $metric_groups) {
            foreach ($definitions as $metric_name => $def) {
                if (!isset($metric_groups[$metric_name]) || !is_array($metric_groups[$metric_name])) {
                    $records[$org_key][$metric_name] = array(
                        'value' => '-',
                        'status' => '',
                        'trend' => '',
                        'days_between' => null,
                        'record_count' => 0,
                        'numeric_value' => null,
                    );
                    continue;
                }
                $records[$org_key][$metric_name] = $this->build_metric_record_from_observations(
                    $metric_groups[$metric_name],
                    !empty($def['lower_is_better']),
                    !empty($def['minutes_mode'])
                );
            }
        }

        return $records;
    }

    private function build_org_metric_records($folder_files, $definitions) {
        $metrics = array();
        foreach ($definitions as $metric_name => $def) {
            $folders = isset($def['folders']) && is_array($def['folders']) ? $def['folders'] : array();
            $lower_is_better = !empty($def['lower_is_better']);
            $minutes_mode = !empty($def['minutes_mode']);
            $found = null;

            foreach ($folders as $folder_id) {
                $list = isset($folder_files[$folder_id]) && is_array($folder_files[$folder_id]) ? $folder_files[$folder_id] : array();
                for ($i = count($list) - 1; $i >= 0; $i--) {
                    $rows = $this->read_tabular_rows_from_record($list[$i]);
                    if (!$rows) {
                        continue;
                    }
                    $parsed = $this->extract_metric_record_from_rows($rows, $lower_is_better, $minutes_mode, $metric_name);
                    if ($parsed) {
                        $found = $parsed;
                        break 2;
                    }
                }
            }

            $metrics[$metric_name] = $found ? $found : array(
                'value' => '-',
                'status' => '',
                'trend' => '',
                'days_between' => null,
                'record_count' => 0,
                'numeric_value' => null,
            );
        }

        return $metrics;
    }

    private function format_comparison_delta($current_value, $cohort_average, $lower_is_better = false, $minutes_mode = false) {
        if (!is_numeric($current_value) || !is_numeric($cohort_average)) {
            return 'Not uploaded';
        }

        $delta = $lower_is_better
            ? ((float) $cohort_average - (float) $current_value)
            : ((float) $current_value - (float) $cohort_average);

        $prefix = $delta > 0 ? '+' : '';
        if ($minutes_mode) {
            return $prefix . number_format($delta, 0) . ' min';
        }

        $rounded = abs($delta) >= 10 ? round($delta) : round($delta, 1);
        if (abs($rounded) < 0.05) {
            $rounded = 0;
        }
        $prefix = $rounded > 0 ? '+' : '';
        $formatted = abs($rounded - round($rounded)) < 0.001
            ? (string) (int) round($rounded)
            : rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.');
        return $prefix . $formatted . '%';
    }

    private function benchmark_numeric_target($benchmark) {
        $benchmark = trim((string) $benchmark);
        if ($benchmark === '' || $benchmark === '-') {
            return null;
        }
        if (!preg_match('/-?\d+(?:\.\d+)?/', $benchmark, $match)) {
            return null;
        }
        return (float) $match[0];
    }

    private function calculate_org_comparisons($current_org_key, $current_org_state_id, $current_org_state, $definitions, $all_org_metric_records) {
        $comparisons = array();
        foreach ($definitions as $metric_name => $def) {
            $current_record = $all_org_metric_records[$current_org_key][$metric_name] ?? null;
            $current_value = is_array($current_record) ? ($current_record['numeric_value'] ?? null) : null;
            $lower_is_better = !empty($def['lower_is_better']);
            $minutes_mode = !empty($def['minutes_mode']);
            $benchmark_target = $this->benchmark_numeric_target($def['benchmark'] ?? '');

            $state_values = array();

            foreach ($all_org_metric_records as $org_key => $metric_records) {
                if ($org_key === $current_org_key) {
                    continue;
                }
                $record = $metric_records[$metric_name] ?? null;
                $numeric = is_array($record) ? ($record['numeric_value'] ?? null) : null;
                if (!is_numeric($numeric)) {
                    continue;
                }

                $org_state_id = isset($metric_records['__meta']['state_id']) ? (int) $metric_records['__meta']['state_id'] : 0;
                $org_state = (string) ($metric_records['__meta']['state'] ?? '');
                if (
                    ($current_org_state_id > 0 && $org_state_id > 0 && $org_state_id === $current_org_state_id)
                    || ($current_org_state_id <= 0 && $current_org_state !== '' && $org_state !== '' && $org_state === $current_org_state)
                ) {
                    $state_values[] = (float) $numeric;
                }
            }

            $state_average = $state_values ? array_sum($state_values) / count($state_values) : null;

            $comparisons[$metric_name] = array(
                'national_comparison' => $this->format_comparison_delta($current_value, $benchmark_target, $lower_is_better, $minutes_mode),
                'state_comparison'    => $this->format_comparison_delta($current_value, $state_average, $lower_is_better, $minutes_mode),
            );
        }

        return $comparisons;
    }

    private function calculate_benchmark_distributions($definitions, $all_org_metric_records) {
        $distributions = array();

        foreach ($definitions as $metric_name => $def) {
            $counts = array(
                'reporting_orgs' => 0,
                'below_count' => 0,
                'near_count' => 0,
                'above_count' => 0,
            );

            foreach ($all_org_metric_records as $org_key => $metric_records) {
                if (!is_array($metric_records)) {
                    continue;
                }
                $record = $metric_records[$metric_name] ?? null;
                if (!is_array($record) || !isset($record['numeric_value']) || !is_numeric($record['numeric_value'])) {
                    continue;
                }

                $counts['reporting_orgs']++;
                $status = (string) ($record['status'] ?? '');
                if ($status === 'green') {
                    $counts['above_count']++;
                } elseif ($status === 'yellow') {
                    $counts['near_count']++;
                } elseif ($status === 'red') {
                    $counts['below_count']++;
                }
            }

            $distributions[$metric_name] = $counts;
        }

        return $distributions;
    }

    private function dashboard_report_filters_from_request() {
        $hospital_type = sanitize_key((string) ($_GET['hospital_type'] ?? ($_GET['organization_type'] ?? 'all')));
        $bed_bucket = sanitize_key((string) ($_GET['bed_size'] ?? ($_GET['bed_bucket'] ?? 'all')));
        $year = absint($_GET['year'] ?? 0);

        $allowed_hospital_types = array('all', 'cah', 'rural_pps', 'ipps_general_acute');
        if (!in_array($hospital_type, $allowed_hospital_types, true)) {
            $hospital_type = 'all';
        }

        $allowed_bed_buckets = array('all', '1-10', '11-25', '26-50', '51-100', '101-plus');
        if (!in_array($bed_bucket, $allowed_bed_buckets, true)) {
            $bed_bucket = 'all';
        }

        if ($year < 2012 || $year > ((int) gmdate('Y') + 1)) {
            $year = 0;
        }

        return array(
            'hospital_type' => $hospital_type,
            'bed_bucket'    => $bed_bucket,
            'year'          => $year,
        );
    }

    private function dashboard_bed_bucket($beds) {
        if (!is_numeric($beds) || (int) $beds <= 0) {
            return 'unknown';
        }

        $beds = (int) $beds;
        if ($beds <= 10) {
            return '1-10';
        }
        if ($beds <= 25) {
            return '11-25';
        }
        if ($beds <= 50) {
            return '26-50';
        }
        if ($beds <= 100) {
            return '51-100';
        }
        return '101-plus';
    }

    private function table_exists($table_name) {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === (string) $table_name;
    }

    private function get_organization_profiles() {
        global $wpdb;

        $orgs_table = $wpdb->prefix . 'organizations';
        if (!$this->table_exists($orgs_table)) {
            return array();
        }

        $states_table = $wpdb->prefix . 'states';
        $has_states = $this->table_exists($states_table);
        $state_select = $has_states ? ', s.code AS state_code' : ', NULL AS state_code';
        $state_join = $has_states ? " LEFT JOIN {$states_table} s ON s.id = o.state_id" : '';

        $rows = $wpdb->get_results(
            "SELECT o.id, o.name, o.state_id, o.hospital_type, o.beds{$state_select}
               FROM {$orgs_table} o{$state_join}",
            ARRAY_A
        );

        $profiles = array();
        foreach ((array) $rows as $row) {
            $org_id = (int) ($row['id'] ?? 0);
            if ($org_id <= 0) {
                continue;
            }
            $hospital_type = sanitize_key((string) ($row['hospital_type'] ?? ''));
            $profiles[$org_id] = array(
                'id'            => $org_id,
                'label'         => (string) ($row['name'] ?? ''),
                'state_id'      => is_numeric($row['state_id'] ?? null) ? (int) $row['state_id'] : 0,
                'state'         => strtoupper(substr((string) ($row['state_code'] ?? ''), 0, 50)),
                'hospital_type' => $hospital_type !== '' ? $hospital_type : 'unknown',
                'beds'          => is_numeric($row['beds'] ?? null) ? (int) $row['beds'] : null,
                'bed_bucket'    => $this->dashboard_bed_bucket($row['beds'] ?? null),
            );
        }

        return $profiles;
    }

    private function dashboard_filter_options($org_profiles) {
        $hospital_types = array();
        $bed_buckets = array();

        foreach ((array) $org_profiles as $profile) {
            $hospital_type = (string) ($profile['hospital_type'] ?? 'unknown');
            $bed_bucket = (string) ($profile['bed_bucket'] ?? 'unknown');
            if ($hospital_type === 'unknown' && $bed_bucket === 'unknown') {
                continue;
            }
            $hospital_types[$hospital_type !== '' ? $hospital_type : 'unknown'] = true;
            $bed_buckets[$bed_bucket !== '' ? $bed_bucket : 'unknown'] = true;
        }

        $hospital_type_labels = array(
            'cah' => 'Critical Access Hospital',
            'rural_pps' => 'Rural PPS',
            'ipps_general_acute' => 'IPPS General Acute',
        );
        $bed_bucket_labels = array(
            '1-10' => '1-10 beds',
            '11-25' => '11-25 beds',
            '26-50' => '26-50 beds',
            '51-100' => '51-100 beds',
            '101-plus' => '101+ beds',
        );

        $to_options = static function($keys, $labels) {
            $out = array(array('id' => 'all', 'label' => 'All'));
            foreach (array_keys($labels) as $key) {
                if (!isset($keys[$key])) {
                    continue;
                }
                $out[] = array('id' => $key, 'label' => $labels[$key]);
            }
            return $out;
        };

        return array(
            'hospital_types' => $to_options($hospital_types, $hospital_type_labels),
            'bed_buckets'    => $to_options($bed_buckets, $bed_bucket_labels),
        );
    }

    private function org_matches_dashboard_filters($org_id, $filters, $org_profiles) {
        $org_id = (int) $org_id;
        $profile = $org_id > 0 && isset($org_profiles[$org_id]) ? $org_profiles[$org_id] : array(
            'hospital_type' => 'unknown',
            'bed_bucket' => 'unknown',
        );

        $hospital_type_filter = (string) ($filters['hospital_type'] ?? 'all');
        if ($hospital_type_filter !== 'all' && $hospital_type_filter !== (string) ($profile['hospital_type'] ?? 'unknown')) {
            return false;
        }

        $bed_bucket_filter = (string) ($filters['bed_bucket'] ?? 'all');
        if ($bed_bucket_filter !== 'all' && $bed_bucket_filter !== (string) ($profile['bed_bucket'] ?? 'unknown')) {
            return false;
        }

        return true;
    }

    private function bucket_org_id($bucket) {
        if (preg_match('/^org-(\d+)$/', (string) $bucket, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private function filter_metric_records_by_dashboard_filters($records, $filters, $org_profiles, $current_bucket) {
        $hospital_type_filter = (string) ($filters['hospital_type'] ?? 'all');
        $bed_bucket_filter = (string) ($filters['bed_bucket'] ?? 'all');
        if ($hospital_type_filter === 'all' && $bed_bucket_filter === 'all') {
            return $records;
        }

        $filtered = array();
        foreach ((array) $records as $bucket => $metric_records) {
            if ($bucket === $current_bucket) {
                $filtered[$bucket] = $metric_records;
                continue;
            }
            $org_id = $this->bucket_org_id($bucket);
            if ($this->org_matches_dashboard_filters($org_id, $filters, $org_profiles)) {
                $filtered[$bucket] = $metric_records;
            }
        }
        return $filtered;
    }

    private function dashboard_period_timestamp($year, $period = '', $fallback_date = '') {
        $date_ts = $fallback_date !== '' ? strtotime((string) $fallback_date) : false;
        if ($date_ts !== false) {
            return $date_ts;
        }

        $year = (int) $year;
        if ($year < 1900) {
            return null;
        }

        $period = strtolower(trim((string) $period));
        if (preg_match('/q([1-4])/', $period, $m)) {
            return strtotime(sprintf('%04d-%02d-01', $year, (((int) $m[1] - 1) * 3) + 1));
        }

        return strtotime(sprintf('%04d-12-31', $year));
    }

    private function parse_dashboard_benchmark_target($benchmark) {
        $raw = trim((string) $benchmark);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/-?\d+(?:\.\d+)?/', $raw, $m)) {
            return null;
        }
        $direction = 'gte';
        if (strpos($raw, '<') !== false || stripos($raw, 'under') !== false || stripos($raw, 'below') !== false) {
            $direction = 'lte';
        }
        return array('target' => (float) $m[0], 'direction' => $direction);
    }

    private function fallback_dashboard_status_for_value($value, $lower_is_better = false, $minutes_mode = false) {
        if (!is_numeric($value)) {
            return '';
        }
        if ($minutes_mode) {
            if ((float) $value <= 90) return 'green';
            if ((float) $value <= 120) return 'yellow';
            return 'red';
        }
        if ($lower_is_better) {
            if ((float) $value <= 10) return 'green';
            if ((float) $value <= 20) return 'yellow';
            return 'red';
        }
        if ((float) $value >= 75) return 'green';
        if ((float) $value >= 50) return 'yellow';
        return 'red';
    }

    private function dashboard_status_for_value($value, $benchmark, $lower_is_better = false, $minutes_mode = false) {
        if (!is_numeric($value)) {
            return '';
        }

        $target = $this->parse_dashboard_benchmark_target($benchmark);
        if ($target) {
            $target_value = (float) $target['target'];
            $direction = $target['direction'];
            if ($lower_is_better) {
                $direction = 'lte';
            }
            if ($direction === 'lte') {
                if ((float) $value <= $target_value) {
                    return 'green';
                }
                return (float) $value <= ($target_value * 1.1) ? 'yellow' : 'red';
            }
            if ((float) $value >= $target_value) {
                return 'green';
            }
            return (float) $value >= ($target_value * 0.9) ? 'yellow' : 'red';
        }

        return $this->fallback_dashboard_status_for_value($value, $lower_is_better, $minutes_mode);
    }

    private function build_dashboard_record_from_observations($observations, $benchmark, $lower_is_better = false, $minutes_mode = false, $display_year = 0) {
        $record = $this->build_current_record_from_observations($observations, $lower_is_better, $minutes_mode, $benchmark, $display_year);
        return isset($record['numeric_value']) && $record['numeric_value'] !== null ? $record : null;
    }

    private function mbqip_dashboard_aliases() {
        return array(
            'cah_quality_infrastructure_assessment' => array('CAH Quality Infrastructure Assessment', 'CAH global measure'),
            'hcp_imm_3_healthcare_personnel_influenza_vaccination' => array('HCP/IMM-3 — Healthcare Personnel Influenza Vaccination', 'HCP Flu (Staff Vaccination)', 'HCP IMM 3', 'IMM-3 (Vaccination Coverage)'),
            'antibiotic_stewardship' => array('Antibiotic Stewardship', 'ASP (Antibiotic Stewardship)', 'Antibiotic Stewardship Implement'),
            'safe_use_of_opioids_ecqm_mbqip_submission' => array('Safe Use of Opioids eCQM — MBQIP Submission', 'Safe Use of Opioids', 'Safe Use of Opioids - Concurrent'),
            'edtc_emergency_department_transfer_communication' => array('EDTC — Emergency Department Transfer Communication', 'EDTC-All (Composite)', 'EDTC'),
            'op_18_median_ed_arrival_to_departure_time_discharged_patients' => array('OP-18 — Median ED Arrival to Departure Time (Discharged Patients)', 'OP-18 (ED Arrival to Departure)', 'Median Time from ED'),
            'op_22_patient_left_without_being_seen_lwbs_rate' => array('OP-22 — Patient Left Without Being Seen (LWBS) Rate', 'OP-22 (Left Without Being Seen)', 'OP-22 Left Without Being Seen'),
        );
    }

    private function hacs_hais_dashboard_definitions() {
        return array(
            'c_diff' => array('label' => 'C. Diff', 'benchmark' => '-', 'lower_is_better' => true),
            'mrsa' => array('label' => 'MRSA', 'benchmark' => '-', 'lower_is_better' => true),
            'cauti' => array('label' => 'CAUTI', 'benchmark' => '-', 'lower_is_better' => true),
            'clabsi' => array('label' => 'CLABSI', 'benchmark' => '-', 'lower_is_better' => true),
            'pressure_ulcers_3_plus' => array('label' => 'Pressure Ulcers 3+', 'benchmark' => '-', 'lower_is_better' => true),
            'falls_with_injury' => array('label' => 'Inpatient Falls with Injury', 'benchmark' => '-', 'lower_is_better' => true),
            'sepsis_mortality' => array('label' => 'Sepsis Mortality', 'benchmark' => '-', 'lower_is_better' => true),
            'readmissions' => array('label' => 'Readmissions', 'benchmark' => '-', 'lower_is_better' => true),
        );
    }

    private function normalize_hacs_hais_rate_value($value, $event_count = null, $denominator_value = null) {
        if (is_numeric($event_count) && is_numeric($denominator_value) && (float) $denominator_value > 0) {
            return ((float) $event_count / (float) $denominator_value) * 100.0;
        }

        if (!is_numeric($value)) {
            return null;
        }
        $value = (float) $value;
        if (abs($value) > 100) {
            return $value / 100.0;
        }
        return abs($value) <= 1 ? $value * 100.0 : $value;
    }

    private function dashboard_output_definitions($definitions) {
        $out = is_array($definitions) ? $definitions : array();
        $mbqip_benchmarks = $this->mbqip_definition_benchmarks();
        $mbqip_directions = $this->mbqip_definition_directions();
        $mbqip_aliases = array_merge($this->mbqip_definition_aliases(), $this->mbqip_dashboard_aliases());

        foreach ($mbqip_aliases as $measure_key => $labels) {
            $base = array('benchmark' => '-');
            foreach ((array) $labels as $label) {
                if (isset($out[$label]) && is_array($out[$label])) {
                    $base = $out[$label];
                    break;
                }
            }
            $base['measure_key'] = (string) $measure_key;
            if (isset($mbqip_benchmarks[$measure_key])) {
                $base['benchmark'] = $mbqip_benchmarks[$measure_key];
            }
            if (isset($mbqip_directions[$measure_key])) {
                $base['direction'] = $mbqip_directions[$measure_key];
                $base['lower_is_better'] = $mbqip_directions[$measure_key] === 'lower';
            }
            foreach ((array) $labels as $label) {
                if (isset($out[$label]) && is_array($out[$label])) {
                    $out[$label]['measure_key'] = (string) $measure_key;
                    if (isset($mbqip_benchmarks[$measure_key])) {
                        $out[$label]['benchmark'] = $mbqip_benchmarks[$measure_key];
                    }
                    if (isset($mbqip_directions[$measure_key])) {
                        $out[$label]['direction'] = $mbqip_directions[$measure_key];
                        $out[$label]['lower_is_better'] = $mbqip_directions[$measure_key] === 'lower';
                    }
                } else {
                    $out[$label] = $base;
                }
            }
        }

        foreach ($this->hacs_hais_dashboard_definitions() as $measure_key => $def) {
            $label = (string) ($def['label'] ?? '');
            if ($label === '' || isset($out[$label])) {
                continue;
            }
            $out[$label] = array(
                'benchmark' => (string) ($def['benchmark'] ?? '<=0%'),
                'direction' => !empty($def['lower_is_better']) ? 'lower' : 'higher',
                'lower_is_better' => !empty($def['lower_is_better']),
                'measure_key' => (string) $measure_key,
            );
        }

        foreach ($out as $label => $def) {
            if (!is_array($def)) {
                continue;
            }
            $direction = strtolower((string) ($def['direction'] ?? '')) === 'lower' || !empty($def['lower_is_better'])
                ? 'lower'
                : 'higher';
            $out[$label]['direction'] = $direction;
            $out[$label]['lower_is_better'] = $direction === 'lower';
        }

        return $out;
    }

    private function mbqip_fallback_definition_directions() {
        return array(
            'cah_quality_infrastructure_assessment' => 'higher',
            'hcp_imm_3_healthcare_personnel_influenza_vaccination' => 'higher',
            'antibiotic_stewardship' => 'higher',
            'safe_use_of_opioids_ecqm_mbqip_submission' => 'lower',
            'edtc_emergency_department_transfer_communication' => 'higher',
            'op_18_median_ed_arrival_to_departure_time_discharged_patients' => 'lower',
            'op_22_patient_left_without_being_seen_lwbs_rate' => 'lower',
            'hcahps-composite-1-communication-with-nurses' => 'higher',
            'hcahps_composite_1_communication_with_nurses' => 'higher',
            'hcahps-composite-2-communication-with-doctors' => 'higher',
            'hcahps_composite_2_communication_with_doctors' => 'higher',
            'hcahps-composite-3-restfulness-of-hospital-environment' => 'higher',
            'hcahps_composite_3_restfulness_of_hospital_environment' => 'higher',
            'hcahps-composite-4-responsiveness-of-hospital-staff' => 'higher',
            'hcahps_composite_4_responsiveness_of_hospital_staff' => 'higher',
            'hcahps-composite-5-communication-about-medicines' => 'higher',
            'hcahps_composite_5_communication_about_medicines' => 'higher',
            'hcahps-composite-6-discharge-information-care-coordination' => 'higher',
            'hcahps_composite_6_discharge_information_care_coordination' => 'higher',
            'hcahps-composite-7-transitions-of-care' => 'higher',
            'hcahps_composite_7_transitions_of_care' => 'higher',
            'hcahps-q7-cleanliness-of-hospital-environment' => 'higher',
            'hcahps_q7_cleanliness_of_hospital_environment' => 'higher',
            'hcahps-q20-info-about-symptoms-to-watch-for-after-discharge' => 'higher',
            'hcahps_q20_info_about_symptoms_to_watch_for_after_discharge' => 'higher',
            'hcahps-q24-overall-rating-of-hospital-0-10' => 'higher',
            'hcahps_q24_overall_rating_of_hospital_0_10' => 'higher',
            'hcahps-q5-willingness-to-recommend-hospital' => 'higher',
            'hcahps_q5_willingness_to_recommend_hospital' => 'higher',
        );
    }

    private function mbqip_definition_directions() {
        global $wpdb;

        $directions = $this->mbqip_fallback_definition_directions();

        if (
            ! function_exists('qualinav_data_hub_mbqip_measure_definitions_table')
            || ! $this->table_exists(qualinav_data_hub_mbqip_measure_definitions_table())
        ) {
            return $directions;
        }

        $table = qualinav_data_hub_mbqip_measure_definitions_table();
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('direction', (array) $columns, true)) {
            return $directions;
        }

        $rows = $wpdb->get_results(
            "SELECT measure_key, direction
               FROM {$table}
              WHERE active = 1",
            ARRAY_A
        );

        foreach ((array) $rows as $row) {
            $measure_key = sanitize_key((string) ($row['measure_key'] ?? ''));
            if ($measure_key === '') {
                continue;
            }
            $direction = strtolower((string) ($row['direction'] ?? 'higher')) === 'lower' ? 'lower' : 'higher';
            $directions[$measure_key] = $direction;
            $directions[str_replace('-', '_', $measure_key)] = $direction;
        }

        return $directions;
    }

    private function mbqip_definition_aliases() {
        global $wpdb;

        if (
            ! function_exists('qualinav_data_hub_mbqip_measure_definitions_table')
            || ! $this->table_exists(qualinav_data_hub_mbqip_measure_definitions_table())
        ) {
            return array();
        }

        $table = qualinav_data_hub_mbqip_measure_definitions_table();
        $rows = $wpdb->get_results(
            "SELECT measure_key, measure_name
               FROM {$table}
              WHERE active = 1",
            ARRAY_A
        );

        $aliases = array();
        foreach ((array) $rows as $row) {
            $measure_key = sanitize_key((string) ($row['measure_key'] ?? ''));
            $measure_name = trim((string) ($row['measure_name'] ?? ''));
            if ($measure_key === '' || $measure_name === '') {
                continue;
            }
            $underscore_key = str_replace('-', '_', $measure_key);
            foreach (array($measure_key, $underscore_key) as $key) {
                if (!isset($aliases[$key])) {
                    $aliases[$key] = array();
                }
                $aliases[$key][] = $measure_name;
            }
        }

        foreach ($aliases as $key => $labels) {
            $aliases[$key] = array_values(array_unique(array_filter($labels)));
        }

        return $aliases;
    }

    private function mbqip_definition_benchmarks() {
        global $wpdb;

        $benchmarks = $this->mbqip_fallback_definition_benchmarks();

        if (
            ! function_exists('qualinav_data_hub_mbqip_measure_definitions_table')
            || ! $this->table_exists(qualinav_data_hub_mbqip_measure_definitions_table())
        ) {
            return $benchmarks;
        }

        $table = qualinav_data_hub_mbqip_measure_definitions_table();
        $rows = $wpdb->get_results(
            "SELECT measure_key, rate_label, benchmark_value
               FROM {$table}
              WHERE active = 1
                AND benchmark_value IS NOT NULL",
            ARRAY_A
        );

        foreach ((array) $rows as $row) {
            $measure_key = sanitize_key((string) ($row['measure_key'] ?? ''));
            if ($measure_key === '') {
                continue;
            }
            $value = isset($row['benchmark_value']) && is_numeric($row['benchmark_value'])
                ? (float) $row['benchmark_value']
                : null;
            if ($value === null) {
                continue;
            }
            $formatted = abs($value - round($value)) < 0.001
                ? (string) (int) round($value)
                : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
            $rate_label = strtolower((string) ($row['rate_label'] ?? ''));
            $benchmarks[$measure_key] = strpos($rate_label, 'minute') !== false
                ? $formatted . ' min'
                : $formatted . '%';
        }

        return $benchmarks;
    }

    private function mbqip_fallback_definition_benchmarks() {
        return array(
            'hcahps-composite-1-communication-with-nurses' => '85.6%',
            'hcahps_composite_1_communication_with_nurses' => '85.6%',
            'hcahps-composite-2-communication-with-doctors' => '85.9%',
            'hcahps_composite_2_communication_with_doctors' => '85.9%',
            'hcahps-composite-3-restfulness-of-hospital-environment' => '77.5%',
            'hcahps_composite_3_restfulness_of_hospital_environment' => '77.5%',
            'hcahps-composite-4-responsiveness-of-hospital-staff' => '77.2%',
            'hcahps_composite_4_responsiveness_of_hospital_staff' => '77.2%',
            'hcahps-composite-5-communication-about-medicines' => '70.1%',
            'hcahps_composite_5_communication_about_medicines' => '70.1%',
            'hcahps-composite-6-discharge-information-care-coordination' => '91.1%',
            'hcahps_composite_6_discharge_information_care_coordination' => '91.1%',
            'hcahps-composite-7-transitions-of-care' => '60.9%',
            'hcahps_composite_7_transitions_of_care' => '60.9%',
            'hcahps-q7-cleanliness-of-hospital-environment' => '77.5%',
            'hcahps_q7_cleanliness_of_hospital_environment' => '77.5%',
            'hcahps-q24-overall-rating-of-hospital-0-10' => '83.2%',
            'hcahps_q24_overall_rating_of_hospital_0_10' => '83.2%',
        );
    }

    private function add_dashboard_observation(&$groups, $bucket, $meta, $metric_label, $benchmark, $lower_is_better, $minutes_mode, $observation) {
        if ($bucket === '' || $metric_label === '' || !is_array($observation)) {
            return;
        }
        if (!isset($groups[$bucket])) {
            $groups[$bucket] = array('__meta' => $meta);
        }
        if (!isset($groups[$bucket][$metric_label])) {
            $groups[$bucket][$metric_label] = array(
                'benchmark'       => $benchmark,
                'lower_is_better' => $lower_is_better,
                'minutes_mode'    => $minutes_mode,
                'observations'    => array(),
            );
        }
        $groups[$bucket][$metric_label]['observations'][] = $observation;
    }

    private function get_canonical_dashboard_metric_records($definitions, $filters, $org_profiles) {
        global $wpdb;

        $groups = array();
        $definition_meta = array();
        foreach ((array) $definitions as $label => $def) {
            $direction = strtolower((string) ($def['direction'] ?? 'higher')) === 'lower' ? 'lower' : 'higher';
            $definition_meta[$label] = array(
                'benchmark'       => (string) ($def['benchmark'] ?? '>=75%'),
                'direction'       => $direction,
                'lower_is_better' => $direction === 'lower' || !empty($def['lower_is_better']),
                'minutes_mode'    => !empty($def['minutes_mode']),
            );
        }

        if (function_exists('qualinav_data_hub_mbqip_submissions_table') && function_exists('qualinav_data_hub_mbqip_submission_values_table')) {
            $submissions_table = qualinav_data_hub_mbqip_submissions_table();
            $values_table = qualinav_data_hub_mbqip_submission_values_table();
            if ($this->table_exists($submissions_table) && $this->table_exists($values_table)) {
                $rows = $wpdb->get_results(
                    "SELECT s.id, s.organization_id, s.user_id, s.measure_key, s.measure_name, s.reporting_year, s.reporting_period, s.date_reported,
                            v.field_key, v.field_value_numeric, v.row_index
                       FROM {$submissions_table} s
                       INNER JOIN {$values_table} v ON v.submission_id = s.id
                      WHERE s.status = 'active'
                        AND s.is_current = 1
                        AND v.field_value_numeric IS NOT NULL
                      ORDER BY s.organization_id ASC, s.user_id ASC, s.measure_key ASC, s.reporting_year ASC, s.reporting_period ASC, v.row_index ASC",
                    ARRAY_A
                );

                $aliases = array_merge($this->mbqip_definition_aliases(), $this->mbqip_dashboard_aliases());
                foreach ($aliases as $alias_key => $alias_labels) {
                    $aliases[str_replace('_', '-', (string) $alias_key)] = $alias_labels;
                }
                $criteria_rate_groups = array();
                foreach ((array) $rows as $row) {
                    $field_key = strtolower((string) ($row['field_key'] ?? ''));
                    if (
                        strpos($field_key, 'criteria') === false
                        && strpos($field_key, 'met') === false
                        && strpos($field_key, 'rate') === false
                    ) {
                        continue;
                    }
                    $value = isset($row['field_value_numeric']) && is_numeric($row['field_value_numeric'])
                        ? (float) $row['field_value_numeric']
                        : null;
                    if ($value === null) {
                        continue;
                    }
                    $group_key = implode('|', array(
                        (string) ($row['id'] ?? ''),
                        (string) ($row['measure_key'] ?? ''),
                        (string) ($row['reporting_year'] ?? ''),
                        (string) ($row['reporting_period'] ?? ''),
                        (string) ($row['row_index'] ?? '0'),
                    ));
                    if (!isset($criteria_rate_groups[$group_key])) {
                        $criteria_rate_groups[$group_key] = array(
                            'row' => $row,
                            'rate' => null,
                            'criteria_met' => null,
                            'criteria_count' => null,
                            'boolean_met' => 0.0,
                            'boolean_count' => 0,
                        );
                    }
                    if (strpos($field_key, 'rate') !== false) {
                        $criteria_rate_groups[$group_key]['rate'] = $value;
                    } elseif (strpos($field_key, 'count') !== false || strpos($field_key, 'total') !== false) {
                        $criteria_rate_groups[$group_key]['criteria_count'] = $value;
                    } elseif (strpos($field_key, 'criteria_met') !== false || strpos($field_key, 'criteria met') !== false) {
                        $criteria_rate_groups[$group_key]['criteria_met'] = $value;
                    } elseif (strpos($field_key, 'met') !== false) {
                        $criteria_rate_groups[$group_key]['boolean_met'] += $value > 0 ? 1.0 : 0.0;
                        $criteria_rate_groups[$group_key]['boolean_count'] += 1;
                    }
                }

                $handled_submissions = array();
                foreach ($criteria_rate_groups as $group_key => $group) {
                    $rate = null;
                    $num_value = null;
                    $denom_value = null;
                    if ($group['rate'] !== null) {
                        $rate = (float) $group['rate'];
                    } elseif ($group['criteria_met'] !== null && $group['criteria_count'] !== null && (float) $group['criteria_count'] > 0) {
                        $num_value = (float) $group['criteria_met'];
                        $denom_value = (float) $group['criteria_count'];
                        $rate = ($num_value / $denom_value) * 100.0;
                    } elseif (!empty($group['boolean_count'])) {
                        $num_value = (float) $group['boolean_met'];
                        $denom_value = (float) $group['boolean_count'];
                        $rate = ($num_value / $denom_value) * 100.0;
                    }
                    if ($rate === null) {
                        continue;
                    }
                    $row = $group['row'];
                    $org_id = (int) ($row['organization_id'] ?? 0);
                    $user_id = (int) ($row['user_id'] ?? 0);
                    $bucket = $org_id > 0 ? ('org-' . $org_id) : ($user_id > 0 ? ('user-' . $user_id) : '');
                    if ($bucket === '') {
                        continue;
                    }
                    $profile = $org_id > 0 && isset($org_profiles[$org_id]) ? $org_profiles[$org_id] : array();
                    $meta = array(
                        'state' => (string) ($profile['state'] ?? ''),
                        'state_id' => isset($profile['state_id']) ? (int) $profile['state_id'] : 0,
                        'label' => (string) ($profile['label'] ?? ''),
                        'hospital_type' => (string) ($profile['hospital_type'] ?? 'unknown'),
                        'bed_bucket' => (string) ($profile['bed_bucket'] ?? 'unknown'),
                    );
                    $measure_key = (string) ($row['measure_key'] ?? '');
                    $alias_key = str_replace('-', '_', $measure_key);
                    $labels = isset($aliases[$measure_key]) ? $aliases[$measure_key] : ($aliases[$alias_key] ?? array((string) ($row['measure_name'] ?? $measure_key)));
                    $ts = $this->dashboard_period_timestamp($row['reporting_year'] ?? 0, $row['reporting_period'] ?? '', '');
                    if ($ts === null) {
                        $ts = $this->dashboard_period_timestamp($row['reporting_year'] ?? 0, $row['reporting_period'] ?? '', $row['date_reported'] ?? '');
                    }
                    foreach ($labels as $label) {
                        $meta_for_label = $definition_meta[$label] ?? array('benchmark' => '>=75%', 'lower_is_better' => false, 'minutes_mode' => false);
                        $this->add_dashboard_observation(
                            $groups,
                            $bucket,
                            $meta,
                            $label,
                            $meta_for_label['benchmark'],
                            $meta_for_label['lower_is_better'],
                            $meta_for_label['minutes_mode'],
                            array(
                                'raw_value' => $rate,
                                'ts' => $ts,
                                'year' => (int) ($row['reporting_year'] ?? 0),
                                'period' => (string) ($row['reporting_period'] ?? ''),
                                'row_index' => (int) ($row['row_index'] ?? 0),
                                'num_value' => $num_value,
                                'denom_value' => $denom_value,
                            )
                        );
                    }
                    $handled_submissions[implode('|', array_slice(explode('|', $group_key), 0, 4))] = true;
                }

                $best_values = array();
                foreach ((array) $rows as $row) {
                    $field_key = strtolower((string) ($row['field_key'] ?? ''));
                    $score = 0;
                    if (strpos($field_key, 'rate') !== false || strpos($field_key, 'median') !== false || strpos($field_key, 'score') !== false) {
                        $score = 3;
                    } elseif (strpos($field_key, 'met') !== false || strpos($field_key, 'value') !== false) {
                        $score = 2;
                    } elseif (strpos($field_key, 'num') === false && strpos($field_key, 'den') === false) {
                        $score = 1;
                    }
                    if ($score <= 0) {
                        continue;
                    }
                    $key = implode('|', array(
                        (string) ($row['id'] ?? ''),
                        (string) ($row['measure_key'] ?? ''),
                        (string) ($row['reporting_year'] ?? ''),
                        (string) ($row['reporting_period'] ?? ''),
                        (string) ($row['row_index'] ?? '0'),
                    ));
                    $submission_key = implode('|', array(
                        (string) ($row['id'] ?? ''),
                        (string) ($row['measure_key'] ?? ''),
                        (string) ($row['reporting_year'] ?? ''),
                        (string) ($row['reporting_period'] ?? ''),
                    ));
                    if (isset($handled_submissions[$submission_key])) {
                        continue;
                    }
                    if (!isset($best_values[$key]) || $score > (int) $best_values[$key]['score']) {
                        $best_values[$key] = array('score' => $score, 'row' => $row);
                    }
                }

                foreach ($best_values as $entry) {
                    $row = $entry['row'];
                    $org_id = (int) ($row['organization_id'] ?? 0);
                    $user_id = (int) ($row['user_id'] ?? 0);
                    $bucket = $org_id > 0 ? ('org-' . $org_id) : ($user_id > 0 ? ('user-' . $user_id) : '');
                    if ($bucket === '') {
                        continue;
                    }
                    $profile = $org_id > 0 && isset($org_profiles[$org_id]) ? $org_profiles[$org_id] : array();
                    $meta = array(
                        'state' => (string) ($profile['state'] ?? ''),
                        'state_id' => isset($profile['state_id']) ? (int) $profile['state_id'] : 0,
                        'label' => (string) ($profile['label'] ?? ''),
                        'hospital_type' => (string) ($profile['hospital_type'] ?? 'unknown'),
                        'bed_bucket' => (string) ($profile['bed_bucket'] ?? 'unknown'),
                    );
                    $measure_key = (string) ($row['measure_key'] ?? '');
                    $alias_key = str_replace('-', '_', $measure_key);
                    $labels = isset($aliases[$measure_key]) ? $aliases[$measure_key] : ($aliases[$alias_key] ?? array((string) ($row['measure_name'] ?? $measure_key)));
                    $ts = $this->dashboard_period_timestamp($row['reporting_year'] ?? 0, $row['reporting_period'] ?? '', '');
                    if ($ts === null) {
                        $ts = $this->dashboard_period_timestamp($row['reporting_year'] ?? 0, $row['reporting_period'] ?? '', $row['date_reported'] ?? '');
                    }
                    foreach ($labels as $label) {
                        $meta_for_label = $definition_meta[$label] ?? array('benchmark' => '>=75%', 'lower_is_better' => false, 'minutes_mode' => false);
                        $this->add_dashboard_observation(
                            $groups,
                            $bucket,
                            $meta,
                            $label,
                            $meta_for_label['benchmark'],
                            $meta_for_label['lower_is_better'],
                            $meta_for_label['minutes_mode'],
                            array(
                                'raw_value' => (float) $row['field_value_numeric'],
                                'ts' => $ts,
                                'year' => (int) ($row['reporting_year'] ?? 0),
                                'period' => (string) ($row['reporting_period'] ?? ''),
                                'row_index' => (int) ($row['row_index'] ?? 0),
                            )
                        );
                    }
                }
            }
        }

        if (function_exists('qualinav_data_hub_improvement_submissions_table') && function_exists('qualinav_data_hub_improvement_values_table')) {
            $submissions_table = qualinav_data_hub_improvement_submissions_table();
            $values_table = qualinav_data_hub_improvement_values_table();
            if ($this->table_exists($submissions_table) && $this->table_exists($values_table)) {
                $rows = $wpdb->get_results(
                    "SELECT s.id, s.organization_id, s.user_id, s.reporting_year, s.reference_date,
                            v.month_num, v.measure_key, v.event_count, v.denominator_value, v.rate_value
                       FROM {$submissions_table} s
                       INNER JOIN {$values_table} v ON v.submission_id = s.id
                      WHERE s.status = 'active'
                        AND s.is_current = 1
                        AND v.rate_value IS NOT NULL
                      ORDER BY s.organization_id ASC, s.user_id ASC, v.measure_key ASC, s.reporting_year ASC, v.month_num ASC",
                    ARRAY_A
                );

                $hacs_defs = $this->hacs_hais_dashboard_definitions();
                $hacs_user_org_ids = array();
                foreach ((array) $rows as $row) {
                    $year = (int) ($row['reporting_year'] ?? 0);
                    $measure_key = (string) ($row['measure_key'] ?? '');
                    if (!isset($hacs_defs[$measure_key])) {
                        continue;
                    }
                    $org_id = (int) ($row['organization_id'] ?? 0);
                    $user_id = (int) ($row['user_id'] ?? 0);
                    if ($org_id <= 0 && $user_id > 0) {
                        if (!array_key_exists($user_id, $hacs_user_org_ids)) {
                            $org_id_lookup = $wpdb->get_var($wpdb->prepare(
                                "SELECT organization_id FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
                                $user_id
                            ));
                            $hacs_user_org_ids[$user_id] = ($org_id_lookup !== null && (int) $org_id_lookup > 0) ? (int) $org_id_lookup : 0;
                        }
                        $org_id = (int) $hacs_user_org_ids[$user_id];
                    }
                    $bucket = $org_id > 0 ? ('org-' . $org_id) : ($user_id > 0 ? ('user-' . $user_id) : '');
                    if ($bucket === '') {
                        continue;
                    }
                    $profile = $org_id > 0 && isset($org_profiles[$org_id]) ? $org_profiles[$org_id] : array();
                    $meta = array(
                        'state' => (string) ($profile['state'] ?? ''),
                        'state_id' => isset($profile['state_id']) ? (int) $profile['state_id'] : 0,
                        'label' => (string) ($profile['label'] ?? ''),
                        'hospital_type' => (string) ($profile['hospital_type'] ?? 'unknown'),
                        'bed_bucket' => (string) ($profile['bed_bucket'] ?? 'unknown'),
                    );
                    $month = max(1, min(12, (int) ($row['month_num'] ?? 12)));
                    $ts = strtotime(sprintf('%04d-%02d-01', $year, $month));
                    $def = $hacs_defs[$measure_key];
                    $rate_value = $this->normalize_hacs_hais_rate_value(
                        $row['rate_value'],
                        $row['event_count'] ?? null,
                        $row['denominator_value'] ?? null
                    );
                    if ($rate_value === null) {
                        continue;
                    }
                    $this->add_dashboard_observation(
                        $groups,
                        $bucket,
                        $meta,
                        (string) $def['label'],
                        (string) $def['benchmark'],
                        !empty($def['lower_is_better']),
                        false,
                        array(
                            'raw_value' => $rate_value,
                            'ts' => $ts,
                            'year' => $year,
                        )
                    );
                }
            }
        }

        $records = array();
        foreach ($groups as $bucket => $metric_groups) {
            $records[$bucket] = array('__meta' => $metric_groups['__meta'] ?? array());
            foreach ($metric_groups as $metric_label => $group) {
                if ($metric_label === '__meta' || !is_array($group)) {
                    continue;
                }
                $record = $this->build_dashboard_record_from_observations(
                    $group['observations'] ?? array(),
                    (string) ($group['benchmark'] ?? ''),
                    !empty($group['lower_is_better']),
                    !empty($group['minutes_mode']),
                    (int) ($filters['year'] ?? 0)
                );
                if ($record) {
                    $records[$bucket][$metric_label] = $record;
                }
            }
        }

        return $records;
    }

    private function merge_dashboard_metric_records($legacy_records, $canonical_records) {
        $merged = is_array($legacy_records) ? $legacy_records : array();
        foreach ((array) $canonical_records as $bucket => $metrics) {
            if (!isset($merged[$bucket]) || !is_array($merged[$bucket])) {
                $merged[$bucket] = array();
            }
            if (isset($metrics['__meta']) && is_array($metrics['__meta'])) {
                $merged[$bucket]['__meta'] = array_merge($merged[$bucket]['__meta'] ?? array(), $metrics['__meta']);
            }
            foreach ($metrics as $metric_label => $record) {
                if ($metric_label === '__meta') {
                    continue;
                }
                $merged[$bucket][$metric_label] = $record;
            }
        }
        return $merged;
    }

    private function extract_metric_record_from_rows($rows, $lower_is_better = false, $minutes_mode = false, $metric_label = null) {
        $series = $this->extract_metric_observations_from_rows($rows, $lower_is_better, $minutes_mode, $metric_label);
        if (!$series) return null;
        return $this->build_current_record_from_observations($series, $lower_is_better, $minutes_mode);
    }

    private function build_live_metrics_payload() {
        global $wpdb;
        list($org_key) = $this->get_current_org_context();
        $filters = $this->dashboard_report_filters_from_request();
        $cache_key = 'qaqd_live_metrics_' . $org_key . '_' . md5(wp_json_encode($filters));

        // Reconcile BEFORE the cache check. Phantom rows (files deleted but
        // rows stranded under a mismatched org_id/user_id) must be cleaned
        // even on a cached hit, otherwise the stale payload keeps being
        // served. If anything was removed, drop the cached payload so it
        // regenerates from the corrected table.
        $this->ensure_metric_data_table();
        $this->reconcile_metric_data_orphans();
        $this->backfill_current_org_mbqip_submissions($org_key);
        $this->sync_metric_data_table($org_key);
        delete_transient($cache_key);

        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['metrics']) && is_array($cached['metrics'])) {
            return $cached;
        }

        $definitions = $this->get_live_metric_definitions();
        $dashboard_definitions = $this->dashboard_output_definitions($definitions);
        $table_name = $this->get_metric_data_table_name();
        $row_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $current_user_id = (int) get_current_user_id();
        if ($row_count === 0) {
            $this->sync_metric_data_table();
        } else {
            $current_org_rows = $current_user_id > 0 ? (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", $current_user_id)
            ) : 0;
            if ($current_org_rows === 0 && get_option('dm_org_folder_files_' . $org_key, null) !== null) {
                $this->sync_metric_data_table($org_key);
            }
        }

        $org_directory = $this->get_org_directory();
        $org_profiles = $this->get_organization_profiles();
        $legacy_metric_records = $this->get_all_org_metric_records_from_table($dashboard_definitions);
        $canonical_metric_records = $this->get_canonical_dashboard_metric_records($dashboard_definitions, $filters, $org_profiles);
        $all_org_metric_records = $this->merge_dashboard_metric_records($legacy_metric_records, $canonical_metric_records);

        // Resolve the viewer's bucket using the SAME identity the writer uses
        // (wp_users.organization_id). Previously this read from wp_qi_orgs by
        // slug, which produced a different id than the one stored on the row
        // and caused uploaded data to never show up on the dashboard.
        $current_org_id = null;
        if ($current_user_id > 0) {
            $org_id_lookup = $wpdb->get_var($wpdb->prepare(
                "SELECT organization_id FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
                $current_user_id
            ));
            if ($org_id_lookup !== null && (int) $org_id_lookup > 0) {
                $current_org_id = (int) $org_id_lookup;
            }
        }
        $current_bucket = $current_org_id ? ('org-' . $current_org_id) : ($current_user_id > 0 ? ('user-' . $current_user_id) : $org_key);

        if (!isset($all_org_metric_records[$current_bucket])) {
            $all_org_metric_records[$current_bucket] = array('__meta' => array('state' => (string) ($org_directory[$org_key]['state'] ?? '')));
        }
        if ($current_org_id && isset($org_profiles[$current_org_id])) {
            $all_org_metric_records[$current_bucket]['__meta'] = array_merge(
                $all_org_metric_records[$current_bucket]['__meta'] ?? array(),
                $org_profiles[$current_org_id]
            );
        }
        $filtered_metric_records = $this->filter_metric_records_by_dashboard_filters($all_org_metric_records, $filters, $org_profiles, $current_bucket);
        $current_org_state = (string) ($filtered_metric_records[$current_bucket]['__meta']['state'] ?? ($org_directory[$org_key]['state'] ?? ''));
        $current_org_state_id = isset($filtered_metric_records[$current_bucket]['__meta']['state_id']) ? (int) $filtered_metric_records[$current_bucket]['__meta']['state_id'] : 0;
        $comparisons = $this->calculate_org_comparisons($current_bucket, $current_org_state_id, $current_org_state, $dashboard_definitions, $filtered_metric_records);
        $distributions = $this->calculate_benchmark_distributions($dashboard_definitions, $filtered_metric_records);

        $metrics = array();
        foreach ($dashboard_definitions as $metric_name => $def) {
            $record = $all_org_metric_records[$current_bucket][$metric_name] ?? null;
            $comparison = $comparisons[$metric_name] ?? array(
                'national_comparison' => 'Not uploaded',
                'state_comparison' => 'Not uploaded',
            );
            $distribution = $distributions[$metric_name] ?? array(
                'reporting_orgs' => 0,
                'below_count' => 0,
                'near_count' => 0,
                'above_count' => 0,
            );

            if (is_array($record) && isset($record['numeric_value']) && $record['numeric_value'] !== null) {
                $metrics[$metric_name] = array(
                    'value' => $record['value'],
                    'benchmark' => (string) ($def['benchmark'] ?? '>=75%'),
                    'status' => $record['status'],
                    'trend' => $record['trend'],
                    'measure_key' => (string) ($def['measure_key'] ?? ''),
                    'direction' => strtolower((string) ($def['direction'] ?? '')) === 'lower' || !empty($def['lower_is_better']) ? 'lower' : 'higher',
                    'lower_is_better' => strtolower((string) ($def['direction'] ?? '')) === 'lower' || !empty($def['lower_is_better']),
                    'days_between' => isset($record['days_between']) ? $record['days_between'] : null,
                    'record_count' => isset($record['record_count']) ? (int) $record['record_count'] : 0,
                    'series' => isset($record['series']) && is_array($record['series']) ? $record['series'] : array(),
                    'national_comparison' => $comparison['national_comparison'],
                    'state_comparison' => $comparison['state_comparison'],
                    'reporting_orgs' => (int) $distribution['reporting_orgs'],
                    'below_count' => (int) $distribution['below_count'],
                    'near_count' => (int) $distribution['near_count'],
                    'above_count' => (int) $distribution['above_count'],
                );
            } else {
                $metrics[$metric_name] = array(
                    'value' => '-',
                    'benchmark' => (string) ($def['benchmark'] ?? '>=75%'),
                    'status' => '',
                    'trend' => '',
                    'measure_key' => (string) ($def['measure_key'] ?? ''),
                    'direction' => strtolower((string) ($def['direction'] ?? '')) === 'lower' || !empty($def['lower_is_better']) ? 'lower' : 'higher',
                    'lower_is_better' => strtolower((string) ($def['direction'] ?? '')) === 'lower' || !empty($def['lower_is_better']),
                    'days_between' => null,
                    'record_count' => 0,
                    'national_comparison' => 'Not uploaded',
                    'state_comparison' => 'Not uploaded',
                    'reporting_orgs' => (int) $distribution['reporting_orgs'],
                    'below_count' => (int) $distribution['below_count'],
                    'near_count' => (int) $distribution['near_count'],
                    'above_count' => (int) $distribution['above_count'],
                );
            }
        }

        $payload = array(
            'generated_from' => !empty($canonical_metric_records) ? 'canonical_data_hub_tables' : 'dm_org_folder_files_' . $org_key,
            'filters' => $filters,
            'filter_options' => $this->dashboard_filter_options($org_profiles),
            'metrics' => $metrics,
            'measure_goals' => $this->dashboard_measure_goals(),
        );
        set_transient($cache_key, $payload, 60);
        return $payload;
    }

    private function backfill_current_org_mbqip_submissions($org_key) {
        if (
            ! function_exists('qualinav_data_hub_dm_sync_mbqip_submission_rows')
            || ! function_exists('qualinav_data_hub_get_org_context')
        ) {
            return;
        }

        $org_key = sanitize_title((string) $org_key);
        if ($org_key === '') {
            return;
        }

        $folder_files = get_option('dm_org_folder_files_' . $org_key, array());
        if (!is_array($folder_files) || empty($folder_files)) {
            return;
        }

        $context = qualinav_data_hub_get_org_context(get_current_user_id());
        $upload_dir = wp_upload_dir();
        $base_url = trailingslashit((string) ($upload_dir['baseurl'] ?? '')) . 'qualinav-dm/' . $org_key . '/';

        foreach ($folder_files as $folder_id => $records) {
            if (!is_array($records)) {
                continue;
            }
            foreach ($records as $record) {
                if (!is_array($record) || !empty($record['archived'])) {
                    continue;
                }
                $raw_rows = !empty($record['raw_rows']) && is_array($record['raw_rows']) ? $record['raw_rows'] : array();
                $measure = sanitize_text_field((string) ($record['measure'] ?? ''));
                $file_name = sanitize_file_name((string) ($record['name'] ?? ''));
                if ($measure === '' || $file_name === '' || empty($raw_rows)) {
                    continue;
                }
                qualinav_data_hub_dm_sync_mbqip_submission_rows(array(
                    'user_id'       => get_current_user_id(),
                    'org_context'   => $context,
                    'folder_id'     => sanitize_key((string) $folder_id),
                    'measure'       => $measure,
                    'template_type' => sanitize_key((string) ($record['template_type'] ?? '')),
                    'raw_rows'      => $raw_rows,
                    'source_type'   => sanitize_key((string) ($record['source'] ?? 'upload')),
                    'file_name'     => $file_name,
                    'file_url'      => esc_url_raw((string) ($record['url'] ?? ($base_url . $file_name))),
                ));
            }
        }
    }

    private function purge_all_live_metrics_caches($org_key) {
        if ($org_key === '') return;
        $this->sync_metric_data_table($org_key);
        delete_transient('qaqd_live_metrics_' . $org_key);
        global $wpdb;
        $like = $wpdb->esc_like('_transient_qaqd_live_metrics_') . '%';
        $transient_options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
        foreach ((array) $transient_options as $transient_option) {
            $transient_key = str_replace('_transient_', '', (string) $transient_option);
            if ($transient_key !== '') {
                delete_transient($transient_key);
            }
        }
    }

    public function invalidate_live_metrics_cache_on_option_update($option, $old_value, $value) {
        if (!is_string($option)) return;
        if (strpos($option, 'dm_org_folder_files_') !== 0) return;
        $org_key = substr($option, strlen('dm_org_folder_files_'));
        $this->purge_all_live_metrics_caches($org_key);
    }

    public function invalidate_live_metrics_cache_on_option_add($option, $value) {
        if (!is_string($option)) return;
        if (strpos($option, 'dm_org_folder_files_') !== 0) return;
        $org_key = substr($option, strlen('dm_org_folder_files_'));
        $this->purge_all_live_metrics_caches($org_key);
    }

    public function live_metrics_handler() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'), 403);
            return;
        }
        $nonce = sanitize_text_field((string) ($_GET['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'qaqd_live_metrics')) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
            return;
        }
        wp_send_json($this->build_live_metrics_payload());
    }

    public function render_dashboard_shortcode($atts = array()) {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            return '<div class="qaqd-login-required"><a href="' . esc_url($login_url) . '">' . esc_html__('Log in to view the dashboard.', 'quainav-qapi-dasboard') . '</a></div>';
        }

        $this->enqueue_dashboard_assets();

        $partial = QUAINAV_QAPI_DASBOARD_PLUGIN_DIR . 'templates/partials/dashboard-content.php';
        if (!file_exists($partial)) {
            return '<div class="qaqd-missing-template">' . esc_html__('Dashboard template is missing.', 'quainav-qapi-dasboard') . '</div>';
        }

        ob_start();
        include $partial;
        return ob_get_clean();
    }

    public function render_data_management_shortcode($atts = array()) {
        if (!is_user_logged_in()) {
            return '';
        }

        $this->enqueue_dashboard_assets();

        $user_id = get_current_user_id();
        $scope = 'org';
        $scope_label = 'Organization Data';

        $report_query = array(
            'post_type'      => 'qd_report',
            'post_status'    => 'publish',
            'posts_per_page' => 300,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $reports = get_posts($report_query);
        $type_counts = array(
            'all'        => 0,
            'board'      => 0,
            'committee'  => 0,
            'dashboard'  => 0,
            'qapi'       => 0,
            'assessment' => 0,
            'other'      => 0,
        );
        $report_rows = array();

        foreach ($reports as $report) {
            $report_id = (int) $report->ID;
            $report_type = sanitize_key((string) get_post_meta($report_id, '_qd_report_type', true));
            $report_label = (string) get_post_meta($report_id, '_qd_report_label', true);
            $report_date = (string) get_post_meta($report_id, '_qd_report_date', true);
            $metrics_json = (string) get_post_meta($report_id, '_qd_report_metrics', true);
            $metrics = json_decode($metrics_json, true);

            if (!is_array($metrics)) {
                $metrics = array();
            }

            $normalized_type = in_array($report_type, array('board', 'committee', 'dashboard', 'qapi', 'assessment'), true)
                ? $report_type
                : 'other';

            $type_counts['all']++;
            $type_counts[$normalized_type]++;

            $green = 0;
            $yellow = 0;
            $red = 0;
            $metrics_count = 0;

            if ($normalized_type === 'assessment') {
                $section_scores = isset($metrics['section_scores']) && is_array($metrics['section_scores']) ? $metrics['section_scores'] : array();
                $metrics_count = count($section_scores);
            } else {
                $metrics_count = count($metrics);
                foreach ($metrics as $metric_row) {
                    if (!is_array($metric_row)) {
                        continue;
                    }
                    $status = strtolower((string) ($metric_row['status'] ?? ''));
                    if ($status === 'green' || $status === 'passing') {
                        $green++;
                    } elseif ($status === 'yellow' || $status === 'warning') {
                        $yellow++;
                    } elseif ($status === 'red' || $status === 'danger') {
                        $red++;
                    }
                }
            }

            $report_rows[] = array(
                'id'            => $report_id,
                'type'          => $normalized_type,
                'type_label'    => ucfirst($normalized_type),
                'title'         => (string) ($report->post_title ?: 'Untitled Report'),
                'label'         => (string) ($report_label ?: $report->post_title),
                'report_date'   => $report_date,
                'created'       => get_the_date('M j, Y g:i A', $report),
                'author'        => (string) get_the_author_meta('display_name', (int) $report->post_author),
                'metrics_count' => $metrics_count,
                'green'         => $green,
                'yellow'        => $yellow,
                'red'           => $red,
                'metrics'       => $metrics,
            );
        }

        $chart_count = count(get_posts(array(
            'post_type'      => 'dttc_chart',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'fields'         => 'ids',
        )));
        $qi_count = count(get_posts(array(
            'post_type'      => 'qi_project',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'fields'         => 'ids',
        )));
        $assessment_count = (int) $type_counts['assessment'];
        $qapi_reports_count = (int) ($type_counts['board'] + $type_counts['committee'] + $type_counts['dashboard'] + $type_counts['qapi']);
        $org_name = trim((string) get_user_meta($user_id, 'organization', true));
        if ($org_name === '') {
            $org_name = 'User ' . $user_id;
        }
        $org_key = sanitize_title($org_name);
        if ($org_key === '') {
            $org_key = 'user-' . $user_id;
        }
        if ($org_key === '') {
            $org_key = 'org-' . $user_id;
        }
        $folder_files_option_key = 'dm_org_folder_files_' . $org_key;
        $folder_files = get_option($folder_files_option_key, array());
        if (!is_array($folder_files)) {
            $folder_files = array();
        }

        $partial = QUAINAV_QAPI_DASBOARD_PLUGIN_DIR . 'templates/partials/data-management-content.php';
        if (!file_exists($partial)) {
            return '<div class="qaqd-missing-template">' . esc_html__('Data Management template is missing.', 'quainav-qapi-dasboard') . '</div>';
        }

        return include $partial;
    }

    public function register_admin_page() {
        add_menu_page(
            __('QAPI Dashboard', 'quainav-qapi-dasboard'),
            __('QAPI Dashboard', 'quainav-qapi-dasboard'),
            'manage_options',
            'qaqd-dashboard-help',
            array($this, 'render_admin_page'),
            'dashicons-chart-line',
            59
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'hub';
        if (!in_array($tab, array('hub', 'design', 'data', 'health'), true)) {
            $tab = 'hub';
        }
        $theme_defaults = $this->get_theme_palette_defaults();
        ?>
        <div class="wrap">
            <style>
                .qaqd-admin-tabs{display:flex;gap:8px;margin:0 0 16px;padding:0;border-bottom:1px solid #dbe4ef}
                .qaqd-admin-tabs a{display:inline-flex;align-items:center;padding:10px 14px;border:1px solid #dbe4ef;border-bottom:none;border-radius:8px 8px 0 0;background:#f6f8fb;color:#17324d;text-decoration:none;font-weight:600}
                .qaqd-admin-tabs a.is-active{background:#fff;color:#0b3a53}
                .qaqd-admin-panel{background:#fff;border:1px solid #dbe4ef;border-radius:0 10px 10px 10px;padding:18px}
                .qaqd-admin-grid{display:grid;grid-template-columns:190px minmax(0,1fr) 360px;gap:16px;align-items:start}
                .qaqd-admin-nav{position:sticky;top:64px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px;display:grid;gap:6px}
                .qaqd-admin-nav a{display:block;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#1d3557;text-decoration:none;font-weight:600}
                .qaqd-admin-section{scroll-margin-top:80px;padding-top:4px}
                .qaqd-admin-section + .qaqd-admin-section{margin-top:24px}
                .qaqd-admin-section h3{margin:0 0 4px}
                .qaqd-admin-section p{margin:0 0 12px;color:#526072}
                .qaqd-admin-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
                .qaqd-admin-field{display:grid;gap:6px}
                .qaqd-admin-field label{font-weight:600}
                .qaqd-admin-field input[type=color]{width:72px;height:38px;padding:2px}
                .qaqd-admin-preview{position:sticky;top:64px;border:1px solid #dbe4ef;padding:14px;border-radius:10px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04)}
                .qaqd-admin-preview-card{border:1px solid #dbe4ef;border-radius:12px;background:#f8fafc;padding:14px}
                .qaqd-admin-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
                .qaqd-admin-kpi{border:1px solid #dbe4ef;border-radius:10px;background:#fff;padding:14px}
                .qaqd-admin-kpi strong{display:block;font-size:24px;color:#0f2740}
                .qaqd-admin-code{background:#fff;border:1px solid #dcdcde;padding:12px;max-width:900px;overflow:auto}
                @media (max-width: 1280px){.qaqd-admin-grid{grid-template-columns:1fr}.qaqd-admin-nav,.qaqd-admin-preview{position:static}}
            </style>
            <h1><?php esc_html_e('QualiNav QAPI Dashboard', 'quainav-qapi-dasboard'); ?></h1>
            <p><?php esc_html_e('Configure dashboard presentation, Data Management help, and runtime health from one admin screen.', 'quainav-qapi-dasboard'); ?></p>
            <nav class="qaqd-admin-tabs">
                <?php
                $tabs = array(
                    'hub'    => __('Hub & Shortcodes', 'quainav-qapi-dasboard'),
                    'design' => __('Design', 'quainav-qapi-dasboard'),
                    'data'   => __('Data Management', 'quainav-qapi-dasboard'),
                    'health' => __('Health', 'quainav-qapi-dasboard'),
                );
                foreach ($tabs as $key => $label) :
                    $url = admin_url('admin.php?page=qaqd-dashboard-help&tab=' . $key);
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo $tab === $key ? 'is-active' : ''; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="qaqd-admin-panel">
                <?php
                switch ($tab) {
                    case 'design':
                        $this->render_admin_design_tab($theme_defaults);
                        break;
                    case 'data':
                        $this->render_admin_data_tab();
                        break;
                    case 'health':
                        $this->render_admin_health_tab();
                        break;
                    case 'hub':
                    default:
                        $this->render_admin_hub_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
        return;
        $saved_run_chart_url = esc_url(get_option('qaqd_run_chart_url', home_url('/run-chart/')));
        $design = $this->get_design_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('QualiNav QAPI Dashboard - Admin Guide', 'quainav-qapi-dasboard'); ?></h1>
            <p><?php esc_html_e('Use this page to configure where and how to display the dashboard.', 'quainav-qapi-dasboard'); ?></p>

            <h2><?php esc_html_e('Run Chart Button URL', 'quainav-qapi-dasboard'); ?></h2>
            <form method="post" action="options.php" style="max-width: 900px; background: #fff; border: 1px solid #dcdcde; padding: 16px;">
                <?php settings_fields('qaqd_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="qaqd_run_chart_url"><?php esc_html_e('Run Chart URL', 'quainav-qapi-dasboard'); ?></label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="qaqd_run_chart_url"
                                name="qaqd_run_chart_url"
                                value="<?php echo esc_attr($saved_run_chart_url); ?>"
                                class="regular-text"
                                style="width:100%;max-width:700px;"
                                placeholder="<?php echo esc_attr(home_url('/run-chart/')); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('This URL is used by the "Run Chart" button in the QAPI dashboard.', 'quainav-qapi-dasboard'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="qaqd_font_family"><?php esc_html_e('Font Family', 'quainav-qapi-dasboard'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="qaqd_font_family"
                                name="qaqd_design_settings[font_family]"
                                value="<?php echo esc_attr((string) ($design['font_family'] ?? '')); ?>"
                                class="regular-text"
                                style="width:100%;max-width:700px;"
                                placeholder="Inter, system-ui, -apple-system, Segoe UI, sans-serif"
                            />
                            <p class="description"><?php esc_html_e('Optional CSS font-family stack for the QAPI dashboard.', 'quainav-qapi-dasboard'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="qaqd_base_font_size"><?php esc_html_e('Base Font Size (px)', 'quainav-qapi-dasboard'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                min="11"
                                max="18"
                                step="1"
                                id="qaqd_base_font_size"
                                name="qaqd_design_settings[base_font_size]"
                                value="<?php echo esc_attr((string) ($design['base_font_size'] ?? '13')); ?>"
                                class="small-text"
                            />
                            <p class="description"><?php esc_html_e('Global font size for dashboard UI (recommended: 12-14px).', 'quainav-qapi-dasboard'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Theme Colors', 'quainav-qapi-dasboard'); ?></th>
                        <td>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:900px;">
                                <?php
                                $color_fields = array(
                                    'qd_navy' => 'Primary Navy',
                                    'qd_cyan' => 'Accent Cyan',
                                    'qd_green' => 'Success Green',
                                    'qd_bg' => 'Page Background',
                                    'qd_card_bg' => 'Card Background',
                                    'qd_muted' => 'Muted Text',
                                    'qd_line' => 'Borders/Lines',
                                    'btn_bg' => 'Button Background',
                                    'btn_bg_hover' => 'Button Hover',
                                    'btn_text' => 'Button Text',
                                    'primary_text' => 'Header Text',
                                    'header_bg' => 'Header Bar BG',
                                    'header_text' => 'Header Bar Text',
                                    'sidebar_bg' => 'Sidebar BG',
                                    'sidebar_border' => 'Sidebar Border',
                                    'search_bg' => 'Search/Select BG',
                                    'search_border' => 'Search/Select Border',
                                    'text_color' => 'Body Text',
                                    'heading_color' => 'Heading Text',
                                    'card_border' => 'Card Border',
                                    'badge_bg' => 'Badge BG',
                                    'badge_text' => 'Badge Text',
                                    'table_head_bg' => 'Table Header BG',
                                    'table_head_text' => 'Table Header Text',
                                );
                                foreach ($color_fields as $key => $label) :
                                ?>
                                <label style="display:flex;flex-direction:column;gap:6px;">
                                    <span style="font-weight:600;"><?php echo esc_html($label); ?></span>
                                    <input
                                        type="color"
                                        name="qaqd_design_settings[<?php echo esc_attr($key); ?>]"
                                        value="<?php echo esc_attr((string) ($design[$key] ?? '')); ?>"
                                        style="width:72px;height:38px;padding:2px;"
                                    />
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e('These settings control dashboard colors/buttons/fonts from admin, similar to other configurable plugins.', 'quainav-qapi-dasboard'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="qaqd_custom_css"><?php esc_html_e('Custom CSS (Optional)', 'quainav-qapi-dasboard'); ?></label>
                        </th>
                        <td>
                            <textarea
                                id="qaqd_custom_css"
                                name="qaqd_design_settings[custom_css]"
                                rows="8"
                                class="large-text code"
                                style="max-width:900px;"
                                placeholder=".qd-folder-item{ letter-spacing: .01em; }"
                            ><?php echo esc_textarea((string) ($design['custom_css'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Optional advanced styling layer. Use only valid CSS rules.', 'quainav-qapi-dasboard'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save QAPI Settings', 'quainav-qapi-dasboard')); ?>
            </form>

            <h2><?php esc_html_e('Shortcode', 'quainav-qapi-dasboard'); ?></h2>
            <p><?php esc_html_e('Insert this shortcode into any page or post:', 'quainav-qapi-dasboard'); ?></p>
            <code>[qaqd_dashboard]</code>

            <h2><?php esc_html_e('Data Management Shortcode (Admin Help)', 'quainav-qapi-dasboard'); ?></h2>
            <p><?php esc_html_e('Use the shortcode below to render the standalone organization-scoped Data Management interface:', 'quainav-qapi-dasboard'); ?></p>
            <p><code>[qualinav_qapi_data_management]</code></p>
            <p class="description">
                <?php esc_html_e('This shortcode name is unique to the standalone QAPI plugin and avoids collisions with other QualiNav plugins.', 'quainav-qapi-dasboard'); ?>
            </p>

            <h2><?php esc_html_e('Template Option', 'quainav-qapi-dasboard'); ?></h2>
            <ol>
                <li><?php esc_html_e('Create or edit a WordPress Page.', 'quainav-qapi-dasboard'); ?></li>
                <li><?php esc_html_e('Set template to: QualiNav QAPI Dashboard.', 'quainav-qapi-dasboard'); ?></li>
                <li><?php esc_html_e('Publish and open the page while logged in.', 'quainav-qapi-dasboard'); ?></li>
            </ol>

            <h2><?php esc_html_e('Hooks', 'quainav-qapi-dasboard'); ?></h2>
            <p><?php esc_html_e('Optional filters you can add in your theme/plugin:', 'quainav-qapi-dasboard'); ?></p>
            <pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-width:900px;overflow:auto;">add_filter('qaqd_run_chart_url', fn($url) => home_url('/run-chart/'));
add_filter('qaqd_back_url', fn($url) => home_url('/my-org/'));
add_filter('qaqd_back_label', fn($label) => 'Back to My Org');
add_filter('qaqd_save_report_capability', fn($cap) => 'edit_posts');</pre>

            <h2><?php esc_html_e('Notes', 'quainav-qapi-dasboard'); ?></h2>
            <ul>
                <li><?php esc_html_e('Users must be logged in to view the dashboard.', 'quainav-qapi-dasboard'); ?></li>
                <li><?php esc_html_e('Assets load automatically for shortcode pages and template pages.', 'quainav-qapi-dasboard'); ?></li>
                <li><?php esc_html_e('If changes do not appear, clear cache and hard refresh.', 'quainav-qapi-dasboard'); ?></li>
            </ul>
        </div>
        <?php
    }

    private function render_admin_hub_tab() {
        $saved_run_chart_url = esc_url(get_option('qaqd_run_chart_url', home_url('/run-chart/')));
        ?>
        <div class="qaqd-admin-section">
            <h2><?php esc_html_e('Hub & Shortcodes', 'quainav-qapi-dasboard'); ?></h2>
            <p><?php esc_html_e('Use one shortcode per page. Dashboard and Data Management share the same style system and AJAX layer.', 'quainav-qapi-dasboard'); ?></p>
        </div>
        <form method="post" action="options.php" style="max-width:900px;background:#fff;border:1px solid #dcdcde;padding:16px;border-radius:10px;">
            <?php settings_fields('qaqd_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="qaqd_run_chart_url"><?php esc_html_e('Run Chart URL', 'quainav-qapi-dasboard'); ?></label></th>
                    <td>
                        <input type="url" id="qaqd_run_chart_url" name="qaqd_run_chart_url" value="<?php echo esc_attr($saved_run_chart_url); ?>" class="regular-text" style="width:100%;max-width:700px;" />
                        <p class="description"><?php esc_html_e('This URL is used by the "Run Chart" button in the QAPI dashboard.', 'quainav-qapi-dasboard'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Hub Settings', 'quainav-qapi-dasboard')); ?>
        </form>
        <h3><?php esc_html_e('Dashboard shortcode', 'quainav-qapi-dasboard'); ?></h3>
        <p><code>[qaqd_dashboard]</code></p>
        <h3><?php esc_html_e('Data Management shortcode', 'quainav-qapi-dasboard'); ?></h3>
        <p><code>[qualinav_qapi_data_management]</code></p>
        <h3><?php esc_html_e('Template usage', 'quainav-qapi-dasboard'); ?></h3>
        <ol>
            <li><?php esc_html_e('Create or edit a WordPress page.', 'quainav-qapi-dasboard'); ?></li>
            <li><?php esc_html_e('Set the template to QualiNav QAPI Dashboard for the dashboard page, or use the shortcode inside a standard page for Data Management.', 'quainav-qapi-dasboard'); ?></li>
            <li><?php esc_html_e('Publish and open the page while logged in.', 'quainav-qapi-dasboard'); ?></li>
        </ol>
    <?php }

    private function render_admin_design_tab($theme_defaults) {
        $design = $this->get_design_settings();
        $color_groups = array(
            'global' => array(
                'title' => __('Global Theme', 'quainav-qapi-dasboard'),
                'desc'  => __('Base colors and typography used throughout the dashboard and Data Management views.', 'quainav-qapi-dasboard'),
                'fields' => array(
                    'font_family' => __('Font Family', 'quainav-qapi-dasboard'),
                    'base_font_size' => __('Base Font Size (px)', 'quainav-qapi-dasboard'),
                    'qd_navy' => __('Primary Navy', 'quainav-qapi-dasboard'),
                    'qd_cyan' => __('Accent Cyan', 'quainav-qapi-dasboard'),
                    'qd_green' => __('Success Green', 'quainav-qapi-dasboard'),
                    'qd_bg' => __('Background', 'quainav-qapi-dasboard'),
                    'qd_card_bg' => __('Surface', 'quainav-qapi-dasboard'),
                    'qd_muted' => __('Muted Text', 'quainav-qapi-dasboard'),
                    'qd_line' => __('Borders/Lines', 'quainav-qapi-dasboard'),
                ),
            ),
            'header' => array(
                'title' => __('Headers and Navigation', 'quainav-qapi-dasboard'),
                'desc'  => __('Top bars, headings, sidebar treatment, and search/select colors.', 'quainav-qapi-dasboard'),
                'fields' => array(
                    'header_bg' => __('Header Bar BG', 'quainav-qapi-dasboard'),
                    'header_text' => __('Header Bar Text', 'quainav-qapi-dasboard'),
                    'sidebar_bg' => __('Sidebar BG', 'quainav-qapi-dasboard'),
                    'sidebar_border' => __('Sidebar Border', 'quainav-qapi-dasboard'),
                    'search_bg' => __('Search/Select BG', 'quainav-qapi-dasboard'),
                    'search_border' => __('Search/Select Border', 'quainav-qapi-dasboard'),
                    'text_color' => __('Body Text', 'quainav-qapi-dasboard'),
                    'heading_color' => __('Heading Text', 'quainav-qapi-dasboard'),
                ),
            ),
            'actions' => array(
                'title' => __('Buttons, Cards and Tables', 'quainav-qapi-dasboard'),
                'desc'  => __('Action colors, badges, cards, and table heading states.', 'quainav-qapi-dasboard'),
                'fields' => array(
                    'btn_bg' => __('Button BG', 'quainav-qapi-dasboard'),
                    'btn_bg_hover' => __('Button Hover', 'quainav-qapi-dasboard'),
                    'btn_text' => __('Button Text', 'quainav-qapi-dasboard'),
                    'primary_text' => __('Primary Text', 'quainav-qapi-dasboard'),
                    'card_border' => __('Card Border', 'quainav-qapi-dasboard'),
                    'badge_bg' => __('Badge BG', 'quainav-qapi-dasboard'),
                    'badge_text' => __('Badge Text', 'quainav-qapi-dasboard'),
                    'table_head_bg' => __('Table Header BG', 'quainav-qapi-dasboard'),
                    'table_head_text' => __('Table Header Text', 'quainav-qapi-dasboard'),
                ),
            ),
        );
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('qaqd_settings_group'); ?>
            <div class="qaqd-admin-grid">
                <aside class="qaqd-admin-nav">
                    <?php foreach ($color_groups as $group_key => $group) : ?>
                        <a href="#qaqd-<?php echo esc_attr($group_key); ?>"><?php echo esc_html($group['title']); ?></a>
                    <?php endforeach; ?>
                    <a href="#qaqd-custom-css"><?php esc_html_e('Custom CSS', 'quainav-qapi-dasboard'); ?></a>
                </aside>
                <div>
                    <h2><?php esc_html_e('Design Settings', 'quainav-qapi-dasboard'); ?></h2>
                    <p><?php esc_html_e('Defaults are pulled from the active theme palette when available, then stored as plugin settings once you save.', 'quainav-qapi-dasboard'); ?></p>
                    <?php foreach ($color_groups as $group_key => $group) : ?>
                        <section id="qaqd-<?php echo esc_attr($group_key); ?>" class="qaqd-admin-section">
                            <h3><?php echo esc_html($group['title']); ?></h3>
                            <p><?php echo esc_html($group['desc']); ?></p>
                            <div class="qaqd-admin-fields">
                                <?php foreach ($group['fields'] as $key => $label) : ?>
                                    <div class="qaqd-admin-field">
                                        <label for="qaqd-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                                        <?php if ($key === 'font_family') : ?>
                                            <input type="text" id="qaqd-<?php echo esc_attr($key); ?>" name="qaqd_design_settings[font_family]" value="<?php echo esc_attr((string) ($design['font_family'] ?? '')); ?>" class="regular-text" placeholder="var(--scout-font-sans), system-ui, sans-serif" />
                                        <?php elseif ($key === 'base_font_size') : ?>
                                            <input type="number" min="11" max="18" step="1" id="qaqd-<?php echo esc_attr($key); ?>" name="qaqd_design_settings[base_font_size]" value="<?php echo esc_attr((string) ($design['base_font_size'] ?? '13')); ?>" class="small-text" />
                                        <?php else : ?>
                                            <input type="color" id="qaqd-<?php echo esc_attr($key); ?>" name="qaqd_design_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) ($design[$key] ?? '')); ?>" />
                                        <?php endif; ?>
                                        <?php if (isset($theme_defaults[$key])) : ?>
                                            <span class="description"><?php echo esc_html(sprintf(__('Theme default: %s', 'quainav-qapi-dasboard'), $theme_defaults[$key])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                    <section id="qaqd-custom-css" class="qaqd-admin-section">
                        <h3><?php esc_html_e('Custom CSS', 'quainav-qapi-dasboard'); ?></h3>
                        <p><?php esc_html_e('Optional advanced styling layer for edge cases.', 'quainav-qapi-dasboard'); ?></p>
                        <textarea id="qaqd_custom_css" name="qaqd_design_settings[custom_css]" rows="8" class="large-text code" style="max-width:1000px;"><?php echo esc_textarea((string) ($design['custom_css'] ?? '')); ?></textarea>
                    </section>
                    <?php submit_button(__('Save Design Settings', 'quainav-qapi-dasboard')); ?>
                </div>
                <aside class="qaqd-admin-preview">
                    <h3 style="margin-top:0;"><?php esc_html_e('Live Preview', 'quainav-qapi-dasboard'); ?></h3>
                    <div class="qaqd-admin-preview-card" style="background:<?php echo esc_attr($design['qd_card_bg']); ?>;color:<?php echo esc_attr($design['text_color']); ?>;font-family:<?php echo esc_attr($design['font_family'] !== '' ? $design['font_family'] : 'inherit'); ?>;font-size:<?php echo esc_attr((string) $design['base_font_size']); ?>px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;background:<?php echo esc_attr($design['header_bg']); ?>;color:<?php echo esc_attr($design['header_text']); ?>;padding:14px;border-radius:10px;">
                            <div>
                                <div style="font-size:26px;font-weight:800;line-height:1.1;"><?php esc_html_e('Section Header Title', 'quainav-qapi-dasboard'); ?></div>
                                <div style="margin-top:2px;opacity:.85;"><?php esc_html_e('Section focus helper text', 'quainav-qapi-dasboard'); ?></div>
                            </div>
                            <div style="font-weight:700;">0/2</div>
                        </div>
                        <div style="display:grid;gap:12px;margin-top:12px;">
                            <input type="text" value="<?php esc_attr_e('Instruction panel text and emphasis preview.', 'quainav-qapi-dasboard'); ?>" readonly style="width:100%;padding:10px 12px;border:1px solid <?php echo esc_attr($design['search_border']); ?>;background:<?php echo esc_attr($design['search_bg']); ?>;border-radius:10px;color:<?php echo esc_attr($design['text_color']); ?>;">
                            <div style="display:flex;gap:8px;align-items:center;">
                                <button type="button" style="cursor:pointer;border:1px solid <?php echo esc_attr($design['btn_bg']); ?>;background:<?php echo esc_attr($design['btn_bg']); ?>;color:<?php echo esc_attr($design['btn_text']); ?>;border-radius:8px;padding:10px 14px;"><?php esc_html_e('Save and Calculate', 'quainav-qapi-dasboard'); ?></button>
                                <span style="display:inline-flex;align-items:center;justify-content:center;min-width:56px;padding:8px 12px;border-radius:999px;background:<?php echo esc_attr($design['badge_bg']); ?>;color:<?php echo esc_attr($design['badge_text']); ?>;font-weight:700;">4.20</span>
                            </div>
                            <div style="border:1px solid <?php echo esc_attr($design['card_border']); ?>;border-radius:12px;padding:12px;">
                                <div style="font-weight:800;color:<?php echo esc_attr($design['heading_color']); ?>;"><?php esc_html_e('Result Recommendation', 'quainav-qapi-dasboard'); ?></div>
                                <div style="margin-top:6px;color:<?php echo esc_attr($design['qd_green']); ?>;font-weight:700;"><?php esc_html_e('Complete state color', 'quainav-qapi-dasboard'); ?></div>
                                <div style="color:<?php echo esc_attr($design['qd_cyan']); ?>;font-weight:700;"><?php esc_html_e('Instruction + panel accent', 'quainav-qapi-dasboard'); ?></div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </form>
        <?php
    }

    private function render_admin_data_tab() {
        global $wpdb;

        $table_name = $this->get_metric_data_table_name();
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        $option_rows = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'dm_org_folder_files_%' ORDER BY option_name ASC",
            ARRAY_A
        );

        $upload_sets = array();
        foreach ($option_rows as $row) {
            $org_key = str_replace('dm_org_folder_files_', '', (string) $row['option_name']);
            $decoded = maybe_unserialize($row['option_value']);
            if (!is_array($decoded)) {
                $decoded = array();
            }

            $folder_count = count($decoded);
            $file_count = 0;
            $latest_update = '';
            $latest_file = '';

            foreach ($decoded as $folder_files) {
                if (!is_array($folder_files)) {
                    continue;
                }
                $file_count += count($folder_files);
                foreach ($folder_files as $file_row) {
                    if (!is_array($file_row)) {
                        continue;
                    }
                    $uploaded_at = (string) ($file_row['uploaded_at'] ?? '');
                    if ($uploaded_at !== '' && ($latest_update === '' || strtotime($uploaded_at) > strtotime($latest_update))) {
                        $latest_update = $uploaded_at;
                        $latest_file = (string) ($file_row['name'] ?? '');
                    }
                }
            }

            $upload_sets[] = array(
                'org_key'       => $org_key,
                'folder_count'  => $folder_count,
                'file_count'    => $file_count,
                'latest_update' => $latest_update,
                'latest_file'   => $latest_file,
            );
        }

        $metric_rows = array();
        if ($table_exists) {
            $metric_rows = $wpdb->get_results(
                "SELECT user_id, org_id, metric_label, folder_key, source_name, benchmark_label, raw_value, num_value, denom_value, period_date, uploaded_at
                 FROM {$table_name}
                 ORDER BY uploaded_at DESC, id DESC
                 LIMIT 150",
                ARRAY_A
            );
            // Resolve org label + state from each row's user_id (user_meta).
            foreach ($metric_rows as &$metric_row) {
                $resolved = $this->resolve_user_org_meta($metric_row['user_id'] ?? 0);
                $metric_row['org_name'] = $resolved['label'];
                $metric_row['state_code'] = $resolved['state'];
            }
            unset($metric_row);
        }

        ?>
        <h2><?php esc_html_e('Data Management', 'quainav-qapi-dasboard'); ?></h2>
        <p><?php esc_html_e('This plugin ships with the organization-scoped Data Management UI. Use it on a dedicated page and keep one shortcode per page.', 'quainav-qapi-dasboard'); ?></p>
        <div class="qaqd-admin-kpis" style="margin:16px 0 20px;">
            <div class="qaqd-admin-kpi"><strong>[qualinav_qapi_data_management]</strong><span><?php esc_html_e('Primary shortcode for Data Management pages in the standalone QAPI plugin.', 'quainav-qapi-dasboard'); ?></span></div>
        </div>
        <h3><?php esc_html_e('Recommended page template', 'quainav-qapi-dasboard'); ?></h3>
        <pre class="qaqd-admin-code">&lt;?php echo do_shortcode('[qualinav_qapi_data_management]'); ?&gt;</pre>
        <h3><?php esc_html_e('Backend data flow', 'quainav-qapi-dasboard'); ?></h3>
        <ol>
            <li><?php esc_html_e('Users upload CSV/XLS/XLSX files in Data Management.', 'quainav-qapi-dasboard'); ?></li>
            <li><?php esc_html_e('The upload index is stored per organization in WordPress options.', 'quainav-qapi-dasboard'); ?></li>
            <li><?php esc_html_e('The plugin normalizes metric rows into the qapi_metric_data table for reporting.', 'quainav-qapi-dasboard'); ?></li>
            <li><?php esc_html_e('Dashboard, Benchmarking, Heat Map, and comparisons read from the normalized table.', 'quainav-qapi-dasboard'); ?></li>
        </ol>
        <div class="qaqd-admin-grid" style="grid-template-columns:minmax(0,1fr);margin-top:20px;">
            <section class="qaqd-admin-section">
                <h3><?php esc_html_e('Organization Upload Sets', 'quainav-qapi-dasboard'); ?></h3>
                <p><?php esc_html_e('Human-readable view of the uploaded file groups stored for each organization.', 'quainav-qapi-dasboard'); ?></p>
                <?php if (empty($upload_sets)) : ?>
                    <p><?php esc_html_e('No organization upload sets found yet.', 'quainav-qapi-dasboard'); ?></p>
                <?php else : ?>
                    <div style="overflow:auto;">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Organization Key', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Folders', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Files', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Latest Upload', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Latest File', 'quainav-qapi-dasboard'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upload_sets as $set) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html($set['org_key']); ?></code></td>
                                        <td><?php echo esc_html((string) $set['folder_count']); ?></td>
                                        <td><?php echo esc_html((string) $set['file_count']); ?></td>
                                        <td><?php echo esc_html($set['latest_update'] !== '' ? $set['latest_update'] : __('No uploads yet', 'quainav-qapi-dasboard')); ?></td>
                                        <td><?php echo esc_html($set['latest_file'] !== '' ? $set['latest_file'] : 'â€”'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="qaqd-admin-section">
                <h3><?php esc_html_e('Normalized Metric Data', 'quainav-qapi-dasboard'); ?></h3>
                <p><?php esc_html_e('This is the reporting data after uploads are parsed into the qapi_metric_data table. It is meant to be readable by admins, not raw SQL.', 'quainav-qapi-dasboard'); ?></p>
                <?php if (!$table_exists) : ?>
                    <p><?php esc_html_e('The normalized data table does not exist yet.', 'quainav-qapi-dasboard'); ?></p>
                <?php elseif (empty($metric_rows)) : ?>
                    <p><?php esc_html_e('No normalized metric rows found yet.', 'quainav-qapi-dasboard'); ?></p>
                <?php else : ?>
                    <div style="overflow:auto;">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Organization', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('State', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Metric', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Folder', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Value', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Num', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Denom', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Benchmark', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Period', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Source File', 'quainav-qapi-dasboard'); ?></th>
                                    <th><?php esc_html_e('Imported', 'quainav-qapi-dasboard'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metric_rows as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html($row['org_name'] !== '' ? $row['org_name'] : ('user ' . (int) ($row['user_id'] ?? 0))); ?></td>
                                        <td><?php echo esc_html($row['state_code'] !== '' ? $row['state_code'] : 'â€”'); ?></td>
                                        <td><?php echo esc_html($row['metric_label']); ?></td>
                                        <td><code><?php echo esc_html($row['folder_key'] !== '' ? $row['folder_key'] : 'â€”'); ?></code></td>
                                        <td><?php echo esc_html($row['raw_value'] !== null ? (string) $row['raw_value'] : 'â€”'); ?></td>
                                        <td><?php echo esc_html($row['num_value'] !== null ? (string) $row['num_value'] : 'â€”'); ?></td>
                                        <td><?php echo esc_html($row['denom_value'] !== null ? (string) $row['denom_value'] : 'â€”'); ?></td>
                                        <td><?php echo esc_html($row['benchmark_label'] !== '' ? $row['benchmark_label'] : 'â€”'); ?></td>
                                        <td><?php echo esc_html($row['period_date'] !== null ? (string) $row['period_date'] : 'â€”'); ?></td>
                                        <td><?php echo esc_html($row['source_name'] !== '' ? $row['source_name'] : 'â€”'); ?></td>
                                        <td><?php echo esc_html($row['uploaded_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    private function render_admin_health_tab() {
        global $wpdb;
        $table_name = $this->get_metric_data_table_name();
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        $row_count = $table_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") : 0;
        $report_counts = wp_count_posts('qd_report');
        $report_count = isset($report_counts->publish) ? (int) $report_counts->publish : 0;
        $org_option_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'dm_org_folder_files_%'");
        ?>
        <h2><?php esc_html_e('Plugin Health', 'quainav-qapi-dasboard'); ?></h2>
        <p><?php esc_html_e('Use this tab to confirm the normalized data layer exists and is receiving uploads.', 'quainav-qapi-dasboard'); ?></p>
        <div class="qaqd-admin-kpis">
            <div class="qaqd-admin-kpi"><span><?php esc_html_e('Metric table', 'quainav-qapi-dasboard'); ?></span><strong><?php echo $table_exists ? esc_html__('Ready', 'quainav-qapi-dasboard') : esc_html__('Missing', 'quainav-qapi-dasboard'); ?></strong><code><?php echo esc_html($table_name); ?></code></div>
            <div class="qaqd-admin-kpi"><span><?php esc_html_e('Metric rows', 'quainav-qapi-dasboard'); ?></span><strong><?php echo esc_html((string) $row_count); ?></strong></div>
            <div class="qaqd-admin-kpi"><span><?php esc_html_e('Saved reports', 'quainav-qapi-dasboard'); ?></span><strong><?php echo esc_html((string) $report_count); ?></strong></div>
            <div class="qaqd-admin-kpi"><span><?php esc_html_e('Org upload sets', 'quainav-qapi-dasboard'); ?></span><strong><?php echo esc_html((string) $org_option_count); ?></strong></div>
        </div>
        <ul style="margin-top:18px;list-style:disc;margin-left:20px;">
            <li><?php esc_html_e('Users must be logged in to view the dashboard.', 'quainav-qapi-dasboard'); ?></li>
            <li><?php esc_html_e('Assets load automatically for template pages and any page containing the dashboard or Data Management shortcodes.', 'quainav-qapi-dasboard'); ?></li>
            <li><?php esc_html_e('If changes do not appear, clear cache and hard refresh.', 'quainav-qapi-dasboard'); ?></li>
        </ul>
        <?php
    }

    public function register_report_post_type() {
        if (post_type_exists('qd_report')) {
            return;
        }

        register_post_type('qd_report', array(
            'labels' => array(
                'name'          => 'Quality Reports',
                'singular_name' => 'Quality Report',
            ),
            'public'       => false,
            'show_ui'      => false,
            'has_archive'  => false,
            'supports'     => array('title', 'author'),
            'capabilities' => array(
                'create_posts' => 'edit_posts',
            ),
        ));
    }

    public function save_report_handler() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
            return;
        }
        $capability = apply_filters('qaqd_save_report_capability', 'read');
        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'qaqd_report_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        $user_id = get_current_user_id();
        $report_type = sanitize_text_field($_POST['report_type'] ?? '');
        $report_label = sanitize_text_field($_POST['report_label'] ?? '');
        $report_date = sanitize_text_field($_POST['report_date'] ?? '');
        $report_metrics_raw = wp_unslash($_POST['report_metrics'] ?? '[]');
        $report_metrics_json = json_decode($report_metrics_raw, true);
        if (!is_array($report_metrics_json)) {
            $report_metrics_json = array();
        }
        if (count($report_metrics_json) > 500) {
            $report_metrics_json = array_slice($report_metrics_json, 0, 500);
        }
        $report_metrics = wp_json_encode($report_metrics_json);

        if (empty($report_type) || empty($report_label)) {
            wp_send_json_error(array('message' => 'Missing report data'));
            return;
        }

        $post_title = trim($report_label . ' - ' . $report_date);
        $post_id = wp_insert_post(array(
            'post_type'    => 'qd_report',
            'post_title'   => $post_title,
            'post_status'  => 'publish',
            'post_author'  => $user_id,
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => 'Could not create report'));
            return;
        }

        update_post_meta($post_id, '_qd_report_type', $report_type);
        update_post_meta($post_id, '_qd_report_label', $report_label);
        update_post_meta($post_id, '_qd_report_date', $report_date);
        update_post_meta($post_id, '_qd_report_metrics', $report_metrics);

        wp_send_json_success(array(
            'message' => 'Report saved successfully',
            'post_id' => $post_id,
        ));
    }

    public function save_org_metrics_handler() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'qaqd_report_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        list($org_key) = $this->get_current_org_context();
        $raw = wp_unslash((string) ($_POST['metrics'] ?? '{}'));
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            wp_send_json_error(array('message' => 'Invalid metrics payload'));
            return;
        }

        if (count($decoded) > 1000) {
            $decoded = array_slice($decoded, 0, 1000, true);
        }

        update_option('qaqd_org_metrics_' . $org_key, $decoded, false);
        wp_send_json_success(array('message' => 'Organization metrics saved'));
    }

    public function mydata_delete_report_handler() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }

        if (!wp_verify_nonce($_POST['_nonce'] ?? '', 'mydata_delete_report')) {
            wp_send_json_error('Security check failed');
        }

        $report_id = absint($_POST['report_id'] ?? 0);
        if (!$report_id) {
            wp_send_json_error('Invalid report');
        }

        $post = get_post($report_id);
        $is_owner = ($post && (int) $post->post_author === get_current_user_id());
        $can_manage = current_user_can('manage_options');
        if (!$post || $post->post_type !== 'qd_report' || (!$is_owner && !$can_manage)) {
            wp_send_json_error('Not authorized');
        }

        wp_delete_post($report_id, true);
        wp_send_json_success('Deleted');
    }

    public function dm_update_report_metrics_handler() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }

        if (!wp_verify_nonce($_POST['_nonce'] ?? '', 'dm_update_report_metrics')) {
            wp_send_json_error('Security check failed');
        }

        $report_id = absint($_POST['report_id'] ?? 0);
        if (!$report_id) {
            wp_send_json_error('Invalid report');
        }

        $post = get_post($report_id);
        if (!$post || $post->post_type !== 'qd_report') {
            wp_send_json_error('Report not found');
        }

        $is_owner = ((int) $post->post_author === get_current_user_id());
        $can_manage = current_user_can('manage_options');
        if (!$is_owner && !$can_manage) {
            wp_send_json_error('Not authorized');
        }

        $raw = wp_unslash((string) ($_POST['report_metrics'] ?? '[]'));
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            wp_send_json_error('Invalid metrics payload');
        }

        if (count($decoded) > 1000) {
            $decoded = array_slice($decoded, 0, 1000);
        }

        update_post_meta($report_id, '_qd_report_metrics', wp_json_encode($decoded));
        wp_send_json_success('Updated');
    }

    public function dm_upload_folder_file_handler() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }

        if (!wp_verify_nonce($_POST['_nonce'] ?? '', 'dm_upload_folder_file')) {
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('upload_files') && !current_user_can('edit_posts')) {
            wp_send_json_error('Not authorized');
        }

        $folder_id = sanitize_key((string) ($_POST['folder_id'] ?? ''));
        if ($folder_id === '') {
            wp_send_json_error('Invalid folder');
        }

        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
        }

        $allowed_mimes = array(
            'csv'  => 'text/csv',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $uploaded_file = $_FILES['file'];

        // Reject errored or oversized uploads before any further processing.
        if ((int) ($uploaded_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_send_json_error('Upload failed. Please try again.');
        }
        $dm_max_upload_bytes = 10 * 1024 * 1024; // 10 MB
        if ((int) ($uploaded_file['size'] ?? 0) > $dm_max_upload_bytes) {
            wp_send_json_error('File is too large. The maximum allowed size is 10 MB.');
        }

        $file_check = wp_check_filetype_and_ext($uploaded_file['tmp_name'], $uploaded_file['name'], $allowed_mimes);
        if (empty($file_check['ext'])) {
            wp_send_json_error('Only CSV, XLS, and XLSX files are allowed');
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Hash the file contents BEFORE wp_handle_upload moves it, so we can
        // reject duplicates without polluting the uploads directory with
        // identical files under different timestamped names.
        $content_hash = '';
        if (is_readable($uploaded_file['tmp_name'])) {
            $content_hash = (string) md5_file($uploaded_file['tmp_name']);
        }

        $upload = wp_handle_upload($uploaded_file, array(
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ));

        if (!is_array($upload) || !empty($upload['error'])) {
            wp_send_json_error(!empty($upload['error']) ? $upload['error'] : 'Upload failed');
        }

        $user_id = get_current_user_id();
        $org_name = trim((string) get_user_meta($user_id, 'organization', true));
        if ($org_name === '') {
            $org_name = 'User ' . $user_id;
        }
        $org_key = sanitize_title($org_name);
        if ($org_key === '') {
            $org_key = 'user-' . $user_id;
        }

        $option_key = 'dm_org_folder_files_' . $org_key;
        $folder_files = get_option($option_key, array());
        if (!is_array($folder_files)) {
            $folder_files = array();
        }
        if (!isset($folder_files[$folder_id]) || !is_array($folder_files[$folder_id])) {
            $folder_files[$folder_id] = array();
        }

        $replace_index = isset($_POST['replace_index']) ? (int) $_POST['replace_index'] : -1;

        // Content-hash dedup. Files uploaded before content_hash tracking
        // existed don't have one yet — lazy-backfill by reading them from
        // disk so legacy duplicates are also caught.
        if ($content_hash !== '' && $replace_index < 0) {
            $upload_basedir   = wp_upload_dir();
            $org_storage_dir  = $upload_basedir['basedir'] . '/qualinav-dm/' . $org_key;
            $hashes_backfilled = false;
            foreach ($folder_files[$folder_id] as $idx => $existing) {
                if (!is_array($existing)) { continue; }
                $existing_hash = (string) ($existing['content_hash'] ?? '');
                if ($existing_hash === '') {
                    $existing_path = $org_storage_dir . '/' . (string) ($existing['name'] ?? '');
                    if (is_readable($existing_path)) {
                        $existing_hash = (string) md5_file($existing_path);
                        $folder_files[$folder_id][$idx]['content_hash'] = $existing_hash;
                        $hashes_backfilled = true;
                    }
                }
                if ($existing_hash !== '' && $existing_hash === $content_hash) {
                    if ($hashes_backfilled) {
                        update_option($option_key, $folder_files, false);
                    }
                    if (!empty($upload['file']) && file_exists($upload['file'])) {
                        @unlink($upload['file']);
                    }
                    wp_send_json_error(sprintf(
                        'This file is identical to "%s" already uploaded. Skipped to avoid duplicates.',
                        (string) ($existing['name'] ?? 'an existing file')
                    ));
                }
            }
            if ($hashes_backfilled) {
                update_option($option_key, $folder_files, false);
            }
        }

        $record = array(
            'name'         => sanitize_file_name((string) $uploaded_file['name']),
            'url'          => esc_url_raw((string) ($upload['url'] ?? '')),
            'type'         => sanitize_text_field((string) ($file_check['type'] ?? ($upload['type'] ?? ''))),
            'size_kb'      => round(((int) ($uploaded_file['size'] ?? 0)) / 1024, 1),
            'uploaded_at'  => wp_date('M j, Y g:i A'),
            'content_hash' => $content_hash,
        );

        // Mirror to Google Drive via the shared helper so manual-entry CSVs
        // and direct file uploads end up in the same Drive hierarchy.
        if (class_exists('Qualinav_Data_Hub_Drive')) {
            $drive_meta = Qualinav_Data_Hub_Drive::mirror_local_file(
                $user_id,
                $folder_id,
                (string) ($upload['file'] ?? ''),
                $record['name'],
                (string) ($file_check['type'] ?? ($upload['type'] ?? 'application/octet-stream'))
            );
            if (!empty($drive_meta)) {
                $record = array_merge($record, $drive_meta);
            }
        }

        if ($replace_index >= 0 && isset($folder_files[$folder_id][$replace_index])) {
            // Trash the previous Drive copy (if any) before overwriting the
            // record — otherwise the old file lingers in Drive forever.
            $prev = $folder_files[$folder_id][$replace_index];
            if (is_array($prev) && !empty($prev['drive_file_id']) && class_exists('Qualinav_Data_Hub_Drive')) {
                Qualinav_Data_Hub_Drive::trash_file($prev['drive_file_id']);
            }
            $folder_files[$folder_id][$replace_index] = $record;
        } else {
            $folder_files[$folder_id][] = $record;
        }

        update_option($option_key, $folder_files, false);

        // Explicitly sync metric data table and purge caches right now.
        // The 'updated_option' hook may or may not have fired depending on
        // whether WordPress detected a value change, so we force it.
        $this->purge_all_live_metrics_caches($org_key);

        // Build a quick summary of the current metric state to return to the frontend.
        $definitions = $this->get_live_metric_definitions();
        $live_metrics = $this->build_org_metric_records($folder_files, $definitions);
        $synced_count = 0;
        $synced_green = 0;
        $synced_yellow = 0;
        $synced_red = 0;
        foreach ($live_metrics as $metric_name => $record) {
            if (is_array($record) && isset($record['value']) && $record['value'] !== '-') {
                $synced_count++;
                $status = (string) ($record['status'] ?? '');
                if ($status === 'green') $synced_green++;
                elseif ($status === 'yellow') $synced_yellow++;
                elseif ($status === 'red') $synced_red++;
            }
        }

        wp_send_json_success(array(
            'files'       => array_values($folder_files[$folder_id]),
            'synced'      => true,
            'sync_summary' => array(
                'metrics_with_data' => $synced_count,
                'green'             => $synced_green,
                'yellow'            => $synced_yellow,
                'red'               => $synced_red,
            ),
        ));
    }
}

register_activation_hook(QUAINAV_QAPI_DASBOARD_PLUGIN_FILE, array('Quainav_Qapi_Dasboard', 'activate'));
add_action('plugins_loaded', array('Quainav_Qapi_Dasboard', 'instance'));
