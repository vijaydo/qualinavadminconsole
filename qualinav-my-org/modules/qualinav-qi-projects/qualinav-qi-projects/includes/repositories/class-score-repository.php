<?php
/**
 * Idea-score repository (Matrix Diagram tab).
 *
 *   - One score per (idea_card_id, criterion_key, scored_by_user_id) so each
 *     teammate keeps their own value. The displayed cell value is the average
 *     across the team; the slider reflects the current user's own pick.
 *   - Idea cards live in wp_qi_project_cards (field_path='improvement_canvas.change_ideas',
 *     slot_key='idea_1'..'idea_5'); score row's idea_card_id FKs to that card
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Score_Repository extends Qualinav_QI_Base_Repository {

	protected $table_suffix = 'project_idea_scores';

	/** @var Qualinav_QI_Activity_Repository */
	private $activity;

	public function __construct( ?Qualinav_QI_Activity_Repository $activity = null ) {
		$this->activity = $activity ?: new Qualinav_QI_Activity_Repository();
	}

	/**
	 * Returns scores indexed by [idea_card_id][criterion_key] = [
	 *   'avg'      => float,    // team mean (1.0-5.0)
	 *   'count'    => int,      // number of teammates who scored this cell
	 *   'my_score' => int|null, // current user's own score, or null if unset
	 * ].
	 *
	 * Pass $user_id = 0 to get team-only data (no per-user `my_score`).
	 */
	public function list_for_project( $project_id, $org_id, $user_id = 0 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT
				idea_card_id,
				criterion_key,
				AVG(score) AS avg_score,
				COUNT(*)   AS scorer_count,
				MAX(CASE WHEN scored_by_user_id = %d THEN score END) AS my_score
			 FROM {$this->table()}
			 WHERE project_id = %d AND org_id = %d
			 GROUP BY idea_card_id, criterion_key",
			(int) $user_id,
			(int) $project_id,
			(int) $org_id
		), ARRAY_A );

		$out = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r['idea_card_id'] ][ (string) $r['criterion_key'] ] = array(
				'avg'      => round( (float) $r['avg_score'], 1 ),
				'count'    => (int) $r['scorer_count'],
				'my_score' => $r['my_score'] !== null ? (int) $r['my_score'] : null,
			);
		}
		return $out;
	}

	public function upsert( $project_id, $org_id, $idea_card_id, $criterion_key, $score, $user_id ) {
		global $wpdb;
		$score         = max( 1, min( 5, (int) $score ) );
		$idea_card_id  = (int) $idea_card_id;
		$criterion_key = sanitize_key( $criterion_key );
		$user_id       = (int) $user_id;
		$now           = $this->now();

		// Per-user uniqueness — find the user's own row for this cell first.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$this->table()}
			 WHERE idea_card_id = %d AND criterion_key = %s AND scored_by_user_id = %d AND org_id = %d
			 LIMIT 1",
			$idea_card_id,
			$criterion_key,
			$user_id,
			(int) $org_id
		) );

		if ( $existing ) {
			$wpdb->update(
				$this->table(),
				array(
					'score'      => $score,
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing, 'org_id' => (int) $org_id ),
				array( '%d', '%s' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$this->table(),
				array(
					'project_id'        => (int) $project_id,
					'org_id'            => (int) $org_id,
					'idea_card_id'      => $idea_card_id,
					'criterion_key'     => $criterion_key,
					'score'             => $score,
					'scored_by_user_id' => $user_id,
					'created_at'        => $now,
					'updated_at'        => $now,
				),
				array( '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
			);
		}

		$this->activity->log( $org_id, $project_id, $user_id, 'score.set', 'score', $idea_card_id, array(
			'criterion' => $criterion_key,
			'score'     => $score,
		) );

		// Return the freshly-computed team aggregates for this cell so the
		// client can update the displayed average in real time.
		$agg = $wpdb->get_row( $wpdb->prepare(
			"SELECT AVG(score) AS avg_score, COUNT(*) AS scorer_count
			 FROM {$this->table()}
			 WHERE idea_card_id = %d AND criterion_key = %s AND org_id = %d",
			$idea_card_id,
			$criterion_key,
			(int) $org_id
		), ARRAY_A );

		return array(
			'idea_card_id'  => $idea_card_id,
			'criterion_key' => $criterion_key,
			'my_score'      => $score,
			'avg'           => $agg ? round( (float) $agg['avg_score'], 1 ) : (float) $score,
			'count'         => $agg ? (int) $agg['scorer_count'] : 1,
		);
	}
}
