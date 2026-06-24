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
        add_action('template_redirect', array(__CLASS__, 'render_console_routes'), 0);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    public static function add_rewrite_rules()
    {
        add_rewrite_rule('^qualinav/accept-invite/?$', 'index.php?qualinav_console=accept_invite', 'top');
        add_rewrite_rule('^qualinav/admin/?$', 'index.php?qualinav_console=admin', 'top');
        add_rewrite_rule('^qualinav/?$', 'index.php?qualinav_console=hospital', 'top');
    }

    public static function add_query_vars($vars)
    {
        $vars[] = 'qualinav_console';

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
            if (
                QN_Permissions::user_can(get_current_user_id(), 'access_super_admin')
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
        wp_enqueue_style('dashicons');
        wp_enqueue_style('qualinav-console');
        wp_enqueue_script('qualinav-console');
        $console_config = array(
            'restUrl' => esc_url_raw(rest_url('qualinav/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => esc_url_raw(home_url('/')),
        );
        wp_add_inline_script(
            'qualinav-console',
            'window.QualiNavConsole = ' . wp_json_encode($console_config) . ';',
            'before'
        );

        $current_user = QN_Users::get_current_user_row();

        status_header(200);
        nocache_headers();
        include QN_ADMIN_CONSOLE_DIR . 'templates/' . $template;
        exit;
    }
}
