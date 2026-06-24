<?php
//class-dtu-endpoints.php

defined('ABSPATH') || exit;

class DTU_Endpoints
{
    private const MAX_FILES_PER_UPLOAD = 10;
    private const ALLOWED_MIMES = [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct()
    {
        add_action('wp_ajax_dtu_upload', [$this, 'handle_upload']);
        add_action('wp_ajax_dtu_list_files', [$this, 'handle_list_files']);
        add_action('admin_post_dtu_download_file', [$this, 'handle_download_file']);
        add_action('admin_post_dtu_download_document', [$this, 'handle_download_document']);
        add_action('admin_post_dtu_view_document', [$this, 'handle_view_document']);
    }

    /**
     * List files for the current user
     */
    public function handle_list_files()
    {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Permission denied.'], 403);
            }

            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'dtu_list_files')) {
                wp_send_json_error(['message' => 'Invalid request'], 403);
            }

            $this->ensure_documents_schema();

            $hub = sanitize_text_field($_POST['hub'] ?? '');
            $user = sanitize_text_field($_POST['user'] ?? '');
            if (!$hub || !$user) {
                wp_send_json_error(['message' => 'Hub or User missing'], 400);
            }

            $hubSlug = sanitize_title($hub);
            $userSlug = sanitize_title($user);
            $orgContext = $this->current_org_context(get_current_user_id());

            if ($orgContext['organization_id'] <= 0) {
                wp_send_json_success(['files' => []]);
            }

            $files = $this->list_wordpress_files($hubSlug, $orgContext['organization_id']);

            wp_send_json_success(['files' => $files]);

        } catch (Throwable $e) {
            error_log('[DTU] List error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle WordPress uploads.
     */
    public function handle_upload()
    {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Login required.'], 403);
            }

            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'dtu_upload')) {
                wp_send_json_error(['message' => 'Invalid request'], 403);
            }

            $this->ensure_documents_schema();

            $hub = sanitize_text_field($_POST['hub'] ?? '');
            $user = sanitize_text_field($_POST['user'] ?? '');
            if (!$hub || !$user) {
                wp_send_json_error(['message' => 'Hub or User missing'], 400);
            }

            if (empty($_FILES['file'])) {
                wp_send_json_error(['message' => 'No files received'], 400);
            }

            $files = $this->normalize_files($_FILES['file']);
            if (empty($files)) {
                wp_send_json_error(['message' => 'No valid files after normalization'], 400);
            }
            if (count($files) > self::MAX_FILES_PER_UPLOAD) {
                wp_send_json_error(['message' => 'You can upload a maximum of 10 files at once.'], 400);
            }

            $hubSlug = sanitize_title($hub);
            $userSlug = sanitize_title($user);
            $currentUserId = get_current_user_id();
            $orgContext = $this->current_org_context($currentUserId);
            if ($orgContext['organization_id'] <= 0) {
                wp_send_json_error(['message' => 'Your user account is not assigned to an organization.'], 403);
            }

            $results = [];

            foreach ($files as $i => $f) {
                $name = sanitize_file_name($f['name'] ?? ('unnamed-' . $i));

                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $results[] = ['name' => $name, 'ok' => false, 'error' => 'Upload error'];
                    continue;
                }

                $tmp = $f['tmp_name'] ?? '';
                if (!$tmp || !is_uploaded_file($tmp)) {
                    $results[] = ['name' => $name, 'ok' => false, 'error' => 'Temp file invalid'];
                    continue;
                }

                $filetype = wp_check_filetype_and_ext($tmp, $name, self::ALLOWED_MIMES);
                $ext = strtolower((string)($filetype['ext'] ?? ''));
                $mime = (string)($filetype['type'] ?? '');
                if (!isset(self::ALLOWED_MIMES[$ext]) || self::ALLOWED_MIMES[$ext] !== $mime) {
                    $results[] = ['name' => $name, 'ok' => false, 'error' => 'Only PDF and DOCX files are accepted.'];
                    continue;
                }

                try {
                    $document = $this->save_document_file($f, $name, $mime, $hubSlug, $userSlug, $orgContext, $currentUserId);
                    $results[] = ['name' => $name, 'ok' => true, 'file' => $document];

                } catch (Throwable $ex) {
                    $results[] = ['name' => $name, 'ok' => false, 'error' => $ex->getMessage()];
                }
            }

            wp_send_json_success([
                'hub' => $hubSlug,
                'user' => $userSlug,
                'results' => $results,
            ]);

        } catch (Throwable $e) {
            error_log('[DTU] Upload fatal: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handle_download_file()
    {
        if (!is_user_logged_in()) {
            wp_die('Permission denied.', '', ['response' => 403]);
        }

        $attachmentId = absint($_GET['file_id'] ?? 0);
        if (!$attachmentId) {
            wp_die('Missing file.', '', ['response' => 400]);
        }

        check_admin_referer('dtu_download_file_' . $attachmentId);

        $orgContext = $this->current_org_context(get_current_user_id());
        $fileOrgId = (int)get_post_meta($attachmentId, '_dtu_organization_id', true);
        $storage = (string)get_post_meta($attachmentId, '_dtu_storage', true);

        if ($orgContext['organization_id'] <= 0 || $fileOrgId !== (int)$orgContext['organization_id'] || $storage !== 'wordpress') {
            wp_die('Permission denied.', '', ['response' => 403]);
        }

        $path = (string)get_attached_file($attachmentId);
        if (!$path || !is_readable($path)) {
            wp_die('File not found.', '', ['response' => 404]);
        }

        $mime = (string)get_post_mime_type($attachmentId);
        $filename = basename($path);

        while (ob_get_level()) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        exit;
    }

    public function handle_download_document()
    {
        $this->serve_document_response(true);
    }

    public function handle_view_document()
    {
        $this->serve_document_response(false);
    }

    private function normalize_files(array $f): array
    {
        $out = [];
        if (is_array($f['name'])) {
            $count = count($f['name']);
            for ($i = 0; $i < $count; $i++) {
                if (empty($f['name'][$i]))
                    continue;
                $out[] = [
                    'name' => $f['name'][$i],
                    'type' => $f['type'][$i] ?? '',
                    'tmp_name' => $f['tmp_name'][$i] ?? '',
                    'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $f['size'][$i] ?? 0,
                ];
            }
        } else {
            if (!empty($f['name']))
                $out[] = $f;
        }
        return $out;
    }

    private function save_document_file(array $file, string $name, string $mime, string $hubSlug, string $userSlug, array $orgContext, int $userId): array
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => self::ALLOWED_MIMES,
        ]);

        if (!is_array($upload) || !empty($upload['error'])) {
            throw new RuntimeException((string)($upload['error'] ?? 'WordPress upload failed.'));
        }

        if (!class_exists('DTU_Documents_DB')) {
            throw new RuntimeException('Document database is unavailable.');
        }

        $path = (string)$upload['file'];
        $fileName = basename($path);
        $fileType = strtoupper(pathinfo($path, PATHINFO_EXTENSION));
        $fileSize = is_readable($path) ? (int)filesize($path) : 0;
        $uploadedAt = current_time('mysql');

        $documentId = DTU_Documents_DB::insert_document([
            'organization_id' => (int)$orgContext['organization_id'],
            'organization_name' => (string)$orgContext['org_name'],
            'attachment_id' => 0,
            'hub_slug' => $hubSlug,
            'user_slug' => $userSlug,
            'storage_provider' => 'wordpress',
            'storage_path' => $path,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'mime_type' => $mime,
            'file_size' => $fileSize,
            'uploaded_by_user_id' => $userId,
            'uploaded_at' => $uploadedAt,
        ]);

        if ($documentId <= 0) {
            throw new RuntimeException('Could not write document record.');
        }

        return [
            'id' => 0,
            'document_id' => $documentId,
            'name' => $fileName,
            'url' => wp_nonce_url(admin_url('admin-post.php?action=dtu_view_document&document_id=' . $documentId), 'dtu_view_document_' . $documentId),
            'mimeType' => $mime,
        ];
    }

    private function list_wordpress_files(string $hubSlug, int $organizationId): array
    {
        if (class_exists('DTU_Documents_DB')) {
            $documents = DTU_Documents_DB::list_documents($hubSlug, $organizationId);
            if (!empty($documents)) {
                return array_values(array_filter(array_map(function ($document) {
                    return $this->document_row_to_response($document);
                }, $documents)));
            }
        }

        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_dtu_storage',
                    'value' => 'wordpress',
                ],
                [
                    'key' => '_dtu_hub',
                    'value' => $hubSlug,
                ],
                [
                    'key' => '_dtu_organization_id',
                    'value' => $organizationId,
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        return array_map(function ($attachment) {
            $id = (int)$attachment->ID;
            $mime = (string)get_post_mime_type($id);
            $path = (string)get_attached_file($id);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $uploadedByUserId = (int)get_post_meta($id, '_dtu_uploaded_by_user_id', true);
            if ($uploadedByUserId <= 0) {
                $uploadedByUserId = (int)$attachment->post_author;
            }
            $uploadedBy = $this->uploaded_by_name($uploadedByUserId);

            return [
                'id' => $id,
                'name' => basename($path) ?: get_the_title($id),
                'created' => get_post_time('c', true, $id),
                'uploaded_by' => $uploadedBy,
                'size' => is_readable($path) ? (int)filesize($path) : 0,
                'icon' => wp_mime_type_icon($id),
                'url' => wp_get_attachment_url($id),
                'view_url' => wp_get_attachment_url($id),
                'download_url' => wp_nonce_url(admin_url('admin-post.php?action=dtu_download_file&file_id=' . $id), 'dtu_download_file_' . $id),
                'type' => $ext === 'pdf' ? 'PDF' : ($ext === 'docx' ? 'DOCX' : $mime),
            ];
        }, $attachments);
    }

    private function document_row_to_response(array $document): ?array
    {
        $attachmentId = (int)($document['attachment_id'] ?? 0);
        $documentId = (int)($document['id'] ?? 0);
        $path = $attachmentId > 0 ? (string)get_attached_file($attachmentId) : (string)($document['storage_path'] ?? '');
        $fileName = (string)($document['file_name'] ?? '');
        if ($fileName === '') {
            $fileName = basename($path) ?: get_the_title($attachmentId);
        }
        $url = $attachmentId > 0 ? wp_get_attachment_url($attachmentId) : '';
        if (!$url && $documentId > 0) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=dtu_view_document&document_id=' . $documentId), 'dtu_view_document_' . $documentId);
        }

        if (!$url) {
            return null;
        }

        return [
            'id' => $attachmentId,
            'document_id' => $documentId,
            'name' => $fileName,
            'created' => mysql2date('c', (string)($document['uploaded_at'] ?? ''), false),
            'uploaded_by' => $this->uploaded_by_name((int)($document['uploaded_by_user_id'] ?? 0)),
            'size' => (int)($document['file_size'] ?? 0),
            'icon' => $attachmentId > 0 ? wp_mime_type_icon($attachmentId) : '',
            'url' => $url,
            'view_url' => $url,
            'download_url' => $attachmentId > 0
                ? wp_nonce_url(admin_url('admin-post.php?action=dtu_download_file&file_id=' . $attachmentId), 'dtu_download_file_' . $attachmentId)
                : wp_nonce_url(admin_url('admin-post.php?action=dtu_download_document&document_id=' . $documentId), 'dtu_download_document_' . $documentId),
            'type' => (string)($document['file_type'] ?? ''),
        ];
    }

    private function serve_document_response(bool $download): void
    {
        if (!is_user_logged_in()) {
            wp_die('Permission denied.', '', ['response' => 403]);
        }

        $this->ensure_documents_schema();

        $documentId = absint($_GET['document_id'] ?? 0);
        if (!$documentId || !class_exists('DTU_Documents_DB')) {
            wp_die('Missing file.', '', ['response' => 400]);
        }

        check_admin_referer(($download ? 'dtu_download_document_' : 'dtu_view_document_') . $documentId);

        $document = DTU_Documents_DB::get_document($documentId);
        if (!$document) {
            wp_die('File not found.', '', ['response' => 404]);
        }

        $orgContext = $this->current_org_context(get_current_user_id());
        if ($orgContext['organization_id'] <= 0 || (int)$document['organization_id'] !== (int)$orgContext['organization_id']) {
            wp_die('Permission denied.', '', ['response' => 403]);
        }

        $path = (string)($document['storage_path'] ?? '');
        if (!$path || !is_readable($path)) {
            wp_die('File not found.', '', ['response' => 404]);
        }

        $mime = (string)($document['mime_type'] ?? '');
        $filename = (string)($document['file_name'] ?? basename($path));

        while (ob_get_level()) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        exit;
    }

    private function uploaded_by_name(int $userId): string
    {
        if ($userId <= 0) {
            return 'Unknown';
        }

        $user = get_userdata($userId);
        if (!$user) {
            return 'Unknown';
        }

        $name = trim((string)$user->display_name);
        if ($name === '') {
            $name = trim((string)$user->user_login);
        }

        return $name !== '' ? $name : 'Unknown';
    }

    private function current_org_context(int $userId): array
    {
        $context = [
            'organization_id' => 0,
            'org_name' => '',
        ];

        if ($userId <= 0) {
            return $context;
        }

        if (function_exists('qualinav_data_hub_get_org_context')) {
            $hubContext = qualinav_data_hub_get_org_context($userId);
            if (is_array($hubContext)) {
                $context['organization_id'] = (int)($hubContext['organization_id'] ?? 0);
                $context['org_name'] = trim((string)($hubContext['org_name'] ?? ''));
            }
        }

        if ($context['organization_id'] <= 0) {
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT u.organization_id, o.name AS org_name
                   FROM {$wpdb->users} u
                   LEFT JOIN {$wpdb->prefix}organizations o ON o.id = u.organization_id
                  WHERE u.ID = %d
                  LIMIT 1",
                $userId
            ), ARRAY_A);

            if (is_array($row)) {
                $context['organization_id'] = (int)($row['organization_id'] ?? 0);
                $context['org_name'] = trim((string)($row['org_name'] ?? ''));
            }
        }

        return $context;
    }

    private function ensure_documents_schema(): void
    {
        if (class_exists('DTU_Documents_DB')) {
            DTU_Documents_DB::ensure_schema();
        }
    }
}

add_action("wp_ajax_dtu_dashboard_analytics", "dtu_dashboard_analytics_handler");

function dtu_dashboard_analytics_handler()
{
    check_ajax_referer("dtu_dashboard_nonce");

    $root = DTU_Config::get_root_folder();
    if (!$root) {
        wp_send_json_success([
            "folder_html" => "<span style='color:red;'>No root folder configured</span>",
            "sa_html" => "<span style='color:red;'>N/A</span>",
            "total_files" => 0,
            "files_this_week" => 0,
            "storage_used" => "0 B",
            "recent" => []
        ]);
    }

    try {
        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->setScopes([Google_Service_Drive::DRIVE]);
        $svc = new Google_Service_Drive($client);
    } catch (Throwable $e) {
        wp_send_json_success([
            "folder_html" => "<span style='color:red;'>Error loading SA</span>",
            "sa_html" => "<span style='color:red;'>Connection failed</span>",
            "total_files" => 0,
            "files_this_week" => 0,
            "storage_used" => "0 B",
            "recent" => []
        ]);
    }

    // ------------------------------
    // FOLDER STATUS
    // ------------------------------
    $folder_html = "";
    try {
        $item = $svc->files->get($root, [
            "fields" => "id,name",
            "supportsAllDrives" => true
        ]);
        $folder_html = "<span style='color:green;font-weight:bold;'>✔ Accessible</span><br>{$item->name}";
    } catch (Throwable $e) {
        $folder_html = "<span style='color:red;font-weight:bold;'>✖ Error</span><br>" . esc_html($e->getMessage());
    }

    $sa_html = "<span style='color:green;font-weight:bold;'>✔ Connected</span>";

    // ------------------------------
    // FILE SCAN (same hierarchy)
    // ------------------------------
    $all_files = [];
    $total_size = 0;
    $one_week_ago = strtotime("-7 days");

    try {
        // SITE folders
        $sites = $svc->files->listFiles([
            "q" => "'$root' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            "fields" => "files(id,name)",
            "supportsAllDrives" => true,
            "includeItemsFromAllDrives" => true
        ])->files;

        foreach ($sites as $site) {
            // USER folders
            $users = $svc->files->listFiles([
                "q" => "'{$site->id}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                "fields" => "files(id,name)",
                "supportsAllDrives" => true,
                "includeItemsFromAllDrives" => true
            ])->files;

            foreach ($users as $user) {
                // TYPE folders
                $types = $svc->files->listFiles([
                    "q" => "'{$user->id}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                    "fields" => "files(id,name)",
                    "supportsAllDrives" => true,
                    "includeItemsFromAllDrives" => true
                ])->files;

                foreach ($types as $type) {
                    $files = $svc->files->listFiles([
                        "q" => "'{$type->id}' in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'",
                        "fields" => "files(id,name,size,createdTime)",
                        "supportsAllDrives" => true,
                        "includeItemsFromAllDrives" => true
                    ])->files;

                    foreach ($files as $f) {
                        $all_files[] = [
                            "name" => $f->name,
                            "user" => $user->name,
                            "site" => $site->name,
                            "size" => (int) $f->size,
                            "date" => date("d M Y H:i", strtotime($f->createdTime)),
                            "created" => strtotime($f->createdTime)
                        ];
                    }
                }
            }
        }

    } catch (Throwable $e) {
        // silent
    }

    // ------------------------------
    // ANALYTICS
    // ------------------------------
    $files_this_week = 0;
    foreach ($all_files as $f) {
        if ($f["size"] > 0) {
            $total_size += $f["size"];
        }
        if ($f["created"] >= $one_week_ago) {
            $files_this_week++;
        }
    }

    usort($all_files, fn($a, $b) => $b["created"] - $a["created"]);
    $recent = array_slice($all_files, 0, 5);

    wp_send_json_success([
        "folder_html" => $folder_html,
        "sa_html" => $sa_html,
        "total_files" => count($all_files),
        "files_this_week" => $files_this_week,
        "storage_used" => size_format($total_size, 2),
        "recent" => $recent
    ]);
}
