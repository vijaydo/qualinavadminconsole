<?php
/**
 * Templates + Template Versions repository.
 *
 * Templates with org_id = NULL are global "starter library" templates that orgs
 * can clone. Per-org templates have org_id = the owning org.
 *
 * Snapshot-on-publish: every published edit creates a new immutable version row.
 * Existing projects keep their original template_version_id and don't auto-migrate.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Template_Repository {

	protected function templates_table() {
		global $wpdb;
		return $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'templates';
	}

	protected function versions_table() {
		global $wpdb;
		return $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'template_versions';
	}

	public function find_template( $template_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->templates_table()} WHERE id = %d", (int) $template_id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function find_template_by_slug( $slug, $org_id = null ) {
		global $wpdb;
		if ( $org_id === null ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$this->templates_table()} WHERE slug = %s AND org_id IS NULL", (string) $slug ),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->templates_table()} WHERE slug = %s AND (org_id = %d OR org_id IS NULL) ORDER BY org_id IS NULL ASC LIMIT 1",
					(string) $slug,
					(int) $org_id
				),
				ARRAY_A
			);
		}
		return $row ?: null;
	}

	public function find_version( $version_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->versions_table()} WHERE id = %d", (int) $version_id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function list_for_org( $org_id, $include_global = true ) {
		global $wpdb;
		if ( $include_global ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->templates_table()} WHERE (org_id = %d OR org_id IS NULL) AND status = 'published' ORDER BY org_id IS NULL DESC, name ASC",
					(int) $org_id
				),
				ARRAY_A
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->templates_table()} WHERE org_id = %d AND status = 'published' ORDER BY name ASC",
				(int) $org_id
			),
			ARRAY_A
		);
	}

	public function create_template( $name, $slug, $description, $org_id, $created_by_user_id ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->templates_table(),
			array(
				'org_id'             => $org_id ? (int) $org_id : null,
				'name'               => sanitize_text_field( $name ),
				'slug'               => sanitize_title( $slug ),
				'description'        => wp_kses_post( (string) $description ),
				'status'             => 'draft',
				'current_version_id' => null,
				'created_by_user_id' => $created_by_user_id ? (int) $created_by_user_id : null,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function publish_version( $template_id, $structure_array, $created_by_user_id ) {
		global $wpdb;
		$template_id = (int) $template_id;
		$next        = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COALESCE(MAX(version_number), 0) + 1 FROM {$this->versions_table()} WHERE template_id = %d", $template_id )
		);
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->versions_table(),
			array(
				'template_id'        => $template_id,
				'version_number'     => $next,
				'structure_json'     => wp_json_encode( $structure_array ),
				'published_at'       => $now,
				'created_by_user_id' => $created_by_user_id ? (int) $created_by_user_id : null,
				'created_at'         => $now,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);
		$version_id = (int) $wpdb->insert_id;

		$wpdb->update(
			$this->templates_table(),
			array(
				'current_version_id' => $version_id,
				'status'             => 'published',
				'updated_at'         => $now,
			),
			array( 'id' => $template_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return $version_id;
	}

	/**
	 * Updates an existing version's structure in place (no new version row).
	 *
	 * Used by the admin Template Editor and the Seeder re-seed: edits to the
	 * global Improvement Charter must propagate to existing projects that point
	 * at this version, rather than snapshotting a new one they'd never adopt.
	 */
	public function update_version_structure( $version_id, $structure_array ) {
		global $wpdb;
		$ok = $wpdb->update(
			$this->versions_table(),
			array( 'structure_json' => wp_json_encode( $structure_array ) ),
			array( 'id' => (int) $version_id ),
			array( '%s' ),
			array( '%d' )
		);
		return false !== $ok;
	}

	public function get_structure( $version_id ) {
		$version = $this->find_version( $version_id );
		if ( ! $version ) {
			return null;
		}
		$decoded = json_decode( $version['structure_json'], true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
