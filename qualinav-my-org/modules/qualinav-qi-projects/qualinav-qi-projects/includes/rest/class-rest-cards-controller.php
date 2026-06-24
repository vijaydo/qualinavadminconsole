<?php
/**
 * REST endpoints for cards (card_list / card_list_2col / card_slots_named / card_slots_numbered).
 *
 *   GET    /qualinav-qi/v1/projects/{id}/cards?field_path=&slot_key=
 *   POST   /qualinav-qi/v1/projects/{id}/cards   { field_path, slot_key?, content }
 *   PATCH  /qualinav-qi/v1/cards/{id}             { content }
 *   DELETE /qualinav-qi/v1/cards/{id}
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_REST_Cards_Controller extends Qualinav_QI_REST_Controller_Base {

	/** @var Qualinav_QI_Card_Repository */
	private $cards;

	/** @var Qualinav_QI_Project_Repository */
	private $projects;

	/** @var Qualinav_QI_Project_Member_Repository */
	private $members;

	public function __construct() {
		$this->cards    = new Qualinav_QI_Card_Repository();
		$this->projects = new Qualinav_QI_Project_Repository();
		$this->members  = new Qualinav_QI_Project_Member_Repository();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/projects/(?P<id>\d+)/cards', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => array( $this, 'require_org_context' ),
				'args'                => array(
					'field_path' => array( 'type' => 'string', 'required' => false ),
					'slot_key'   => array( 'type' => 'string', 'required' => false ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'require_org_context' ),
				'args'                => array(
					'field_path' => array( 'type' => 'string', 'required' => true ),
					'slot_key'   => array( 'type' => 'string', 'required' => false ),
					'content'    => array( 'type' => 'string', 'required' => true ),
				),
			),
		) );

		register_rest_route( $this->namespace, '/cards/(?P<id>\d+)', array(
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

		$slot = $request->get_param( 'slot_key' );
		$rows = $this->cards->list_for_project(
			$project_id,
			$org_id,
			$request->get_param( 'field_path' ),
			$slot === null ? null : (string) $slot
		);
		return rest_ensure_response( array( 'data' => $rows ) );
	}

	public function create_item( $request ) {
		$org_id     = $this->current_org_id();
		$project_id = (int) $request['id'];

		if ( ! $this->projects->find_for_org( $project_id, $org_id, $this->current_user_id() ) ) {
			return new WP_Error( 'qi_not_found', 'Project not found', array( 'status' => 404 ) );
		}

		$field_path = (string) $request->get_param( 'field_path' );
		$content    = (string) $request->get_param( 'content' );

		$result = $this->cards->add(
			$project_id,
			$org_id,
			$field_path,
			$request->get_param( 'slot_key' ),
			$content,
			$this->current_user_id()
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		$this->sync_team_member_added( $field_path, $content, $project_id );
		$this->maybe_notify_team_member( $field_path, $content, $project_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Sends a bell-icon notification when a card on a *.team_members field is
	 * added with a content string that matches a same-canonical-org user's
	 * display_name. No-op if the target user is the actor themselves, if the
	 * notification plugin isn't loaded, or if no display_name match exists
	 * (e.g. the user typed a custom name via the picker's "Add custom" fallback).
	 */
	private function maybe_notify_team_member( $field_path, $content, $project_id ) {
		if ( strpos( $field_path, 'team_members' ) === false ) {
			return;
		}
		if ( ! function_exists( 'dttc_create_notification' ) ) {
			return;
		}

		$target_id = $this->find_org_user_by_display_name( $content );
		$actor_id  = (int) $this->current_user_id();
		if ( $target_id <= 0 || $target_id === $actor_id ) {
			return;
		}

		$project    = $this->projects->find_for_org( $project_id, $this->current_org_id(), $actor_id );
		$title      = is_array( $project ) && ! empty( $project['title'] ) ? (string) $project['title'] : 'a QI project';
		$actor      = get_userdata( $actor_id );
		$actor_name = $actor ? $actor->display_name : 'Someone';
		$message    = sprintf( '%s added you to QI project: %s', $actor_name, $title );
		$link       = home_url( '/qi-projects/?qi=' . (int) $project_id );

		dttc_create_notification( $target_id, $actor_id, 0, 'qi_team_member_added', $message, $link );
	}

	public function update_item( $request ) {
		$body    = $request->get_json_params() ?: $request->get_params();
		$card_id = (int) $request['id'];
		$org_id  = $this->current_org_id();

		// Snapshot the old content before update so we can revoke the previous
		// teammate's access if the card was rewritten to point at someone else.
		$old_card = $this->cards->find_for_org( $card_id, $org_id );

		$result = $this->cards->update(
			$card_id,
			$org_id,
			isset( $body['content'] ) ? (string) $body['content'] : '',
			$this->current_user_id()
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}

		if ( $old_card && is_array( $result ) ) {
			$old_content = (string) $old_card['content'];
			$new_content = (string) $result['content'];
			if ( $old_content !== $new_content ) {
				$this->sync_team_member_removed( (string) $old_card['field_path'], $old_content, (int) $old_card['project_id'] );
				$this->sync_team_member_added( (string) $result['field_path'], $new_content, (int) $result['project_id'] );
				$this->maybe_notify_team_member( (string) $result['field_path'], $new_content, (int) $result['project_id'] );
			}
		}

		return rest_ensure_response( $result );
	}

	public function delete_item( $request ) {
		$card_id = (int) $request['id'];
		$org_id  = $this->current_org_id();

		// Capture the card BEFORE delete; afterwards we can no longer read its
		// field_path/content to know whether to revoke teammate access.
		$card    = $this->cards->find_for_org( $card_id, $org_id );

		$result  = $this->cards->delete( $card_id, $org_id, $this->current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}

		if ( $card && $result ) {
			$this->sync_team_member_removed( (string) $card['field_path'], (string) $card['content'], (int) $card['project_id'] );
		}

		return rest_ensure_response( array( 'deleted' => (bool) $result ) );
	}

	private function find_org_user_by_display_name( $display_name ) {
		$display_name = trim( $display_name );
		if ( $display_name === '' ) {
			return 0;
		}
		global $wpdb;
		$canonical_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT organization_id FROM {$wpdb->users} WHERE ID = %d",
			(int) $this->current_user_id()
		) );
		if ( $canonical_id <= 0 ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->users}
			 WHERE organization_id = %d AND display_name = %s
			 LIMIT 1",
			$canonical_id,
			$display_name
		) );
	}

	private function sync_team_member_added( $field_path, $content, $project_id ) {
		if ( strpos( $field_path, 'team_members' ) === false ) {
			return;
		}
		$user_id = $this->find_org_user_by_display_name( $content );
		if ( $user_id <= 0 ) {
			return;
		}
		$this->members->add( $project_id, $user_id, $this->current_org_id(), $this->current_user_id() );
	}

	private function sync_team_member_removed( $field_path, $content, $project_id ) {
		if ( strpos( $field_path, 'team_members' ) === false ) {
			return;
		}
		$user_id = $this->find_org_user_by_display_name( $content );
		if ( $user_id <= 0 ) {
			return;
		}
		// Owners are always implicit members and shouldn't be evictable. The
		// 3-arg overload of find_for_org enforces access; here we just need
		// the raw row to compare owner_user_id, so use the base 2-arg lookup.
		$project = $this->projects->find_for_org( $project_id, $this->current_org_id() );
		if ( is_array( $project ) && (int) $project['owner_user_id'] === $user_id ) {
			return;
		}
		$this->members->remove( $project_id, $user_id );
	}
}
