<?php
/**
 * Plugin Name: Qualinav My Org
 * Plugin URI:  https://qualinav.com
 * Description: Self-contained organization workspace. Bundles Data Hub, Assessment & Readiness and QI Projects as internal modules, owns and enforces the /my-org/ slug and the tabbed Assessments page, renders the tile launcher (plus custom tiles), and provides a unified admin settings hub. No external plugin dependencies.
 * Version:     0.5.121
 * Author:      Grapevine Team / Markelo Rapti
 * License:     GPL v2 or later
 * Text Domain: qualinav-my-org
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'QUALINAV_MY_ORG_VERSION', '0.5.121' );
define( 'QUALINAV_MY_ORG_PLUGIN_FILE', __FILE__ );
define( 'QUALINAV_MY_ORG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QUALINAV_MY_ORG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once QUALINAV_MY_ORG_PLUGIN_DIR . 'includes/class-myorg-settings.php';
require_once QUALINAV_MY_ORG_PLUGIN_DIR . 'includes/class-data-hub-measure-library.php';
if ( is_admin() ) {
    require_once QUALINAV_MY_ORG_PLUGIN_DIR . 'includes/class-myorg-admin.php';
}

/**
 * Bundled modules.
 *
 * Data Hub, Assessment & Readiness and QI Projects used to be standalone
 * plugins; their directories now live under modules/ and are loaded from
 * here so all functionality runs under the single My Org plugin. Each entry
 * file is __FILE__-relative (asset URLs/paths still resolve) and each module
 * self-heals its DB tables/pages on the next request, so the missing
 * register_activation_hook is harmless.
 *
 * The class_exists guards mean that if an old standalone copy is somehow
 * still active, we defer to it instead of double-loading (fatal redeclare).
 */
$qualinav_my_org_modules = array(
    // guard: returns true if the module is already loaded (skip to avoid
    // fatal redeclaration if an old standalone copy is somehow still active).
    array(
        'guard' => function () { return class_exists( 'CAH_Assess\\Plugin' ); },
        'file'  => 'modules/cah-assessment-tool/cah-assessment-tool.php',
    ),
    array(
        'guard' => function () { return class_exists( 'Qualinav_Data_Hub_Plugin' ); },
        'file'  => 'modules/data-hub/data-hub.php',
    ),
    array(
        'guard' => function () { return class_exists( 'Qualinav_QI_Plugin' ); },
        'file'  => 'modules/qualinav-qi-projects/qualinav-qi-projects.php',
    ),
    // do-tank-table-chart is procedural (no class) and defines constants
    // unconditionally — guard on its constant.
    array(
        'guard' => function () { return defined( 'DTTC_VERSION' ); },
        'file'  => 'modules/do-tank-table-chart/do-tank-table-chart.php',
    ),
);
foreach ( $qualinav_my_org_modules as $qualinav_my_org_module ) {
    $module_file = QUALINAV_MY_ORG_PLUGIN_DIR . $qualinav_my_org_module['file'];
    $minimum_php = isset( $qualinav_my_org_module['requires_php'] ) ? (int) $qualinav_my_org_module['requires_php'] : 0;
    if ( $minimum_php > 0 && PHP_VERSION_ID < $minimum_php ) {
        continue;
    }
    $missing_required_file = false;
    foreach ( (array) ( $qualinav_my_org_module['requires_files'] ?? array() ) as $required_file ) {
        if ( ! file_exists( QUALINAV_MY_ORG_PLUGIN_DIR . $required_file ) ) {
            $missing_required_file = true;
            break;
        }
    }
    if ( $missing_required_file ) {
        continue;
    }
    if ( ! call_user_func( $qualinav_my_org_module['guard'] ) && file_exists( $module_file ) ) {
        require_once $module_file;
    }
}
unset( $qualinav_my_org_modules, $qualinav_my_org_module, $module_file, $minimum_php, $missing_required_file, $required_file );

class Qualinav_My_Org_Plugin {

    const TEMPLATE        = 'page-my-org-hub.php';
    const TEMPLATE_ASSESS = 'page-assessments.php';

    /** Re-bootstrap when this option != current version. */
    const PAGE_BOOTSTRAP_OPTION = 'qualinav_my_org_page_bootstrapped';
    /** Tracks the slug the Assessments page was last bootstrapped at. */
    const ASSESS_SLUG_OPTION = 'qualinav_my_org_assess_slug';
    const ROUTE_QUERY_VAR    = 'qualinav_my_org_route';
    const ROUTE_HUB          = 'hub';
    const ROUTE_ASSESS       = 'assessments';

    /** Configured front-end slug for the Assessments section. */
    public static function assessments_slug() {
        $sec = Qualinav_My_Org_Settings::get_section( 'assessments' );
        $slug = $sec && ! empty( $sec['slug'] ) ? trim( $sec['slug'], '/' ) : 'org-assessments';
        return $slug;
    }

    public static function boot() {
        add_action( 'init',                 array( __CLASS__, 'register_routes' ), 0 );
        add_filter( 'query_vars',           array( __CLASS__, 'register_query_vars' ) );
        add_filter( 'pre_handle_404',       array( __CLASS__, 'handle_virtual_route_404' ), 10, 2 );
        add_filter( 'theme_page_templates', array( __CLASS__, 'register_template' ) );
        // Run late so the hub template wins over qualinav-pages' /my-org/ mapping
        // even before the page-template meta is bootstrapped.
        add_filter( 'template_include',     array( __CLASS__, 'load_template' ), 99 );
        add_action( 'parse_request',        array( __CLASS__, 'guard_disabled_sections' ), 1 );
        add_action( 'wp_enqueue_scripts',   array( __CLASS__, 'enqueue_assets' ) );
        add_filter( 'body_class',           array( __CLASS__, 'body_class' ) );
        add_filter( 'dtam_menu_item_active', array( __CLASS__, 'highlight_owned_nav' ), 10, 4 );
        add_action( 'init',                 array( __CLASS__, 'ensure_module_setup' ), 1 );

        // Appearance settings (button roundness slider, shared with grapevine-menus dtam_menu_radius).
        add_action( 'admin_menu',           array( __CLASS__, 'register_settings_page' ) );
        add_action( 'admin_post_qmo_save_appearance', array( __CLASS__, 'handle_save_appearance' ) );
        add_action( 'wp_head',              array( __CLASS__, 'print_button_radius_style' ), 100 );

        if ( is_admin() && class_exists( 'Qualinav_My_Org_Admin' ) ) {
            Qualinav_My_Org_Admin::boot();
        }
        if ( class_exists( 'Qualinav_Data_Hub_Measure_Library' ) ) {
            Qualinav_Data_Hub_Measure_Library::boot();
        }
    }

    /* ===== Appearance settings (button radius) =====
     *
     * Shared with the grapevine-menus plugin: the same value drives both
     * the menu-item corner radius and My Org's button/tile corner radius.
     * Stored as `dtam_menu_radius` in the wp_dt_brand_settings table when
     * grapevine-menus is active; otherwise falls back to a wp_options row
     * of the same name so the value still round-trips between page loads.
     */

    const BTN_RADIUS_OPTION  = 'dtam_menu_radius';
    const BTN_RADIUS_DEFAULT = 6;
    const BTN_RADIUS_MAX     = 50;

    public static function get_btn_radius() {
        if ( function_exists( 'dtam_get_branding_option' ) ) {
            $v = (int) dtam_get_branding_option( self::BTN_RADIUS_OPTION, self::BTN_RADIUS_DEFAULT );
        } else {
            $v = (int) get_option( self::BTN_RADIUS_OPTION, self::BTN_RADIUS_DEFAULT );
        }
        return max( 0, min( self::BTN_RADIUS_MAX, $v ) );
    }

    public static function set_btn_radius( $value ) {
        $value = max( 0, min( self::BTN_RADIUS_MAX, (int) $value ) );
        if ( function_exists( 'dtam_update_branding_option' ) ) {
            dtam_update_branding_option( self::BTN_RADIUS_OPTION, $value );
        } else {
            update_option( self::BTN_RADIUS_OPTION, $value, false );
        }
        return $value;
    }

    public static function handle_save_appearance() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'qualinav-my-org' ) );
        }
        check_admin_referer( 'qmo_save_appearance' );
        $raw = isset( $_POST[ self::BTN_RADIUS_OPTION ] ) ? wp_unslash( $_POST[ self::BTN_RADIUS_OPTION ] ) : self::BTN_RADIUS_DEFAULT;
        self::set_btn_radius( $raw );
        wp_safe_redirect( add_query_arg(
            array( 'page' => 'qualinav-my-org-appearance', 'updated' => '1' ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    /** Owned-area detection (same logic as the sidebar highlight). */
    public static function is_owned_request() {
        $req = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $path = trim( (string) wp_parse_url( $req, PHP_URL_PATH ), '/' );
        $home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
        if ( '' !== $home && 0 === strpos( $path, $home ) ) {
            $path = trim( substr( $path, strlen( $home ) ), '/' );
        }
        $first = $path === '' ? '' : ( false === strpos( $path, '/' ) ? $path : substr( $path, 0, strpos( $path, '/' ) ) );
        $owned = array( Qualinav_My_Org_Settings::MY_ORG_SLUG, 'data-hub', 'data-management', 'qapi-dashboard' );
        if ( class_exists( 'Qualinav_My_Org_Settings' ) ) {
            foreach ( Qualinav_My_Org_Settings::all() as $sec ) {
                if ( ! empty( $sec['slug'] ) ) {
                    $owned[] = trim( $sec['slug'], '/' );
                }
            }
        }
        return in_array( $first, array_filter( array_unique( $owned ) ), true );
    }

    public static function register_settings_page() {
        // Register as a hidden page so the tab bar (rendered by
        // Qualinav_My_Org_Admin) is the only entry point — no extra
        // submenu in the sidebar.
        add_submenu_page(
            null,
            'Appearance',
            'Appearance',
            'manage_options',
            'qualinav-my-org-appearance',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $val = self::get_btn_radius();
        ?>
        <style>
            .qmo-appearance-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 28px 32px;
                margin-top: 16px;
                max-width: 760px;
                box-shadow: 0 1px 2px rgba(15, 39, 64, 0.04);
            }
            .qmo-settings-subtitle {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #94a3b8;
                margin: 0 0 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f1f5f9;
                font-weight: 700;
            }
            .qmo-shape-field { margin-bottom: 8px; }
            .qmo-field-label { display: block; }
            .qmo-label-text {
                font-weight: 600;
                font-size: 14px;
                color: #1e293b;
                display: block;
            }
            .qmo-label-hint {
                font-size: 12px;
                color: #64748b;
                font-weight: 400;
                display: block;
                margin-top: 2px;
            }
            .qmo-slider-control { margin-top: 18px; }
            .qmo-slider-header { display: flex; justify-content: flex-end; }
            .qmo-slider-current {
                font-size: 12px;
                font-weight: 700;
                color: #0f6a67;
                background: #f1f5f9;
                padding: 2px 8px;
                border-radius: 4px;
            }
            .qmo-pro-slider {
                -webkit-appearance: none;
                appearance: none;
                width: 100%;
                height: 6px;
                background: #f1f5f9;
                border-radius: 3px;
                outline: none;
                margin: 15px 0;
            }
            .qmo-pro-slider::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 18px;
                height: 18px;
                background: #fff;
                border: 2px solid #0f6a67;
                border-radius: 50%;
                cursor: pointer;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                transition: all 0.2s;
            }
            .qmo-pro-slider::-webkit-slider-thumb:hover {
                transform: scale(1.1);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            }
            .qmo-pro-slider::-moz-range-thumb {
                width: 18px;
                height: 18px;
                background: #fff;
                border: 2px solid #0f6a67;
                border-radius: 50%;
                cursor: pointer;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .qmo-slider-labels {
                display: flex;
                justify-content: space-between;
                font-size: 10px;
                color: #94a3b8;
                margin-top: -5px;
            }
            .qmo-preview-row {
                margin-top: 24px;
                padding-top: 20px;
                border-top: 1px dashed #f1f5f9;
            }
            .qmo-preview-label {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #94a3b8;
                font-weight: 700;
                margin-bottom: 10px;
                display: block;
            }
            .qmo-btn-preview {
                display: inline-block;
                padding: 10px 20px;
                background: #d0f5f9;
                color: #03283e;
                border: 1px solid #03283e;
                font-weight: 700;
                font-size: 13px;
            }
        </style>
        <div class="wrap">
            <h1>My Org &mdash; Appearance</h1>
            <?php if ( ! empty( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Appearance saved.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="qmo_save_appearance">
                <?php wp_nonce_field( 'qmo_save_appearance' ); ?>
                <div class="qmo-appearance-card">
                    <h3 class="qmo-settings-subtitle">Button Shape</h3>
                    <div class="qmo-shape-field">
                        <label for="qmo-btn-radius" class="qmo-field-label">
                            <span class="qmo-label-text">Button Corner Radius</span>
                            <span class="qmo-label-hint">Adjust from sharp corners (0px) to fully rounded (<?php echo (int) self::BTN_RADIUS_MAX; ?>px). Applies to action buttons and tiles across My Org, Data Hub, QI Projects, and Assessments. <strong>Shared with the Grapevine Menus &rarr; Branding &rarr; Menu Item Corner Radius setting</strong> &mdash; changing it here also updates the sidebar menu shape.</span>
                        </label>
                        <div class="qmo-slider-control">
                            <div class="qmo-slider-header">
                                <span class="qmo-slider-current"><span id="qmo-btn-radius-out"><?php echo esc_html( $val ); ?></span>px</span>
                            </div>
                            <input type="range" name="<?php echo esc_attr( self::BTN_RADIUS_OPTION ); ?>"
                                id="qmo-btn-radius" min="0" max="<?php echo (int) self::BTN_RADIUS_MAX; ?>" step="1"
                                value="<?php echo esc_attr( $val ); ?>"
                                class="qmo-pro-slider"
                                oninput="document.getElementById('qmo-btn-radius-out').textContent=this.value;document.getElementById('qmo-btn-preview').style.borderRadius=this.value+'px';">
                            <div class="qmo-slider-labels">
                                <span>0px</span>
                                <span><?php echo (int) self::BTN_RADIUS_MAX; ?>px</span>
                            </div>
                        </div>
                        <div class="qmo-preview-row">
                            <span class="qmo-preview-label">Preview</span>
                            <span id="qmo-btn-preview" class="qmo-btn-preview" style="border-radius:<?php echo (int) $val; ?>px;">Sample button</span>
                        </div>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function print_button_radius_style() {
        if ( ! self::is_owned_request() ) return;
        $r = (int) self::get_btn_radius();
        echo '<style id="qmo-btn-radius-style">';
        echo ':root{--qmo-btn-radius:' . $r . 'px;}';
        // Curated set of "buttons" across My Org and its bundled modules.
        // Note: tiles (.myorg-tile) are intentionally excluded — the radius
        // applies to action buttons only, not the tile launcher cards.
        echo '.qd-back-btn,.gv-back-btn,.cah-btn,.cah-button,.qi-btn-primary,.qi-btn-secondary,.dh-btn,.dm-btn{border-radius:var(--qmo-btn-radius) !important;}';
        echo '.as-tab{border-radius:0 !important;}';
        echo '</style>';
    }

    public static function register_routes() {
        add_rewrite_rule(
            '^' . preg_quote( trim( Qualinav_My_Org_Settings::MY_ORG_SLUG, '/' ), '#' ) . '/?$',
            'index.php?' . self::ROUTE_QUERY_VAR . '=' . self::ROUTE_HUB,
            'top'
        );

        add_rewrite_rule(
            '^' . preg_quote( trim( self::assessments_slug(), '/' ), '#' ) . '/?$',
            'index.php?' . self::ROUTE_QUERY_VAR . '=' . self::ROUTE_ASSESS,
            'top'
        );
    }

    public static function register_query_vars( $vars ) {
        $vars[] = self::ROUTE_QUERY_VAR;
        return $vars;
    }

    private static function current_route() {
        $route = get_query_var( self::ROUTE_QUERY_VAR );
        return is_string( $route ) ? $route : '';
    }

    public static function handle_virtual_route_404( $preempt, $wp_query ) {
        if ( in_array( self::current_route(), array( self::ROUTE_HUB, self::ROUTE_ASSESS ), true ) ) {
            $wp_query->is_404 = false;
            status_header( 200 );
            return true;
        }

        return $preempt;
    }

    /**
     * Bundled-module setup that does NOT self-heal on its own, run here
     * (version/flag-gated, so it's effectively one-time):
     *
     *  - QI Projects only self-heals its 11 tables on admin_init; a pure
     *    front-end first hit could land first, so re-run its activator.
     *  - do-tank-table-chart's `dttc_chart_rows` table is created ONLY by its
     *    activation hook (no version-gated runner upstream), so create it
     *    once here; caps + the notifications table self-heal on their own.
     */
    public static function ensure_module_setup() {
        if (
            class_exists( 'Qualinav_QI_Activator' )
            && defined( 'QUALINAV_QI_DB_VERSION' )
            && get_option( 'qualinav_qi_db_version' ) !== QUALINAV_QI_DB_VERSION
        ) {
            Qualinav_QI_Activator::activate();
        }

        if (
            function_exists( 'dttc_install_db' )
            && get_option( 'qualinav_my_org_dttc_ready' ) !== '1'
        ) {
            dttc_install_db(); // idempotent dbDelta CREATE TABLE dttc_chart_rows
            if ( function_exists( 'dttc_install_notifications_table' ) ) {
                dttc_install_notifications_table();
            }
            if ( function_exists( 'dttc_add_chart_caps' ) ) {
                dttc_add_chart_caps();
            }
            update_option( 'qualinav_my_org_dttc_ready', '1' );
        }

        if ( function_exists( 'qualinav_data_hub_improvement_calculator_maybe_install' ) ) {
            qualinav_data_hub_improvement_calculator_maybe_install();
        }

        if ( function_exists( 'qualinav_data_hub_mbqip_maybe_install' ) ) {
            qualinav_data_hub_mbqip_maybe_install();
        }
    }

    public static function register_template( $templates ) {
        $templates[ self::TEMPLATE ]        = 'My Org (Hub)';
        $templates[ self::TEMPLATE_ASSESS ] = 'My Org Assessments';
        return $templates;
    }

    public static function load_template( $template ) {
        $route = self::current_route();
        if ( self::ROUTE_HUB === $route ) {
            $candidate = QUALINAV_MY_ORG_PLUGIN_DIR . 'templates/' . self::TEMPLATE;
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
        }

        if ( self::ROUTE_ASSESS === $route ) {
            $candidate = QUALINAV_MY_ORG_PLUGIN_DIR . 'templates/' . self::TEMPLATE_ASSESS;
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
        }

        global $post;
        if ( ! $post ) {
            return $template;
        }
        $selected = get_post_meta( $post->ID, '_wp_page_template', true );
        $slug     = isset( $post->post_name ) ? $post->post_name : '';

        if ( $selected === self::TEMPLATE || $slug === Qualinav_My_Org_Settings::MY_ORG_SLUG ) {
            $candidate = QUALINAV_MY_ORG_PLUGIN_DIR . 'templates/' . self::TEMPLATE;
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
        }

        if ( $selected === self::TEMPLATE_ASSESS || $slug === self::assessments_slug() ) {
            $candidate = QUALINAV_MY_ORG_PLUGIN_DIR . 'templates/' . self::TEMPLATE_ASSESS;
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
        }
        return $template;
    }

    /**
     * If a visitor hits a section whose toggle is OFF, send them back to the
     * hub instead of the (now hidden) section page. Matches on the first path
     * segment so deeper URLs inside an enabled section are untouched.
     */
    public static function guard_disabled_sections() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }
        $path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        if ( ! $path ) {
            return;
        }
        $home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
        $path      = trim( $path, '/' );
        if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
            $path = ltrim( substr( $path, strlen( $home_path ) ), '/' );
        }
        $first_segment = strtolower( $path );
        $slash_pos     = strpos( $first_segment, '/' );
        if ( false !== $slash_pos ) {
            $first_segment = substr( $first_segment, 0, $slash_pos );
        }
        if ( '' === $first_segment ) {
            return;
        }
        $matched_disabled_section = false;
        foreach ( Qualinav_My_Org_Settings::all() as $section ) {
            if ( empty( $section['slug'] ) ) {
                continue; // custom sections may point anywhere; don't police them.
            }
            if ( trim( $section['slug'], '/' ) !== $first_segment ) {
                continue;
            }
            if ( ! empty( $section['enabled'] ) ) {
                return;
            }
            $matched_disabled_section = true;
        }
        if ( $matched_disabled_section ) {
            wp_safe_redirect( home_url( '/' . Qualinav_My_Org_Settings::MY_ORG_SLUG . '/' ), 302 );
            exit;
        }
    }

    public static function enqueue_assets() {
        if ( ! self::is_my_org_page() ) {
            return;
        }
        wp_enqueue_style(
            'qualinav-my-org',
            QUALINAV_MY_ORG_PLUGIN_URL . 'assets/css/my-org.css',
            array(),
            QUALINAV_MY_ORG_VERSION
        );
        wp_enqueue_style(
            'qualinav-my-org-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
            array(),
            QUALINAV_MY_ORG_VERSION
        );
    }

    public static function body_class( $classes ) {
        if ( self::is_my_org_page() ) {
            $classes[] = 'page-my-org';
        }
        if ( self::is_assessments_page() ) {
            $classes[] = 'page-org-assessments';
        }
        return $classes;
    }

    /**
     * Keep the "My Org" sidebar item highlighted across every area My Org
     * orchestrates (the hub plus Data Hub, QI Projects, Assessments and any
     * custom sections), even though those live at sibling top-level slugs.
     * Hooked onto grapevine-menus' `dtam_menu_item_active` filter.
     *
     * @param bool   $active   Active state decided so far.
     * @param object $item     Menu item (has ->url).
     * @param string $cur_url  Current absolute URL (no query, no trailing /).
     * @param string $item_url Item absolute URL (no trailing /).
     * @return bool
     */
    public static function highlight_owned_nav( $active, $item, $cur_url, $item_url ) {
        $first = function ( $url ) {
            $path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
            $home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
            if ( '' !== $home && 0 === strpos( $path, $home ) ) {
                $path = trim( substr( $path, strlen( $home ) ), '/' );
            }
            $slash = strpos( $path, '/' );
            return false === $slash ? $path : substr( $path, 0, $slash );
        };

        // Only act on the "My Org" item itself; leave every other item as-is.
        if ( $first( $item_url ) !== Qualinav_My_Org_Settings::MY_ORG_SLUG ) {
            return $active;
        }

        $owned = array(
            Qualinav_My_Org_Settings::MY_ORG_SLUG,
            'data-hub', 'data-management', 'qapi-dashboard', // Data Hub area
        );
        if ( class_exists( 'Qualinav_My_Org_Settings' ) ) {
            foreach ( Qualinav_My_Org_Settings::all() as $sec ) {
                if ( ! empty( $sec['slug'] ) ) {
                    $owned[] = trim( $sec['slug'], '/' );
                } elseif ( ! empty( $sec['href'] ) ) {
                    $owned[] = $first( $sec['href'] );
                }
            }
        }

        return in_array( $first( $cur_url ), array_filter( array_unique( $owned ) ), true );
    }

    public static function is_my_org_page() {
        if ( self::ROUTE_HUB === self::current_route() ) {
            return true;
        }

        if ( ! is_page() ) {
            return false;
        }
        global $post;
        if ( ! $post ) {
            return false;
        }
        $selected = get_post_meta( $post->ID, '_wp_page_template', true );
        if ( $selected === self::TEMPLATE ) {
            return true;
        }
        return ( isset( $post->post_name ) && $post->post_name === Qualinav_My_Org_Settings::MY_ORG_SLUG );
    }

    public static function is_assessments_page() {
        if ( self::ROUTE_ASSESS === self::current_route() ) {
            return true;
        }

        if ( ! is_page() ) {
            return false;
        }
        global $post;
        if ( ! $post ) {
            return false;
        }
        $selected = get_post_meta( $post->ID, '_wp_page_template', true );
        if ( $selected === self::TEMPLATE_ASSESS ) {
            return true;
        }
        return ( isset( $post->post_name ) && $post->post_name === self::assessments_slug() );
    }
}

add_action( 'plugins_loaded', array( 'Qualinav_My_Org_Plugin', 'boot' ) );

register_activation_hook( __FILE__, function() {
    delete_option( Qualinav_My_Org_Plugin::PAGE_BOOTSTRAP_OPTION );
    delete_option( Qualinav_My_Org_Plugin::ASSESS_SLUG_OPTION );
    delete_option( 'qualinav_my_org_dttc_ready' );
    Qualinav_My_Org_Plugin::register_routes();
    if ( class_exists( 'Qualinav_Data_Hub_Plugin' ) ) {
        Qualinav_Data_Hub_Plugin::register_routes();
    }
    if ( function_exists( 'qualinav_data_hub_improvement_calculator_install' ) ) {
        qualinav_data_hub_improvement_calculator_install();
    }
    if ( function_exists( 'qualinav_data_hub_mbqip_install' ) ) {
        qualinav_data_hub_mbqip_install();
    }
    if ( class_exists( 'Qualinav_Data_Hub_Measure_Library' ) ) {
        Qualinav_Data_Hub_Measure_Library::install();
    }
    flush_rewrite_rules( false );
} );
