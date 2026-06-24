<?php
/**
 * REST endpoints for idea scores (Matrix Diagram).
 *
 *   GET /qualinav-qi/v1/projects/{id}/scores
 *   PUT /qualinav-qi/v1/projects/{id}/scores   { idea_card_id, criterion_key, score }
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_REST_Scores_Controller extends Qualinav_QI_REST_Controller_Base {

	/** @var Qualinav_QI_Score_Repository */
	private $scores;

	/** @var Qualinav_QI_Project_Repository */
	private $projects;

	/** @var Qualinav_QI_Card_Repository */
	private $cards;

	public function __construct() {
		$this->scores   = new Qualinav_QI_Score_Repository();
		$this->projects = new Qualinav_QI_Project_Repository();
		$this->cards    = new Qualinav_QI_Card_Repository();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/projects/(?P<id>\d+)/scores', array(
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
					'idea_card_id'  => array( 'type' => 'integer', 'required' => true ),
					'criterion_key' => array( 'type' => 'string',  'required' => true ),
					'score'         => array( 'type' => 'integer', 'required' => true ),
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
		return rest_ensure_response( array(
			'data' => $this->scores->list_for_project( $project_id, $org_id, $this->current_user_id() ),
		) );
	}

	public function upsert_item( $request ) {
		$org_id     = $this->current_org_id();
		$project_id = (int) $request['id'];

		if ( ! $this->projects->find_for_org( $project_id, $org_id, $this->current_user_id() ) ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}

		$body = $request->get_json_params() ?: $request->get_params();
		$card_id = isset( $body['idea_card_id'] ) ? (int) $body['idea_card_id'] : 0;

		// Verify the card belongs to this project (prevents cross-project score injection)
		$card = $this->cards->find_for_org( $card_id, $org_id );
		if ( ! $card || (int) $card['project_id'] !== $project_id ) {
			return new WP_Error( 'qi_invalid_idea', 'Idea card not found in this project', array( 'status' => 400 ) );
		}

		$result = $this->scores->upsert(
			$project_id,
			$org_id,
			$card_id,
			isset( $body['criterion_key'] ) ? (string) $body['criterion_key'] : '',
			isset( $body['score'] ) ? (int) $body['score'] : 0,
			$this->current_user_id()
		);
		return rest_ensure_response( $result );
	}
}
