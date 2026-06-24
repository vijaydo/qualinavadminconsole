<?php
/**
 * Template Name: QualiNav QAPI Dashboard
 * Description: Quainav QAPI dashboard report builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url() );
    exit;
}

get_header();
include dirname( __FILE__ ) . '/partials/dashboard-content.php';
get_footer();

