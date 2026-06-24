<?php
/**
 * Creates / migrates the wp_qi_* tables on plugin activation.
 *
 * Runs dbDelta() for each table; safe to re-run on upgrades.
 * Multi-tenant: every project-scoped table carries org_id (indexed).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Activator {

	public static function activate() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX;

		$schemas = array(

			// 1. Org stub (replaced when My Org plugin ships).
			//    canonical_org_id maps to wp_users.organization_id / wp_organizations.id —
			//    the site-wide org system used by every other qualinav plugin. Lets
			//    Qualinav_QI_Org_Context resolve a user's qi_org from their canonical
			//    org so users sharing wp_users.organization_id share QI projects.
			"CREATE TABLE {$p}orgs (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				canonical_org_id BIGINT UNSIGNED NULL,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(190) NOT NULL,
				owner_user_id BIGINT UNSIGNED NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY canonical_org_id (canonical_org_id),
				KEY owner_user_id (owner_user_id)
			) {$collate};",

			// 2. Org membership stub
			"CREATE TABLE {$p}org_members (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				org_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				role VARCHAR(40) NOT NULL DEFAULT 'member',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY org_user (org_id, user_id),
				KEY user_id (user_id)
			) {$collate};",

			// 3. Templates (org_id NULL = global starter library)
			"CREATE TABLE {$p}templates (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				org_id BIGINT UNSIGNED NULL,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(190) NOT NULL,
				description TEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'draft',
				current_version_id BIGINT UNSIGNED NULL,
				created_by_user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY org_slug (org_id, slug),
				KEY status (status)
			) {$collate};",

			// 4. Template versions (snapshot-on-publish)
			"CREATE TABLE {$p}template_versions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				template_id BIGINT UNSIGNED NOT NULL,
				version_number INT UNSIGNED NOT NULL,
				structure_json LONGTEXT NOT NULL,
				published_at DATETIME NULL,
				created_by_user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY template_version (template_id, version_number),
				KEY template_id (template_id)
			) {$collate};",

			// 5. Projects (hybrid: post_id links to qi_project CPT shell)
			"CREATE TABLE {$p}projects (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				org_id BIGINT UNSIGNED NOT NULL,
				template_version_id BIGINT UNSIGNED NOT NULL,
				post_id BIGINT UNSIGNED NULL,
				title VARCHAR(255) NOT NULL,
				status VARCHAR(40) NOT NULL DEFAULT 'draft',
				pillar VARCHAR(190) NULL,
				focus_area VARCHAR(190) NULL,
				owner_user_id BIGINT UNSIGNED NOT NULL,
				completed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY org_id (org_id),
				KEY org_status (org_id, status),
				KEY template_version_id (template_version_id),
				KEY owner_user_id (owner_user_id),
				KEY post_id (post_id)
			) {$collate};",

			// 6. Single-value fields (textarea, single_text, computed, etc.)
			"CREATE TABLE {$p}project_fields (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				project_id BIGINT UNSIGNED NOT NULL,
				org_id BIGINT UNSIGNED NOT NULL,
				field_path VARCHAR(255) NOT NULL,
				field_type VARCHAR(40) NOT NULL,
				value_text LONGTEXT NULL,
				value_number DECIMAL(20,4) NULL,
				value_json LONGTEXT NULL,
				updated_by_user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY project_field (project_id, field_path),
				KEY org_id (org_id)
			) {$collate};",

			// 7. Cards (card_list, card_slots_named/numbered, card_list_2col)
			"CREATE TABLE {$p}project_cards (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				project_id BIGINT UNSIGNED NOT NULL,
				org_id BIGINT UNSIGNED NOT NULL,
				field_path VARCHAR(255) NOT NULL,
				slot_key VARCHAR(100) NULL,
				position INT UNSIGNED NOT NULL DEFAULT 0,
				content LONGTEXT NOT NULL,
				created_by_user_id BIGINT UNSIGNED NOT NULL,
				updated_by_user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY project_field (project_id, field_path),
				KEY project_field_slot (project_id, field_path, slot_key),
				KEY org_id (org_id),
				KEY created_by (created_by_user_id)
			) {$collate};",

			// 8. Measures (outcome/process with current/target)
			"CREATE TABLE {$p}project_measures (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				project_id BIGINT UNSIGNED NOT NULL,
				org_id BIGINT UNSIGNED NOT NULL,
				measure_type VARCHAR(20) NOT NULL,
				position INT UNSIGNED NOT NULL DEFAULT 0,
				description LONGTEXT NOT NULL,
				current_value VARCHAR(190) NULL,
				target_value VARCHAR(190) NULL,
				created_by_user_id BIGINT UNSIGNED NOT NULL,
				updated_by_user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY project_type (project_id, measure_type),
				KEY org_id (org_id)
			) {$collate};",

			// 9. Idea scores (matrix sliders). One row per (idea, criterion,
			//    scoring user) so each teammate keeps their own value and the
			//    displayed cell value can be averaged across the team.
			"CREATE TABLE {$p}project_idea_scores (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				project_id BIGINT UNSIGNED NOT NULL,
				org_id BIGINT UNSIGNED NOT NULL,
				idea_card_id BIGINT UNSIGNED NOT NULL,
				criterion_key VARCHAR(60) NOT NULL,
				score TINYINT UNSIGNED NOT NULL,
				scored_by_user_id BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idea_criterion_user (idea_card_id, criterion_key, scored_by_user_id),
				KEY project_id (project_id),
				KEY org_id (org_id)
			) {$collate};",

			// 10. Project members (explicit per-project ACL). A project is visible
			//     to its owner_user_id OR any user with a row here. Synced from
			//     team_members cards in the cards REST controller so the canvas
			//     "Team Members" UI is the source of truth for collaborators.
			"CREATE TABLE {$p}project_members (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				project_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				org_id BIGINT UNSIGNED NOT NULL,
				added_by_user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY project_user (project_id, user_id),
				KEY user_id (user_id),
				KEY org_id (org_id)
			) {$collate};",

			// 11. Activity log (audit trail; AI brain reads this for context)
			"CREATE TABLE {$p}activity_log (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				org_id BIGINT UNSIGNED NOT NULL,
				project_id BIGINT UNSIGNED NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				action VARCHAR(60) NOT NULL,
				target_type VARCHAR(40) NULL,
				target_id BIGINT UNSIGNED NULL,
				payload_json LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY org_id (org_id),
				KEY project_id (project_id),
				KEY user_id (user_id),
				KEY action (action),
				KEY created_at (created_at)
			) {$collate};",
		);

		foreach ( $schemas as $sql ) {
			dbDelta( $sql );
		}

		// Backfill canonical_org_id on legacy qi_orgs rows by inheriting from
		// the owner user's wp_users.organization_id. dbDelta adds the column
		// but leaves it NULL; without this, existing projects stay invisible
		// to other members of the canonical org.
		$orgs_tbl = $p . 'orgs';
		$wpdb->query( "UPDATE {$orgs_tbl} qo
			INNER JOIN {$wpdb->users} u ON u.ID = qo.owner_user_id
			SET qo.canonical_org_id = u.organization_id
			WHERE qo.canonical_org_id IS NULL
			  AND u.organization_id IS NOT NULL
			  AND u.organization_id > 0" );

		// dbDelta can't transform a UNIQUE key from (idea, criterion) to
		// (idea, criterion, scored_by_user_id) — it only adds new indexes,
		// never replaces them. Drop the legacy 2-column key explicitly so
		// dbDelta then creates the 3-column one above and per-user scoring
		// becomes possible.
		$scores_tbl = $p . 'project_idea_scores';
		$legacy_idx = (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = %s
			   AND INDEX_NAME = 'idea_criterion'
			 LIMIT 1",
			$scores_tbl
		) );
		if ( $legacy_idx === 'idea_criterion' ) {
			$wpdb->query( "ALTER TABLE {$scores_tbl} DROP INDEX idea_criterion" );
			// Re-run dbDelta for this one table so the new key is created now.
			dbDelta( "CREATE TABLE {$scores_tbl} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				project_id BIGINT UNSIGNED NOT NULL,
				org_id BIGINT UNSIGNED NOT NULL,
				idea_card_id BIGINT UNSIGNED NOT NULL,
				criterion_key VARCHAR(60) NOT NULL,
				score TINYINT UNSIGNED NOT NULL,
				scored_by_user_id BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idea_criterion_user (idea_card_id, criterion_key, scored_by_user_id),
				KEY project_id (project_id),
				KEY org_id (org_id)
			) {$collate};" );
		}

		// Backfill project_members from existing team_members cards. Matches
		// card content to wp_users.display_name, so any teammates added before
		// this migration retain their access. INSERT IGNORE skips duplicates
		// (re-running the migration is a no-op).
		$members_tbl = $p . 'project_members';
		$cards_tbl   = $p . 'project_cards';
		$wpdb->query( "INSERT IGNORE INTO {$members_tbl}
				(project_id, user_id, org_id, added_by_user_id, created_at)
			SELECT c.project_id, u.ID, c.org_id, c.created_by_user_id, c.created_at
			FROM {$cards_tbl} c
			INNER JOIN {$wpdb->users} u ON u.display_name = c.content
			WHERE c.field_path LIKE '%team_members%'" );

		update_option( 'qualinav_qi_db_version', QUALINAV_QI_DB_VERSION );

		// Activation/migration may run before the `init` action that normally
		// registers the router's rewrite rules, so register them inline here
		// before flushing — otherwise the flush would only persist the rules
		// that happen to already be registered.
		if ( class_exists( 'Qualinav_QI_Router' ) ) {
			Qualinav_QI_Router::register_rewrite_rules();
		}
		flush_rewrite_rules( false );
	}
}
