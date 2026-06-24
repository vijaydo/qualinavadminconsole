<?php
/**
 * Admin area bootstrap.
 *
 * Registers the top-level "QI Projects" menu with two screens:
 *   - Overview      : cross-org monitoring (stats, projects, activity, orgs)
 *   - Template Editor: form-based editing of the Improvement Charter template
 *                       (tabs, sections, and bundled canvas images)
 *
 * All screens require `manage_options` (platform/site admin). The admin views
 * are intentionally cross-org: this is the super-admin monitoring surface.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Admin {

	const MENU_SLUG     = 'qualinav-qi';
	const EDITOR_SLUG   = 'qualinav-qi-template';
	const CAPABILITY    = 'manage_options';
	const NOTICE_OPTION = 'qualinav_qi_admin_notice';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_menu_page(
			'QI Projects',
			'QI Projects',
			self::CAPABILITY,
			self::MENU_SLUG,
			array( 'Qualinav_QI_Admin_Overview', 'render' ),
			'dashicons-analytics',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Overview',
			'Overview',
			self::CAPABILITY,
			self::MENU_SLUG,
			array( 'Qualinav_QI_Admin_Overview', 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Template Editor',
			'Template Editor',
			self::CAPABILITY,
			self::EDITOR_SLUG,
			array( 'Qualinav_QI_Admin_Template_Editor', 'render' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( (string) $hook, self::MENU_SLUG ) === false ) {
			return;
		}
		wp_enqueue_style(
			'qualinav-qi-admin',
			QUALINAV_QI_PLUGIN_URL . 'assets/css/qi-admin.css',
			array(),
			QUALINAV_QI_VERSION
		);
		wp_enqueue_script(
			'qualinav-qi-admin',
			QUALINAV_QI_PLUGIN_URL . 'assets/js/qi-admin.js',
			array(),
			QUALINAV_QI_VERSION,
			true
		);
	}

	/**
	 * Routes admin form submissions to the right handler. Each form carries its
	 * own nonce; capability is enforced here before anything is written.
	 */
	public static function handle_post() {
		if ( empty( $_POST['qualinav_qi_action'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to manage QI Projects.' );
		}

		$action = sanitize_key( wp_unslash( $_POST['qualinav_qi_action'] ) );

		if ( $action === 'save_template' ) {
			check_admin_referer( 'qualinav_qi_save_template' );
			Qualinav_QI_Admin_Template_Editor::handle_save();
		} elseif ( $action === 'restore_template' ) {
			check_admin_referer( 'qualinav_qi_restore_template' );
			Qualinav_QI_Admin_Template_Editor::handle_restore();
		}
	}

	public static function flash_notice( $message, $type = 'success' ) {
		set_transient(
			self::NOTICE_OPTION,
			array( 'message' => (string) $message, 'type' => ( $type === 'error' ? 'error' : 'success' ) ),
			60
		);
	}

	public static function render_notice() {
		$notice = get_transient( self::NOTICE_OPTION );
		if ( ! $notice || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( self::NOTICE_OPTION );
		$class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}

	public static function render_header( $active ) {
		$tabs = array(
			self::MENU_SLUG   => 'Overview',
			self::EDITOR_SLUG => 'Template Editor',
		);
		echo '<div class="wrap qi-admin">';
		echo '<h1 class="qi-admin-title">QI Projects</h1>';
		self::render_notice();
		echo '<nav class="nav-tab-wrapper qi-admin-nav">';
		foreach ( $tabs as $slug => $label ) {
			$url = admin_url( 'admin.php?page=' . $slug );
			$cls = 'nav-tab' . ( $slug === $active ? ' nav-tab-active' : '' );
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $cls ), esc_html( $label ) );
		}
		echo '</nav>';
	}

	public static function render_footer() {
		echo '</div>';
	}
}
