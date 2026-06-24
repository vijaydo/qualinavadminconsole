<?php
/**
 * Admin settings page for Data Hub → Google Drive storage.
 *
 * Adds a "Drive" submenu under the existing QAPI Dashboard menu and renders a
 * tiny settings form. The handler writes the same option Qualinav_Data_Hub_Drive
 * reads (qualinav_data_hub_drive_settings).
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Qualinav_Data_Hub_Drive_Settings' ) ) {

class Qualinav_Data_Hub_Drive_Settings {

	const PAGE_SLUG = 'data-hub-drive';

	public static function boot() {
		add_action( 'admin_menu',                        array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_init',                        array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_qualinav_dh_drive_test', array( __CLASS__, 'handle_test_connection' ) );
	}

	public static function register_menu() {
		// Hang off the existing QAPI Dashboard admin page if it's registered.
		$parent = 'qaqd-dashboard-help';
		add_submenu_page(
			$parent,
			__( 'Data Hub Drive', 'data-hub' ),
			__( 'Drive Storage', 'data-hub' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'qualinav_data_hub_drive_group',
			Qualinav_Data_Hub_Drive::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(
					'enabled'        => 0,
					'root_folder_id' => '',
					'make_public'    => 0,
				),
			)
		);
	}

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		return array(
			'root_folder_id' => isset( $input['root_folder_id'] ) ? sanitize_text_field( (string) $input['root_folder_id'] ) : '',
			'make_public'    => empty( $input['make_public'] ) ? 0 : 1,
			'paused'         => empty( $input['paused'] ) ? 0 : 1,
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings   = Qualinav_Data_Hub_Drive::get_settings();
		$sa_path    = Qualinav_Data_Hub_Drive::service_account_path();
		$sdk_ok     = Qualinav_Data_Hub_Drive::load_sdk();
		$root_id    = (string) $settings['root_folder_id'];
		$has_root   = $root_id !== '';
		$test       = isset( $_GET['drive_test'] ) ? sanitize_text_field( (string) $_GET['drive_test'] ) : '';
		$test_msg   = isset( $_GET['drive_msg'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['drive_msg'] ) ) : '';

		// Resolve folder status for the overview panel. Only probe Drive when we
		// have everything needed — otherwise show "Not configured".
		$folder_status_label = '';
		$folder_status_color = '#9ca3af';
		$folder_status_hint  = '';
		if ( ! $sdk_ok ) {
			$folder_status_label = __( 'Google API SDK missing', 'data-hub' );
			$folder_status_color = '#b3261e';
			$folder_status_hint  = __( 'Install google/apiclient or activate a plugin that bundles it.', 'data-hub' );
		} elseif ( $sa_path === '' ) {
			$folder_status_label = __( 'Service account missing', 'data-hub' );
			$folder_status_color = '#b3261e';
			$folder_status_hint  = __( 'Place service-account JSON at wp-content/secure/grapevine-drive-sa.json.', 'data-hub' );
		} elseif ( ! $has_root ) {
			$folder_status_label = __( 'Not configured', 'data-hub' );
			$folder_status_color = '#b45309';
			$folder_status_hint  = __( 'Add a Google Drive root folder ID below.', 'data-hub' );
		} else {
			try {
				$drive = Qualinav_Data_Hub_Drive::service();
				$meta  = $drive->files->get( $root_id, array(
					'fields'            => 'id,name,mimeType',
					'supportsAllDrives' => true,
				) );
				if ( ! empty( $meta->id ) ) {
					$folder_status_label = sprintf( __( 'Connected — "%s"', 'data-hub' ), (string) $meta->name );
					$folder_status_color = '#1a7f37';
				} else {
					$folder_status_label = __( 'Folder not found', 'data-hub' );
					$folder_status_color = '#b3261e';
					$folder_status_hint  = __( 'The configured ID did not return a folder.', 'data-hub' );
				}
			} catch ( Throwable $e ) {
				$folder_status_label = __( 'Connection failed', 'data-hub' );
				$folder_status_color = '#b3261e';
				$folder_status_hint  = $e->getMessage();
			}
		}
		?>
		<div class="wrap dh-drive-settings">
			<style>
				.dh-drive-settings .dh-card{background:#fff;border:1px solid #dbe4ef;border-radius:10px;padding:18px 22px;margin:14px 0;box-shadow:0 1px 2px rgba(15,23,42,.04)}
				.dh-drive-settings .dh-card h2{margin-top:0;display:flex;align-items:center;gap:8px;font-size:16px}
				.dh-drive-settings .dh-card h2 .dashicons{color:#1565a0}
				.dh-drive-settings .dh-card .description{color:#526072}
				.dh-drive-settings .form-table th{padding:14px 10px 14px 0;width:200px}
				.dh-drive-settings .dh-overview{margin-top:6px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#fafbfc}
				.dh-drive-settings .dh-overview-row{display:grid;grid-template-columns:200px 1fr;border-top:1px solid #e2e8f0;padding:10px 14px;font-size:13px}
				.dh-drive-settings .dh-overview-row:first-child{border-top:none}
				.dh-drive-settings .dh-overview-row .dh-overview-label{color:#4b5563;font-weight:600}
				.dh-drive-settings .dh-overview-row .dh-overview-value code{background:#eef2f7;padding:2px 6px;border-radius:4px;color:#1f2937}
				.dh-drive-settings .dh-overview-hint{color:#6b7280;font-size:12px;display:block;margin-top:2px}
				.dh-drive-settings .dh-pill{display:inline-flex;align-items:center;gap:6px;padding:2px 10px;border-radius:999px;font-weight:600;font-size:12px;background:#eef2f7;color:#374151}
				.dh-drive-settings .dh-pill .dh-dot{width:8px;height:8px;border-radius:50%;background:currentColor}
				.dh-drive-settings .dh-section-hint{color:#6b7280;margin:0 0 10px}
			</style>

			<h1 style="display:flex;align-items:center;gap:8px;"><span class="dashicons dashicons-cloud-upload" style="font-size:28px;width:28px;height:28px;color:#1565a0;"></span><?php esc_html_e( 'Data Hub — Google Drive Storage', 'data-hub' ); ?></h1>
			<p class="dh-section-hint"><?php esc_html_e( 'Once a Google Drive root folder ID is set below, every file uploaded through Data Management is mirrored to Drive under the hierarchy: state / organization / user id / folder. Local copies are kept so existing parsing and dashboard sync continue to work. Use the Pause switch to temporarily disable mirroring.', 'data-hub' ); ?></p>

			<?php if ( $test === 'ok' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $test_msg !== '' ? $test_msg : __( 'Drive connection successful.', 'data-hub' ) ); ?></p></div>
			<?php elseif ( $test === 'fail' ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $test_msg !== '' ? $test_msg : __( 'Drive connection failed.', 'data-hub' ) ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'qualinav_data_hub_drive_group' ); ?>
				<?php
				$opt_name      = Qualinav_Data_Hub_Drive::SETTINGS_OPTION;
				$is_active     = Qualinav_Data_Hub_Drive::is_enabled();
				$is_paused     = ! empty( $settings['paused'] );
				$mirror_label  = $is_active ? __( 'Active — uploads will be mirrored to Drive', 'data-hub' ) : ( $is_paused ? __( 'Paused — uploads stay local until you resume', 'data-hub' ) : __( 'Inactive — set a Root folder ID below to start mirroring', 'data-hub' ) );
				$mirror_color  = $is_active ? '#1a7f37' : ( $is_paused ? '#b45309' : '#6b7280' );
				?>

				<div class="dh-card">
					<h2><span class="dashicons dashicons-cloud"></span><?php esc_html_e( 'Storage', 'data-hub' ); ?></h2>
					<p class="dh-section-hint"><?php esc_html_e( 'Paste the ID of the Drive folder that should hold uploads. Share that folder with the service account email so it can write inside it.', 'data-hub' ); ?></p>

					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th><label for="dh_drive_root"><?php esc_html_e( 'Google Drive root folder ID', 'data-hub' ); ?></label></th>
							<td>
								<input type="text" id="dh_drive_root" class="regular-text code" name="<?php echo esc_attr( $opt_name ); ?>[root_folder_id]" value="<?php echo esc_attr( $root_id ); ?>" placeholder="0AI...example...drive folder ID" style="min-width:420px;">
								<p class="description"><?php esc_html_e( 'You can find this ID in the Drive URL: drive.google.com/drive/folders/<strong>FOLDER_ID</strong>.', 'data-hub' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="dh_drive_public"><?php esc_html_e( 'Make uploaded Drive files accessible by link', 'data-hub' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="dh_drive_public" name="<?php echo esc_attr( $opt_name ); ?>[make_public]" value="1" <?php checked( ! empty( $settings['make_public'] ) ); ?>>
									<?php esc_html_e( 'Anyone with the link can view (otherwise only the service account and people you share with).', 'data-hub' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="dh_drive_paused"><?php esc_html_e( 'Pause Drive mirroring', 'data-hub' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="dh_drive_paused" name="<?php echo esc_attr( $opt_name ); ?>[paused]" value="1" <?php checked( ! empty( $settings['paused'] ) ); ?>>
									<?php esc_html_e( 'Stop sending new uploads to Drive (local copies still saved). Leave unchecked for normal operation.', 'data-hub' ); ?>
								</label>
							</td>
						</tr>
					</tbody></table>

					<h3 style="margin-top:18px;"><?php esc_html_e( 'Configuration Overview', 'data-hub' ); ?></h3>
					<div class="dh-overview">
						<div class="dh-overview-row">
							<div class="dh-overview-label"><?php esc_html_e( 'Mirroring', 'data-hub' ); ?></div>
							<div class="dh-overview-value">
								<span class="dh-pill" style="color:<?php echo esc_attr( $mirror_color ); ?>;background:<?php echo esc_attr( self::tint( $mirror_color ) ); ?>;"><span class="dh-dot"></span><?php echo esc_html( $mirror_label ); ?></span>
							</div>
						</div>
						<div class="dh-overview-row">
							<div class="dh-overview-label"><?php esc_html_e( 'Root Folder ID', 'data-hub' ); ?></div>
							<div class="dh-overview-value">
								<?php if ( $has_root ) : ?>
									<code><?php echo esc_html( $root_id ); ?></code>
								<?php else : ?>
									<span class="dh-pill" style="color:#6b7280;background:#f3f4f6;"><?php esc_html_e( 'Not configured', 'data-hub' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="dh-overview-row">
							<div class="dh-overview-label"><?php esc_html_e( 'Folder Status', 'data-hub' ); ?></div>
							<div class="dh-overview-value">
								<span class="dh-pill" style="color:<?php echo esc_attr( $folder_status_color ); ?>;background:<?php echo esc_attr( self::tint( $folder_status_color ) ); ?>;"><span class="dh-dot"></span><?php echo esc_html( $folder_status_label ); ?></span>
								<?php if ( $folder_status_hint !== '' ) : ?>
									<span class="dh-overview-hint"><?php echo esc_html( $folder_status_hint ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="dh-overview-row">
							<div class="dh-overview-label"><?php esc_html_e( 'Service Account', 'data-hub' ); ?></div>
							<div class="dh-overview-value">
								<?php if ( $sa_path !== '' ) : ?>
									<span class="dh-pill" style="color:#1a7f37;background:#e7f5ed;"><span class="dh-dot"></span><?php esc_html_e( 'Connected', 'data-hub' ); ?></span>
									<span class="dh-overview-hint"><code><?php echo esc_html( $sa_path ); ?></code></span>
								<?php else : ?>
									<span class="dh-pill" style="color:#b3261e;background:#fdecea;"><span class="dh-dot"></span><?php esc_html_e( 'Not connected', 'data-hub' ); ?></span>
									<span class="dh-overview-hint"><?php esc_html_e( 'Place service-account JSON at wp-content/secure/grapevine-drive-sa.json.', 'data-hub' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="dh-overview-row">
							<div class="dh-overview-label"><?php esc_html_e( 'Google API SDK', 'data-hub' ); ?></div>
							<div class="dh-overview-value">
								<?php if ( $sdk_ok ) : ?>
									<span class="dh-pill" style="color:#1a7f37;background:#e7f5ed;"><span class="dh-dot"></span><?php esc_html_e( 'Loaded', 'data-hub' ); ?></span>
								<?php else : ?>
									<span class="dh-pill" style="color:#b3261e;background:#fdecea;"><span class="dh-dot"></span><?php esc_html_e( 'Not available', 'data-hub' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<p>
					<?php submit_button( __( 'Save Drive Settings', 'data-hub' ), 'primary', 'submit', false ); ?>
				</p>
			</form>

			<div class="dh-card">
				<h2><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Test connection', 'data-hub' ); ?></h2>
				<p class="dh-section-hint"><?php esc_html_e( 'Re-checks the service account can read the configured root folder.', 'data-hub' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="qualinav_dh_drive_test">
					<?php wp_nonce_field( 'qualinav_dh_drive_test' ); ?>
					<?php submit_button( __( 'Run Drive test', 'data-hub' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns a pale background tint paired with the given foreground hex color
	 * for the status pills. Falls back to a neutral grey if the color is unknown.
	 */
	private static function tint( $hex ) {
		switch ( strtolower( (string) $hex ) ) {
			case '#1a7f37': return '#e7f5ed'; // green
			case '#b3261e': return '#fdecea'; // red
			case '#b45309': return '#fef3c7'; // amber
			default:        return '#f3f4f6'; // grey
		}
	}

	public static function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not authorized' );
		}
		check_admin_referer( 'qualinav_dh_drive_test' );

		$redirect = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		try {
			$settings = Qualinav_Data_Hub_Drive::get_settings();
			if ( empty( $settings['root_folder_id'] ) ) {
				throw new RuntimeException( 'Root folder ID is not set.' );
			}
			$drive = Qualinav_Data_Hub_Drive::service();
			$meta  = $drive->files->get( (string) $settings['root_folder_id'], array(
				'fields'            => 'id,name,mimeType',
				'supportsAllDrives' => true,
			) );
			if ( empty( $meta->id ) ) {
				throw new RuntimeException( 'Folder not found.' );
			}
			$msg = sprintf( 'Connected. Root folder "%s" is reachable.', (string) $meta->name );
			wp_safe_redirect( add_query_arg( array( 'drive_test' => 'ok', 'drive_msg' => rawurlencode( $msg ) ), $redirect ) );
		} catch ( Throwable $e ) {
			$msg = $e->getMessage();
			wp_safe_redirect( add_query_arg( array( 'drive_test' => 'fail', 'drive_msg' => rawurlencode( $msg ) ), $redirect ) );
		}
		exit;
	}
}

Qualinav_Data_Hub_Drive_Settings::boot();

}
