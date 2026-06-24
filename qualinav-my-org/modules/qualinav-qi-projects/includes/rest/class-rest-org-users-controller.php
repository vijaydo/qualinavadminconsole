<?php
/**
 * GET /qualinav-qi/v1/org-users
 *
 * Returns the list of WordPress users that share the current user's canonical
 * organisation. "Canonical" here means the site-wide `wp_users.organization_id`
 * column + `wp_organizations` table — *not* the qi-projects plugin's internal
 * `wp_qi_org_members` table. This endpoint powers the Team Members picker on
 * the QI project canvas.
 *
 * Deliberately bypasses `require_org_context()` from the base controller so it
 * doesn't 403 users who are in a wp_organization but haven't been bootstrapped
 * into a qi_org yet.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_REST_Org_Users_Controller extends Qualinav_QI_REST_Controller_Base {

	public function register_routes() {
		register_rest_route( self::NAMESPACE_V1, '/org-users', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_org_users' ),
			'permission_callback' => array( $this, 'require_login' ),
		) );
	}

	public function require_login() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'qi_unauthorized', 'Authentication required', array( 'status' => 401 ) );
		}
		return true;
	}

	public function list_org_users( WP_REST_Request $request ) {
		global $wpdb;

		$current_user_id = (int) get_current_user_id();
		$org_id          = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT organization_id FROM {$wpdb->users} WHERE ID = %d",
			$current_user_id
		) );

		if ( $org_id <= 0 ) {
			return rest_ensure_response( array(
				'org_id' => 0,
				'users'  => array(),
			) );
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, display_name, user_email
			 FROM {$wpdb->users}
			 WHERE organization_id = %d
			 ORDER BY display_name ASC",
			$org_id
		), ARRAY_A );

		$users = array();
		foreach ( (array) $rows as $r ) {
			$users[] = array(
				'id'           => (int) $r['ID'],
				'display_name' => (string) $r['display_name'],
				'email'        => (string) $r['user_email'],
				'is_self'      => ( (int) $r['ID'] === $current_user_id ),
			);
		}

		return rest_ensure_response( array(
			'org_id' => $org_id,
			'users'  => $users,
		) );
	}
}
