<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Router
{
    public static function init()
    {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_filter('pre_handle_404', array(__CLASS__, 'handle_site_console_404'), 10, 2);
        add_action('template_redirect', array(__CLASS__, 'render_console_routes'), 0);
        add_filter('template_include', array(__CLASS__, 'load_site_console_template'), 5);
        add_filter('body_class', array(__CLASS__, 'site_console_body_class'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_action('wp_footer', array(__CLASS__, 'render_home_welcome_modal'));
    }

    public static function add_rewrite_rules()
    {
        add_rewrite_rule('^qualinav/accept-invite/?$', 'index.php?qualinav_console=accept_invite', 'top');
        add_rewrite_rule('^qualinav/admin/?$', 'index.php?qualinav_console=admin', 'top');
        add_rewrite_rule('^qualinav/?$', 'index.php?qualinav_console=hospital', 'top');
        add_rewrite_rule('^organization-setup/?$', 'index.php?qualinav_site_console=organization_setup', 'top');
    }

    public static function add_query_vars($vars)
    {
        $vars[] = 'qualinav_console';
        $vars[] = 'qualinav_site_console';

        return $vars;
    }

    public static function register_assets()
    {
        $css_file = QN_ADMIN_CONSOLE_DIR . 'assets/css/qualinav-console.css';
        $js_file = QN_ADMIN_CONSOLE_DIR . 'assets/js/qualinav-console.js';
        $css_version = QN_ADMIN_CONSOLE_VERSION . '-' . (file_exists($css_file) ? filemtime($css_file) : '1');
        $js_version = QN_ADMIN_CONSOLE_VERSION . '-' . (file_exists($js_file) ? filemtime($js_file) : '1');

        wp_register_style(
            'qualinav-console',
            QN_ADMIN_CONSOLE_URL . 'assets/css/qualinav-console.css',
            array(),
            $css_version
        );

        wp_register_script(
            'qualinav-console',
            QN_ADMIN_CONSOLE_URL . 'assets/js/qualinav-console.js',
            array(),
            $js_version,
            true
        );

        if (self::is_site_console_route()) {
            self::enqueue_console_assets(self::site_console_config());
        }

        if (self::is_home_welcome_route()) {
            self::enqueue_console_assets(self::home_welcome_config());
        }
    }

    public static function handle_site_console_404($preempt, $wp_query)
    {
        if (self::is_site_console_route()) {
            $wp_query->is_404 = false;
            status_header(200);
            return true;
        }

        return $preempt;
    }

    public static function load_site_console_template($template)
    {
        if (!self::is_site_console_route()) {
            return $template;
        }

        self::prepare_hospital_console_request(true);
        self::enqueue_console_assets(self::site_console_config());

        $candidate = QN_ADMIN_CONSOLE_DIR . 'templates/site-shell-console.php';

        return file_exists($candidate) ? $candidate : $template;
    }

    public static function site_console_body_class($classes)
    {
        if (self::is_site_console_route()) {
            $classes[] = 'qualinav-console-page';
            $classes[] = 'qn-hospital-console-page';
            $classes[] = 'qn-myorg-embedded-console';
            $classes[] = 'qn-site-shell-console';
        }
        if (self::is_home_welcome_route()) {
            $classes[] = 'qualinav-home-welcome-page';
        }

        return $classes;
    }

    public static function render_console_routes()
    {
        $console = get_query_var('qualinav_console');
        if (!$console) {
            return;
        }

        if ($console === 'accept_invite') {
            self::render_accept_invite();
        }

        if ($console === 'admin') {
            QN_Permissions::require_permission('access_super_admin');
            self::render_template('admin-console-shell.php');
        }

        if ($console === 'hospital') {
            self::prepare_hospital_console_request(true);
            self::render_template('console-shell.php');
        }
    }

    private static function render_accept_invite()
    {
        $raw_token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $invitation = $raw_token ? QN_Invitations::get_invitation_by_token($raw_token) : null;
        $invite_error = null;
        $accept_error = null;

        if (!$raw_token) {
            $invite_error = __('This invitation link is missing a token.', 'qualinav-admin-console');
        } elseif ($invitation && isset($invitation['status']) && $invitation['status'] === 'accepted') {
            $accepted_redirect = QN_Invitations::accepted_invitation_redirect($invitation);
            if (is_wp_error($accepted_redirect)) {
                $invite_error = $accepted_redirect->get_error_message();
            } elseif (is_user_logged_in()) {
                wp_safe_redirect($accepted_redirect['redirect']);
                exit;
            } elseif (QN_Invitations::accepted_invitation_allows_magic_login($invitation) && function_exists('gvl_magic_create_login_url')) {
                $magic_url = gvl_magic_create_login_url($accepted_redirect['email'], $accepted_redirect['redirect']);
                if (is_wp_error($magic_url)) {
                    wp_safe_redirect(wp_login_url($accepted_redirect['redirect']));
                    exit;
                }
                wp_safe_redirect($magic_url);
                exit;
            } else {
                wp_safe_redirect(wp_login_url($accepted_redirect['redirect']));
                exit;
            }
        } else {
            $valid = QN_Invitations::validate_invitation_for_acceptance($invitation);
            if (is_wp_error($valid)) {
                $invite_error = $valid->get_error_message();
            }
        }

        if (!$invite_error && $invitation && QN_Invitations::is_hospital_invitation_role($invitation['qualinav_role'])) {
            if (!is_user_logged_in() && !function_exists('gvl_magic_create_login_url')) {
                $invite_error = __('Secure magic-link sign-in is not available right now. Please contact your QualiNav administrator.', 'qualinav-admin-console');
            } else {
                $accepted = QN_Invitations::accept_invitation_for_magic_handoff($raw_token);
                if (is_wp_error($accepted)) {
                    $invite_error = $accepted->get_error_message();
                } elseif (is_user_logged_in()) {
                    wp_safe_redirect($accepted['redirect']);
                    exit;
                } else {
                    $magic_url = gvl_magic_create_login_url($accepted['email'], $accepted['redirect']);
                    if (is_wp_error($magic_url)) {
                        $invite_error = __('Unable to create a secure sign-in link for this invitation. Please contact your QualiNav administrator.', 'qualinav-admin-console');
                    } else {
                        wp_safe_redirect($magic_url);
                        exit;
                    }
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invite_error) {
            if (!isset($_POST['qn_accept_invite_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['qn_accept_invite_nonce'])), 'qn_accept_invite')) {
                $accept_error = __('The invitation form expired. Please try again.', 'qualinav-admin-console');
            } else {
                $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
                $confirm = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';
                $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';

                if ($password !== $confirm) {
                    $accept_error = __('Passwords do not match.', 'qualinav-admin-console');
                } else {
                    $accepted = QN_Invitations::accept_invitation($raw_token, $password, $display_name);
                    if (is_wp_error($accepted)) {
                        $accept_error = $accepted->get_error_message();
                    } else {
                        wp_set_current_user($accepted['user_id']);
                        wp_set_auth_cookie($accepted['user_id'], true);
                        wp_safe_redirect($accepted['redirect']);
                        exit;
                    }
                }
            }
        }

        self::register_assets();
        wp_enqueue_style('dashicons');
        wp_enqueue_style('qualinav-console');
        wp_enqueue_script('qualinav-console');
        wp_add_inline_script(
            'qualinav-console',
            'window.QualiNavConsole = ' . wp_json_encode(array(
                'restUrl' => esc_url_raw(rest_url('qualinav/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'homeUrl' => esc_url_raw(home_url('/')),
            )) . ';',
            'before'
        );
        status_header(200);
        nocache_headers();
        include QN_ADMIN_CONSOLE_DIR . 'templates/accept-invite.php';
        exit;
    }

    private static function render_template($template)
    {
        self::register_assets();
        $shell_mode = '';
        if ($template === 'console-shell.php' && isset($_GET['shell'])) {
            $requested_shell = sanitize_key(wp_unslash($_GET['shell']));
            if ($requested_shell === 'my-org') {
                $shell_mode = 'my-org';
            }
        }
        $console_config = array(
            'restUrl' => esc_url_raw(rest_url('qualinav/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => esc_url_raw(home_url('/')),
            'shellMode' => $shell_mode,
            'isEmbeddedShell' => $shell_mode === 'my-org',
        );
        self::enqueue_console_assets($console_config);

        $current_user = QN_Users::get_current_user_row();

        status_header(200);
        nocache_headers();
        include QN_ADMIN_CONSOLE_DIR . 'templates/' . $template;
        exit;
    }

    private static function is_site_console_route()
    {
        return get_query_var('qualinav_site_console') === 'organization_setup';
    }

    private static function is_home_welcome_route()
    {
        if (!is_front_page() || empty($_GET['qualinav_welcome']) || !is_user_logged_in()) {
            return false;
        }

        $welcome_organization_id = self::welcome_organization_id();
        if (isset($_GET['organization_id']) && !$welcome_organization_id) {
            return false;
        }

        $role = $welcome_organization_id
            ? QN_Users::get_role_for_organization(get_current_user_id(), $welcome_organization_id)
            : QN_Users::get_user_qualinav_role(get_current_user_id());
        return in_array($role, array('quality_director', 'executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user'), true);
    }

    private static function welcome_organization_id()
    {
        if (empty($_GET['organization_id'])) {
            return 0;
        }

        $organization_id = absint($_GET['organization_id']);
        if (!$organization_id || !QN_Users::user_has_organization(get_current_user_id(), $organization_id)) {
            return 0;
        }

        return $organization_id;
    }

    private static function prepare_hospital_console_request($redirect_global_admin_without_org)
    {
        if (
            $redirect_global_admin_without_org
            && QN_Permissions::user_can(get_current_user_id(), 'access_super_admin')
            && !isset($_GET['organization_id'])
        ) {
            wp_safe_redirect(home_url('/qualinav/admin'));
            exit;
        }

        if (isset($_GET['organization_id'])) {
            QN_Users::set_current_organization(get_current_user_id(), absint($_GET['organization_id']));
            QN_Invitations::clear_completed_handoff_for_organization(get_current_user_id(), absint($_GET['organization_id']));
        }

        if (
            !QN_Permissions::user_can(get_current_user_id(), 'access_hospital_console')
            && !QN_Permissions::user_can(get_current_user_id(), 'access_super_admin')
        ) {
            QN_Permissions::require_permission('access_hospital_console');
        }
    }

    public static function site_console_config()
    {
        return array(
            'restUrl' => esc_url_raw(rest_url('qualinav/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => esc_url_raw(home_url('/')),
            'shellMode' => 'my-org',
            'isEmbeddedShell' => true,
            'isSiteShellConsole' => true,
            'defaultSection' => 'day-0-setup',
        );
    }

    public static function home_welcome_config()
    {
        $welcome_organization_id = self::welcome_organization_id();
        if ($welcome_organization_id) {
            QN_Users::set_current_organization(get_current_user_id(), $welcome_organization_id);
        }

        return array(
            'restUrl' => esc_url_raw(rest_url('qualinav/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => esc_url_raw(home_url('/')),
            'isHomeWelcomePage' => true,
            'welcomeOrganizationId' => $welcome_organization_id,
        );
    }

    public static function render_home_welcome_modal()
    {
        if (!self::is_home_welcome_route()) {
            return;
        }

        include QN_ADMIN_CONSOLE_DIR . 'templates/partials/workspace-welcome-modal.php';
    }

    private static function enqueue_console_assets($console_config)
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('qualinav-console');
        wp_enqueue_script('qualinav-console');
        wp_add_inline_script(
            'qualinav-console',
            'window.QualiNavConsole = ' . wp_json_encode($console_config) . ';',
            'before'
        );
    }
}
