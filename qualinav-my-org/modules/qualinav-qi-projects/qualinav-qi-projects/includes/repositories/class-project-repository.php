<?php
/**
 * Projects repository. Every method takes (or derives) an org_id and filters by it.
 *
 * Uses the hybrid model: each project row has an optional post_id pointing at a
 * qi_project CPT shell, created on first save so the project gets a stable WP-side
 * ID for permissions / REST / AI brain references.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Project_Repository extends Qualinav_QI_Base_Repository {

	protected $table_suffix = 'projects';

	/** @var Qualinav_QI_Activity_Repository */
	private $activity;

	public function __construct( ?Qualinav_QI_Activity_Repository $activity = null ) {
		$this->activity = $activity ?: new Qualinav_QI_Activity_Repository();
	}

	public function list_for_org( $org_id, $args = array() ) {
		global $wpdb;
		$org_id = (int) $org_id;
		if ( $org_id <= 0 ) {
			return array();
		}

		$defaults = array(
			'status'          => null,
			'owner'           => null,
			'visible_to_user' => null,
			'limit'           => 50,
			'offset'          => 0,
			'order_by'        => 'updated_at',
			'order'           => 'DESC',
		);
		$args = array_merge( $defaults, $args );

		$projects_tbl = $this->table();
		$members_tbl  = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'project_members';

		$where  = array( 'p.org_id = %d' );
		$params = array( $org_id );

		if ( $args['status'] ) {
			$where[]  = 'p.status = %s';
			$params[] = (string) $args['status'];
		}
		if ( $args['owner'] ) {
			$where[]  = 'p.owner_user_id = %d';
			$params[] = (int) $args['owner'];
		}
		// Per-project ACL: a user sees a project iff they own it OR they're an
		// explicit team member. Org-only scoping (visible_to_user=null) is kept
		// for admin/AI-brain code paths that intentionally cross-cut users.
		if ( $args['visible_to_user'] !== null ) {
			$where[]  = '(p.owner_user_id = %d OR EXISTS (SELECT 1 FROM ' . $members_tbl . ' m WHERE m.project_id = p.id AND m.user_id = %d))';
			$params[] = (int) $args['visible_to_user'];
			$params[] = (int) $args['visible_to_user'];
		}

		$order_by = in_array( $args['order_by'], array( 'created_at', 'updated_at', 'completed_at', 'title', 'status' ), true )
			? $args['order_by']
			: 'updated_at';
		$order    = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$limit    = max( 1, min( 200, (int) $args['limit'] ) );
		$offset   = max( 0, (int) $args['offset'] );

		$sql = "SELECT p.* FROM {$projects_tbl} p WHERE " . implode( ' AND ', $where )
			. " ORDER BY p.{$order_by} {$order} LIMIT {$limit} OFFSET {$offset}";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Project-scoped override of the base `find_for_org`. When $user_id is
	 * provided, also enforce the per-project ACL (owner OR team member).
	 * Internal repo methods that already verified access (e.g. inside create()
	 * after the actor inserted the row) pass null to preserve legacy behavior.
	 */
	public function find_for_org( $id, $org_id, $user_id = null ) {
		global $wpdb;
		$id     = (int) $id;
		$org_id = (int) $org_id;
		if ( $id <= 0 || $org_id <= 0 ) {
			return null;
		}

		if ( $user_id === null ) {
			return parent::find_for_org( $id, $org_id );
		}

		$projects_tbl = $this->table();
		$members_tbl  = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'project_members';
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.* FROM {$projects_tbl} p
			 WHERE p.id = %d AND p.org_id = %d
			   AND (p.owner_user_id = %d OR EXISTS (
				 SELECT 1 FROM {$members_tbl} m WHERE m.project_id = p.id AND m.user_id = %d
			   ))",
			$id,
			$org_id,
			(int) $user_id,
			(int) $user_id
		), ARRAY_A );
		return $row ?: null;
	}

	public function count_for_org( $org_id, $status = null ) {
		global $wpdb;
		$org_id = (int) $org_id;
		if ( $org_id <= 0 ) {
			return 0;
		}
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table()} WHERE org_id = %d AND status = %s",
				$org_id,
				(string) $status
			) );
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table()} WHERE org_id = %d",
			$org_id
		) );
	}

	public function create( $org_id, $owner_user_id, $title, $template_version_id, $extras = array() ) {
		global $wpdb;
		$org_id              = (int) $org_id;
		$owner_user_id       = (int) $owner_user_id;
		$template_version_id = (int) $template_version_id;
		$title               = sanitize_text_field( $title );

		if ( $org_id <= 0 || $owner_user_id <= 0 || $template_version_id <= 0 || $title === '' ) {
			return new WP_Error( 'qi_invalid_args', 'org_id, owner_user_id, template_version_id, title are required' );
		}

		$post_id = wp_insert_post( array(
			'post_type'   => Qualinav_QI_CPT::POST_TYPE,
			'post_title'  => $title,
			'post_status' => 'draft',
			'post_author' => $owner_user_id,
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$now = $this->now();
		$wpdb->insert(
			$this->table(),
			array(
				'org_id'              => $org_id,
				'template_version_id' => $template_version_id,
				'post_id'             => (int) $post_id,
				'title'               => $title,
				'status'              => 'draft',
				'pillar'              => isset( $extras['pillar'] ) ? sanitize_text_field( $extras['pillar'] ) : null,
				'focus_area'          => isset( $extras['focus_area'] ) ? sanitize_text_field( $extras['focus_area'] ) : null,
				'owner_user_id'       => $owner_user_id,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$project_id = (int) $wpdb->insert_id;

		$this->activity->log( $org_id, $project_id, $owner_user_id, 'project.created', 'project', $project_id, array( 'title' => $title ) );

		return $this->find_for_org( $project_id, $org_id );
	}

	public function update_metadata( $project_id, $org_id, $patch, $user_id ) {
		global $wpdb;
		$project = $this->find_for_org( $project_id, $org_id );
		if ( ! $project ) {
			return new WP_Error( 'qi_not_found', 'Project not found in this org' );
		}

		$set    = array( 'updated_at' => $this->now() );
		$format = array( '%s' );

		if ( isset( $patch['title'] ) ) {
			$set['title']  = sanitize_text_field( $patch['title'] );
			$format[]      = '%s';
			if ( $project['post_id'] ) {
				wp_update_post( array( 'ID' => (int) $project['post_id'], 'post_title' => $set['title'] ) );
			}
		}
		foreach ( array( 'pillar', 'focus_area' ) as $f ) {
			if ( isset( $patch[ $f ] ) ) {
				$set[ $f ] = sanitize_text_field( $patch[ $f ] );
				$format[]  = '%s';
			}
		}
		if ( isset( $patch['status'] ) && in_array( $patch['status'], array( 'draft', 'in_progress', 'completed', 'archived' ), true ) ) {
			$set['status'] = $patch['status'];
			$format[]      = '%s';
		}

		$wpdb->update(
			$this->table(),
			$set,
			array( 'id' => (int) $project_id, 'org_id' => (int) $org_id ),
			$format,
			array( '%d', '%d' )
		);

		$this->activity->log( $org_id, $project_id, $user_id, 'project.updated', 'project', $project_id, $patch );
		return $this->find_for_org( $project_id, $org_id );
	}

	public function complete( $project_id, $org_id, $user_id ) {
		global $wpdb;
		$project = $this->find_for_org( $project_id, $org_id );
		if ( ! $project ) {
			return new WP_Error( 'qi_not_found', 'Project not found in this org' );
		}
		if ( (int) $project['owner_user_id'] !== (int) $user_id ) {
			return new WP_Error(
				'qi_forbidden',
				'Only the project owner can mark this project complete.',
				array( 'status' => 403 )
			);
		}
		$now = $this->now();
		$wpdb->update(
			$this->table(),
			array( 'status' => 'completed', 'completed_at' => $now, 'updated_at' => $now ),
			array( 'id' => (int) $project_id, 'org_id' => (int) $org_id ),
			array( '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);

		// Publish the qi_project CPT shell so it surfaces in My Space (which
		// queries post_status='publish'). Before completion it stays as a
		// draft — the "report" exists only once the owner finalises it.
		if ( ! empty( $project['post_id'] ) ) {
			wp_update_post( array(
				'ID'          => (int) $project['post_id'],
				'post_status' => 'publish',
			) );
		}

		$this->activity->log( $org_id, $project_id, $user_id, 'project.completed', 'project', $project_id );
		$this->notify_team_on_complete( $project, $user_id );

		// AI brain hook — webhooks subscribe here in Phase 5
		do_action( 'qualinav_qi_project_completed', $project_id, $org_id, $user_id );

		return $this->find_for_org( $project_id, $org_id );
	}

	/**
	 * Sends a bell-icon notification to every project_members user (plus a
	 * separate "you completed it" notice for the owner). No-op if the
	 * notifications plugin isn't loaded.
	 */
	private function notify_team_on_complete( $project, $actor_id ) {
		if ( ! function_exists( 'dttc_create_notification' ) ) {
			return;
		}
		global $wpdb;
		$members_tbl = $wpdb->prefix . QUALINAV_QI_TABLE_PREFIX . 'project_members';
		$project_id  = (int) $project['id'];
		$title       = (string) ( $project['title'] ?? 'a QI project' );
		$actor       = get_userdata( (int) $actor_id );
		$actor_name  = $actor ? $actor->display_name : 'The project owner';
		$link        = home_url( '/qi-projects/?qi=' . $project_id );
		$message     = sprintf( '%s completed QI project "%s" — the report is now in your My Space.', $actor_name, $title );

		$recipient_ids = (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM {$members_tbl} WHERE project_id = %d",
			$project_id
		) );
		// Owner is the actor; skip them in the team blast and send a tailored confirmation.
		$seen = array();
		foreach ( $recipient_ids as $rid ) {
			$rid = (int) $rid;
			if ( $rid <= 0 || $rid === (int) $actor_id || isset( $seen[ $rid ] ) ) {
				continue;
			}
			$seen[ $rid ] = true;
			dttc_create_notification( $rid, (int) $actor_id, 0, 'qi_project_completed', $message, $link );
		}
		dttc_create_notification(
			(int) $actor_id,
			(int) $actor_id,
			0,
			'qi_project_completed_self',
			sprintf( 'You completed "%s" and shared the report with the team.', $title ),
			$link
		);
	}

	public function archive( $project_id, $org_id, $user_id ) {
		return $this->update_metadata( $project_id, $org_id, array( 'status' => 'archived' ), $user_id );
	}
}
