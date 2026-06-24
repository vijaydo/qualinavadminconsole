<?php
/**
 * Single-value fields repository. Backs single_text, single_textarea, computed.
 * Upsert per (project_id, field_path).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Field_Repository extends Qualinav_QI_Base_Repository {

	protected $table_suffix = 'project_fields';

	/** @var Qualinav_QI_Activity_Repository */
	private $activity;

	public function __construct( ?Qualinav_QI_Activity_Repository $activity = null ) {
		$this->activity = $activity ?: new Qualinav_QI_Activity_Repository();
	}

	public function list_for_project( $project_id, $org_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE project_id = %d AND org_id = %d",
				(int) $project_id,
				(int) $org_id
			),
			ARRAY_A
		);
	}

	public function get( $project_id, $org_id, $field_path ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE project_id = %d AND org_id = %d AND field_path = %s",
				(int) $project_id,
				(int) $org_id,
				(string) $field_path
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function upsert( $project_id, $org_id, $field_path, $field_type, $value, $user_id ) {
		global $wpdb;
		$field_path = sanitize_text_field( $field_path );
		$field_type = sanitize_key( $field_type );

		$value_text   = null;
		$value_number = null;
		$value_json   = null;

		if ( in_array( $field_type, array( 'text', 'textarea' ), true ) ) {
			$value_text = $field_type === 'textarea' ? wp_kses_post( $value ) : sanitize_text_field( $value );
		} elseif ( $field_type === 'number' || $field_type === 'computed' ) {
			$value_number = is_numeric( $value ) ? (float) $value : null;
		} else {
			$value_json = is_string( $value ) ? $value : wp_json_encode( $value );
		}

		$existing = $this->get( $project_id, $org_id, $field_path );
		$now      = $this->now();

		if ( $existing ) {
			$wpdb->update(
				$this->table(),
				array(
					'field_type'         => $field_type,
					'value_text'         => $value_text,
					'value_number'       => $value_number,
					'value_json'         => $value_json,
					'updated_by_user_id' => (int) $user_id,
					'updated_at'         => $now,
				),
				array( 'id' => (int) $existing['id'] ),
				array( '%s', '%s', '%f', '%s', '%d', '%s' ),
				array( '%d' )
			);
			$this->activity->log( $org_id, $project_id, $user_id, 'field.updated', 'field', (int) $existing['id'], array( 'field_path' => $field_path ) );
		} else {
			$wpdb->insert(
				$this->table(),
				array(
					'project_id'         => (int) $project_id,
					'org_id'             => (int) $org_id,
					'field_path'         => $field_path,
					'field_type'         => $field_type,
					'value_text'         => $value_text,
					'value_number'       => $value_number,
					'value_json'         => $value_json,
					'updated_by_user_id' => (int) $user_id,
					'created_at'         => $now,
					'updated_at'         => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s' )
			);
			$this->activity->log( $org_id, $project_id, $user_id, 'field.set', 'field', (int) $wpdb->insert_id, array( 'field_path' => $field_path ) );
		}

		return $this->get( $project_id, $org_id, $field_path );
	}
}
