<?php
/**
 * Admin Template Editor — form-based editing of the global Improvement Charter.
 *
 * Edits the global (org_id = NULL) "improvement-charter" template's CURRENT
 * version structure IN PLACE so changes propagate to existing projects (same
 * behaviour as the Seeder re-seed). Complex per-step config (slots, columns,
 * overlays, references) is preserved by merging edits onto the original arrays
 * keyed by their stable `key`; only fields surfaced in the form are changed.
 *
 * Canvas images upload into the plugin's own assets/canvas/ folder (per the
 * chosen storage model). Note: that folder is overwritten on plugin update and
 * may be read-only on locked-down hosts — uploads will fail gracefully there.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Admin_Template_Editor {

	const LAST_EDIT_OPTION = 'qualinav_qi_template_last_edit';

	/** Field types offered when changing a section's primary field. */
	private static function field_types() {
		return array(
			'card_list'           => 'Card list',
			'card_list_2col'      => 'Card list (2 columns)',
			'card_slots_named'    => 'Named card slots',
			'card_slots_numbered' => 'Numbered card slots',
			'single_text'         => 'Single line text',
			'single_textarea'     => 'Text area',
			'measure_row'         => 'Measure rows',
			'slider_1_5'          => 'Slider (1–5)',
			'computed_sum'        => 'Computed sum',
			'field_reference'     => 'Field reference',
			'section_repeat'      => 'Repeating section',
			'submit_button'       => 'Submit button',
		);
	}

	private static function load() {
		$repo = new Qualinav_QI_Template_Repository();
		$tpl  = $repo->find_template_by_slug( Qualinav_QI_Seeder::DEFAULT_SLUG, null );
		if ( ! $tpl || empty( $tpl['current_version_id'] ) ) {
			return array( null, null, null );
		}
		$structure = $repo->get_structure( (int) $tpl['current_version_id'] );
		return array( $repo, $tpl, is_array( $structure ) ? $structure : null );
	}

	public static function render() {
		if ( ! current_user_can( Qualinav_QI_Admin::CAPABILITY ) ) {
			wp_die( 'Access denied.' );
		}
		list( , $tpl, $structure ) = self::load();

		Qualinav_QI_Admin::render_header( Qualinav_QI_Admin::EDITOR_SLUG );

		if ( ! $structure ) {
			echo '<div class="notice notice-warning"><p>The Improvement Charter template has not been seeded yet. Visit any admin page once to trigger seeding, then return here.</p></div>';
			Qualinav_QI_Admin::render_footer();
			return;
		}

		$last = get_option( self::LAST_EDIT_OPTION );
		$tabs = isset( $structure['tabs'] ) && is_array( $structure['tabs'] ) ? $structure['tabs'] : array();
		$types = self::field_types();
		?>
		<p class="qi-editor-meta">
			Editing <strong><?php echo esc_html( $tpl['name'] ); ?></strong>
			(version #<?php echo esc_html( $tpl['current_version_id'] ); ?>).
			Changes apply immediately to all projects using this template.
			<?php if ( $last && ! empty( $last['at'] ) ) :
				$eu = ! empty( $last['user_id'] ) ? get_userdata( (int) $last['user_id'] ) : null;
				?>
				<br><span class="description">Last edited <?php echo esc_html( mysql2date( 'M j, Y H:i', $last['at'] ) ); ?><?php echo $eu ? ' by ' . esc_html( $eu->display_name ) : ''; ?>.</span>
			<?php endif; ?>
		</p>

		<form method="post" class="qi-restore-form" onsubmit="return confirm('Restore the Improvement Charter to its built-in defaults? This discards all template edits (including removed tabs/sections) and applies immediately to every project using it.');">
			<?php wp_nonce_field( 'qualinav_qi_restore_template' ); ?>
			<input type="hidden" name="qualinav_qi_action" value="restore_template" />
			<button type="submit" class="button button-secondary">↺ Restore defaults</button>
			<span class="description">Brings back the original 4 tabs (Improvement Canvas, Matrix, Gameplan, Commit) and all sections.</span>
		</form>

		<form method="post" enctype="multipart/form-data" class="qi-editor-form">
			<?php wp_nonce_field( 'qualinav_qi_save_template' ); ?>
			<input type="hidden" name="qualinav_qi_action" value="save_template" />

			<div class="qi-tabs-editor" id="qi-tabs-editor">
				<?php foreach ( $tabs as $ti => $tab ) :
					$tab_key   = isset( $tab['key'] ) ? (string) $tab['key'] : 'tab_' . $ti;
					$steps     = isset( $tab['steps'] ) && is_array( $tab['steps'] ) ? $tab['steps'] : array();
					$has_steps = ! empty( $steps );
					?>
					<fieldset class="qi-tab-block">
						<legend><?php echo esc_html( $tab['label'] ?? $tab_key ); ?></legend>
						<input type="hidden" name="tab_key[<?php echo esc_attr( $ti ); ?>]" value="<?php echo esc_attr( $tab_key ); ?>" />

						<label class="qi-field">
							<span>Remove this tab</span>
							<input type="checkbox" name="tab_remove[<?php echo esc_attr( $ti ); ?>]" value="1" />
						</label>

						<label class="qi-field">
							<span>Menu label</span>
							<input type="text" name="tab_label[<?php echo esc_attr( $ti ); ?>]" value="<?php echo esc_attr( $tab['label'] ?? '' ); ?>" />
						</label>
						<label class="qi-field">
							<span>Title</span>
							<input type="text" name="tab_title[<?php echo esc_attr( $ti ); ?>]" value="<?php echo esc_attr( $tab['title'] ?? '' ); ?>" />
						</label>
						<label class="qi-field">
							<span>Description</span>
							<textarea name="tab_description[<?php echo esc_attr( $ti ); ?>]" rows="3"><?php echo esc_textarea( $tab['description'] ?? '' ); ?></textarea>
						</label>

						<div class="qi-field qi-canvas-field">
							<span>Canvas image</span>
							<div class="qi-canvas-preview">
								<?php if ( ! empty( $tab['image'] ) ) : ?>
									<img src="<?php echo esc_url( Qualinav_QI_Renderer::resolve_asset_url( $tab['image'] ) ); ?>" alt="" />
									<code><?php echo esc_html( $tab['image'] ); ?></code>
								<?php else : ?>
									<em>No canvas image set.</em>
								<?php endif; ?>
							</div>
							<input type="file" name="canvas_image_<?php echo esc_attr( $ti ); ?>" accept=".webp,.png,.jpg,.jpeg,.gif,.svg" />
							<p class="description">Upload to replace. Stored in the plugin's <code>assets/canvas/</code> folder.</p>
						</div>

						<?php if ( $has_steps ) : ?>
							<div class="qi-steps">
								<h4>Sections</h4>
								<?php foreach ( $steps as $si => $step ) :
									$step_key = isset( $step['key'] ) ? (string) $step['key'] : 'step_' . $si;
									$cur_type = isset( $step['fields'][0]['type'] ) ? (string) $step['fields'][0]['type'] : 'card_list';
									?>
									<div class="qi-step-row">
										<input type="hidden" name="step_key[<?php echo esc_attr( $ti ); ?>][<?php echo esc_attr( $si ); ?>]" value="<?php echo esc_attr( $step_key ); ?>" />
										<label class="qi-field">
											<span>Section title</span>
											<input type="text" name="step_title[<?php echo esc_attr( $ti ); ?>][<?php echo esc_attr( $si ); ?>]" value="<?php echo esc_attr( $step['title'] ?? '' ); ?>" />
										</label>
										<label class="qi-field">
											<span>Helper text</span>
											<textarea name="step_helper[<?php echo esc_attr( $ti ); ?>][<?php echo esc_attr( $si ); ?>]" rows="2"><?php echo esc_textarea( $step['helper'] ?? '' ); ?></textarea>
										</label>
										<label class="qi-field qi-field--inline">
											<span>Field type</span>
											<select name="step_type[<?php echo esc_attr( $ti ); ?>][<?php echo esc_attr( $si ); ?>]">
												<?php foreach ( $types as $tval => $tlabel ) : ?>
													<option value="<?php echo esc_attr( $tval ); ?>" <?php selected( $cur_type, $tval ); ?>><?php echo esc_html( $tlabel ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
										<label class="qi-field qi-field--inline qi-step-remove">
											<span>Remove section</span>
											<input type="checkbox" name="step_remove[<?php echo esc_attr( $ti ); ?>][<?php echo esc_attr( $si ); ?>]" value="1" />
										</label>
									</div>
								<?php endforeach; ?>

								<div class="qi-step-row qi-step-row--new">
									<h4>Add a new section</h4>
									<label class="qi-field">
										<span>Section title</span>
										<input type="text" name="new_step_title[<?php echo esc_attr( $ti ); ?>]" placeholder="e.g. Step 10: Lessons Learned" />
									</label>
									<label class="qi-field">
										<span>Helper text</span>
										<textarea name="new_step_helper[<?php echo esc_attr( $ti ); ?>]" rows="2"></textarea>
									</label>
									<label class="qi-field qi-field--inline">
										<span>Field type</span>
										<select name="new_step_type[<?php echo esc_attr( $ti ); ?>]">
											<?php foreach ( $types as $tval => $tlabel ) : ?>
												<option value="<?php echo esc_attr( $tval ); ?>" <?php selected( 'card_list', $tval ); ?>><?php echo esc_html( $tlabel ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</div>
							</div>
						<?php else : ?>
							<p class="description">This tab uses a fixed layout (no editable sections).</p>
						<?php endif; ?>
					</fieldset>
				<?php endforeach; ?>
			</div>

			<fieldset class="qi-tab-block qi-tab-block--new">
				<legend>Add a new tab</legend>
				<label class="qi-field">
					<span>Menu label</span>
					<input type="text" name="new_tab_label" placeholder="e.g. 5. Retrospective" />
				</label>
				<label class="qi-field">
					<span>Title</span>
					<input type="text" name="new_tab_title" placeholder="e.g. The Retrospective Canvas" />
				</label>
				<label class="qi-field">
					<span>Description</span>
					<textarea name="new_tab_description" rows="2"></textarea>
				</label>
				<div class="qi-field qi-canvas-field">
					<span>Canvas image (optional)</span>
					<input type="file" name="new_tab_canvas_image" accept=".webp,.png,.jpg,.jpeg,.gif,.svg" />
				</div>
			</fieldset>

			<p class="submit">
				<button type="submit" class="button button-primary button-large">Save changes</button>
			</p>
		</form>
		<?php
		Qualinav_QI_Admin::render_footer();
	}

	/* ---------------------------------------------------------------------
	 * Save
	 * ------------------------------------------------------------------- */

	public static function handle_save() {
		list( $repo, $tpl, $structure ) = self::load();
		if ( ! $repo || ! $structure ) {
			Qualinav_QI_Admin::flash_notice( 'Template not found — nothing saved.', 'error' );
			self::redirect();
		}

		$orig_tabs = isset( $structure['tabs'] ) && is_array( $structure['tabs'] ) ? array_values( $structure['tabs'] ) : array();
		$valid_types = array_keys( self::field_types() );
		$new_tabs  = array();

		foreach ( $orig_tabs as $ti => $tab ) {
			if ( ! empty( $_POST['tab_remove'][ $ti ] ) ) {
				continue;
			}

			if ( isset( $_POST['tab_label'][ $ti ] ) ) {
				$tab['label'] = sanitize_text_field( wp_unslash( $_POST['tab_label'][ $ti ] ) );
			}
			if ( isset( $_POST['tab_title'][ $ti ] ) ) {
				$tab['title'] = sanitize_text_field( wp_unslash( $_POST['tab_title'][ $ti ] ) );
			}
			if ( isset( $_POST['tab_description'][ $ti ] ) ) {
				$tab['description'] = sanitize_textarea_field( wp_unslash( $_POST['tab_description'][ $ti ] ) );
			}

			$uploaded = self::maybe_upload( 'canvas_image_' . $ti, isset( $tab['key'] ) ? $tab['key'] : ( 'tab' . $ti ) );
			if ( is_wp_error( $uploaded ) ) {
				Qualinav_QI_Admin::flash_notice( $uploaded->get_error_message(), 'error' );
				self::redirect();
			} elseif ( $uploaded ) {
				$tab['image'] = $uploaded;
			}

			if ( isset( $tab['steps'] ) && is_array( $tab['steps'] ) ) {
				$orig_steps = array_values( $tab['steps'] );
				$kept_steps = array();
				foreach ( $orig_steps as $si => $step ) {
					if ( ! empty( $_POST['step_remove'][ $ti ][ $si ] ) ) {
						continue;
					}
					if ( isset( $_POST['step_title'][ $ti ][ $si ] ) ) {
						$step['title'] = sanitize_text_field( wp_unslash( $_POST['step_title'][ $ti ][ $si ] ) );
					}
					if ( isset( $_POST['step_helper'][ $ti ][ $si ] ) ) {
						$step['helper'] = sanitize_textarea_field( wp_unslash( $_POST['step_helper'][ $ti ][ $si ] ) );
					}
					if ( isset( $_POST['step_type'][ $ti ][ $si ] ) ) {
						$new_type = sanitize_key( wp_unslash( $_POST['step_type'][ $ti ][ $si ] ) );
						$cur_type = isset( $step['fields'][0]['type'] ) ? $step['fields'][0]['type'] : null;
						if ( in_array( $new_type, $valid_types, true ) && $new_type !== $cur_type ) {
							$path = isset( $step['fields'][0]['field_path'] )
								? $step['fields'][0]['field_path']
								: ( ( $tab['key'] ?? 'tab' ) . '.' . ( $step['key'] ?? 'step' ) );
							$step['fields'] = array( array( 'type' => $new_type, 'field_path' => $path ) );
						}
					}
					$kept_steps[] = $step;
				}

				$nt = isset( $_POST['new_step_title'][ $ti ] ) ? sanitize_text_field( wp_unslash( $_POST['new_step_title'][ $ti ] ) ) : '';
				if ( $nt !== '' ) {
					$skey = self::unique_key( sanitize_key( $nt ), $kept_steps );
					$ntype = isset( $_POST['new_step_type'][ $ti ] ) ? sanitize_key( wp_unslash( $_POST['new_step_type'][ $ti ] ) ) : 'card_list';
					if ( ! in_array( $ntype, $valid_types, true ) ) {
						$ntype = 'card_list';
					}
					$kept_steps[] = array(
						'key'    => $skey,
						'title'  => $nt,
						'helper' => isset( $_POST['new_step_helper'][ $ti ] ) ? sanitize_textarea_field( wp_unslash( $_POST['new_step_helper'][ $ti ] ) ) : '',
						'color'  => 'blue',
						'fields' => array( array( 'type' => $ntype, 'field_path' => ( $tab['key'] ?? 'tab' ) . '.' . $skey ) ),
					);
				}

				$tab['steps'] = $kept_steps;
			}

			$new_tabs[] = $tab;
		}

		// Append a brand-new tab if requested.
		$ntl = isset( $_POST['new_tab_label'] ) ? sanitize_text_field( wp_unslash( $_POST['new_tab_label'] ) ) : '';
		$ntt = isset( $_POST['new_tab_title'] ) ? sanitize_text_field( wp_unslash( $_POST['new_tab_title'] ) ) : '';
		if ( $ntl !== '' || $ntt !== '' ) {
			$tkey = self::unique_key( sanitize_key( $ntt ?: $ntl ), $new_tabs );
			$new_tab = array(
				'key'         => $tkey,
				'label'       => $ntl ?: $ntt,
				'title'       => $ntt ?: $ntl,
				'description' => isset( $_POST['new_tab_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['new_tab_description'] ) ) : '',
				'image'       => '',
				'steps'       => array(),
			);
			$img = self::maybe_upload( 'new_tab_canvas_image', $tkey );
			if ( is_wp_error( $img ) ) {
				Qualinav_QI_Admin::flash_notice( $img->get_error_message(), 'error' );
				self::redirect();
			} elseif ( $img ) {
				$new_tab['image'] = $img;
			}
			$new_tabs[] = $new_tab;
		}

		$structure['tabs'] = $new_tabs;

		$ok = $repo->update_version_structure( (int) $tpl['current_version_id'], $structure );
		if ( $ok ) {
			update_option( self::LAST_EDIT_OPTION, array( 'at' => current_time( 'mysql' ), 'user_id' => get_current_user_id() ) );
			Qualinav_QI_Admin::flash_notice( 'Template saved. Changes are live for all projects using it.' );
		} else {
			Qualinav_QI_Admin::flash_notice( 'Nothing changed, or the save failed.', 'error' );
		}
		self::redirect();
	}

	/**
	 * Restores the live template version to the plugin's bundled seed JSON,
	 * discarding any admin edits (e.g. a removed Improvement Canvas tab).
	 */
	public static function handle_restore() {
		list( $repo, $tpl, $structure ) = self::load();
		if ( ! $repo || ! $tpl || empty( $tpl['current_version_id'] ) ) {
			Qualinav_QI_Admin::flash_notice( 'Template not found — nothing restored.', 'error' );
			self::redirect();
		}

		$default = Qualinav_QI_Seeder::get_default_structure();
		if ( ! is_array( $default ) ) {
			Qualinav_QI_Admin::flash_notice( 'The bundled default template could not be read.', 'error' );
			self::redirect();
		}

		$ok = $repo->update_version_structure( (int) $tpl['current_version_id'], $default );
		if ( $ok ) {
			update_option( self::LAST_EDIT_OPTION, array( 'at' => current_time( 'mysql' ), 'user_id' => get_current_user_id() ) );
			Qualinav_QI_Admin::flash_notice( 'Template restored to built-in defaults. All tabs and sections are back.' );
		} else {
			Qualinav_QI_Admin::flash_notice( 'Restore failed — the template was not changed.', 'error' );
		}
		self::redirect();
	}

	/**
	 * Validates and moves an uploaded canvas image into assets/canvas/.
	 * Returns the plugin-relative path, '' if no file, or WP_Error on failure.
	 */
	private static function maybe_upload( $input_name, $name_hint ) {
		if ( empty( $_FILES[ $input_name ] ) || empty( $_FILES[ $input_name ]['name'] ) ) {
			return '';
		}
		$file = $_FILES[ $input_name ];
		if ( ! empty( $file['error'] ) && (int) $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'qi_upload', 'Upload failed (error code ' . (int) $file['error'] . ').' );
		}

		$check = wp_check_filetype( $file['name'] );
		$allowed = array( 'webp', 'png', 'jpg', 'jpeg', 'gif', 'svg' );
		$ext = strtolower( (string) $check['ext'] );
		if ( ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error( 'qi_upload', 'Unsupported file type. Use WEBP, PNG, JPG, GIF or SVG.' );
		}
		// Raster files must be real images.
		if ( $ext !== 'svg' && false === @getimagesize( $file['tmp_name'] ) ) {
			return new WP_Error( 'qi_upload', 'That file is not a valid image.' );
		}
		if ( (int) $file['size'] > 8 * 1024 * 1024 ) {
			return new WP_Error( 'qi_upload', 'Image is too large (max 8 MB).' );
		}

		$dir = QUALINAV_QI_PLUGIN_DIR . 'assets/canvas/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_writable( $dir ) ) {
			return new WP_Error( 'qi_upload', 'The plugin assets/canvas/ folder is not writable on this host.' );
		}

		$base     = sanitize_file_name( $name_hint . '-' . pathinfo( $file['name'], PATHINFO_FILENAME ) );
		$filename = $base . '-' . time() . '.' . $ext;
		$dest     = $dir . $filename;

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'qi_upload', 'Could not write the uploaded file to the plugin folder.' );
		}
		@chmod( $dest, 0644 );

		return 'assets/canvas/' . $filename;
	}

	/** Returns a key not already used by any item's `key` in $list. */
	private static function unique_key( $key, $list ) {
		$key = $key !== '' ? $key : 'item';
		$existing = array();
		foreach ( $list as $item ) {
			if ( isset( $item['key'] ) ) {
				$existing[] = $item['key'];
			}
		}
		$try = $key;
		$n   = 2;
		while ( in_array( $try, $existing, true ) ) {
			$try = $key . '_' . $n;
			$n++;
		}
		return $try;
	}

	private static function redirect() {
		wp_safe_redirect( admin_url( 'admin.php?page=' . Qualinav_QI_Admin::EDITOR_SLUG ) );
		exit;
	}
}
