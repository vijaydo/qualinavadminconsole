<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Auth
{
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'block_wp_admin_access'));
        add_filter('show_admin_bar', array(__CLASS__, 'hide_admin_bar_for_non_admins'));
        add_filter('login_redirect', array(__CLASS__, 'login_redirect'), 10, 3);
    }

    public static function block_wp_admin_access()
    {
        if (!is_user_logged_in() || wp_doing_ajax()) {
            return;
        }

        if (self::current_user_is_wp_administrator()) {
            return;
        }

        wp_safe_redirect(home_url('/qualinav'));
        exit;
    }

    public static function hide_admin_bar_for_non_admins($show)
    {
        if (is_user_logged_in() && !self::current_user_is_wp_administrator()) {
            return false;
        }

        return $show;
    }

    private static function current_user_is_wp_administrator()
    {
        $user = wp_get_current_user();

        return $user instanceof WP_User && in_array('administrator', (array) $user->roles, true);
    }

    public static function login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if (!$user instanceof WP_User || empty($user->ID)) {
            return $redirect_to;
        }

        $role = QN_Users::get_user_qualinav_role($user->ID);

        if (in_array($role, array('qualinav_super_admin', 'qualinav_admin'), true)) {
            return home_url('/qualinav/admin');
        }

        return home_url('/qualinav');
    }
}
