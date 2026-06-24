<?php
/**
 * Seeds (and re-seeds) the global default Improvement Charter template.
 *
 *  - Initial seed: creates the template + first published version on first activation.
 *  - Re-seed: when SEED_VERSION is bumped, updates the existing template's current
 *    version_json in place so existing projects pick up edits to the seed JSON
 *    (e.g. canvas reference images, helper-text fixes) without breaking their data.
 *
 * Templates are scoped to org_id = NULL → available as a global starter to every org.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Seeder {

	const DEFAULT_SLUG       = 'improvement-charter';
	const SEED_VERSION       = 24;
	const SEED_VERSION_OPT   = 'qualinav_qi_default_template_seed_version';
	/** @deprecated kept only to read the legacy boolean flag; no longer written. */
	const SEED_OPTION_FLAG   = 'qualinav_qi_default_template_seeded';

	public static function seed_default_template_if_missing() {
		$repo     = new Qualinav_QI_Template_Repository();
		$existing = $repo->find_template_by_slug( self::DEFAULT_SLUG, null );
		$stored   = (int) get_option( self::SEED_VERSION_OPT, 0 );

		if ( ! $existing ) {
			$seed = self::load_seed_json();
			if ( ! $seed ) {
				return;
			}
			$template_id = $repo->create_template(
				isset( $seed['name'] ) ? $seed['name'] : 'Improvement Charter',
				isset( $seed['slug'] ) ? $seed['slug'] : self::DEFAULT_SLUG,
				isset( $seed['description'] ) ? $seed['description'] : '',
				null,
				0
			);
			if ( $template_id ) {
				$repo->publish_version( $template_id, $seed, 0 );
				update_option( self::SEED_VERSION_OPT, self::SEED_VERSION );
				update_option( self::SEED_OPTION_FLAG, '1' );
			}
			return;
		}

		// Re-seed: update existing template's current version structure_json in place.
		if ( $stored < self::SEED_VERSION ) {
			$seed = self::load_seed_json();
			if ( ! $seed ) {
				return;
			}
			if ( ! empty( $existing['current_version_id'] ) ) {
				global $wpdb;
				$versions_table = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'template_versions';
				$wpdb->update(
					$versions_table,
					array( 'structure_json' => wp_json_encode( $seed ) ),
					array( 'id' => (int) $existing['current_version_id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
			update_option( self::SEED_VERSION_OPT, self::SEED_VERSION );
		}
	}

	/** Public accessor so the admin "Restore defaults" action shares one source of truth. */
	public static function get_default_structure() {
		return self::load_seed_json();
	}

	private static function load_seed_json() {
		$seed_path = QUALINAV_QI_PLUGIN_DIR . 'seeds/improvement-charter-default.json';
		if ( ! file_exists( $seed_path ) ) {
			return null;
		}
		$seed = json_decode( file_get_contents( $seed_path ), true );
		return is_array( $seed ) ? $seed : null;
	}
}
