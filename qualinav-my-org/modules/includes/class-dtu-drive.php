<?php
//class-dtu-drive.php
defined('ABSPATH') || exit;

class DTU_Drive {

	/** @var Google_Service_Drive */
	private static $svc = null;

	/**
	 * Get authenticated Drive service (Service Account via GOOGLE_APPLICATION_CREDENTIALS)
	 * @throws Exception
	 */
	public static function service(): Google_Service_Drive {
		if (self::$svc) return self::$svc;

		// Check for credentials env var
		if (empty(getenv('GOOGLE_APPLICATION_CREDENTIALS'))) {
			throw new Exception('GOOGLE_APPLICATION_CREDENTIALS environment variable not set.');
		}

		try {
			$client = new Google_Client();
			$client->useApplicationDefaultCredentials();
			$client->setScopes([ Google_Service_Drive::DRIVE_FILE ]);
			self::$svc = new Google_Service_Drive($client);
			return self::$svc;
		} catch (Exception $e) {
			error_log('[DTU] Google API Client Auth Error: ' . $e->getMessage());
			throw new Exception('Failed to authenticate with Google Drive API. Check server logs and GOOGLE_APPLICATION_CREDENTIALS.', 0, $e);
		}
	}

	/**
	 * Find (or create) a folder by name, optionally under a parent
	 * Returns folder ID
	 */
	public static function get_or_create_folder(string $name, ?string $parentId = null): string {
		$drive = self::service();

		// Search query
		$q = sprintf("name='%s' and mimeType='application/vnd.google-apps.folder' and trashed=false", addslashes($name));
		if ($parentId) $q .= sprintf(" and '%s' in parents", $parentId);

		$list = $drive->files->listFiles([
			'q' => $q,
			'fields' => 'files(id,name)',
			'supportsAllDrives' => true,
			'includeItemsFromAllDrives' => true,
			'spaces' => 'drive',
			'pageSize' => 1,
		]);

		if (!empty($list->files) && isset($list->files[0]->id)) {
			return $list->files[0]->id;
		}

		$meta = new Google_Service_Drive_DriveFile([
			'name' => $name,
			'mimeType' => 'application/vnd.google-apps.folder',
		]);
		if ($parentId) $meta->setParents([$parentId]);

		$new = $drive->files->create($meta, [
			'fields' => 'id',
			'supportsAllDrives' => true,
		]);
		return $new->id;
	}

	/**
	 * Find a folder by name, optionally under a parent
	 * Returns folder ID or null
	 */
	public static function find_folder_id(string $name, ?string $parentId = null): ?string {
		$drive = self::service();

		// Search query
		$q = sprintf("name='%s' and mimeType='application/vnd.google-apps.folder' and trashed=false", addslashes($name));
		if ($parentId) $q .= sprintf(" and '%s' in parents", $parentId);

		$list = $drive->files->listFiles([
			'q' => $q,
			'fields' => 'files(id)',
			'supportsAllDrives' => true,
			'includeItemsFromAllDrives' => true,
			'spaces' => 'drive',
			'pageSize' => 1,
		]);

		if (!empty($list->files) && isset($list->files[0]->id)) {
			return $list->files[0]->id;
		}

		return null;
	}

	/**
	 * List all files for a user across all their sub-folders (Images, Videos, etc.).
	 * Returns an array of file data.
	 */
	public static function list_all_user_files(string $userFolderId): array {
		$drive = self::service();
		$allFiles = [];
		
		// First, find all sub-folders (Images, Videos, etc.)
		$subFolders = [];
		$pageToken = null;
		do {
			$response = $drive->files->listFiles([
				'q' => sprintf("'%s' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false", $userFolderId),
				'fields' => 'nextPageToken, files(id, name)',
				'supportsAllDrives' => true,
				'includeItemsFromAllDrives' => true,
				'spaces' => 'drive',
				'pageSize' => 100,
				'pageToken' => $pageToken,
			]);
			foreach ($response->getFiles() as $folder) {
				$subFolders[] = $folder;
			}
			$pageToken = $response->getNextPageToken();
		} while ($pageToken);

		// Now, get files from each sub-folder
		foreach ($subFolders as $folder) {
			$filesPageToken = null;
			do {
				$fileResponse = $drive->files->listFiles([
					'q' => sprintf("'%s' in parents and trashed=false", $folder->id),
					'fields' => 'nextPageToken, files(id, name, createdTime, size, iconLink, webViewLink)',
					'supportsAllDrives' => true,
					'includeItemsFromAllDrives' => true,
					'spaces' => 'drive',
					'pageSize' => 100,
					'pageToken' => $filesPageToken,
				]);

				foreach ($fileResponse->getFiles() as $file) {
					$allFiles[] = [
						'id'      => $file->id,
						'name'    => $file->name,
						'created' => $file->createdTime,
						'size'    => (int) $file->size,
						'icon'    => $file->iconLink,
						'url'     => $file->webViewLink,
						'type'    => $folder->name, // Add the type based on the parent folder
					];
				}
				$filesPageToken = $fileResponse->getNextPageToken();
			} while ($filesPageToken);
		}

		// Sort files by creation date, newest first
		usort($allFiles, function($a, $b) {
			return strtotime($b['created']) - strtotime($a['created']);
		});

		return $allFiles;
	}


	/**
	 * Upload a file (resumable) to a parent folder ID
	 * Returns array with id, name, previewUrl, downloadUrl
	 * @throws Exception
	 */
	public static function upload_file(string $parentId, string $tmpPath, string $origName, string $mime, bool $makePublic = true): array {
		$drive = self::service();

		$fileMeta = new Google_Service_Drive_DriveFile([
			'name'    => $origName,
			'parents' => [$parentId],
		]);

		$client = $drive->getClient();
		$client->setDefer(true);

		$request = $drive->files->create($fileMeta, [
			'supportsAllDrives' => true,
			'fields' => 'id,name,mimeType,webViewLink,webContentLink',
		]);

		$chunkSize = 5 * 1024 * 1024; // 5MB
		$media = new Google_Http_MediaFileUpload(
			$client,
			$request,
			$mime ?: 'application/octet-stream',
			null,
			true,
			$chunkSize
		);
		$media->setFileSize(filesize($tmpPath));

		$status = false;
		$h = fopen($tmpPath, 'rb');
		if (!$h) {
			throw new RuntimeException("Failed to open temp file: {$tmpPath}");
		}

		try {
			while (!$status && !feof($h)) {
				$chunk = fread($h, $chunkSize);
				$status = $media->nextChunk($chunk);
			}
		} finally {
			fclose($h);
			$client->setDefer(false);
		}

		if (!$status || empty($status->id)) {
			// This part is critical. If the upload fails, the $status will be `false`.
			// We need to provide a better error message. The Google API client doesn't throw exceptions here,
			// it just returns false. The actual error is often on the request object, but that's hard to get.
			// The best we can do is log what we have and give a clearer message.
			error_log('[DTU] Drive upload failed. Status was not a valid file object. This often means an API error occurred during the upload chunks.');
			throw new RuntimeException('Drive upload failed. The API did not return a file ID after the upload completed.');
		}

		$fileId = $status->id;

		if ($makePublic) {
			try {
				$perm = new Google_Service_Drive_Permission([
					'type' => 'anyone',
					'role' => 'reader',
				]);
				$drive->permissions->create($fileId, $perm, ['supportsAllDrives' => true]);
			} catch (Google_Service_Exception $e) {
				// Log non-fatal permission errors, but don't kill the whole process.
				error_log("[DTU] Could not set public permission for file {$fileId}. Message: " . $e->getMessage());
			}
		}

		$preview  = "https://drive.google.com/file/d/{$fileId}/preview";
		$download = "https://drive.google.com/uc?export=download&id={$fileId}";

		return [
			'id'           => $fileId,
			'name'         => $status->name,
			'mimeType'     => $status->mimeType,
			'previewUrl'   => $preview,
			'downloadUrl'  => $download,
		];
	}
}