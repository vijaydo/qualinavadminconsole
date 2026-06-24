<?php
/**
 * Resolves the active org_id for the current request. Single source of truth for
 * org filtering — every repository call goes through this. When the real My Org
 * plugin ships, only this class needs to change.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Org_Context {

	private static $cache = array();

	/**
	 * Returns the canonical wp_organizations.id for the user, or 0 if none.
	 * Order: wp_users.organization_id (joined to wp_organizations to verify the
	 * row exists), then user_meta 'organization' resolved by name.
	 */
	public static function user_org_id( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}
		if ( isset( self::$cache[ $user_id ] ) ) {
			return self::$cache[ $user_id ];
		}

		global $wpdb;

		// Source of truth: wp_users.organization_id → wp_organizations.id. No
		// qi_orgs indirection — the org_id stored in qi_projects is the
		// canonical organization id directly.
		$org_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT u.organization_id
			 FROM {$wpdb->users} u
			 INNER JOIN {$wpdb->prefix}organizations o ON o.id = u.organization_id
			 WHERE u.ID = %d
			 LIMIT 1",
			$user_id
		) );

		// Secondary path: org stored as a user_meta string ('organization' meta key).
		// Resolve the name to wp_organizations.id when the FK column isn't set.
		if ( $org_id <= 0 ) {
			$org_name = trim( (string) get_user_meta( $user_id, 'organization', true ) );
			if ( $org_name !== '' ) {
				$org_id = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}organizations WHERE name = %s LIMIT 1",
					$org_name
				) );
			}
		}

		self::$cache[ $user_id ] = $org_id;
		return $org_id;
	}

	/**
	 * Creates a qi_orgs row linked to a canonical wp_organizations row.
	 * Inherits the canonical org's name and a derived slug.
	 */
	private static function create_qi_org_for_canonical( $canonical_id, $user_id ) {
		global $wpdb;
		$orgs_tbl = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'orgs';

		$canonical = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, name, slug FROM {$wpdb->prefix}organizations WHERE id = %d",
			(int) $canonical_id
		), ARRAY_A );
		if ( ! $canonical ) {
			return 0;
		}

		$now  = current_time( 'mysql' );
		$slug = ! empty( $canonical['slug'] ) ? sanitize_title( $canonical['slug'] ) : 'org-' . (int) $canonical_id;
		// Slug column is UNIQUE; guarantee uniqueness across canonical/legacy rows.
		$slug = $slug . '-canon-' . (int) $canonical_id;

		$wpdb->insert(
			$orgs_tbl,
			array(
				'canonical_org_id' => (int) $canonical_id,
				'name'             => (string) $canonical['name'],
				'slug'             => $slug,
				'owner_user_id'    => (int) $user_id,
				'status'           => 'active',
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function current_user_org_id() {
		return self::user_org_id( get_current_user_id() );
	}

	public static function user_belongs_to_org( $user_id, $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'org_members';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND org_id = %d",
				(int) $user_id,
				(int) $org_id
			)
		) > 0;
	}

	/**
	 * Test/dev helper: ensures a default org exists and that the given user is a member of it.
	 * NOT for production user flows — that's a job for the My Org plugin.
	 */
	public static function ensure_default_org_for_user( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}

		$existing = self::user_org_id( $user_id );
		if ( $existing ) {
			return $existing;
		}

		$orgs    = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'orgs';
		$members = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'org_members';
		$now     = current_time( 'mysql' );

		$wpdb->insert(
			$orgs,
			array(
				'name'          => 'Default Org',
				'slug'          => 'default-org-' . $user_id,
				'owner_user_id' => $user_id,
				'status'        => 'active',
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		$org_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$members,
			array(
				'org_id'     => $org_id,
				'user_id'    => $user_id,
				'role'       => 'owner',
				'created_at' => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);

		unset( self::$cache[ $user_id ] );
		return $org_id;
	}
}
