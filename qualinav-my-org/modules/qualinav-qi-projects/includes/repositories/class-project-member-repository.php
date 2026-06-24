<?php
/**
 * Project members repository — explicit per-project ACL.
 *
 * The canvas "Team Members" cards are the user-facing source of truth; this
 * table mirrors them in a queryable form so project visibility checks stay an
 * indexed lookup rather than a LIKE-on-card-content scan. Keep them in sync
 * via the cards REST controller (add/update/delete hooks).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Project_Member_Repository extends Qualinav_QI_Base_Repository {

	protected $table_suffix = 'project_members';

	public function add( $project_id, $user_id, $org_id, $added_by_user_id ) {
		global $wpdb;
		$project_id = (int) $project_id;
		$user_id    = (int) $user_id;
		$org_id     = (int) $org_id;
		if ( $project_id <= 0 || $user_id <= 0 || $org_id <= 0 ) {
			return false;
		}
		// UNIQUE KEY (project_id, user_id) prevents duplicates; INSERT IGNORE
		// keeps repeated picker adds (which can race) idempotent.
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$this->table()}
				(project_id, user_id, org_id, added_by_user_id, created_at)
			 VALUES (%d, %d, %d, %d, %s)",
			$project_id,
			$user_id,
			$org_id,
			(int) $added_by_user_id,
			$this->now()
		) );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : true;
	}

	public function remove( $project_id, $user_id ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			$this->table(),
			array( 'project_id' => (int) $project_id, 'user_id' => (int) $user_id ),
			array( '%d', '%d' )
		);
	}

	public function user_is_member( $project_id, $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$this->table()} WHERE project_id = %d AND user_id = %d LIMIT 1",
			(int) $project_id,
			(int) $user_id
		) );
	}
}
