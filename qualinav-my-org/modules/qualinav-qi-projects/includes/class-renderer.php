<?php
/**
 * JSON-driven canvas renderer. Walks the template structure and emits HTML for each
 * field type. The front-end JS (qi-canvas.js) wires up CRUD via the REST API by
 * reading data-* attributes on the rendered nodes.
 *
 * Field types implemented in v1:
 *   card_list, card_list_2col, card_slots_named, card_slots_numbered, single_textarea
 *
 * Stubbed (rendered as informational placeholders for now):
 *   measure_row, idea_score_matrix, phase_timeline, field_reference,
 *   scored_ideas_summary, submit_button
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Renderer {

	/** @var array Scores indexed by [idea_card_id][criterion_key] = score (set per render). */
	private static $scores = array();

	/** @var array Measures rows (flat list, set per render). */
	private static $measures = array();

	/** @var array Project (set per render — used by submit_button to read status). */
	private static $project = array();

	/** @var array Structure (set per render — used to cross-reference matrix criteria from the commit summary). */
	private static $structure = array();

	/** @var array Flat field_values map (set per render — used to read lock flags etc.). */
	private static $field_values = array();

	/** @var int Current viewer user_id — set per render, used to gate owner-only UI. */
	private static $current_user_id = 0;

	/**
	 * Resolves a stored canvas image reference to a usable URL.
	 *
	 * Default canvases now ship inside the plugin and are stored as plugin-relative
	 * paths (e.g. "assets/canvas/improvement-canvas.webp"). Absolute URLs and
	 * site-root paths (e.g. legacy "/wp-content/uploads/..." media-library values)
	 * are passed through unchanged for backward compatibility.
	 */
	public static function resolve_asset_url( $value ) {
		$value = (string) $value;
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '#^(https?:)?//#', $value ) || $value[0] === '/' ) {
			return $value;
		}
		return QUALINAV_QI_PLUGIN_URL . ltrim( $value, '/' );
	}

	public static function render_canvas( $project, $structure, $cards_by_field, $field_values, $scores = array(), $measures = array() ) {
		self::$scores       = is_array( $scores ) ? $scores : array();
		self::$measures     = is_array( $measures ) ? $measures : array();
		self::$project      = is_array( $project ) ? $project : array();
		self::$structure    = is_array( $structure ) ? $structure : array();
		self::$field_values = is_array( $field_values ) ? $field_values : array();
		self::$current_user_id = (int) get_current_user_id();
		$tabs           = $structure['tabs'] ?? array();
		$pid            = (int) $project['id'];
		$dashboard_url  = Qualinav_QI_Shortcodes::find_page_url_with_shortcode( 'qi_projects_dashboard' );
		$back_url       = $dashboard_url ?: apply_filters( 'qualinav_qi_back_to_org_url', home_url( '/my-org/' ) );
		$back_label     = $dashboard_url ? 'Back to QI Projects' : 'Back to My Org';
		?>
		<?php $is_completed = isset( $project['status'] ) && $project['status'] === 'completed'; ?>
		<div class="qi-canvas<?php echo $is_completed ? ' qi-canvas--readonly' : ''; ?>" data-project-id="<?php echo esc_attr( $pid ); ?>" data-project-status="<?php echo esc_attr( $project['status'] ?? '' ); ?>" data-project-title="<?php echo esc_attr( $project['title'] ); ?>">

			<div class="qi-dashboard-topbar">
				<a class="qi-back-circle" href="<?php echo esc_url( $back_url ); ?>" aria-label="<?php echo esc_attr( $back_label ); ?>" title="<?php echo esc_attr( $back_label ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="19" y1="12" x2="5" y2="12"/>
						<polyline points="12 19 5 12 12 5"/>
					</svg>
				</a>
				<h1 class="qi-topbar-title">
					<span class="qi-topbar-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M9 3h6"/>
							<path d="M10 3v7l-5 8a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-8V3"/>
						</svg>
					</span>
					QI Projects
				</h1>
				<span class="qi-topbar-divider" aria-hidden="true"></span>
				<span class="qi-topbar-subtitle"><?php echo esc_html( $project['title'] ); ?></span>
			</div>

			<nav class="qi-tabs" role="tablist">
				<?php foreach ( $tabs as $i => $tab ) : ?>
					<button type="button" class="qi-tab<?php echo $i === 0 ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $tab['key'] ); ?>"><?php echo esc_html( $tab['label'] ); ?></button>
				<?php endforeach; ?>
			</nav>

			<div class="qi-canvas-content">

			<?php foreach ( $tabs as $i => $tab ) : ?>
				<section class="qi-tab-panel<?php echo $i === 0 ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $tab['key'] ); ?>">

					<?php
					$has_intro = ! empty( $tab['title'] ) || ! empty( $tab['description'] ) || ! empty( $tab['image'] );
					if ( $has_intro ) :
						$has_image    = ! empty( $tab['image'] );
						$intro_class  = 'qi-tab-intro' . ( $has_image ? '' : ' qi-tab-intro--no-image' );
					?>
						<div class="<?php echo esc_attr( $intro_class ); ?>">
							<div class="qi-tab-intro-text">
								<?php if ( ! empty( $tab['title'] ) ) : ?>
									<h2 class="qi-tab-title"><?php echo esc_html( $tab['title'] ); ?></h2>
								<?php endif; ?>
								<?php if ( ! empty( $tab['description'] ) ) : ?>
									<?php foreach ( preg_split( '/\n\s*\n/', $tab['description'] ) as $para ) : ?>
										<?php $para = trim( $para ); if ( $para === '' ) continue; ?>
										<p class="qi-tab-desc"><?php echo esc_html( $para ); ?></p>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<?php if ( $has_image ) : ?>
								<div class="qi-tab-intro-image">
									<img src="<?php echo esc_url( self::resolve_asset_url( $tab['image'] ) ); ?>" alt="<?php echo esc_attr( $tab['title'] ?? '' ); ?>" />
									<?php if ( ! empty( $tab['canvas_overlays'] ) ) : ?>
										<?php self::render_canvas_overlays( $tab['canvas_overlays'], $cards_by_field, $field_values ); ?>
									<?php endif; ?>
									<?php if ( ! empty( $tab['canvas_score_grid'] ) ) : ?>
										<?php self::render_matrix_score_grid( $tab['canvas_score_grid'], $cards_by_field ); ?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php
					// Tabs may have either steps (Improvement, Gameplan, Commit) or top-level fields (Matrix)
					if ( ! empty( $tab['steps'] ) ) {
						foreach ( $tab['steps'] as $step ) {
							self::render_step( $step, $cards_by_field, $field_values );
						}
					} elseif ( ! empty( $tab['fields'] ) ) {
						echo '<div class="qi-step qi-color-' . esc_attr( $tab['color'] ?? 'blue' ) . '">';
						foreach ( $tab['fields'] as $field ) {
							self::render_field( $field, $cards_by_field, $field_values );
						}
						echo '</div>';
					}
					?>
				</section>
			<?php endforeach; ?>

			</div><!-- /.qi-canvas-content -->
		</div>
		<?php
	}

	private static function render_step( $step, $cards_by_field, $field_values ) {
		$color   = isset( $step['color'] ) ? $step['color'] : 'blue';
		$compact = ! empty( $step['compact'] );
		?>
		<div class="qi-step qi-color-<?php echo esc_attr( $color ); ?><?php echo $compact ? ' qi-step--compact' : ''; ?>">
			<div class="qi-step-header"><?php echo esc_html( $step['title'] ?? '' ); ?></div>
			<?php if ( ! empty( $step['helper'] ) ) : ?>
				<p class="qi-step-helper"><?php echo esc_html( $step['helper'] ); ?></p>
			<?php endif; ?>
			<?php
			if ( ! empty( $step['fields'] ) ) {
				foreach ( $step['fields'] as $field ) {
					self::render_field( $field, $cards_by_field, $field_values );
				}
			}
			?>
		</div>
		<?php
	}

	private static function render_field( $field, $cards_by_field, $field_values ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';
		switch ( $type ) {
			case 'card_list':
				self::render_card_list( $field, $cards_by_field );
				break;
			case 'card_list_2col':
				self::render_card_list_2col( $field, $cards_by_field );
				break;
			case 'card_slots_named':
				self::render_card_slots_named( $field, $cards_by_field );
				break;
			case 'card_slots_numbered':
				self::render_card_slots_numbered( $field, $cards_by_field );
				break;
			case 'single_textarea':
				self::render_single_textarea( $field, $field_values );
				break;
			case 'idea_score_matrix':
				self::render_idea_score_matrix( $field, $cards_by_field );
				break;
			case 'field_reference':
				self::render_field_reference( $field, $cards_by_field, $field_values );
				break;
			case 'phase_timeline':
				self::render_phase_timeline( $field, $field_values );
				break;
			case 'measure_row':
				self::render_measure_row( $field );
				break;
			case 'scored_ideas_summary':
				self::render_scored_ideas_summary( $field, $cards_by_field );
				break;
			case 'submit_button':
				self::render_submit_button( $field );
				break;
			default:
				echo '<div class="qi-field qi-stub">'
					. '<p class="qi-stub-note">Field type <code>' . esc_html( $type ) . '</code> renderer coming in next iteration.</p>'
					. '</div>';
		}
	}

	/**
	 * Renders the Matrix Diagram scoring rows. One row per idea card found in the
	 * referenced Improvement Canvas slot, with a slider for each criterion and
	 * a live-updating cumulative score.
	 */
	private static function render_idea_score_matrix( $field, $cards_by_field ) {
		$ideas_source  = isset( $field['ideas_source'] ) ? $field['ideas_source'] : '';
		$criteria      = isset( $field['criteria'] ) ? $field['criteria'] : array();
		$lock_path     = isset( $field['lock_path'] ) ? $field['lock_path'] : '';
		$is_locked     = $lock_path && self::is_field_truthy( $lock_path );
		$ideas_by_slot = isset( $cards_by_field[ $ideas_source ] ) ? $cards_by_field[ $ideas_source ] : array();

		// Order slots: idea_1, idea_2, ..., idea_N
		if ( ! empty( $ideas_by_slot ) ) {
			uksort( $ideas_by_slot, function ( $a, $b ) {
				return ( (int) preg_replace( '/\D+/', '', $a ) ) <=> ( (int) preg_replace( '/\D+/', '', $b ) );
			} );
		}

		$has_rows = false;
		echo '<div class="qi-field qi-idea-matrix' . ( $is_locked ? ' is-locked' : '' ) . '"'
			. ' data-criteria-count="' . esc_attr( count( $criteria ) ) . '"'
			. ' data-criteria-json="' . esc_attr( wp_json_encode( $criteria ) ) . '"'
			. ' data-ideas-source="' . esc_attr( $ideas_source ) . '"'
			. ( $lock_path ? ' data-lock-path="' . esc_attr( $lock_path ) . '"' : '' )
			. '>';
		if ( ! empty( $criteria ) && ! empty( $ideas_by_slot ) ) {
			foreach ( $ideas_by_slot as $slot_key => $cards ) {
				if ( strpos( (string) $slot_key, 'idea_' ) !== 0 ) {
					continue;
				}
				$idea_num = (int) preg_replace( '/\D+/', '', $slot_key );
				foreach ( $cards as $card ) {
					self::render_idea_score_row( $card, $idea_num, $criteria, $is_locked );
					$has_rows = true;
				}
			}
		}
		if ( ! $has_rows ) {
			echo '<div class="qi-idea-matrix-empty qi-stub"><p class="qi-stub-note">'
				. 'No change ideas yet. Add them on the <strong>Improvement Canvas</strong> tab first.'
				. '</p></div>';
		}
		if ( $lock_path && $has_rows ) {
			echo '<div class="qi-matrix-lock-bar">';
			if ( $is_locked ) {
				echo '<button type="button" class="qi-matrix-lock-btn is-locked" data-lock-path="' . esc_attr( $lock_path ) . '" data-lock-action="unlock">';
				echo '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
				echo '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
				echo 'Scores Locked. Unlock to Edit</button>';
				echo '<p class="qi-matrix-lock-note">Scores are locked. They appear read-only here and on the Commit canvas.</p>';
			} else {
				echo '<button type="button" class="qi-matrix-lock-btn" data-lock-path="' . esc_attr( $lock_path ) . '" data-lock-action="lock">';
				echo '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
				echo '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
				echo 'Submit & Lock Scores</button>';
				echo '<p class="qi-matrix-lock-note">When you lock scores, they become read-only here and appear on the Commit canvas.</p>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Overlays scores onto the matrix-diagram reference image once the matrix is locked.
	 * Cells are positioned via grid percentages from $cfg, computed per row × column.
	 *
	 * Expected $cfg keys:
	 *   ideas_source       (e.g. "improvement_canvas.change_ideas")
	 *   scores_source      (e.g. "matrix_diagram.scores")
	 *   lock_path          (e.g. "matrix_diagram.locked") — only shows when locked
	 *   criteria           [{ key, label }, ...] (mirrors idea_score_matrix.criteria)
	 *   row_count          int — number of idea rows on the diagram (e.g. 5)
	 *   header_top         %  — top edge of the first idea row
	 *   row_height         %  — height of each idea row
	 *   idea_col_width     %  — width of the leftmost "IDEA N" column
	 *   idea_col_left      %  — left edge of the leftmost "IDEA N" column
	 *   criterion_col_width %
	 *   criteria_left      %  — left edge of the first criterion column
	 *   cumulative_left    %
	 *   cumulative_width   %
	 */
	private static function render_matrix_score_grid( $cfg, $cards_by_field ) {
		$lock_path = isset( $cfg['lock_path'] ) ? $cfg['lock_path'] : '';
		if ( ! $lock_path || ! self::is_field_truthy( $lock_path ) ) {
			return;
		}
		$ideas_source = isset( $cfg['ideas_source'] ) ? $cfg['ideas_source'] : '';
		$criteria     = isset( $cfg['criteria'] ) ? $cfg['criteria'] : array();
		if ( ! $ideas_source || empty( $criteria ) ) {
			return;
		}
		$ideas_by_slot = isset( $cards_by_field[ $ideas_source ] ) ? $cards_by_field[ $ideas_source ] : array();
		if ( empty( $ideas_by_slot ) ) {
			return;
		}

		$row_count    = isset( $cfg['row_count'] ) ? (int) $cfg['row_count'] : 5;
		$header_top   = isset( $cfg['header_top'] ) ? (float) $cfg['header_top'] : 16.0;
		$row_height   = isset( $cfg['row_height'] ) ? (float) $cfg['row_height'] : 16.8;
		$crit_left    = isset( $cfg['criteria_left'] ) ? (float) $cfg['criteria_left'] : 16.0;
		$crit_width   = isset( $cfg['criterion_col_width'] ) ? (float) $cfg['criterion_col_width'] : 14.5;
		$cum_left     = isset( $cfg['cumulative_left'] ) ? (float) $cfg['cumulative_left'] : 84.0;
		$cum_width    = isset( $cfg['cumulative_width'] ) ? (float) $cfg['cumulative_width'] : 14.0;

		$max_total = count( $criteria ) * 5;

		echo '<div class="qi-matrix-score-overlays" aria-hidden="false">';
		for ( $i = 1; $i <= $row_count; $i++ ) {
			$slot_key = 'idea_' . $i;
			if ( empty( $ideas_by_slot[ $slot_key ] ) ) {
				continue;
			}
			$cards = $ideas_by_slot[ $slot_key ];
			$card  = reset( $cards );
			if ( ! $card ) {
				continue;
			}
			$card_id = (int) $card['id'];
			$scores  = isset( self::$scores[ $card_id ] ) ? self::$scores[ $card_id ] : array();

			$top    = $header_top + ( ( $i - 1 ) * $row_height );
			$total  = 0;

			foreach ( $criteria as $idx => $c ) {
				// $scores[$key] is ['avg' => float, 'count' => int, 'my_score' => int|null].
				// Solo scorer → avg == my_score; multiple scorers → real team average.
				$cell   = isset( $scores[ $c['key'] ] ) && is_array( $scores[ $c['key'] ] ) ? $scores[ $c['key'] ] : null;
				$val    = $cell ? (int) round( (float) $cell['avg'] ) : 5;
				$total += $val;
				$left = $crit_left + ( $idx * $crit_width );
				printf(
					'<div class="qi-matrix-score-cell" style="top:%F%%;left:%F%%;width:%F%%;height:%F%%;">%d</div>',
					$top, $left, $crit_width, $row_height, $val
				);
			}
			printf(
				'<div class="qi-matrix-score-cell qi-matrix-score-cell--total" style="top:%F%%;left:%F%%;width:%F%%;height:%F%%;">%d/%d</div>',
				$top, $cum_left, $cum_width, $row_height, $total, $max_total
			);
		}
		echo '</div>';
	}

	/**
	 * Returns true if the project's field_value at $path is non-empty / truthy.
	 * Used to read lock flags stored as textarea/json values.
	 */
	private static function is_field_truthy( $path ) {
		if ( empty( self::$field_values[ $path ] ) ) {
			return false;
		}
		$row  = self::$field_values[ $path ];
		$text = isset( $row['value_text'] ) ? (string) $row['value_text'] : '';
		$json = isset( $row['value_json'] ) ? (string) $row['value_json'] : '';
		if ( $text !== '' && $text !== '0' && strtolower( $text ) !== 'false' ) {
			return true;
		}
		if ( $json !== '' && $json !== 'null' && $json !== 'false' && $json !== '0' ) {
			return true;
		}
		return false;
	}

	private static function render_idea_score_row( $card, $idea_num, $criteria, $is_locked = false ) {
		$card_id  = (int) $card['id'];
		$scores   = isset( self::$scores[ $card_id ] ) ? self::$scores[ $card_id ] : array();
		$max      = count( $criteria ) * 5;
		$total    = 0;
		foreach ( $criteria as $c ) {
			$cell    = isset( $scores[ $c['key'] ] ) ? $scores[ $c['key'] ] : null;
			$my      = is_array( $cell ) && $cell['my_score'] !== null ? (int) $cell['my_score'] : 5;
			$total  += $my;
		}
		?>
		<div class="qi-idea-score-row<?php echo $is_locked ? ' is-locked' : ''; ?>" data-idea-card-id="<?php echo esc_attr( $card_id ); ?>" data-idea-num="<?php echo esc_attr( $idea_num ); ?>" data-criteria-max="<?php echo esc_attr( $max ); ?>">
			<div class="qi-idea-score-header">
				<span class="qi-idea-num">Idea <?php echo (int) $idea_num; ?>:</span>
				<span class="qi-idea-text"><?php echo esc_html( $card['content'] ); ?></span>
			</div>
			<div class="qi-idea-score-body">
				<div class="qi-idea-criteria">
					<?php foreach ( $criteria as $c ) :
						$cell  = isset( $scores[ $c['key'] ] ) ? $scores[ $c['key'] ] : null;
						// Both slider value AND visible number are the *current user's own* score.
						// Team averages surface separately on the Commit tab / canvas overlay.
						$my    = is_array( $cell ) && $cell['my_score'] !== null ? (int) $cell['my_score'] : 5;
						$count = is_array( $cell ) ? (int) $cell['count'] : 0;
						$avg   = is_array( $cell ) ? (float) $cell['avg'] : (float) $my;
					?>
						<div class="qi-idea-criterion">
							<label class="qi-idea-criterion-label"><?php echo esc_html( $c['label'] ); ?></label>
							<div class="qi-idea-slider-wrap">
								<input type="range" class="qi-idea-slider"
									min="1" max="5" step="1"
									value="<?php echo esc_attr( $my ); ?>"
									data-criterion="<?php echo esc_attr( $c['key'] ); ?>"
									data-card-id="<?php echo esc_attr( $card_id ); ?>"
									<?php echo $is_locked ? 'disabled' : ''; ?> />
								<span class="qi-idea-slider-value"
									data-avg="<?php echo esc_attr( $avg ); ?>"
									data-count="<?php echo esc_attr( $count ); ?>"
								><?php echo (int) $my; ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="qi-idea-cumulative">
					<div class="qi-idea-cumulative-value" data-card-id="<?php echo esc_attr( $card_id ); ?>"><?php echo (int) $total; ?>/<?php echo (int) $max; ?></div>
					<div class="qi-idea-cumulative-label">Personal Score</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display helper: render integer values as `4`, fractional team averages
	 * as `3.5`. Avoids the visual noise of `4.0` for cells that only have one
	 * scorer or where everyone agreed.
	 */
	private static function format_score_value( $v ) {
		$f = (float) $v;
		if ( abs( $f - round( $f ) ) < 0.05 ) {
			return (string) (int) round( $f );
		}
		return number_format( $f, 1 );
	}

	private static function render_card_list( $field, $cards_by_field ) {
		$path   = $field['field_path'];
		$label  = isset( $field['label'] ) ? $field['label'] : '';
		$picker = isset( $field['picker'] ) ? (string) $field['picker'] : '';
		$cards  = $cards_by_field[ $path ]['__none__'] ?? array();
		$add_label = $picker === 'org_users' ? '+ Add team member' : '+ Add a card';

		// On team_members fields, the project owner is an implicit member —
		// they shouldn't have to add themselves, and the picker hides their
		// own row. We render an auto card above the DB-backed list and skip
		// any legacy DB card with their display_name to avoid duplicates.
		$auto_owner_name = '';
		if ( $picker === 'org_users' ) {
			$owner_id = isset( self::$project['owner_user_id'] ) ? (int) self::$project['owner_user_id'] : 0;
			if ( $owner_id > 0 ) {
				$owner = get_userdata( $owner_id );
				if ( $owner ) {
					$auto_owner_name = (string) $owner->display_name;
				}
			}
			if ( $auto_owner_name !== '' ) {
				$cards = array_values( array_filter( $cards, function ( $c ) use ( $auto_owner_name ) {
					return trim( (string) $c['content'] ) !== $auto_owner_name;
				} ) );
			}
		}
		?>
		<div class="qi-field qi-card-list" data-field-path="<?php echo esc_attr( $path ); ?>"<?php echo $picker !== '' ? ' data-picker="' . esc_attr( $picker ) . '"' : ''; ?>>
			<?php if ( $label ) : ?><div class="qi-field-label"><?php echo esc_html( $label ); ?></div><?php endif; ?>
			<div class="qi-cards" data-slot="">
				<?php if ( $auto_owner_name !== '' ) : ?>
					<div class="qi-card qi-card-auto-owner" data-auto-owner="1" aria-label="Project owner">
						<div class="qi-card-content"><?php echo esc_html( $auto_owner_name ); ?></div>
						<div class="qi-card-meta">
							<span class="qi-card-author">Owner</span>
						</div>
					</div>
				<?php endif; ?>
				<?php foreach ( $cards as $card ) : self::render_card( $card ); endforeach; ?>
			</div>
			<button type="button" class="qi-add-card" data-field-path="<?php echo esc_attr( $path ); ?>" data-slot=""<?php echo $picker !== '' ? ' data-picker="' . esc_attr( $picker ) . '"' : ''; ?>><?php echo esc_html( $add_label ); ?></button>
		</div>
		<?php
	}

	private static function render_card_list_2col( $field, $cards_by_field ) {
		$path    = $field['field_path'];
		$columns = isset( $field['columns'] ) ? $field['columns'] : array();
		?>
		<div class="qi-field qi-card-list-2col" data-field-path="<?php echo esc_attr( $path ); ?>">
			<div class="qi-2col">
				<?php foreach ( $columns as $col ) :
					$slot  = $col['key'];
					$cards = $cards_by_field[ $path ][ $slot ] ?? array();
				?>
					<div class="qi-col">
						<div class="qi-col-label"><?php echo esc_html( $col['label'] ); ?></div>
						<div class="qi-cards" data-slot="<?php echo esc_attr( $slot ); ?>">
							<?php foreach ( $cards as $card ) : self::render_card( $card ); endforeach; ?>
						</div>
						<button type="button" class="qi-add-card" data-field-path="<?php echo esc_attr( $path ); ?>" data-slot="<?php echo esc_attr( $slot ); ?>">+ Add a card</button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_card_slots_named( $field, $cards_by_field ) {
		$path  = $field['field_path'];
		$slots = isset( $field['slots'] ) ? $field['slots'] : array();
		$cols  = max( 1, min( count( $slots ), 5 ) );
		?>
		<div class="qi-field qi-card-slots-named" data-field-path="<?php echo esc_attr( $path ); ?>" style="--qi-slot-cols: <?php echo (int) $cols; ?>;">
			<?php foreach ( $slots as $slot ) :
				$key   = $slot['key'];
				$cards = $cards_by_field[ $path ][ $key ] ?? array();
			?>
				<div class="qi-slot">
					<div class="qi-slot-label"><?php echo esc_html( $slot['label'] ); ?></div>
					<div class="qi-cards" data-slot="<?php echo esc_attr( $key ); ?>">
						<?php foreach ( $cards as $card ) : self::render_card( $card ); endforeach; ?>
					</div>
					<button type="button" class="qi-add-card" data-field-path="<?php echo esc_attr( $path ); ?>" data-slot="<?php echo esc_attr( $key ); ?>">+ Add a card</button>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_card_slots_numbered( $field, $cards_by_field ) {
		$path   = $field['field_path'];
		$count  = isset( $field['count'] ) ? (int) $field['count'] : 5;
		$format = isset( $field['slot_label_format'] ) ? $field['slot_label_format'] : 'IDEA {n}';
		?>
		<div class="qi-field qi-card-slots-numbered" data-field-path="<?php echo esc_attr( $path ); ?>">
			<?php for ( $i = 1; $i <= $count; $i++ ) :
				$slot  = 'idea_' . $i;
				$label = str_replace( '{n}', (string) $i, $format );
				$cards = $cards_by_field[ $path ][ $slot ] ?? array();
			?>
				<div class="qi-numbered-slot">
					<div class="qi-slot-label"><?php echo esc_html( $label ); ?></div>
					<div class="qi-cards" data-slot="<?php echo esc_attr( $slot ); ?>">
						<?php foreach ( $cards as $card ) : self::render_card( $card ); endforeach; ?>
					</div>
					<button type="button" class="qi-add-card" data-field-path="<?php echo esc_attr( $path ); ?>" data-slot="<?php echo esc_attr( $slot ); ?>">+ Add a card</button>
				</div>
			<?php endfor; ?>
		</div>
		<?php
	}

	private static function render_single_textarea( $field, $field_values ) {
		$path  = $field['field_path'];
		$row   = $field_values[ $path ] ?? null;
		$value = $row ? (string) $row['value_text'] : '';
		?>
		<div class="qi-field qi-single-textarea" data-field-path="<?php echo esc_attr( $path ); ?>">
			<textarea class="qi-textarea" data-field-path="<?php echo esc_attr( $path ); ?>" rows="4" placeholder="Type your answer here..."><?php echo esc_textarea( $value ); ?></textarea>
		</div>
		<?php
	}

	private static function render_card( $card ) {
		?>
		<div class="qi-card" data-card-id="<?php echo esc_attr( $card['id'] ); ?>">
			<div class="qi-card-content"><?php echo wp_kses_post( $card['content'] ); ?></div>
			<div class="qi-card-meta">
				<span class="qi-card-author"><?php echo esc_html( self::author_name( (int) $card['created_by_user_id'] ) ); ?></span>
				<span class="qi-card-date"><?php echo esc_html( human_time_diff( strtotime( $card['created_at'] ), current_time( 'timestamp' ) ) ); ?> ago</span>
				<button type="button" class="qi-card-edit" data-card-id="<?php echo esc_attr( $card['id'] ); ?>">Edit</button>
				<button type="button" class="qi-card-delete" data-card-id="<?php echo esc_attr( $card['id'] ); ?>">Delete</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Read-only mirror of another field/slot (e.g. Gameplan's AIM TO EXECUTE
	 * pulls from Improvement Canvas's Population slot).
	 */
	private static function render_field_reference( $field, $cards_by_field, $field_values ) {
		$label       = isset( $field['label'] ) ? $field['label'] : '';
		$source      = isset( $field['source'] ) ? $field['source'] : '';
		$source_slot = isset( $field['source_slot'] ) ? $field['source_slot'] : null;
		$path        = isset( $field['field_path'] ) ? $field['field_path'] : '';

		$cards      = array();
		$text_value = null;

		if ( $source_slot && isset( $cards_by_field[ $source ][ $source_slot ] ) ) {
			$cards = $cards_by_field[ $source ][ $source_slot ];
		} elseif ( isset( $cards_by_field[ $source ] ) ) {
			foreach ( $cards_by_field[ $source ] as $slot_cards ) {
				foreach ( $slot_cards as $c ) {
					$cards[] = $c;
				}
			}
		} elseif ( isset( $field_values[ $source ] ) ) {
			$text_value = (string) $field_values[ $source ]['value_text'];
		}
		?>
		<div class="qi-field qi-field-reference" data-field-path="<?php echo esc_attr( $path ); ?>">
			<?php if ( $label ) : ?>
				<div class="qi-field-label"><?php echo esc_html( $label ); ?></div>
			<?php endif; ?>
			<div class="qi-field-reference-body">
				<?php if ( $text_value !== null && trim( $text_value ) !== '' ) : ?>
					<div class="qi-field-reference-text"><?php echo nl2br( esc_html( $text_value ) ); ?></div>
				<?php elseif ( ! empty( $cards ) ) : ?>
					<?php foreach ( $cards as $c ) : ?>
						<div class="qi-field-reference-card"><?php echo wp_kses_post( $c['content'] ); ?></div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="qi-field-reference-empty">No content yet. Pulled from earlier canvas.</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Phase timeline — N columns, each with a textarea.
	 * Each phase saves to a sub-path of the base field_path (e.g. gameplan.timeline.phase_1).
	 * Reuses the .qi-textarea class so the existing autosave JS handles persistence.
	 */
	private static function render_phase_timeline( $field, $field_values ) {
		$base_path = isset( $field['field_path'] ) ? $field['field_path'] : '';
		$phases    = isset( $field['phases'] ) ? $field['phases'] : array();
		if ( empty( $phases ) ) {
			return;
		}
		?>
		<div class="qi-field qi-phase-timeline" style="--qi-phase-cols: <?php echo (int) count( $phases ); ?>;">
			<?php foreach ( $phases as $phase ) :
				$phase_key  = isset( $phase['key'] ) ? $phase['key'] : '';
				$phase_lab  = isset( $phase['label'] ) ? $phase['label'] : strtoupper( $phase_key );
				$field_path = $base_path . '.' . $phase_key;
				$row        = isset( $field_values[ $field_path ] ) ? $field_values[ $field_path ] : null;
				$value      = $row ? (string) $row['value_text'] : '';
			?>
				<div class="qi-phase-column">
					<div class="qi-phase-label"><?php echo esc_html( $phase_lab ); ?></div>
					<textarea class="qi-textarea qi-phase-textarea"
						data-field-path="<?php echo esc_attr( $field_path ); ?>"
						rows="3"
						placeholder="When will <?php echo esc_attr( $phase_lab ); ?> be complete?"><?php echo esc_textarea( $value ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Outcome / process measure rows: description | current | target.
	 * Each row is editable inline; new rows added via "+ Add row".
	 */
	private static function render_measure_row( $field ) {
		$measure_type = isset( $field['measure_type'] ) ? $field['measure_type'] : 'outcome';
		$label        = isset( $field['label'] ) ? $field['label'] : strtoupper( $measure_type . ' MEASURES' );
		$rows         = array_values( array_filter( self::$measures, function ( $m ) use ( $measure_type ) {
			return isset( $m['measure_type'] ) && $m['measure_type'] === $measure_type;
		} ) );
		?>
		<div class="qi-field qi-measures" data-measure-type="<?php echo esc_attr( $measure_type ); ?>">
			<div class="qi-measures-grid">
				<div class="qi-measures-header">
					<div class="qi-measures-cell qi-measures-cell-desc"><?php echo esc_html( $label ); ?></div>
					<div class="qi-measures-cell qi-measures-cell-current">CURRENT</div>
					<div class="qi-measures-cell qi-measures-cell-target">TARGET</div>
					<div class="qi-measures-cell qi-measures-cell-actions"></div>
				</div>
				<?php foreach ( $rows as $row ) : ?>
					<div class="qi-measure-row" data-measure-id="<?php echo esc_attr( $row['id'] ); ?>">
						<div class="qi-measures-cell qi-measures-cell-desc" data-field="description"><?php echo esc_html( $row['description'] ); ?></div>
						<div class="qi-measures-cell qi-measures-cell-current" data-field="current_value"><?php echo esc_html( (string) $row['current_value'] ); ?></div>
						<div class="qi-measures-cell qi-measures-cell-target" data-field="target_value"><?php echo esc_html( (string) $row['target_value'] ); ?></div>
						<div class="qi-measures-cell qi-measures-cell-actions">
							<button type="button" class="qi-measure-edit" data-measure-id="<?php echo esc_attr( $row['id'] ); ?>">Edit</button>
							<button type="button" class="qi-measure-delete" data-measure-id="<?php echo esc_attr( $row['id'] ); ?>">Delete</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="qi-measure-add" data-measure-type="<?php echo esc_attr( $measure_type ); ?>">+ Add row</button>
		</div>
		<?php
	}

	/**
	 * Walks the structure to find the idea_score_matrix matching the given field_path
	 * and returns its `criteria` array. Used by the Commit summary to default unsaved
	 * scores to 5 (matching the matrix slider default).
	 */
	private static function find_matrix_criteria( $field_path ) {
		if ( empty( $field_path ) || empty( self::$structure['tabs'] ) ) {
			return array();
		}
		foreach ( self::$structure['tabs'] as $tab ) {
			$field_groups = array();
			if ( ! empty( $tab['fields'] ) ) {
				$field_groups[] = $tab['fields'];
			}
			if ( ! empty( $tab['steps'] ) ) {
				foreach ( $tab['steps'] as $step ) {
					if ( ! empty( $step['fields'] ) ) {
						$field_groups[] = $step['fields'];
					}
				}
			}
			foreach ( $field_groups as $fields ) {
				foreach ( $fields as $f ) {
					if ( isset( $f['type'], $f['field_path'] )
						&& $f['type'] === 'idea_score_matrix'
						&& $f['field_path'] === $field_path
						&& ! empty( $f['criteria'] ) ) {
						return $f['criteria'];
					}
				}
			}
		}
		return array();
	}

	/**
	 * Per-idea summary: shows each scored idea (from Improvement Canvas) with its
	 * Matrix cumulative score. If `changes_field_path` is set, also renders an
	 * editable card list per idea so the team can capture the specific changes
	 * they will make to implement that idea.
	 */
	private static function render_scored_ideas_summary( $field, $cards_by_field ) {
		$ideas_source  = isset( $field['ideas_source'] ) ? $field['ideas_source'] : '';
		$scores_source = isset( $field['scores_source'] ) ? $field['scores_source'] : '';
		$changes_path  = isset( $field['changes_field_path'] ) ? $field['changes_field_path'] : '';
		$ideas_by_slot = isset( $cards_by_field[ $ideas_source ] ) ? $cards_by_field[ $ideas_source ] : array();

		if ( empty( $ideas_by_slot ) ) {
			echo '<div class="qi-field qi-stub"><p class="qi-stub-note">'
				. 'No change ideas yet. Add them on the <strong>Improvement Canvas</strong> tab first.'
				. '</p></div>';
			return;
		}

		// Pull the matrix's criteria so unsaved sliders default to 5 (matches what
		// the Matrix tab renders), keeping the two views in sync.
		$criteria = self::find_matrix_criteria( $scores_source );
		$max      = ! empty( $criteria ) ? count( $criteria ) * 5 : 20;

		uksort( $ideas_by_slot, function ( $a, $b ) {
			return ( (int) preg_replace( '/\D+/', '', $a ) ) <=> ( (int) preg_replace( '/\D+/', '', $b ) );
		} );
		?>
		<div class="qi-field qi-scored-ideas<?php echo $changes_path ? ' qi-scored-ideas--editable' : ''; ?>">
			<?php foreach ( $ideas_by_slot as $slot_key => $cards ) :
				if ( strpos( (string) $slot_key, 'idea_' ) !== 0 ) continue;
				$idea_num = (int) preg_replace( '/\D+/', '', $slot_key );
				foreach ( $cards as $card ) :
					$card_id = (int) $card['id'];
					$scores  = isset( self::$scores[ $card_id ] ) ? self::$scores[ $card_id ] : array();
					$total   = 0.0;
					if ( ! empty( $criteria ) ) {
						foreach ( $criteria as $c ) {
							$cell  = isset( $scores[ $c['key'] ] ) ? $scores[ $c['key'] ] : null;
							$total += is_array( $cell ) ? (float) $cell['avg'] : 5.0;
						}
					} else {
						foreach ( $scores as $cell ) {
							$total += is_array( $cell ) ? (float) $cell['avg'] : 0.0;
						}
					}
					$total_display = self::format_score_value( $total );
					$change_cards = ( $changes_path && isset( $cards_by_field[ $changes_path ][ $slot_key ] ) )
						? $cards_by_field[ $changes_path ][ $slot_key ]
						: array();
			?>
					<div class="qi-scored-idea-row">
						<div class="qi-scored-idea-header">Idea <?php echo (int) $idea_num; ?></div>
						<div class="qi-scored-idea-body">
							<div class="qi-scored-idea-main">
								<div class="qi-scored-idea-text">
									<?php echo esc_html( $card['content'] ); ?>
								</div>
								<?php if ( $changes_path ) : ?>
									<div class="qi-scored-idea-changes qi-card-list" data-field-path="<?php echo esc_attr( $changes_path ); ?>">
										<div class="qi-cards" data-slot="<?php echo esc_attr( $slot_key ); ?>">
											<?php foreach ( $change_cards as $cc ) : self::render_card( $cc ); endforeach; ?>
										</div>
										<button type="button" class="qi-add-card" data-field-path="<?php echo esc_attr( $changes_path ); ?>" data-slot="<?php echo esc_attr( $slot_key ); ?>">+ Add a change</button>
									</div>
								<?php endif; ?>
							</div>
							<div class="qi-scored-idea-score"><?php echo esc_html( $total_display ); ?>/<?php echo (int) $max; ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Mark Complete and Generate Report button. Calls /projects/{id}/complete which
	 * sets status=completed, completed_at, and fires the qualinav_qi_project_completed
	 * action (for AI brain webhooks). Disables itself if project is already completed.
	 */
	private static function render_submit_button( $field ) {
		$label       = isset( $field['label'] ) ? $field['label'] : 'Mark Complete';
		$is_complete = isset( self::$project['status'] ) && self::$project['status'] === 'completed';
		$owner_id    = isset( self::$project['owner_user_id'] ) ? (int) self::$project['owner_user_id'] : 0;
		$is_owner    = $owner_id > 0 && self::$current_user_id === $owner_id;
		?>
		<div class="qi-field qi-submit-field">
			<?php if ( $is_owner || $is_complete ) : ?>
				<button type="button" class="qi-submit-btn<?php echo $is_complete ? ' is-completed' : ''; ?>"
					data-action="complete-project"
					<?php echo ( $is_complete || ! $is_owner ) ? 'disabled' : ''; ?>>
					<?php if ( $is_complete ) : ?>
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<polyline points="20 6 9 17 4 12"/>
						</svg>
						Project Completed
					<?php else : ?>
						<?php echo esc_html( $label ); ?>
					<?php endif; ?>
				</button>
				<?php if ( $is_complete ) : ?>
					<p class="qi-submit-note">This project was finalized and is now read-only. Contact your administrator to re-open it.</p>
				<?php endif; ?>
			<?php else : ?>
				<p class="qi-submit-note qi-submit-note--gated">Only the project owner can mark this project complete and generate the report.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the populated-canvas overlay layer on top of the canvas reference image.
	 * Each overlay zone is positioned via "top,left,width,height" percentages and
	 * pulls live content from the project's cards or single-value fields.
	 */
	private static function render_canvas_overlays( $overlays, $cards_by_field, $field_values ) {
		echo '<div class="qi-canvas-overlays" aria-hidden="false">';
		foreach ( $overlays as $overlay ) {
			$pos       = isset( $overlay['position'] ) ? (string) $overlay['position'] : '';
			$source    = isset( $overlay['source'] ) ? (string) $overlay['source'] : '';
			$slot      = isset( $overlay['source_slot'] ) ? (string) $overlay['source_slot'] : null;
			$zone_key  = isset( $overlay['zone_key'] ) ? (string) $overlay['zone_key'] : sanitize_key( $source . '_' . ( $slot ?: '' ) );
			$number    = isset( $overlay['n'] ) ? (string) $overlay['n'] : '';
			$label     = isset( $overlay['label'] ) ? (string) $overlay['label'] : '';

			$parts = array_map( 'trim', explode( ',', $pos ) );
			if ( count( $parts ) !== 4 ) {
				continue;
			}
			list( $top, $left, $width, $height ) = $parts;

			// Pinned card IDs for this zone (if any). Unset = "show all" default.
			$pin_field_path = 'improvement_canvas.canvas_pins.' . $zone_key;
			$pinned_ids     = array();
			$is_pinned_mode = false;
			if ( isset( $field_values[ $pin_field_path ]['value_json'] ) ) {
				$decoded = json_decode( $field_values[ $pin_field_path ]['value_json'], true );
				if ( is_array( $decoded ) ) {
					$pinned_ids     = array_map( 'intval', $decoded );
					$is_pinned_mode = true;
				}
			}

			// Resolve cards or textarea value
			$is_textarea = isset( $field_values[ $source ] ) && ! isset( $cards_by_field[ $source ] );
			$content_html = self::extract_overlay_content_for_zone(
				$source, $slot, $cards_by_field, $field_values, $pinned_ids, $is_pinned_mode
			);
			$is_empty = $content_html === '';
			?>
			<button type="button"
				class="qi-canvas-overlay<?php echo $is_textarea ? ' is-textarea' : ''; ?><?php echo ! $is_empty ? ' has-pins' : ''; ?>"
				data-zone-key="<?php echo esc_attr( $zone_key ); ?>"
				data-zone-number="<?php echo esc_attr( $number ); ?>"
				data-zone-label="<?php echo esc_attr( $label ); ?>"
				data-source="<?php echo esc_attr( $source ); ?>"
				data-source-slot="<?php echo esc_attr( $slot ?: '' ); ?>"
				data-pin-field="<?php echo esc_attr( $pin_field_path ); ?>"
				data-pinned-ids='<?php echo wp_json_encode( $pinned_ids ); ?>'
				data-is-textarea="<?php echo $is_textarea ? '1' : '0'; ?>"
				title="<?php echo esc_attr( $label ); ?>"
				aria-label="<?php echo esc_attr( $label ); ?>"
				style="top: <?php echo esc_attr( $top ); ?>; left: <?php echo esc_attr( $left ); ?>; width: <?php echo esc_attr( $width ); ?>; height: <?php echo esc_attr( $height ); ?>;">
				<?php if ( $number !== '' ) : ?>
					<span class="qi-canvas-overlay-number" aria-hidden="true"><?php echo esc_html( $number ); ?></span>
				<?php endif; ?>
				<?php if ( $label !== '' ) : ?>
					<span class="qi-canvas-overlay-tooltip" role="tooltip"><?php echo esc_html( $label ); ?></span>
				<?php endif; ?>
			</button>
			<?php
		}
		echo '</div>';

		// Zone picker modal (one per page, reused for any zone)
		?>
		<div class="qi-modal qi-zone-modal" id="qi-zone-modal" hidden>
			<div class="qi-modal-backdrop" data-qi-zone-close></div>
			<div class="qi-modal-card" role="dialog" aria-modal="true" aria-labelledby="qi-zone-title">
				<button type="button" class="qi-modal-close" data-qi-zone-close aria-label="Close">&times;</button>
				<h2 id="qi-zone-title">Choose what to show</h2>
				<p class="qi-zone-modal-desc"></p>
				<div class="qi-zone-cards-list"></div>
				<div class="qi-modal-error" hidden></div>
				<div class="qi-modal-actions">
					<button type="button" class="qi-btn-link" id="qi-zone-reset">Show all (default)</button>
					<div class="qi-modal-actions-right">
						<button type="button" class="qi-btn-secondary" data-qi-zone-close>Cancel</button>
						<button type="button" class="qi-btn-primary" id="qi-zone-save">Save</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Extracts overlay content honoring pin-mode: when pinned_ids are set, only those
	 * cards are shown. Otherwise fall back to "show all" cards (or textarea value).
	 *
	 * Display text is truncated to keep zones readable; full text is preserved in
	 * the title attribute so hovering surfaces it as a native tooltip.
	 */
	private static function extract_overlay_content_for_zone( $source, $slot, $cards_by_field, $field_values, $pinned_ids, $is_pinned_mode ) {
		if ( isset( $cards_by_field[ $source ] ) ) {
			$cards = array();
			if ( $slot !== null && $slot !== '' && isset( $cards_by_field[ $source ][ $slot ] ) ) {
				$cards = $cards_by_field[ $source ][ $slot ];
			} elseif ( $slot === null || $slot === '' ) {
				foreach ( $cards_by_field[ $source ] as $slot_cards ) {
					foreach ( $slot_cards as $c ) {
						$cards[] = $c;
					}
				}
			}

			if ( $is_pinned_mode ) {
				$pinned_set = array_flip( $pinned_ids );
				$cards = array_values( array_filter( $cards, function ( $c ) use ( $pinned_set ) {
					return isset( $pinned_set[ (int) $c['id'] ] );
				} ) );
			}

			if ( ! empty( $cards ) ) {
				$out = '';
				foreach ( $cards as $card ) {
					$out .= self::format_overlay_item( wp_strip_all_tags( $card['content'] ) );
				}
				return $out;
			}
		}

		// Single-value field (e.g. aim_statement)
		if ( isset( $field_values[ $source ] ) && ! empty( $field_values[ $source ]['value_text'] ) ) {
			return self::format_overlay_item( wp_strip_all_tags( $field_values[ $source ]['value_text'] ) );
		}

		return '';
	}

	private static function format_overlay_item( $text ) {
		$text      = trim( (string) $text );
		$truncated = self::truncate_words( $text, 6 );
		return '<div class="qi-canvas-overlay-item" title="' . esc_attr( $text ) . '">' . esc_html( $truncated ) . '</div>';
	}

	private static function truncate_words( $text, $limit = 6 ) {
		$words = preg_split( '/\s+/', $text, $limit + 1 );
		if ( $words === false ) {
			return $text;
		}
		if ( count( $words ) > $limit ) {
			array_pop( $words );
			return implode( ' ', $words ) . '…';
		}
		return implode( ' ', $words );
	}

	private static function author_name( $user_id ) {
		if ( $user_id <= 0 ) {
			return '';
		}
		$u = get_userdata( $user_id );
		return $u ? $u->display_name : '';
	}
}
