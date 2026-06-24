<?php
/**
 * Plugin Name: Do Tank Table Chart
 * Plugin URI:  https://dotankdo.com/
 * Description: Editable table with automatic median (median of Numerator), line chart, exports (CSV/PNG/PDF), and Rule of 7 trend notes.
 * Version: 1.6.4
 * Author:      Maxim Dascalasu
 * Author URI:  https://dascalasu.com
 * License:     GPL v2 or later
 * Text Domain: do-tank-table-chart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DTTC_VERSION', '1.6.4' );
define( 'DTTC_PATH', plugin_dir_path( __FILE__ ) );
define( 'DTTC_URL', plugin_dir_url( __FILE__ ) );

require_once DTTC_PATH . 'includes/enqueue.php';
require_once DTTC_PATH . 'includes/shortcode.php';
require_once DTTC_PATH . 'includes/admin-help.php';

// Multi-chart workspace (Option A: CPT + custom rows table).
require_once DTTC_PATH . 'includes/db.php';
require_once DTTC_PATH . 'includes/cpt.php';
require_once DTTC_PATH . 'includes/rest.php';
require_once DTTC_PATH . 'includes/workspace-shortcode.php';
require_once DTTC_PATH . 'includes/notifications.php';

/**
 * Install DB table on activation.
 */
function dttc_activate_plugin() : void {
    dttc_install_db();
    dttc_install_notifications_table();

    // Option 2 permissions: allow all logged-in roles to create/edit/delete
    // their own charts, while admins can manage all charts.
    if ( function_exists( 'dttc_add_chart_caps' ) ) {
        dttc_add_chart_caps();
    }
}
register_activation_hook( __FILE__, 'dttc_activate_plugin' );

/**
 * Add custom CPT capabilities for charts.
 *
 * We use custom caps (dttc_chart/dttc_charts) so we can safely grant
 * chart permissions to all roles (including Subscribers) without opening
 * up core post editing capabilities.
 */
function dttc_add_chart_caps() : void {
    $roles = array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' );

    // Minimal set required for users to create, edit, and delete *their own* published charts.
    $user_caps = array(
        'read_dttc_chart',
        'edit_dttc_chart',
        'edit_dttc_charts',
        'edit_published_dttc_charts',
        'publish_dttc_charts',
        'delete_dttc_chart',
        'delete_dttc_charts',
        'delete_published_dttc_charts',
    );

    // Full set for administrators.
    $admin_extra_caps = array(
        'read_private_dttc_charts',
        'edit_private_dttc_charts',
        'edit_others_dttc_charts',
        'delete_private_dttc_charts',
        'delete_others_dttc_charts',
    );

    foreach ( $roles as $role_key ) {
        $role = get_role( $role_key );
        if ( ! $role ) {
            continue;
        }

        foreach ( $user_caps as $cap ) {
            $role->add_cap( $cap );
        }

        if ( 'administrator' === $role_key ) {
            foreach ( $admin_extra_caps as $cap ) {
                $role->add_cap( $cap );
            }
        }
    }
}

/**
 * Ensure caps are in place after role changes or plugin updates.
 */
add_action( 'init', function () {
    // Only run in admin to avoid unnecessary front-end work.
    if ( ! is_admin() ) {
        return;
    }

    $v = (string) get_option( 'dttc_version', '' );
    if ( $v !== DTTC_VERSION ) {
        dttc_add_chart_caps();
        update_option( 'dttc_version', DTTC_VERSION );
    }
} );

/**
 * Cleanup chart rows when a chart is permanently deleted.
 */
function dttc_cleanup_rows_on_delete( int $post_id ) : void {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'dttc_chart' ) {
        return;
    }

    if ( function_exists( 'dttc_delete_chart_rows' ) ) {
        dttc_delete_chart_rows( (int) $post_id );
    }
}
add_action( 'before_delete_post', 'dttc_cleanup_rows_on_delete' );
