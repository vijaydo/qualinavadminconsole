<?php
/**
 * Admin Overview screen — cross-org monitoring for platform admins.
 *
 * Read-only. Intentionally NOT org-scoped: this is the super-admin view, so it
 * queries the wp_qi_* tables directly rather than through the org-scoped
 * repositories (which would filter to a single org).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Admin_Overview {

	private static function t( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . $suffix;
	}

	/** True if a (non-prefixed-by-QI) table physically exists. */
	private static function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public static function render() {
		if ( ! current_user_can( Qualinav_QI_Admin::CAPABILITY ) ) {
			wp_die( 'Access denied.' );
		}
		global $wpdb;

		$projects_t = self::t( 'projects' );
		$orgs_t     = self::t( 'orgs' );          // QI mapping table (canonical_org_id -> wp_organizations.id)
		$members_t  = self::t( 'org_members' );   // legacy stub membership
		$activity_t = self::t( 'activity_log' );
		$tpl_t      = self::t( 'templates' );
		$ver_t      = self::t( 'template_versions' );
		$cards_t    = self::t( 'project_cards' );

		// Real org data lives in the platform-wide tables, not the QI stubs.
		$org_real_t = $wpdb->prefix . 'organizations';
		$states_t   = $wpdb->prefix . 'states';
		$users_t    = $wpdb->users;
		$has_real_orgs = self::table_exists( $org_real_t );
		$has_states    = self::table_exists( $states_t );

		$stats = array(
			'projects'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$projects_t}" ),
			'in_progress' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$projects_t} WHERE status IN ('draft','in_progress')" ),
			'completed'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$projects_t} WHERE status = 'completed'" ),
			// "Active" orgs = orgs with at least one active (draft / in_progress) QI project.
			'orgs'        => $has_real_orgs
				? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$org_real_t} org WHERE EXISTS ( SELECT 1 FROM {$projects_t} pr JOIN {$orgs_t} qo ON qo.id = pr.org_id WHERE qo.canonical_org_id = org.id AND pr.status IN ('draft','in_progress') )" )
				: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orgs_t} o WHERE EXISTS ( SELECT 1 FROM {$projects_t} pr WHERE pr.org_id = o.id AND pr.status IN ('draft','in_progress') )" ),
			'members'     => $has_real_orgs
				? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$users_t} WHERE organization_id IS NOT NULL AND organization_id > 0" )
				: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$members_t}" ),
			'templates'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tpl_t}" ),
			'versions'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ver_t}" ),
			'cards'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cards_t}" ),
		);

		// Resolve each project's org to its real organization name (via the QI
		// mapping), falling back to the QI mapping row's own name.
		$projects = $wpdb->get_results(
			"SELECT p.id, p.title, p.status, p.owner_user_id, p.created_at, p.updated_at,
			        COALESCE(" . ( $has_real_orgs ? 'org.name, ' : '' ) . "o.name) AS org_name
			   FROM {$projects_t} p
			   LEFT JOIN {$orgs_t} o ON o.id = p.org_id" .
			( $has_real_orgs ? " LEFT JOIN {$org_real_t} org ON org.id = o.canonical_org_id" : '' ) . "
			  ORDER BY p.updated_at DESC
			  LIMIT 50",
			ARRAY_A
		);

		$activity = $wpdb->get_results(
			"SELECT a.id, a.action, a.target_type, a.user_id, a.project_id, a.created_at,
			        p.title AS project_title
			   FROM {$activity_t} a
			   LEFT JOIN {$projects_t} p ON p.id = a.project_id
			  ORDER BY a.id DESC
			  LIMIT 30",
			ARRAY_A
		);

		if ( $has_real_orgs ) {
			$state_select = $has_states ? 's.code AS state_code, s.name AS state_name' : "'' AS state_code, '' AS state_name";
			$state_join   = $has_states ? "LEFT JOIN {$states_t} s ON s.id = org.state_id" : '';
			$orgs = $wpdb->get_results(
				"SELECT org.id, org.name, org.slug, {$state_select},
				        ( SELECT COUNT(*) FROM {$users_t} u WHERE u.organization_id = org.id ) AS member_count,
				        ( SELECT COUNT(*) FROM {$projects_t} pr
				           JOIN {$orgs_t} qo ON qo.id = pr.org_id
				          WHERE qo.canonical_org_id = org.id ) AS project_count
				   FROM {$org_real_t} org
				   {$state_join}
				  WHERE EXISTS (
				        SELECT 1 FROM {$projects_t} pr
				          JOIN {$orgs_t} qo ON qo.id = pr.org_id
				         WHERE qo.canonical_org_id = org.id
				           AND pr.status IN ('draft','in_progress')
				  )
				  ORDER BY org.name ASC",
				ARRAY_A
			);
		} else {
			$orgs = $wpdb->get_results(
				"SELECT o.id, o.name, o.slug, '' AS state_code, '' AS state_name,
				        ( SELECT COUNT(*) FROM {$members_t} m WHERE m.org_id = o.id ) AS member_count,
				        ( SELECT COUNT(*) FROM {$projects_t} pr WHERE pr.org_id = o.id ) AS project_count
				   FROM {$orgs_t} o
				  WHERE EXISTS ( SELECT 1 FROM {$projects_t} pr WHERE pr.org_id = o.id AND pr.status IN ('draft','in_progress') )
				  ORDER BY o.name ASC",
				ARRAY_A
			);
		}

		Qualinav_QI_Admin::render_header( Qualinav_QI_Admin::MENU_SLUG );
		?>

		<div class="qi-stats">
			<?php
			$cards = array(
				'Projects'        => $stats['projects'],
				'In progress'     => $stats['in_progress'],
				'Completed'       => $stats['completed'],
				'Active orgs'     => $stats['orgs'],
				'Members'         => $stats['members'],
				'Templates'       => $stats['templates'],
				'Versions'        => $stats['versions'],
				'Cards'           => $stats['cards'],
			);
			foreach ( $cards as $label => $value ) :
				?>
				<div class="qi-stat-card">
					<span class="qi-stat-value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
					<span class="qi-stat-label"><?php echo esc_html( $label ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<h2>Projects</h2>
		<table class="widefat striped qi-admin-table">
			<thead>
				<tr>
					<th>Title</th><th>Organization</th><th>Owner</th>
					<th>Status</th><th>Created</th><th>Last updated</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $projects ) ) : ?>
					<tr><td colspan="6">No projects yet.</td></tr>
				<?php else : foreach ( $projects as $p ) :
					$owner = get_userdata( (int) $p['owner_user_id'] );
					?>
					<tr>
						<td><?php echo esc_html( $p['title'] ); ?></td>
						<td><?php echo esc_html( $p['org_name'] ?: '—' ); ?></td>
						<td><?php echo esc_html( $owner ? $owner->display_name : ( '#' . (int) $p['owner_user_id'] ) ); ?></td>
						<td><span class="qi-badge qi-badge--<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( $p['status'] ); ?></span></td>
						<td><?php echo esc_html( mysql2date( 'M j, Y', $p['created_at'] ) ); ?></td>
						<td><?php echo esc_html( mysql2date( 'M j, Y H:i', $p['updated_at'] ) ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>

		<div class="qi-admin-cols">
			<div class="qi-admin-col">
				<h2>Recent activity</h2>
				<table class="widefat striped qi-admin-table">
					<thead><tr><th>When</th><th>User</th><th>Action</th><th>Project</th></tr></thead>
					<tbody>
						<?php if ( empty( $activity ) ) : ?>
							<tr><td colspan="4">No activity logged yet.</td></tr>
						<?php else : foreach ( $activity as $a ) :
							$u = get_userdata( (int) $a['user_id'] );
							?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'M j H:i', $a['created_at'] ) ); ?></td>
								<td><?php echo esc_html( $u ? $u->display_name : ( '#' . (int) $a['user_id'] ) ); ?></td>
								<td><code><?php echo esc_html( $a['action'] ); ?></code></td>
								<td><?php echo esc_html( $a['project_title'] ?: ( $a['project_id'] ? ( '#' . (int) $a['project_id'] ) : '—' ) ); ?></td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>

			<div class="qi-admin-col">
				<h2>Organizations &amp; members</h2>
				<table class="widefat striped qi-admin-table">
					<thead><tr><th>Org</th><th>Slug</th><th>State</th><th>Members</th><th>Projects</th></tr></thead>
					<tbody>
						<?php if ( empty( $orgs ) ) : ?>
							<tr><td colspan="5">No organizations yet.</td></tr>
						<?php else : foreach ( $orgs as $o ) :
							$state = trim( (string) $o['state_code'] );
							if ( $state !== '' && ! empty( $o['state_name'] ) ) {
								$state .= ' — ' . $o['state_name'];
							} elseif ( $state === '' ) {
								$state = '—';
							}
							?>
							<tr>
								<td><?php echo esc_html( $o['name'] ); ?></td>
								<td><code><?php echo esc_html( $o['slug'] ); ?></code></td>
								<td><?php echo esc_html( $state ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $o['member_count'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $o['project_count'] ) ); ?></td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<?php
		Qualinav_QI_Admin::render_footer();
	}
}
