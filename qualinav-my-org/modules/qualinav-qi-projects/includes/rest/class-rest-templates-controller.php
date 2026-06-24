<?php
/**
 * REST endpoints for templates.
 *
 *   GET /qualinav-qi/v1/templates              (org templates + global starters)
 *   GET /qualinav-qi/v1/templates/{id}         (with structure JSON for current version)
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_REST_Templates_Controller extends Qualinav_QI_REST_Controller_Base {

	/** @var Qualinav_QI_Template_Repository */
	private $templates;

	public function __construct() {
		$this->templates = new Qualinav_QI_Template_Repository();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/templates', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_items' ),
			'permission_callback' => array( $this, 'require_org_context' ),
		) );

		register_rest_route( $this->namespace, '/templates/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_item' ),
			'permission_callback' => array( $this, 'require_org_context' ),
		) );
	}

	public function list_items() {
		return rest_ensure_response( array(
			'data' => $this->templates->list_for_org( $this->current_org_id(), true ),
		) );
	}

	public function get_item( $request ) {
		$tpl = $this->templates->find_template( (int) $request['id'] );
		if ( ! $tpl ) {
			return new WP_Error( 'qi_not_found', 'Template not found', array( 'status' => 404 ) );
		}
		// Org filter: must be global (org_id NULL) or own org
		if ( $tpl['org_id'] !== null && (int) $tpl['org_id'] !== $this->current_org_id() ) {
			return new WP_Error( 'qi_forbidden', 'Template not accessible', array( 'status' => 403 ) );
		}
		$structure = $tpl['current_version_id'] ? $this->templates->get_structure( (int) $tpl['current_version_id'] ) : null;
		$tpl['structure'] = $structure;
		return rest_ensure_response( $tpl );
	}
}
