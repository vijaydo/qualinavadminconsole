<?php
/**
 * Data Hub Measure Library admin and registry.
 *
 * Admin-owned measure specifications live here so annual updates can be
 * versioned without editing Data Hub templates directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qualinav_Data_Hub_Measure_Library {

	const DB_VERSION = '1';
	const DB_OPTION  = 'qualinav_data_hub_measure_library_db_version';
	const PAGE_SLUG  = 'qualinav-data-hub-measures';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 2 );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 9998 );
			add_action( 'admin_post_qmo_measure_library_save_measure', array( __CLASS__, 'handle_save_measure' ) );
			add_action( 'admin_post_qmo_measure_library_archive_measure', array( __CLASS__, 'handle_archive_measure' ) );
			add_action( 'admin_post_qmo_measure_library_save_version', array( __CLASS__, 'handle_save_version' ) );
			add_action( 'admin_post_qmo_measure_library_upload_template', array( __CLASS__, 'handle_upload_template' ) );
		}
	}

	public static function measures_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_data_hub_measures';
	}

	public static function versions_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_data_hub_measure_versions';
	}

	public static function templates_table() {
		global $wpdb;
		return $wpdb->prefix . 'qualinav_data_hub_measure_templates';
	}

	public static function maybe_install() {
		global $wpdb;
		$installed = (string) get_option( self::DB_OPTION, '0' );
		$tables    = array( self::measures_table(), self::versions_table(), self::templates_table() );
		$has_all   = true;

		foreach ( $tables as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				$has_all = false;
				break;
			}
		}

		if ( $installed === self::DB_VERSION && $has_all ) {
			return;
		}

		self::install();
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset       = $wpdb->get_charset_collate();
		$measures      = self::measures_table();
		$versions      = self::versions_table();
		$templates     = self::templates_table();

		dbDelta( "CREATE TABLE {$measures} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			measure_key VARCHAR(120) NOT NULL DEFAULT '',
			programme VARCHAR(40) NOT NULL DEFAULT 'mbqip',
			category VARCHAR(120) NOT NULL DEFAULT '',
			title VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT NULL,
			reporting_period_type VARCHAR(30) NOT NULL DEFAULT 'annual',
			entry_type VARCHAR(60) NOT NULL DEFAULT 'rate',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
			created_by BIGINT(20) UNSIGNED NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY measure_key (measure_key),
			KEY programme (programme),
			KEY category (category),
			KEY status (status),
			KEY sort_order (sort_order)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$versions} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			measure_id BIGINT(20) UNSIGNED NOT NULL,
			version_label VARCHAR(120) NOT NULL DEFAULT '',
			effective_start_date DATE NULL,
			effective_end_date DATE NULL,
			spec_json LONGTEXT NULL,
			validation_json LONGTEXT NULL,
			is_current TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_by BIGINT(20) UNSIGNED NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY measure_id (measure_id),
			KEY status (status),
			KEY is_current (is_current),
			KEY effective_start_date (effective_start_date)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$templates} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			measure_id BIGINT(20) UNSIGNED NOT NULL,
			measure_version_id BIGINT(20) UNSIGNED NULL,
			template_type VARCHAR(60) NOT NULL DEFAULT '',
			file_name VARCHAR(255) NOT NULL DEFAULT '',
			file_path TEXT NULL,
			file_url TEXT NULL,
			mime_type VARCHAR(120) NOT NULL DEFAULT '',
			schema_json LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			uploaded_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY measure_id (measure_id),
			KEY measure_version_id (measure_version_id),
			KEY template_type (template_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};" );

		self::seed_defaults();
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function register_admin_page() {
		add_submenu_page(
			null,
			'Data Hub Measure Library',
			'Measure Library',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	private static function default_specs() {
		$specs = array();

		if ( function_exists( 'qualinav_data_hub_mbqip_measure_definitions' ) ) {
			foreach ( qualinav_data_hub_mbqip_measure_definitions() as $definition ) {
				$specs[] = array(
					'measure_key'           => $definition['measure_key'],
					'programme'             => 'mbqip',
					'category'              => $definition['event_type'] ?? 'MBQIP',
					'title'                 => $definition['measure_name'],
					'description'           => '',
					'reporting_period_type' => $definition['reporting_period_type'] ?? 'annual',
					'entry_type'            => self::entry_type_from_definition( $definition ),
					'sort_order'            => (int) ( $definition['sort_order'] ?? 0 ),
					'spec'                  => array(
						'numerator_label'   => $definition['numerator_label'] ?? '',
						'denominator_label' => $definition['denominator_label'] ?? '',
						'rate_label'        => $definition['rate_label'] ?? 'Rate',
						'benchmark_value'   => $definition['benchmark_value'] ?? null,
						'benchmark_label'   => $definition['benchmark_label'] ?? '',
						'template_schema'   => $definition['template_schema'] ?? array(),
						'raw_data_schema'   => $definition['raw_data_schema'] ?? array(),
					),
				);
			}
		}

		$hacs = array(
			array( 'readmissions', '30-Day Unplanned Readmission Rate', 'CMS Planned Readmission Algorithm v4.0 applied to exclude planned readmissions.', 10 ),
			array( 'clabsi', 'CLABSI Rate', 'NHSN CLABSI surveillance protocol required. Rate per 1,000 central line days.', 20 ),
			array( 'sepsis_mortality', 'Sepsis Mortality', 'AHRQ', 30 ),
			array( 'cauti', 'CAUTI Rate', 'NHSN CAUTI surveillance protocol. Rate per 1,000 catheter days.', 40 ),
			array( 'falls_with_injury', 'Inpatient Falls with Injury Rate', 'Rate expressed per 1,000 patient days.', 50 ),
			array( 'pressure_ulcers_3_plus', 'Hospital-Acquired Pressure Injuries (HAPIs) — Stage 3+', 'NPIAP staging definitions required.', 60 ),
			array( 'c_diff', 'C. difficile (CDI) Rate', 'NHSN CDI FacWideIN protocol. Rate per 10,000 patient days.', 70 ),
			array( 'mrsa', 'MRSA Bacteremia Rate', 'NHSN MRSA FacWideIN protocol. Rate per 100,000 patient days.', 80 ),
		);

		foreach ( $hacs as $row ) {
			$specs[] = array(
				'measure_key'           => 'hacs_hais_' . $row[0],
				'programme'             => 'hacs_hais',
				'category'              => 'HACs & HAIs',
				'title'                 => $row[1],
				'description'           => '',
				'reporting_period_type' => 'monthly',
				'entry_type'            => 'hacs_hais_rate',
				'sort_order'            => $row[3],
				'spec'                  => array(
					'measure_id'         => $row[0],
					'specifications'     => $row[2],
					'numerator_label'    => 'Numerator Value',
					'denominator_label'  => 'Denominator Value',
					'rate_label'         => 'Rate',
					'template_schema'    => array( 'Year', 'Month', 'Numerator Value', 'Denominator Value', 'Rate' ),
					'raw_data_schema'    => array( 'Year', 'Month', 'Numerator Value', 'Denominator Value', 'Rate' ),
				),
			);
		}

		return $specs;
	}

	private static function entry_type_from_definition( $definition ) {
		$key = $definition['measure_key'] ?? '';
		if ( false !== strpos( $key, 'cah_quality' ) ) {
			return 'elements_checklist';
		}
		if ( false !== strpos( $key, 'antibiotic' ) ) {
			return 'antibiotic_stewardship';
		}
		if ( false !== strpos( $key, 'edtc' ) ) {
			return 'edtc_checklist';
		}
		if ( false !== strpos( $key, 'op_18' ) ) {
			return 'quarter_median';
		}
		if ( false !== strpos( $key, 'op_22' ) ) {
			return 'annual_numden_rate';
		}
		if ( ( $definition['reporting_period_type'] ?? '' ) === 'quarterly' ) {
			return false !== strpos( $key, 'safe_use' ) ? 'period_rate' : 'quarter_rate';
		}
		return 'annual_rate';
	}

	private static function seed_defaults() {
		global $wpdb;
		$table = self::measures_table();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}

		foreach ( self::default_specs() as $spec ) {
			self::upsert_seed_measure( $spec );
		}
	}

	private static function upsert_seed_measure( $spec ) {
		global $wpdb;
		$now      = current_time( 'mysql' );
		$user_id  = get_current_user_id() ?: null;
		$measures = self::measures_table();

		$wpdb->insert(
			$measures,
			array(
				'measure_key'           => sanitize_key( $spec['measure_key'] ),
				'programme'             => sanitize_key( $spec['programme'] ),
				'category'              => sanitize_text_field( $spec['category'] ),
				'title'                 => sanitize_text_field( $spec['title'] ),
				'description'           => sanitize_textarea_field( $spec['description'] ),
				'reporting_period_type' => sanitize_key( $spec['reporting_period_type'] ),
				'entry_type'            => sanitize_key( $spec['entry_type'] ),
				'status'                => 'active',
				'sort_order'            => (int) $spec['sort_order'],
				'created_by'            => $user_id,
				'updated_by'            => $user_id,
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		$measure_id = (int) $wpdb->insert_id;
		if ( $measure_id > 0 ) {
			self::insert_version(
				$measure_id,
				array(
					'version_label'        => 'Initial specification',
					'effective_start_date' => '',
					'effective_end_date'   => '',
					'spec_json'            => wp_json_encode( $spec['spec'] ),
					'validation_json'      => wp_json_encode( self::default_validation_for_entry_type( $spec['entry_type'] ) ),
					'status'               => 'active',
					'is_current'           => 1,
				)
			);
		}
	}

	private static function default_validation_for_entry_type( $entry_type ) {
		return array(
			'denominator_must_be_greater_or_equal_numerator' => in_array( $entry_type, array( 'annual_rate', 'annual_numden_rate', 'period_rate', 'quarter_rate', 'edtc_checklist', 'hacs_hais_rate' ), true ),
		);
	}

	private static function insert_version( $measure_id, $data ) {
		global $wpdb;
		$now     = current_time( 'mysql' );
		$user_id = get_current_user_id() ?: null;
		$status  = sanitize_key( $data['status'] ?? 'draft' );
		$current = ! empty( $data['is_current'] ) ? 1 : 0;

		if ( $current ) {
			$wpdb->update( self::versions_table(), array( 'is_current' => 0 ), array( 'measure_id' => $measure_id ), array( '%d' ), array( '%d' ) );
		}

		$wpdb->insert(
			self::versions_table(),
			array(
				'measure_id'            => $measure_id,
				'version_label'         => sanitize_text_field( $data['version_label'] ?? '' ),
				'effective_start_date'  => self::sanitize_date_or_null( $data['effective_start_date'] ?? '' ),
				'effective_end_date'    => self::sanitize_date_or_null( $data['effective_end_date'] ?? '' ),
				'spec_json'             => self::sanitize_json_text( $data['spec_json'] ?? '{}' ),
				'validation_json'       => self::sanitize_json_text( $data['validation_json'] ?? '{}' ),
				'is_current'            => $current,
				'status'                => $status,
				'created_by'            => $user_id,
				'updated_by'            => $user_id,
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	private static function sanitize_date_or_null( $date ) {
		$date = trim( (string) $date );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
	}

	private static function sanitize_json_text( $json ) {
		$json = trim( wp_unslash( (string) $json ) );
		if ( '' === $json ) {
			return '{}';
		}
		json_decode( $json, true );
		return json_last_error() === JSON_ERROR_NONE ? $json : wp_json_encode( array( 'notes' => $json ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		$id     = isset( $_GET['measure_id'] ) ? absint( $_GET['measure_id'] ) : 0;

		echo '<div class="wrap qmo-measure-library">';
		echo '<h1>Data Hub Measure Library</h1>';
		echo '<p class="description">Manage annual measure specifications, archive old measures, and store admin-uploaded workbook templates.</p>';
		self::render_notice();
		self::render_admin_styles();

		if ( 'edit' === $action ) {
			self::render_edit_page( $id );
		} else {
			self::render_list_page();
		}

		echo '</div>';
	}

	private static function render_notice() {
		$message = isset( $_GET['qmo_msg'] ) ? sanitize_key( $_GET['qmo_msg'] ) : '';
		$messages = array(
			'saved'    => 'Measure saved.',
			'archived' => 'Measure archived.',
			'restored' => 'Measure restored.',
			'version'  => 'Specification version saved.',
			'template' => 'Template uploaded.',
			'error'    => 'Something went wrong. Please check the form and try again.',
		);
		if ( isset( $messages[ $message ] ) ) {
			$class = 'error' === $message ? 'notice notice-error' : 'notice notice-success';
			printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $messages[ $message ] ) );
		}
	}

	private static function render_admin_styles() {
		?>
		<style>
			.qmo-measure-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;margin:16px 0;max-width:1180px}
			.qmo-measure-grid{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:14px 18px;max-width:980px}
			.qmo-measure-grid label{font-weight:600;display:block;margin-bottom:5px}
			.qmo-measure-grid input,.qmo-measure-grid select,.qmo-measure-grid textarea{width:100%}
			.qmo-measure-json{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;min-height:150px}
			.qmo-status-pill{display:inline-block;border-radius:999px;padding:2px 9px;font-size:12px;font-weight:700;background:#eef2ff;color:#3730a3}
			.qmo-status-pill.archived{background:#f1f5f9;color:#64748b}
			.qmo-measure-description{margin-top:22px;max-width:980px}
			.qmo-measure-description label{display:block;font-weight:600;margin-bottom:6px}
			.qmo-measure-description textarea{width:100%}
			.qmo-template-list{margin:0;padding-left:18px}
		</style>
		<?php
	}

	private static function render_list_page() {
		$measures = self::get_measures();
		$new_url  = add_query_arg( array( 'page' => self::PAGE_SLUG, 'action' => 'edit' ), admin_url( 'admin.php' ) );
		echo '<p><a class="button button-primary" href="' . esc_url( $new_url ) . '">Add New Measure</a></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>Measure</th><th>Programme</th><th>Category</th><th>Entry Type</th><th>Current Version</th><th>Status</th><th>Templates</th><th>Actions</th></tr></thead><tbody>';

		if ( empty( $measures ) ) {
			echo '<tr><td colspan="8">No measures found.</td></tr>';
		}

		foreach ( $measures as $measure ) {
			$edit_url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'action' => 'edit', 'measure_id' => (int) $measure->id ), admin_url( 'admin.php' ) );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $measure->title ) . '</strong><br><code>' . esc_html( $measure->measure_key ) . '</code></td>';
			echo '<td>' . esc_html( self::programme_label( $measure->programme ) ) . '</td>';
			echo '<td>' . esc_html( $measure->category ) . '</td>';
			echo '<td><code>' . esc_html( $measure->entry_type ) . '</code></td>';
			echo '<td>' . esc_html( $measure->version_label ?: 'None' ) . '</td>';
			echo '<td><span class="qmo-status-pill ' . esc_attr( $measure->status ) . '">' . esc_html( ucfirst( $measure->status ) ) . '</span></td>';
			echo '<td>' . esc_html( (int) $measure->template_count ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $edit_url ) . '">Edit</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private static function render_edit_page( $measure_id ) {
		$measure   = $measure_id ? self::get_measure( $measure_id ) : null;
		$versions  = $measure_id ? self::get_versions( $measure_id ) : array();
		$templates = $measure_id ? self::get_templates( $measure_id ) : array();
		$current   = $measure_id ? self::get_current_version( $measure_id ) : null;

		$back_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; Back to Measure Library</a></p>';

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qmo-measure-card">
			<h2><?php echo $measure ? 'Edit Measure' : 'Add New Measure'; ?></h2>
			<?php wp_nonce_field( 'qmo_measure_library_save_measure' ); ?>
			<input type="hidden" name="action" value="qmo_measure_library_save_measure">
			<input type="hidden" name="measure_id" value="<?php echo esc_attr( $measure_id ); ?>">
			<div class="qmo-measure-grid">
				<p><label>Measure title</label><input type="text" name="title" required value="<?php echo esc_attr( $measure->title ?? '' ); ?>"></p>
				<p><label>Measure key</label><input type="text" name="measure_key" required value="<?php echo esc_attr( $measure->measure_key ?? '' ); ?>" <?php disabled( (bool) $measure ); ?>><span class="description">Stable key. Existing keys cannot be changed.</span></p>
				<p><label>Programme</label><?php self::programme_select( $measure->programme ?? 'mbqip' ); ?></p>
				<p><label>Category</label><input type="text" name="category" value="<?php echo esc_attr( $measure->category ?? '' ); ?>"></p>
				<p><label>Reporting period type</label><?php self::period_select( $measure->reporting_period_type ?? 'annual' ); ?></p>
				<p><label>Entry type</label><?php self::entry_type_select( $measure->entry_type ?? 'annual_rate' ); ?></p>
				<p><label>Status</label><?php self::status_select( $measure->status ?? 'active' ); ?></p>
				<p><label>Sort order</label><input type="number" name="sort_order" value="<?php echo esc_attr( $measure->sort_order ?? 0 ); ?>"></p>
			</div>
			<p class="qmo-measure-description"><label>Description</label><textarea name="description" rows="3"><?php echo esc_textarea( $measure->description ?? '' ); ?></textarea></p>
			<?php submit_button( $measure ? 'Save Measure' : 'Create Measure' ); ?>
		</form>
		<?php

		if ( ! $measure_id ) {
			return;
		}

		self::render_version_form( $measure_id, $current );
		self::render_template_form( $measure_id, $current, $templates );
		self::render_versions_table( $versions );
		self::render_archive_form( $measure );
	}

	private static function render_version_form( $measure_id, $current ) {
		$spec_json       = $current ? self::pretty_json( $current->spec_json ) : "{\n  \"notes\": \"\"\n}";
		$validation_json = $current ? self::pretty_json( $current->validation_json ) : "{\n  \"denominator_must_be_greater_or_equal_numerator\": true\n}";
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qmo-measure-card">
			<h2>Create Specification Version</h2>
			<p class="description">Create a new annual/versioned specification. Saving as current will supersede the previous current version, but old versions remain archived for audit.</p>
			<?php wp_nonce_field( 'qmo_measure_library_save_version' ); ?>
			<input type="hidden" name="action" value="qmo_measure_library_save_version">
			<input type="hidden" name="measure_id" value="<?php echo esc_attr( $measure_id ); ?>">
			<div class="qmo-measure-grid">
				<p><label>Version label</label><input type="text" name="version_label" required placeholder="2027 annual update"></p>
				<p><label>Status</label><select name="status"><option value="draft">Draft</option><option value="active">Active</option></select></p>
				<p><label>Effective start date</label><input type="date" name="effective_start_date"></p>
				<p><label>Effective end date</label><input type="date" name="effective_end_date"></p>
			</div>
			<p><label><input type="checkbox" name="is_current" value="1"> Make this the current active version</label></p>
			<p><label><strong>Specification JSON</strong></label><textarea name="spec_json" class="qmo-measure-json" style="width:100%;"><?php echo esc_textarea( $spec_json ); ?></textarea></p>
			<p><label><strong>Validation JSON</strong></label><textarea name="validation_json" class="qmo-measure-json" style="width:100%;"><?php echo esc_textarea( $validation_json ); ?></textarea></p>
			<?php submit_button( 'Save Specification Version' ); ?>
		</form>
		<?php
	}

	private static function render_template_form( $measure_id, $current, $templates ) {
		?>
		<div class="qmo-measure-card">
			<h2>Measure Templates</h2>
			<?php if ( empty( $templates ) ) : ?>
				<p>No templates uploaded yet.</p>
			<?php else : ?>
				<ul class="qmo-template-list">
					<?php foreach ( $templates as $template ) : ?>
						<li><a href="<?php echo esc_url( $template->file_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $template->file_name ); ?></a> <code><?php echo esc_html( $template->template_type ); ?></code> <?php echo esc_html( mysql2date( 'm/d/Y', $template->created_at ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<hr>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'qmo_measure_library_upload_template' ); ?>
				<input type="hidden" name="action" value="qmo_measure_library_upload_template">
				<input type="hidden" name="measure_id" value="<?php echo esc_attr( $measure_id ); ?>">
				<input type="hidden" name="measure_version_id" value="<?php echo esc_attr( $current->id ?? 0 ); ?>">
				<div class="qmo-measure-grid">
					<p><label>Template type</label><?php self::entry_type_select( $current ? '' : '', 'template_type' ); ?></p>
					<p><label>Upload template</label><input type="file" name="measure_template" accept=".xlsx,.xls,.csv" required></p>
				</div>
				<p><label><strong>Optional schema JSON</strong></label><textarea name="schema_json" class="qmo-measure-json" style="width:100%;" placeholder='{"sheets":["Measure"]}'></textarea></p>
				<?php submit_button( 'Upload Template' ); ?>
			</form>
		</div>
		<?php
	}

	private static function render_versions_table( $versions ) {
		echo '<div class="qmo-measure-card"><h2>Specification History</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Version</th><th>Effective</th><th>Status</th><th>Current</th><th>Updated</th></tr></thead><tbody>';
		if ( empty( $versions ) ) {
			echo '<tr><td colspan="5">No versions found.</td></tr>';
		}
		foreach ( $versions as $version ) {
			echo '<tr>';
			echo '<td>' . esc_html( $version->version_label ) . '</td>';
			echo '<td>' . esc_html( trim( ( $version->effective_start_date ?: '' ) . ' - ' . ( $version->effective_end_date ?: '' ), ' -' ) ?: 'Not set' ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $version->status ) ) . '</td>';
			echo '<td>' . ( $version->is_current ? 'Yes' : 'No' ) . '</td>';
			echo '<td>' . esc_html( mysql2date( 'm/d/Y g:i A', $version->updated_at ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function render_archive_form( $measure ) {
		$is_archived = 'archived' === $measure->status;
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qmo-measure-card">
			<h2><?php echo $is_archived ? 'Restore Measure' : 'Archive Measure'; ?></h2>
			<p class="description"><?php echo $is_archived ? 'Restoring makes the measure active in this library again.' : 'Archiving hides the measure from future active configuration work without deleting historical data.'; ?></p>
			<?php wp_nonce_field( 'qmo_measure_library_archive_measure' ); ?>
			<input type="hidden" name="action" value="qmo_measure_library_archive_measure">
			<input type="hidden" name="measure_id" value="<?php echo esc_attr( $measure->id ); ?>">
			<input type="hidden" name="archive_state" value="<?php echo $is_archived ? 'active' : 'archived'; ?>">
			<?php submit_button( $is_archived ? 'Restore Measure' : 'Archive Measure', $is_archived ? 'primary' : 'delete' ); ?>
		</form>
		<?php
	}

	private static function get_measures() {
		global $wpdb;
		$measures  = self::measures_table();
		$versions  = self::versions_table();
		$templates = self::templates_table();
		return $wpdb->get_results(
			"SELECT m.*,
			        (
			            SELECT v.version_label
			              FROM {$versions} v
			             WHERE v.measure_id = m.id
			               AND v.is_current = 1
			             ORDER BY v.updated_at DESC
			             LIMIT 1
			        ) AS version_label,
			        (
			            SELECT COUNT(t.id)
			              FROM {$templates} t
			             WHERE t.measure_id = m.id
			               AND t.status = 'active'
			        ) AS template_count
			   FROM {$measures} m
		   ORDER BY m.programme ASC, m.sort_order ASC, m.title ASC"
		);
	}

	private static function get_measure( $measure_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::measures_table() . ' WHERE id = %d', $measure_id ) );
	}

	private static function get_versions( $measure_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::versions_table() . ' WHERE measure_id = %d ORDER BY is_current DESC, updated_at DESC', $measure_id ) );
	}

	private static function get_current_version( $measure_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::versions_table() . ' WHERE measure_id = %d AND is_current = 1 ORDER BY updated_at DESC LIMIT 1', $measure_id ) );
	}

	private static function get_templates( $measure_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::templates_table() . ' WHERE measure_id = %d ORDER BY created_at DESC', $measure_id ) );
	}

	public static function handle_save_measure() {
		self::require_admin_post( 'qmo_measure_library_save_measure' );
		global $wpdb;
		$measure_id = isset( $_POST['measure_id'] ) ? absint( $_POST['measure_id'] ) : 0;
		$now        = current_time( 'mysql' );
		$data       = array(
			'programme'             => sanitize_key( $_POST['programme'] ?? 'mbqip' ),
			'category'              => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
			'title'                 => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'           => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'reporting_period_type' => sanitize_key( $_POST['reporting_period_type'] ?? 'annual' ),
			'entry_type'            => sanitize_key( $_POST['entry_type'] ?? 'rate' ),
			'status'                => sanitize_key( $_POST['status'] ?? 'active' ),
			'sort_order'            => absint( $_POST['sort_order'] ?? 0 ),
			'updated_by'            => get_current_user_id(),
			'updated_at'            => $now,
			'archived_at'           => ( ( $_POST['status'] ?? '' ) === 'archived' ) ? $now : null,
		);

		if ( $measure_id > 0 ) {
			$wpdb->update( self::measures_table(), $data, array( 'id' => $measure_id ) );
		} else {
			$data['measure_key'] = sanitize_key( wp_unslash( $_POST['measure_key'] ?? '' ) );
			$data['created_by']  = get_current_user_id();
			$data['created_at']  = $now;
			$wpdb->insert( self::measures_table(), $data );
			$measure_id = (int) $wpdb->insert_id;
		}

		self::redirect_edit( $measure_id, 'saved' );
	}

	public static function handle_archive_measure() {
		self::require_admin_post( 'qmo_measure_library_archive_measure' );
		global $wpdb;
		$measure_id = absint( $_POST['measure_id'] ?? 0 );
		$status     = sanitize_key( $_POST['archive_state'] ?? 'archived' );
		$now        = current_time( 'mysql' );
		$wpdb->update(
			self::measures_table(),
			array(
				'status'      => 'archived' === $status ? 'archived' : 'active',
				'archived_at' => 'archived' === $status ? $now : null,
				'updated_by'  => get_current_user_id(),
				'updated_at'  => $now,
			),
			array( 'id' => $measure_id )
		);
		self::redirect_edit( $measure_id, 'archived' === $status ? 'archived' : 'restored' );
	}

	public static function handle_save_version() {
		self::require_admin_post( 'qmo_measure_library_save_version' );
		$measure_id = absint( $_POST['measure_id'] ?? 0 );
		if ( $measure_id <= 0 ) {
			self::redirect_list( 'error' );
		}
		self::insert_version(
			$measure_id,
			array(
				'version_label'        => wp_unslash( $_POST['version_label'] ?? '' ),
				'effective_start_date' => wp_unslash( $_POST['effective_start_date'] ?? '' ),
				'effective_end_date'   => wp_unslash( $_POST['effective_end_date'] ?? '' ),
				'spec_json'            => wp_unslash( $_POST['spec_json'] ?? '{}' ),
				'validation_json'      => wp_unslash( $_POST['validation_json'] ?? '{}' ),
				'status'               => wp_unslash( $_POST['status'] ?? 'draft' ),
				'is_current'           => ! empty( $_POST['is_current'] ) ? 1 : 0,
			)
		);
		self::redirect_edit( $measure_id, 'version' );
	}

	public static function handle_upload_template() {
		self::require_admin_post( 'qmo_measure_library_upload_template' );
		$measure_id = absint( $_POST['measure_id'] ?? 0 );
		if ( $measure_id <= 0 || empty( $_FILES['measure_template']['name'] ) ) {
			self::redirect_edit( $measure_id, 'error' );
		}

		$allowed = array( 'xlsx', 'xls', 'csv' );
		$ext     = strtolower( pathinfo( sanitize_file_name( $_FILES['measure_template']['name'] ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed, true ) ) {
			self::redirect_edit( $measure_id, 'error' );
		}

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'qualinav-data-hub-measure-templates';
		if ( ! wp_mkdir_p( $target_dir ) ) {
			self::redirect_edit( $measure_id, 'error' );
		}

		$file_name = wp_unique_filename( $target_dir, sanitize_file_name( $_FILES['measure_template']['name'] ) );
		$target    = trailingslashit( $target_dir ) . $file_name;
		if ( ! move_uploaded_file( $_FILES['measure_template']['tmp_name'], $target ) ) {
			self::redirect_edit( $measure_id, 'error' );
		}

		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			self::templates_table(),
			array(
				'measure_id'          => $measure_id,
				'measure_version_id'  => absint( $_POST['measure_version_id'] ?? 0 ) ?: null,
				'template_type'       => sanitize_key( $_POST['template_type'] ?? '' ),
				'file_name'           => $file_name,
				'file_path'           => $target,
				'file_url'            => trailingslashit( $upload_dir['baseurl'] ) . 'qualinav-data-hub-measure-templates/' . rawurlencode( $file_name ),
				'mime_type'           => sanitize_text_field( $_FILES['measure_template']['type'] ?? '' ),
				'schema_json'         => self::sanitize_json_text( $_POST['schema_json'] ?? '{}' ),
				'status'              => 'active',
				'uploaded_by'         => get_current_user_id(),
				'created_at'          => $now,
				'updated_at'          => $now,
			)
		);
		self::redirect_edit( $measure_id, 'template' );
	}

	private static function require_admin_post( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'qualinav-my-org' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect_list( $message ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'qmo_msg' => $message ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function redirect_edit( $measure_id, $message ) {
		if ( $measure_id <= 0 ) {
			self::redirect_list( $message );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'action' => 'edit', 'measure_id' => $measure_id, 'qmo_msg' => $message ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function programme_select( $value ) {
		$options = array( 'mbqip' => 'MBQIP', 'hacs_hais' => 'HACs & HAIs' );
		echo '<select name="programme">';
		foreach ( $options as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private static function period_select( $value ) {
		$options = array( 'annual' => 'Annual', 'quarterly' => 'Quarterly', 'monthly' => 'Monthly' );
		echo '<select name="reporting_period_type">';
		foreach ( $options as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private static function entry_type_select( $value, $name = 'entry_type' ) {
		$options = array(
			'annual_rate'            => 'Annual Rate',
			'annual_numden_rate'     => 'Annual Num/Den Rate',
			'period_rate'            => 'Monthly Rate',
			'quarter_rate'           => 'Quarterly Rate',
			'quarter_median'         => 'Quarterly Median',
			'elements_checklist'     => 'Annual Checklist',
			'antibiotic_stewardship' => 'Antibiotic Stewardship Checklist',
			'edtc_checklist'         => 'EDTC Checklist',
			'hacs_hais_rate'         => 'HACs & HAIs Rate',
		);
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private static function status_select( $value ) {
		$options = array( 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived' );
		echo '<select name="status">';
		foreach ( $options as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private static function programme_label( $programme ) {
		return 'hacs_hais' === $programme ? 'HACs & HAIs' : strtoupper( $programme );
	}

	private static function pretty_json( $json ) {
		$decoded = json_decode( (string) $json, true );
		return json_last_error() === JSON_ERROR_NONE ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : (string) $json;
	}
}
