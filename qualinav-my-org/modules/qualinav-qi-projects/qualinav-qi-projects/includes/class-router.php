<?php
/**
 * Owns the public-facing URLs for the QI Projects feature so the plugin renders
 * its own dashboard / canvas without requiring a WP page + template-dropdown
 * setup. URLs:
 *
 *   /qi-projects/           → dashboard
 *   /qi-projects/<id>/      → canvas for project <id>
 *   /qi-projects/?qi=<id>   → canvas (legacy query-string form, used by the
 *                             create-project JS redirect)
 *
 * Rules are registered with the `top` priority so they intercept the URL
 * before WP's page lookup — any leftover WP page at the same slug becomes
 * inert (the plugin route wins) but is left in place so users can delete it
 * at their leisure.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Router {

	const DASHBOARD_PATH = 'qi-projects';
	const QUERY_VAR_VIEW = 'qualinav_qi_view';
	const QUERY_VAR_QI   = 'qi';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'route_template' ), 99 );
	}

	public static function register_rewrite_rules() {
		add_rewrite_rule(
			'^' . self::DASHBOARD_PATH . '/?$',
			'index.php?' . self::QUERY_VAR_VIEW . '=dashboard',
			'top'
		);
		add_rewrite_rule(
			'^' . self::DASHBOARD_PATH . '/([0-9]+)/?$',
			'index.php?' . self::QUERY_VAR_VIEW . '=dashboard&' . self::QUERY_VAR_QI . '=$matches[1]',
			'top'
		);
	}

	public static function register_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR_VIEW;
		$vars[] = self::QUERY_VAR_QI;
		return $vars;
	}

	public static function route_template( $template ) {
		if ( ! self::is_plugin_route() ) {
			return $template;
		}

		// View=dashboard with a qi id (path segment or ?qi=) means the canvas.
		$qi = self::current_project_id();
		if ( $qi > 0 ) {
			$canvas = QUALINAV_QI_PLUGIN_DIR . 'templates/page-qi-project-canvas.php';
			if ( file_exists( $canvas ) ) {
				return $canvas;
			}
		}

		$dashboard = QUALINAV_QI_PLUGIN_DIR . 'templates/page-qi-projects.php';
		if ( file_exists( $dashboard ) ) {
			return $dashboard;
		}
		return $template;
	}

	public static function is_plugin_route() {
		return (string) get_query_var( self::QUERY_VAR_VIEW ) !== '';
	}

	public static function current_project_id() {
		$from_query = (int) get_query_var( self::QUERY_VAR_QI );
		if ( $from_query > 0 ) {
			return $from_query;
		}
		if ( isset( $_GET[ self::QUERY_VAR_QI ] ) ) {
			return (int) $_GET[ self::QUERY_VAR_QI ];
		}
		return 0;
	}

	public static function dashboard_url() {
		return home_url( '/' . self::DASHBOARD_PATH . '/' );
	}

	public static function canvas_url_for_project( $project_id ) {
		return home_url( '/' . self::DASHBOARD_PATH . '/' . (int) $project_id . '/' );
	}
}
