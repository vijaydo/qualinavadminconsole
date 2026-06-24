<?php
/**
 * Plugin Name: Data Hub
 * Plugin URI:  https://qualinav.com
 * Description: Standalone Data Hub: tabbed page combining Data Management and Dashboard Reports. Owns /data-hub/ and redirects legacy /data-management/ and /qapi-dashboard/ slugs to it. Optionally mirrors uploads to Google Drive under state/organization/user-id.
 * Version:     0.4.7
 * Author:      Grapevine Team / Markelo Rapti
 * License:     GPL v2 or later
 * Text Domain: data-hub
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'QUALINAV_DATA_HUB_VERSION', '0.4.7' );
define( 'QUALINAV_DATA_HUB_PLUGIN_FILE', __FILE__ );
define( 'QUALINAV_DATA_HUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QUALINAV_DATA_HUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load the merged QAPI Dashboard module. The included file's own
// `class_exists` guard handles the case where the legacy standalone
// qualinav-qapi-dashboard plugin already loaded the class first.
require_once QUALINAV_DATA_HUB_PLUGIN_DIR . 'qapi-dashboard.php';

// Data Management manual-entry save handler (moved out of qualinav-pages).
require_once QUALINAV_DATA_HUB_PLUGIN_DIR . 'includes/dm-save-handler.php';

// Improvement Calculator persistence schema.
require_once QUALINAV_DATA_HUB_PLUGIN_DIR . 'includes/improvement-calculator-db.php';

// MBQIP persistence schema and measure definitions.
require_once QUALINAV_DATA_HUB_PLUGIN_DIR . 'includes/mbqip-db.php';

// Google Drive mirroring for Data Management uploads.
require_once QUALINAV_DATA_HUB_PLUGIN_DIR . 'includes/drive-service.php';
if ( is_admin() ) {
	require_once QUALINAV_DATA_HUB_PLUGIN_DIR . 'includes/drive-settings.php';
}

// The module registers itself on the `plugins_loaded` action — but if the
// require above ran AFTER plugins_loaded already fired (e.g. cached
// loading order), the action would never run. Force-instantiate now so the
// shortcode + hooks register reliably regardless of load order.
if ( class_exists( 'Quainav_Qapi_Dasboard' ) && method_exists( 'Quainav_Qapi_Dasboard', 'instance' ) ) {
	Quainav_Qapi_Dasboard::instance();
}

	class Qualinav_Data_Hub_Plugin {

		const TEMPLATE_HUB = 'page-data-hub.php';
		const SLUG_HUB     = 'data-hub';
		const ROUTE_QUERY_VAR = 'qualinav_data_hub_route';
		const ROUTE_HUB       = 'hub';

	/**
	 * Legacy slugs that should redirect to /data-hub/ with the matching tab.
	 * Map: legacy slug => tab id inside the hub.
	 */
	const LEGACY_SLUG_REDIRECTS = array(
		'data-management' => 'dm',
		'qapi-dashboard'  => 'dashboard',
	);

		const PAGE_BOOTSTRAP_OPTION = 'qualinav_data_hub_page_bootstrapped';

		public static function boot() {
			add_action( 'init',                 array( __CLASS__, 'register_routes' ), 0 );
			add_filter( 'query_vars',           array( __CLASS__, 'register_query_vars' ) );
			add_filter( 'pre_handle_404',       array( __CLASS__, 'handle_virtual_route_404' ), 10, 2 );
			add_filter( 'theme_page_templates', array( __CLASS__, 'register_templates' ) );
			add_filter( 'template_include',     array( __CLASS__, 'load_template' ), 5 );
			// Catch legacy URLs as early as possible — even before WP resolves the page object —
			// so a draft/missing legacy page still redirects instead of 404ing.
			add_action( 'parse_request',        array( __CLASS__, 'redirect_legacy_slugs' ), 1 );
			add_action( 'wp_enqueue_scripts',   array( __CLASS__, 'enqueue_assets' ) );
			add_filter( 'body_class',           array( __CLASS__, 'body_class' ) );
		}

		public static function register_routes() {
			add_rewrite_rule(
				'^' . preg_quote( trim( self::SLUG_HUB, '/' ), '#' ) . '/?$',
				'index.php?' . self::ROUTE_QUERY_VAR . '=' . self::ROUTE_HUB,
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
			if ( self::ROUTE_HUB === self::current_route() ) {
				$wp_query->is_404 = false;
				status_header( 200 );
				return true;
			}

			return $preempt;
		}

		public static function register_templates( $templates ) {
		$templates[ self::TEMPLATE_HUB ] = 'Data Hub';
		return $templates;
	}

		public static function load_template( $template ) {
			if ( self::ROUTE_HUB === self::current_route() ) {
				$candidate = QUALINAV_DATA_HUB_PLUGIN_DIR . 'templates/' . self::TEMPLATE_HUB;
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

		if ( $selected === self::TEMPLATE_HUB || ( $slug === self::SLUG_HUB && ( empty( $selected ) || $selected === 'default' ) ) ) {
			$candidate = QUALINAV_DATA_HUB_PLUGIN_DIR . 'templates/' . self::TEMPLATE_HUB;
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}
		return $template;
	}

	/**
	 * Send legacy /data-management/ and /qapi-dashboard/ traffic to /data-hub/
	 * so the Data Hub slug is the single canonical entry point. Matches on the
	 * request URI's first path segment to also catch unpublished legacy pages.
	 */
	public static function redirect_legacy_slugs() {
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
		// Strip any WP subdirectory install prefix so we compare from the site root.
		$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$path      = trim( $path, '/' );
		if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = ltrim( substr( $path, strlen( $home_path ) ), '/' );
		}
		// Only consider the first path segment so we don't redirect deeper URLs.
		$first_segment = strtolower( $path );
		$slash_pos     = strpos( $first_segment, '/' );
		if ( false !== $slash_pos ) {
			$first_segment = substr( $first_segment, 0, $slash_pos );
		}
		if ( ! isset( self::LEGACY_SLUG_REDIRECTS[ $first_segment ] ) ) {
			return;
		}
		$tab    = self::LEGACY_SLUG_REDIRECTS[ $first_segment ];
		$target = home_url( '/' . self::SLUG_HUB . '/#' . $tab );
		wp_safe_redirect( $target, 301 );
		exit;
	}

	public static function enqueue_assets() {
		if ( ! self::is_data_hub_page() ) {
			return;
		}
		wp_enqueue_style(
			'data-hub',
			QUALINAV_DATA_HUB_PLUGIN_URL . 'assets/css/data-hub.css',
			array(),
			QUALINAV_DATA_HUB_VERSION
		);
	}

	public static function body_class( $classes ) {
		if ( self::is_data_hub_page() ) {
			$classes[] = 'page-data-hub';
		}
		return $classes;
	}

		public static function is_data_hub_page() {
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
		if ( $selected === self::TEMPLATE_HUB ) {
			return true;
		}
		return ( isset( $post->post_name ) && $post->post_name === self::SLUG_HUB );
	}
}

add_action( 'plugins_loaded', array( 'Qualinav_Data_Hub_Plugin', 'boot' ) );

	// On activation, flush rewrites for the plugin-owned /data-hub/ route.
	register_activation_hook( __FILE__, function() {
		delete_option( Qualinav_Data_Hub_Plugin::PAGE_BOOTSTRAP_OPTION );
		Qualinav_Data_Hub_Plugin::register_routes();
		flush_rewrite_rules( false );
	} );
