<?php
/**
 * Cards repository. Backs the card_list, card_list_2col, card_slots_named,
 * and card_slots_numbered field types.
 *
 *  - field_path identifies the section (e.g. "improvement_canvas.scope")
 *  - slot_key disambiguates within a section (e.g. "in_scope" / "out_of_scope",
 *    "population" / "idea_1" — NULL for plain card_list).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Card_Repository extends Qualinav_QI_Base_Repository {

	protected $table_suffix = 'project_cards';

	/** @var Qualinav_QI_Activity_Repository */
	private $activity;

	public function __construct( ?Qualinav_QI_Activity_Repository $activity = null ) {
		$this->activity = $activity ?: new Qualinav_QI_Activity_Repository();
	}

	public function list_for_project( $project_id, $org_id, $field_path = null, $slot_key = null ) {
		global $wpdb;
		$where  = array( 'project_id = %d', 'org_id = %d' );
		$params = array( (int) $project_id, (int) $org_id );

		if ( $field_path !== null ) {
			$where[]  = 'field_path = %s';
			$params[] = (string) $field_path;
		}
		if ( $slot_key !== null ) {
			if ( $slot_key === '' ) {
				$where[] = 'slot_key IS NULL';
			} else {
				$where[]  = 'slot_key = %s';
				$params[] = (string) $slot_key;
			}
		}

		$sql = "SELECT * FROM {$this->table()} WHERE " . implode( ' AND ', $where )
			. ' ORDER BY field_path, slot_key, position, id';

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	public function add( $project_id, $org_id, $field_path, $slot_key, $content, $user_id ) {
		global $wpdb;
		$content    = wp_kses_post( $content );
		$field_path = sanitize_text_field( $field_path );
		$slot_key   = $slot_key === null || $slot_key === '' ? null : sanitize_key( $slot_key );

		if ( trim( wp_strip_all_tags( $content ) ) === '' ) {
			return new WP_Error( 'qi_invalid', 'Card content cannot be empty' );
		}

		$next_position = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->table()}
				 WHERE project_id = %d AND field_path = %s AND " . ( $slot_key === null ? 'slot_key IS NULL' : 'slot_key = %s' ),
				$slot_key === null ? array( (int) $project_id, $field_path ) : array( (int) $project_id, $field_path, $slot_key )
			)
		);

		$now = $this->now();
		$wpdb->insert(
			$this->table(),
			array(
				'project_id'         => (int) $project_id,
				'org_id'             => (int) $org_id,
				'field_path'         => $field_path,
				'slot_key'           => $slot_key,
				'position'           => $next_position,
				'content'            => $content,
				'created_by_user_id' => (int) $user_id,
				'updated_by_user_id' => (int) $user_id,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;

		$this->activity->log( $org_id, $project_id, $user_id, 'card.added', 'card', $id, array( 'field_path' => $field_path, 'slot_key' => $slot_key ) );
		return $this->find_for_org( $id, $org_id );
	}

	public function update( $card_id, $org_id, $content, $user_id ) {
		global $wpdb;
		$card = $this->find_for_org( $card_id, $org_id );
		if ( ! $card ) {
			return new WP_Error( 'qi_not_found', 'Card not found' );
		}
		$content = wp_kses_post( $content );
		if ( trim( wp_strip_all_tags( $content ) ) === '' ) {
			return new WP_Error( 'qi_invalid', 'Card content cannot be empty' );
		}
		$wpdb->update(
			$this->table(),
			array(
				'content'            => $content,
				'updated_by_user_id' => (int) $user_id,
				'updated_at'         => $this->now(),
			),
			array( 'id' => (int) $card_id, 'org_id' => (int) $org_id ),
			array( '%s', '%d', '%s' ),
			array( '%d', '%d' )
		);
		$this->activity->log( $org_id, (int) $card['project_id'], $user_id, 'card.updated', 'card', (int) $card_id );
		return $this->find_for_org( $card_id, $org_id );
	}

	public function delete( $card_id, $org_id, $user_id ) {
		$card = $this->find_for_org( $card_id, $org_id );
		if ( ! $card ) {
			return new WP_Error( 'qi_not_found', 'Card not found' );
		}
		$ok = $this->delete_for_org( $card_id, $org_id );
		if ( $ok ) {
			$this->activity->log( $org_id, (int) $card['project_id'], $user_id, 'card.deleted', 'card', (int) $card_id );
		}
		return $ok;
	}
}
