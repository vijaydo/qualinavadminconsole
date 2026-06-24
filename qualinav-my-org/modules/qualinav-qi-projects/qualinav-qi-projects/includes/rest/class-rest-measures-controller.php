<?php
/**
 * REST endpoints for measures (Commit tab).
 *
 *   GET    /qualinav-qi/v1/projects/{id}/measures?type=outcome|process
 *   POST   /qualinav-qi/v1/projects/{id}/measures   { measure_type, description, current_value?, target_value? }
 *   PATCH  /qualinav-qi/v1/measures/{id}             { description?, current_value?, target_value? }
 *   DELETE /qualinav-qi/v1/measures/{id}
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_REST_Measures_Controller extends Qualinav_QI_REST_Controller_Base {

	/** @var Qualinav_QI_Measure_Repository */
	private $measures;

	/** @var Qualinav_QI_Project_Repository */
	private $projects;

	public function __construct() {
		$this->measures = new Qualinav_QI_Measure_Repository();
		$this->projects = new Qualinav_QI_Project_Repository();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/projects/(?P<id>\d+)/measures', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => array( $this, 'require_org_context' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'require_org_context' ),
				'args'                => array(
					'measure_type'  => array( 'type' => 'string', 'required' => true ),
					'description'   => array( 'type' => 'string', 'required' => true ),
					'current_value' => array( 'type' => 'string', 'required' => false ),
					'target_value'  => array( 'type' => 'string', 'required' => false ),
				),
			),
		) );

		register_rest_route( $this->namespace, '/measures/(?P<id>\d+)', array(
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'require_org_context' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'require_org_context' ),
			),
		) );
	}

	public function list_items( $request ) {
		$org_id     = $this->current_org_id();
		$project_id = (int) $request['id'];
		if ( ! $this->projects->find_for_org( $project_id, $org_id, $this->current_user_id() ) ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array(
			'data' => $this->measures->list_for_project( $project_id, $org_id, $request->get_param( 'type' ) ),
		) );
	}

	public function create_item( $request ) {
		$org_id     = $this->current_org_id();
		$project_id = (int) $request['id'];
		if ( ! $this->projects->find_for_org( $project_id, $org_id, $this->current_user_id() ) ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}
		$body = $request->get_json_params() ?: $request->get_params();
		$result = $this->measures->add(
			$project_id,
			$org_id,
			isset( $body['measure_type'] ) ? (string) $body['measure_type'] : 'outcome',
			isset( $body['description'] ) ? (string) $body['description'] : '',
			isset( $body['current_value'] ) ? $body['current_value'] : null,
			isset( $body['target_value'] ) ? $body['target_value'] : null,
			$this->current_user_id()
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function update_item( $request ) {
		$body   = $request->get_json_params() ?: $request->get_params();
		$result = $this->measures->update(
			(int) $request['id'],
			$this->current_org_id(),
			$body,
			$this->current_user_id()
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function delete_item( $request ) {
		$result = $this->measures->delete( (int) $request['id'], $this->current_org_id(), $this->current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return rest_ensure_response( array( 'deleted' => (bool) $result ) );
	}
}
