<?php
/**
 * Measure repository (Commit tab — outcome/process measures with current + target).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Measure_Repository extends Qualinav_QI_Base_Repository {

	protected $table_suffix = 'project_measures';

	/** @var Qualinav_QI_Activity_Repository */
	private $activity;

	public function __construct( ?Qualinav_QI_Activity_Repository $activity = null ) {
		$this->activity = $activity ?: new Qualinav_QI_Activity_Repository();
	}

	public function list_for_project( $project_id, $org_id, $measure_type = null ) {
		global $wpdb;
		$where  = array( 'project_id = %d', 'org_id = %d' );
		$params = array( (int) $project_id, (int) $org_id );
		if ( $measure_type ) {
			$where[]  = 'measure_type = %s';
			$params[] = (string) $measure_type;
		}
		$sql = "SELECT * FROM {$this->table()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY measure_type, position, id';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	public function add( $project_id, $org_id, $measure_type, $description, $current, $target, $user_id ) {
		global $wpdb;
		$measure_type = in_array( $measure_type, array( 'outcome', 'process' ), true ) ? $measure_type : 'outcome';
		$description  = wp_kses_post( $description );
		if ( trim( wp_strip_all_tags( $description ) ) === '' ) {
			return new WP_Error( 'qi_invalid', 'Measure description cannot be empty' );
		}

		$next_position = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->table()} WHERE project_id = %d AND org_id = %d AND measure_type = %s",
			(int) $project_id,
			(int) $org_id,
			$measure_type
		) );

		$now = $this->now();
		$wpdb->insert(
			$this->table(),
			array(
				'project_id'         => (int) $project_id,
				'org_id'             => (int) $org_id,
				'measure_type'       => $measure_type,
				'position'           => $next_position,
				'description'        => $description,
				'current_value'      => $current !== null ? sanitize_text_field( $current ) : null,
				'target_value'       => $target !== null ? sanitize_text_field( $target ) : null,
				'created_by_user_id' => (int) $user_id,
				'updated_by_user_id' => (int) $user_id,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		$this->activity->log( $org_id, $project_id, $user_id, 'measure.added', 'measure', $id, array( 'type' => $measure_type ) );
		return $this->find_for_org( $id, $org_id );
	}

	public function update( $measure_id, $org_id, $patch, $user_id ) {
		global $wpdb;
		$row = $this->find_for_org( $measure_id, $org_id );
		if ( ! $row ) {
			return new WP_Error( 'qi_not_found', 'Measure not found' );
		}
		$set    = array( 'updated_at' => $this->now(), 'updated_by_user_id' => (int) $user_id );
		$format = array( '%s', '%d' );

		if ( isset( $patch['description'] ) ) {
			$desc = wp_kses_post( $patch['description'] );
			if ( trim( wp_strip_all_tags( $desc ) ) === '' ) {
				return new WP_Error( 'qi_invalid', 'Description cannot be empty' );
			}
			$set['description'] = $desc;
			$format[]           = '%s';
		}
		if ( array_key_exists( 'current_value', $patch ) ) {
			$set['current_value'] = $patch['current_value'] === null ? null : sanitize_text_field( $patch['current_value'] );
			$format[]             = '%s';
		}
		if ( array_key_exists( 'target_value', $patch ) ) {
			$set['target_value'] = $patch['target_value'] === null ? null : sanitize_text_field( $patch['target_value'] );
			$format[]            = '%s';
		}

		$wpdb->update(
			$this->table(),
			$set,
			array( 'id' => (int) $measure_id, 'org_id' => (int) $org_id ),
			$format,
			array( '%d', '%d' )
		);
		$this->activity->log( $org_id, (int) $row['project_id'], $user_id, 'measure.updated', 'measure', (int) $measure_id );
		return $this->find_for_org( $measure_id, $org_id );
	}

	public function delete( $measure_id, $org_id, $user_id ) {
		$row = $this->find_for_org( $measure_id, $org_id );
		if ( ! $row ) {
			return new WP_Error( 'qi_not_found', 'Measure not found' );
		}
		$ok = $this->delete_for_org( $measure_id, $org_id );
		if ( $ok ) {
			$this->activity->log( $org_id, (int) $row['project_id'], $user_id, 'measure.deleted', 'measure', (int) $measure_id );
		}
		return $ok;
	}
}
