<?php
/**
 * Template Name: QI Projects — Dashboard
 * Description: List of QI projects for the current user's org. Use the page editor's Template dropdown to select this on the page that should host the dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url() );
	exit;
}

get_header();

echo do_shortcode( '[qi_projects_dashboard]' );

get_footer();
