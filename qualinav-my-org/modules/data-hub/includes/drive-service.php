<?php
/**
 * Google Drive helpers for the Qualinav Data Hub plugin.
 *
 * Uses a Google service account (same JSON key file as the VinePoster plugin).
 * Look for the key at:
 *   1. env GOOGLE_APPLICATION_CREDENTIALS
 *   2. constant QUALINAV_DATA_HUB_DRIVE_SA_PATH
 *   3. WP_CONTENT_DIR . '/secure/grapevine-drive-sa.json'
 *
 * Folder hierarchy created per upload: state / organization / user_id / folder_id.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Qualinav_Data_Hub_Drive' ) ) {

class Qualinav_Data_Hub_Drive {

	const SETTINGS_OPTION = 'qualinav_data_hub_drive_settings';

	public static function get_settings() {
		$defaults = array(
			'root_folder_id' => '',
			'make_public'    => 0,
			'paused'         => 0,
		);
		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		// Backwards-compat: ignore the older `enabled` flag. The new model
		// treats a configured root_folder_id as the only signal needed for
		// mirroring (so users who paste a folder ID don't have to also remember
		// to tick a checkbox). `paused` is an explicit kill switch.
		unset( $saved['enabled'] );
		return array_merge( $defaults, $saved );
	}

	/**
	 * Drive mirroring is active whenever a root folder ID is configured and the
	 * administrator hasn't explicitly paused it. The SDK + service account are
	 * verified at upload time, not here.
	 */
	public static function is_enabled() {
		$s = self::get_settings();
		if ( empty( $s['root_folder_id'] ) ) {
			return false;
		}
		if ( ! empty( $s['paused'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Try to load the Google API client. Returns true if Google_Client and
	 * Google_Service_Drive are available afterwards.
	 */
	public static function load_sdk() {
		if ( class_exists( 'Google_Client' ) && class_exists( 'Google_Service_Drive' ) ) {
			return true;
		}
		$candidates = array();
		if ( defined( 'QUALINAV_DATA_HUB_GOOGLE_AUTOLOAD' ) ) {
			$candidates[] = QUALINAV_DATA_HUB_GOOGLE_AUTOLOAD;
		}
		if ( defined( 'VP_RESOURCE_DRIVE_VENDOR_AUTOLOAD' ) ) {
			$candidates[] = VP_RESOURCE_DRIVE_VENDOR_AUTOLOAD;
		}
		$candidates[] = WP_CONTENT_DIR . '/vendor/autoload.php';
		// Prefer the complete standalone File Manager vendor bundle when it is
		// installed. The bundled My Org copy is intentionally not used here:
		// Data Hub should not depend on an internal file-manager module.
		$candidates[] = WP_PLUGIN_DIR . '/do-tank-filemanager/vendor/autoload.php';
		// Legacy standalone locations (kept as fallback).
		$candidates[] = WP_PLUGIN_DIR . '/vineposter - v0.192/vendor/autoload.php';
		foreach ( $candidates as $autoload ) {
			if ( is_string( $autoload ) && $autoload !== '' && file_exists( $autoload ) && is_readable( $autoload ) ) {
				if (
					PHP_VERSION_ID < 80100
					&& false !== strpos( str_replace( '\\', '/', $autoload ), '/do-tank-filemanager/vendor/autoload.php' )
				) {
					continue;
				}
				if (
					false !== strpos( str_replace( '\\', '/', $autoload ), '/do-tank-filemanager/vendor/autoload.php' )
					&& ! file_exists( dirname( $autoload ) . '/symfony/deprecation-contracts/function.php' )
				) {
					continue;
				}
				require_once $autoload;
				if ( class_exists( 'Google_Client' ) && class_exists( 'Google_Service_Drive' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function service_account_path() {
		$env = getenv( 'GOOGLE_APPLICATION_CREDENTIALS' );
		if ( $env && file_exists( $env ) && is_readable( $env ) ) {
			return $env;
		}
		if ( defined( 'QUALINAV_DATA_HUB_DRIVE_SA_PATH' ) ) {
			$p = QUALINAV_DATA_HUB_DRIVE_SA_PATH;
			if ( is_string( $p ) && $p !== '' && file_exists( $p ) && is_readable( $p ) ) {
				return $p;
			}
		}
		$fallback = WP_CONTENT_DIR . '/secure/grapevine-drive-sa.json';
		if ( file_exists( $fallback ) && is_readable( $fallback ) ) {
			return $fallback;
		}
		return '';
	}

	/**
	 * Build a Google_Service_Drive instance. Throws on missing creds/SDK.
	 */
	public static function service() {
		if ( ! self::load_sdk() ) {
			throw new RuntimeException( 'Google API client SDK is not installed.' );
		}
		$path = self::service_account_path();
		if ( $path === '' ) {
			throw new RuntimeException( 'Google service account key file not found.' );
		}
		// Tell the Google client where to load the service account.
		putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . $path );
		$_ENV['GOOGLE_APPLICATION_CREDENTIALS']    = $path;
		$_SERVER['GOOGLE_APPLICATION_CREDENTIALS'] = $path;

		$client = new Google_Client();
		// Reuse a trusted CA bundle if Guzzle is around (matches VinePoster).
		if ( class_exists( 'GuzzleHttp\\Client' ) ) {
			$ca = self::ca_bundle_path();
			if ( $ca !== '' ) {
				$client->setHttpClient( new GuzzleHttp\Client( array( 'verify' => $ca ) ) );
			}
		}
		$client->useApplicationDefaultCredentials();
		$client->setScopes( array( Google_Service_Drive::DRIVE ) );
		return new Google_Service_Drive( $client );
	}

	private static function ca_bundle_path() {
		$candidates = array(
			ini_get( 'curl.cainfo' ),
			ini_get( 'openssl.cafile' ),
			ABSPATH . WPINC . '/certificates/ca-bundle.crt',
		);
		foreach ( $candidates as $c ) {
			if ( is_string( $c ) && $c !== '' && file_exists( $c ) && is_readable( $c ) ) {
				return $c;
			}
		}
		return '';
	}

	private static function escape_q( $value ) {
		return str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $value );
	}

	private static function sanitize_folder_name( $name ) {
		$name = trim( (string) $name );
		if ( $name === '' ) {
			return 'unknown';
		}
		// Replace path separators / Drive-hostile chars but keep human-readable.
		$name = preg_replace( '#[\\\\/]+#', '-', $name );
		$name = preg_replace( '/\s+/', ' ', $name );
		return mb_substr( $name, 0, 120 );
	}

	/**
	 * Find a folder by name under a parent, or create it. Returns the folder ID.
	 */
	public static function get_or_create_folder( Google_Service_Drive $drive, $name, $parent_id ) {
		$name      = self::sanitize_folder_name( $name );
		$parent_id = (string) $parent_id;

		$query = sprintf(
			"name='%s' and mimeType='application/vnd.google-apps.folder' and trashed=false",
			self::escape_q( $name )
		);
		if ( $parent_id !== '' ) {
			$query .= sprintf( " and '%s' in parents", self::escape_q( $parent_id ) );
		}

		$list = $drive->files->listFiles( array(
			'q'                         => $query,
			'fields'                    => 'files(id,name)',
			'supportsAllDrives'         => true,
			'includeItemsFromAllDrives' => true,
			'spaces'                    => 'drive',
			'pageSize'                  => 1,
		) );

		$files = $list->getFiles();
		if ( ! empty( $files ) && ! empty( $files[0]->id ) ) {
			return (string) $files[0]->id;
		}

		$meta = new Google_Service_Drive_DriveFile( array(
			'name'     => $name,
			'mimeType' => 'application/vnd.google-apps.folder',
		) );
		if ( $parent_id !== '' ) {
			$meta->setParents( array( $parent_id ) );
		}
		$created = $drive->files->create( $meta, array(
			'fields'            => 'id',
			'supportsAllDrives' => true,
		) );
		if ( empty( $created->id ) ) {
			throw new RuntimeException( 'Could not create Google Drive folder: ' . $name );
		}
		return (string) $created->id;
	}

	/**
	 * Walk the hierarchy state/organization/user_id/folder_id under the configured
	 * root and return the deepest folder ID. Any segment may be empty — those are
	 * coerced to a placeholder so the walk never collapses.
	 */
	public static function resolve_user_folder( Google_Service_Drive $drive, array $path_parts ) {
		$settings = self::get_settings();
		$current  = (string) $settings['root_folder_id'];
		if ( $current === '' ) {
			throw new RuntimeException( 'Drive root folder ID is not configured.' );
		}
		foreach ( $path_parts as $segment ) {
			$current = self::get_or_create_folder( $drive, $segment, $current );
		}
		return $current;
	}

	/**
	 * Upload a file to Drive. Returns an array with id, name, mimeType,
	 * previewUrl, downloadUrl on success.
	 */
	public static function upload_file( Google_Service_Drive $drive, $parent_id, $tmp_path, $original_name, $mime ) {
		$name = sanitize_file_name( (string) $original_name );
		if ( $name === '' ) {
			$name = 'upload.dat';
		}
		$meta = new Google_Service_Drive_DriveFile( array(
			'name'    => $name,
			'parents' => array( (string) $parent_id ),
		) );

		$client = $drive->getClient();
		$client->setDefer( true );
		try {
			$request = $drive->files->create( $meta, array(
				'supportsAllDrives' => true,
				'fields'            => 'id,name,mimeType,webViewLink,webContentLink',
			) );

			$chunk_size = 5 * 1024 * 1024;
			$media      = new Google_Http_MediaFileUpload(
				$client,
				$request,
				$mime ?: 'application/octet-stream',
				null,
				true,
				$chunk_size
			);
			$file_size = (int) filesize( $tmp_path );
			$media->setFileSize( $file_size > 0 ? $file_size : 1 );

			$status = false;
			$handle = fopen( $tmp_path, 'rb' );
			if ( ! $handle ) {
				throw new RuntimeException( 'Could not read file for Drive upload.' );
			}
			try {
				while ( ! $status && ! feof( $handle ) ) {
					$status = $media->nextChunk( fread( $handle, $chunk_size ) );
				}
			} finally {
				fclose( $handle );
			}
		} finally {
			$client->setDefer( false );
		}

		if ( ! $status || empty( $status->id ) ) {
			throw new RuntimeException( 'Google Drive upload did not return a file ID.' );
		}

		$file_id  = (string) $status->id;
		$settings = self::get_settings();
		if ( ! empty( $settings['make_public'] ) ) {
			try {
				$permission = new Google_Service_Drive_Permission( array(
					'type' => 'anyone',
					'role' => 'reader',
				) );
				$drive->permissions->create( $file_id, $permission, array( 'supportsAllDrives' => true ) );
			} catch ( Throwable $e ) {
				error_log( '[Qualinav Data Hub] Could not make Drive file public: ' . $e->getMessage() );
			}
		}

		return array(
			'id'          => $file_id,
			'name'        => (string) ( $status->name ?? $name ),
			'mimeType'    => (string) ( $status->mimeType ?? $mime ),
			'previewUrl'  => 'https://drive.google.com/file/d/' . rawurlencode( $file_id ) . '/preview',
			'downloadUrl' => 'https://drive.google.com/uc?export=download&id=' . rawurlencode( $file_id ),
		);
	}

	/**
	 * Move a Drive file to the trash. Best-effort: returns true on success,
	 * false on any failure (logged). Safe to call when the file might already
	 * be gone — Drive returns 404 in that case and we just return false.
	 *
	 * Trash (not permanent delete) so admins can recover from an accidental UI
	 * delete via the Drive UI's Bin within 30 days.
	 */
	public static function trash_file( $drive_file_id ) {
		$drive_file_id = (string) $drive_file_id;
		if ( $drive_file_id === '' || ! self::is_enabled() ) {
			return false;
		}
		try {
			$drive = self::service();
			$drive->files->update(
				$drive_file_id,
				new Google_Service_Drive_DriveFile( array( 'trashed' => true ) ),
				array( 'supportsAllDrives' => true )
			);
			return true;
		} catch ( Throwable $e ) {
			error_log( '[Data Hub] Drive trash failed for ' . $drive_file_id . ': ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Fallback delete for records that never stored a drive_file_id (mirrored
	 * while Drive was off, or legacy uploads). Re-walks the same canonical
	 * hierarchy mirror_local_file() uses and trashes any non-folder child
	 * matching the file name. Best-effort: returns true if anything was
	 * trashed. Scoped to the user's own folder so it can't hit another org's
	 * same-named file.
	 */
	public static function trash_file_by_name( $user_id, $folder_id, $display_name, $measure = '' ) {
		if ( ! self::is_enabled() ) {
			return false;
		}
		$name = sanitize_file_name( (string) $display_name );
		if ( $name === '' ) {
			return false;
		}
		try {
			$drive        = self::service();
			$path_parts   = self::user_path_parts( (int) $user_id );
			$path_parts[] = (string) $folder_id;
			$measure      = trim( (string) $measure );
			if ( $measure !== '' ) {
				$path_parts[] = $measure;
			}
			$parent = self::resolve_user_folder( $drive, $path_parts );
			$query  = sprintf(
				"name='%s' and mimeType!='application/vnd.google-apps.folder' and trashed=false and '%s' in parents",
				self::escape_q( $name ),
				self::escape_q( $parent )
			);
			$list = $drive->files->listFiles( array(
				'q'                         => $query,
				'fields'                    => 'files(id,name)',
				'supportsAllDrives'         => true,
				'includeItemsFromAllDrives' => true,
				'spaces'                    => 'drive',
				'pageSize'                  => 25,
			) );
			$trashed_any = false;
			foreach ( (array) $list->getFiles() as $f ) {
				if ( ! empty( $f->id ) && self::trash_file( (string) $f->id ) ) {
					$trashed_any = true;
				}
			}
			return $trashed_any;
		} catch ( Throwable $e ) {
			error_log( '[Data Hub] Drive trash-by-name failed for "' . $name . '": ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Mirror a local file (already saved to disk) to Drive under the canonical
	 * hierarchy: state / organization / user_id / folder_id [ / measure ].
	 *
	 * The optional measure segment matches the Data Management UI nesting
	 * (Organization Data → Category → Measure). Caller passes it when the
	 * upload happened in a measure-specific context. Returns the drive_* keys
	 * to merge into a record; returns drive_error on failure.
	 */
	public static function mirror_local_file( $user_id, $folder_id, $local_path, $display_name, $mime = 'application/octet-stream', $measure = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}
		if ( ! is_string( $local_path ) || $local_path === '' || ! is_readable( $local_path ) ) {
			return array( 'drive_error' => 'Local file not readable for Drive mirror.' );
		}
		try {
			$drive       = self::service();
			$path_parts  = self::user_path_parts( (int) $user_id );
			$path_parts[] = (string) $folder_id;
			$measure = trim( (string) $measure );
			if ( $measure !== '' ) {
				$path_parts[] = $measure;
			}
			$parent      = self::resolve_user_folder( $drive, $path_parts );
			$meta        = self::upload_file( $drive, $parent, $local_path, (string) $display_name, (string) $mime );
			return array(
				'drive_file_id'      => $meta['id'],
				'drive_preview_url'  => $meta['previewUrl'],
				'drive_download_url' => $meta['downloadUrl'],
			);
		} catch ( Throwable $e ) {
			error_log( '[Data Hub] Drive mirror failed for user ' . (int) $user_id . ' folder ' . $folder_id . ' measure "' . $measure . '": ' . $e->getMessage() );
			return array( 'drive_error' => $e->getMessage() );
		}
	}

	/**
	 * Resolve the hierarchy path for a given user: [state, organization, user_id].
	 *
	 * Primary source is the proper relational schema:
	 *   wp_users.organization_id → wp_organizations(name, state_id)
	 *   wp_organizations.state_id → wp_states.code
	 *
	 * If the user has no organization_id, fall back to wp_users.state_id for the
	 * state, then to legacy user_meta keys, then to placeholders. The placeholders
	 * are only used when nothing usable is on file — so a fully-onboarded user
	 * always gets clean folder names.
	 */
	public static function user_path_parts( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array( 'unknown-state', 'unknown-org', 'anonymous' );
		}

		$users_table   = $wpdb->users;
		$orgs_table    = $wpdb->prefix . 'organizations';
		$states_table  = $wpdb->prefix . 'states';

		$user_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT organization_id, state_id FROM {$users_table} WHERE ID = %d LIMIT 1",
			$user_id
		), ARRAY_A );

		$state = '';
		$org   = '';

		$has_orgs_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orgs_table ) ) === $orgs_table;
		$org_id         = $user_row ? (int) ( $user_row['organization_id'] ?? 0 ) : 0;
		$user_state_id  = $user_row ? (int) ( $user_row['state_id'] ?? 0 ) : 0;
		$org_state_id   = 0;

		if ( $org_id > 0 && $has_orgs_table ) {
			$org_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT name, state_id FROM {$orgs_table} WHERE id = %d LIMIT 1",
				$org_id
			), ARRAY_A );
			if ( $org_row ) {
				$org          = trim( (string) ( $org_row['name'] ?? '' ) );
				$org_state_id = (int) ( $org_row['state_id'] ?? 0 );
			}
		}

		// Prefer the org's state (folders group naturally under the org's region).
		// Fall back to the user's personal state when the org doesn't have one.
		$resolved_state_id = $org_state_id > 0 ? $org_state_id : $user_state_id;
		if ( $resolved_state_id > 0 && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $states_table ) ) === $states_table ) {
			$state = (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT code FROM {$states_table} WHERE id = %d LIMIT 1",
				$resolved_state_id
			) );
		}

		// Legacy fallbacks for sites that pre-date the relational tables.
		if ( $state === '' ) {
			$state = trim( (string) get_user_meta( $user_id, 'state', true ) );
			if ( $state === '' ) {
				$state = trim( (string) get_user_meta( $user_id, 'states', true ) );
			}
		}
		if ( $org === '' ) {
			$org = trim( (string) get_user_meta( $user_id, 'organization', true ) );
		}

		if ( $state === '' ) {
			$state = 'unknown-state';
		}
		if ( $org === '' ) {
			$org = 'unknown-org';
		}

		$state = strtoupper( substr( $state, 0, 50 ) );

		return array( $state, $org, (string) $user_id );
	}
}

}
