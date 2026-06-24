<?php
/**
 * Plugin bootstrap. Loads classes, wires CPT, REST, shortcodes, and migrations.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Plugin {

	public static function boot() {
		self::load_dependencies();

		add_action( 'init', array( 'Qualinav_QI_CPT', 'register' ) );
		add_action( 'init', array( 'Qualinav_QI_Shortcodes', 'register' ) );
		Qualinav_QI_Router::register();
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_run_migrations' ) );
		add_action( 'admin_init', array( 'Qualinav_QI_Seeder', 'seed_default_template_if_missing' ) );

		if ( is_admin() ) {
			Qualinav_QI_Admin::register();
		}
	}

	private static function load_dependencies() {
		$dir = QUALINAV_QI_PLUGIN_DIR . 'includes/';

		require_once $dir . 'helpers/class-org-context.php';

		require_once $dir . 'repositories/class-base-repository.php';
		require_once $dir . 'repositories/class-org-repository.php';
		require_once $dir . 'repositories/class-activity-repository.php';
		require_once $dir . 'repositories/class-project-repository.php';
		require_once $dir . 'repositories/class-card-repository.php';
		require_once $dir . 'repositories/class-field-repository.php';
		require_once $dir . 'repositories/class-template-repository.php';
		require_once $dir . 'repositories/class-score-repository.php';
		require_once $dir . 'repositories/class-measure-repository.php';
		require_once $dir . 'repositories/class-project-member-repository.php';

		require_once $dir . 'rest/class-rest-controller-base.php';
		require_once $dir . 'rest/class-rest-projects-controller.php';
		require_once $dir . 'rest/class-rest-cards-controller.php';
		require_once $dir . 'rest/class-rest-fields-controller.php';
		require_once $dir . 'rest/class-rest-templates-controller.php';
		require_once $dir . 'rest/class-rest-scores-controller.php';
		require_once $dir . 'rest/class-rest-measures-controller.php';
		require_once $dir . 'rest/class-rest-org-users-controller.php';

		require_once $dir . 'class-seeder.php';
		require_once $dir . 'class-renderer.php';
		require_once $dir . 'class-shortcodes.php';
		require_once $dir . 'class-router.php';

		if ( is_admin() ) {
			require_once $dir . 'admin/class-admin.php';
			require_once $dir . 'admin/class-admin-overview.php';
			require_once $dir . 'admin/class-admin-template-editor.php';
		}
	}

	public static function register_rest_routes() {
		( new Qualinav_QI_REST_Projects_Controller() )->register_routes();
		( new Qualinav_QI_REST_Cards_Controller() )->register_routes();
		( new Qualinav_QI_REST_Fields_Controller() )->register_routes();
		( new Qualinav_QI_REST_Templates_Controller() )->register_routes();
		( new Qualinav_QI_REST_Scores_Controller() )->register_routes();
		( new Qualinav_QI_REST_Measures_Controller() )->register_routes();
		( new Qualinav_QI_REST_Org_Users_Controller() )->register_routes();
	}

	/**
	 * Re-runs activation when the on-disk DB version is ahead of the stored one.
	 * Lets schema changes ship without requiring a deactivate/reactivate.
	 */
	public static function maybe_run_migrations() {
		if ( get_option( 'qualinav_qi_db_version' ) !== QUALINAV_QI_DB_VERSION ) {
			Qualinav_QI_Activator::activate();
		}
	}
}
