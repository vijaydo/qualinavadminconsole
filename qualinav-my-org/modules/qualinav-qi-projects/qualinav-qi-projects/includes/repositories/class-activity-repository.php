<?php
/**
 * Append-only activity log. AI brain reads this to build context about a project's history.
 * Never delete rows — log is the audit trail.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Activity_Repository {

	protected function table() {
		global $wpdb;
		return $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'activity_log';
	}

	public function log( $org_id, $project_id, $user_id, $action, $target_type = null, $target_id = null, $payload = null ) {
		global $wpdb;
		$wpdb->insert(
			$this->table(),
			array(
				'org_id'       => (int) $org_id,
				'project_id'   => $project_id ? (int) $project_id : null,
				'user_id'      => (int) $user_id,
				'action'       => substr( (string) $action, 0, 60 ),
				'target_type'  => $target_type ? substr( (string) $target_type, 0, 40 ) : null,
				'target_id'    => $target_id ? (int) $target_id : null,
				'payload_json' => $payload ? wp_json_encode( $payload ) : null,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	public function list_for_project( $project_id, $org_id, $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE project_id = %d AND org_id = %d ORDER BY id DESC LIMIT %d",
				(int) $project_id,
				(int) $org_id,
				min( 500, max( 1, (int) $limit ) )
			),
			ARRAY_A
		);
	}
}
