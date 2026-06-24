<?php
/**
 * REST endpoints for single-value fields (textarea / single text).
 *
 *   GET /qualinav-qi/v1/projects/{id}/fields
 *   PUT /qualinav-qi/v1/projects/{id}/fields   { field_path, field_type, value }
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_REST_Fields_Controller extends Qualinav_QI_REST_Controller_Base {

	/** @var Qualinav_QI_Field_Repository */
	private $fields;

	/** @var Qualinav_QI_Project_Repository */
	private $projects;

	public function __construct() {
		$this->fields   = new Qualinav_QI_Field_Repository();
		$this->projects = new Qualinav_QI_Project_Repository();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/projects/(?P<id>\d+)/fields', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => array( $this, 'require_org_context' ),
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'upsert_item' ),
				'permission_callback' => array( $this, 'require_org_context' ),
				'args'                => array(
					'field_path' => array( 'type' => 'string', 'required' => true ),
					'field_type' => array( 'type' => 'string', 'required' => true ),
					'value'      => array( 'required' => true ),
				),
			),
		) );
	}

	public function list_items( $request ) {
		$org_id     = $this->current_org_id();
		$project_id = (int) $request['id'];
		if ( ! $this->projects->find_for_org( $project_id, $org_id, $this->current_user_id() ) ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'data' => $this->fields->list_for_project( $project_id, $org_id ) ) );
	}

	public function upsert_item( $request ) {
		$org_id     = $this->current_org_id();
		$project_id = (int) $request['id'];
		if ( ! $this->projects->find_for_org( $project_id, $org_id, $this->current_user_id() ) ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}

		$body = $request->get_json_params() ?: $request->get_params();
		$row  = $this->fields->upsert(
			$project_id,
			$org_id,
			isset( $body['field_path'] ) ? (string) $body['field_path'] : '',
			isset( $body['field_type'] ) ? (string) $body['field_type'] : 'textarea',
			$body['value'] ?? '',
			$this->current_user_id()
		);
		return rest_ensure_response( $row );
	}
}
