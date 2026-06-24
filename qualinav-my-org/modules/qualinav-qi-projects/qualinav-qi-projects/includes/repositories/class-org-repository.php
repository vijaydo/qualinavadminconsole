<?php
/**
 * Org repository. Reads only for v1 — org creation is handled by the future
 * My Org plugin (or the dev helper in Qualinav_QI_Org_Context).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Org_Repository {

	protected function table() {
		global $wpdb;
		return $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'orgs';
	}

	public function find_by_id( $org_id ) {
		global $wpdb;
		$org_id = (int) $org_id;
		if ( $org_id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $org_id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function find_by_slug( $slug ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE slug = %s", (string) $slug ),
			ARRAY_A
		);
		return $row ?: null;
	}
}
