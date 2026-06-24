<?php
/**
 * REST endpoints for QI Projects.
 *
 * All endpoints scope to the current user's org_id automatically.
 *
 *   GET    /qualinav-qi/v1/projects
 *   POST   /qualinav-qi/v1/projects
 *   GET    /qualinav-qi/v1/projects/{id}
 *   PATCH  /qualinav-qi/v1/projects/{id}
 *   DELETE /qualinav-qi/v1/projects/{id}        (soft = archive)
 *   POST   /qualinav-qi/v1/projects/{id}/complete
 *   GET    /qualinav-qi/v1/projects/{id}/activity
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_REST_Projects_Controller extends Qualinav_QI_REST_Controller_Base {

	public $rest_base = 'projects';

	/** @var Qualinav_QI_Project_Repository */
	private $projects;

	/** @var Qualinav_QI_Activity_Repository */
	private $activity;

	public function __construct() {
		$this->projects = new Qualinav_QI_Project_Repository();
		$this->activity = new Qualinav_QI_Activity_Repository();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => array( $this, 'require_org_context' ),
				'args'                => array(
					'status'   => array( 'type' => 'string', 'required' => false ),
					'limit'    => array( 'type' => 'integer', 'required' => false, 'default' => 50 ),
					'offset'   => array( 'type' => 'integer', 'required' => false, 'default' => 0 ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'require_org_context' ),
				'args'                => array(
					'title'               => array( 'type' => 'string', 'required' => true ),
					'template_version_id' => array( 'type' => 'integer', 'required' => true ),
					'pillar'              => array( 'type' => 'string', 'required' => false ),
					'focus_area'          => array( 'type' => 'string', 'required' => false ),
				),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'require_org_context' ),
			),
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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/complete', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'complete_item' ),
			'permission_callback' => array( $this, 'require_org_context' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/activity', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_activity' ),
			'permission_callback' => array( $this, 'require_org_context' ),
		) );
	}

	public function list_items( $request ) {
		$rows = $this->projects->list_for_org( $this->current_org_id(), array(
			'status'          => $request->get_param( 'status' ),
			'limit'           => $request->get_param( 'limit' ),
			'offset'          => $request->get_param( 'offset' ),
			'visible_to_user' => $this->current_user_id(),
		) );
		// `total` is only used by paginating clients; reflect the filtered set
		// rather than the org-wide count so pagination doesn't promise rows
		// the user isn't allowed to fetch.
		return rest_ensure_response( array(
			'data'  => $rows,
			'total' => count( $rows ),
		) );
	}

	public function create_item( $request ) {
		$result = $this->projects->create(
			$this->current_org_id(),
			$this->current_user_id(),
			(string) $request->get_param( 'title' ),
			(int) $request->get_param( 'template_version_id' ),
			array(
				'pillar'     => $request->get_param( 'pillar' ),
				'focus_area' => $request->get_param( 'focus_area' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function get_item( $request ) {
		$row = $this->projects->find_for_org( (int) $request['id'], $this->current_org_id(), $this->current_user_id() );
		if ( ! $row ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public function update_item( $request ) {
		$result = $this->projects->update_metadata(
			(int) $request['id'],
			$this->current_org_id(),
			$request->get_json_params() ?: $request->get_params(),
			$this->current_user_id()
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function complete_item( $request ) {
		$result = $this->projects->complete( (int) $request['id'], $this->current_org_id(), $this->current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function delete_item( $request ) {
		$result = $this->projects->archive( (int) $request['id'], $this->current_org_id(), $this->current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function get_activity( $request ) {
		$project = $this->projects->find_for_org( (int) $request['id'], $this->current_org_id(), $this->current_user_id() );
		if ( ! $project ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array(
			'data' => $this->activity->list_for_project( (int) $request['id'], $this->current_org_id(), 200 ),
		) );
	}
}
