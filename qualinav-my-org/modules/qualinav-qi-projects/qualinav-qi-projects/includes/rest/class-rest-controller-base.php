<?php
/**
 * Base REST controller. Subclasses define routes; this class enforces auth + org context
 * so per-route permission callbacks don't have to reimplement it.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

abstract class Qualinav_QI_REST_Controller_Base extends WP_REST_Controller {

	const NAMESPACE_V1 = 'qualinav-qi/v1';

	public $namespace = self::NAMESPACE_V1;

	public function register_routes() {}

	/**
	 * Permission: must be logged in AND have an org context.
	 * Cross-org access (super-admin) is a future capability check, not v1.
	 */
	public function require_org_context() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'qi_unauthorized', 'Authentication required', array( 'status' => 401 ) );
		}
		$org_id = Qualinav_QI_Org_Context::current_user_org_id();
		if ( $org_id <= 0 ) {
			return new WP_Error( 'qi_no_org', 'User is not a member of any organization', array( 'status' => 403 ) );
		}
		return true;
	}

	protected function current_org_id() {
		return Qualinav_QI_Org_Context::current_user_org_id();
	}

	protected function current_user_id() {
		return get_current_user_id();
	}
}
