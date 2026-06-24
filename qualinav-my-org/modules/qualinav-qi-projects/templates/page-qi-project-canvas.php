<?php
/**
 * Template Name: QI Project — Canvas
 * Description: Renders the 4-tab canvas (Improvement / Matrix / Gameplan / Commit) for a single QI project. The project is resolved from the ?qi=<id> URL parameter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url() );
	exit;
}

get_header();

echo do_shortcode( '[qi_project_canvas]' );

get_footer();
