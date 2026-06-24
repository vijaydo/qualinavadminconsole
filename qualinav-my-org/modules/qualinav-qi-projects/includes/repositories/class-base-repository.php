<?php
/**
 * Base repository. Subclasses set $table_suffix; helpers always join on org_id.
 *
 * Centralizes the "every read/write must be org-scoped" rule so callers can't
 * accidentally leak across orgs.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

abstract class Qualinav_QI_Base_Repository {

	/** @var string e.g. 'projects' (combines with $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX) */
	protected $table_suffix = '';

	protected function table() {
		global $wpdb;
		return $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . $this->table_suffix;
	}

	protected function now() {
		return current_time( 'mysql' );
	}

	public function find_for_org( $id, $org_id ) {
		global $wpdb;
		$id     = (int) $id;
		$org_id = (int) $org_id;
		if ( $id <= 0 || $org_id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d AND org_id = %d", $id, $org_id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function delete_for_org( $id, $org_id ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			$this->table(),
			array( 'id' => (int) $id, 'org_id' => (int) $org_id ),
			array( '%d', '%d' )
		);
	}
}
