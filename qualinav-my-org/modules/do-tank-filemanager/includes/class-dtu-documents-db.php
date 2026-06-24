<?php

defined('ABSPATH') || exit;

class DTU_Documents_DB
{
    private const DB_VERSION = '1.1.0';
    private const OPTION_DB_VERSION = 'dtu_org_documents_db_version';

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'qualinav_org_documents';
    }

    public static function ensure_schema(): void
    {
        global $wpdb;

        $table = self::table_name();
        $installedVersion = (string)get_option(self::OPTION_DB_VERSION, '');
        if ($installedVersion === self::DB_VERSION && !self::table_missing()) {
            return;
        }

        $charsetCollate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        self::drop_legacy_attachment_unique_index();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            organization_name VARCHAR(255) NOT NULL DEFAULT '',
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            hub_slug VARCHAR(100) NOT NULL DEFAULT '',
            user_slug VARCHAR(100) NOT NULL DEFAULT '',
            storage_provider VARCHAR(40) NOT NULL DEFAULT 'wordpress',
            storage_path TEXT NULL,
            file_name VARCHAR(255) NOT NULL DEFAULT '',
            file_type VARCHAR(20) NOT NULL DEFAULT '',
            mime_type VARCHAR(150) NOT NULL DEFAULT '',
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_at DATETIME NOT NULL,
            deleted_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY attachment_id (attachment_id),
            KEY organization_id (organization_id),
            KEY hub_slug (hub_slug),
            KEY uploaded_at (uploaded_at),
            KEY deleted_at (deleted_at)
        ) {$charsetCollate};";

        dbDelta($sql);

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
        self::backfill_legacy_attachments();
    }

    public static function insert_document(array $data): int
    {
        global $wpdb;

        $table = self::table_name();
        $now = current_time('mysql');

        $row = [
            'organization_id' => (int)($data['organization_id'] ?? 0),
            'organization_name' => (string)($data['organization_name'] ?? ''),
            'attachment_id' => (int)($data['attachment_id'] ?? 0),
            'hub_slug' => sanitize_title((string)($data['hub_slug'] ?? '')),
            'user_slug' => sanitize_title((string)($data['user_slug'] ?? '')),
            'storage_provider' => (string)($data['storage_provider'] ?? 'wordpress'),
            'storage_path' => (string)($data['storage_path'] ?? ''),
            'file_name' => sanitize_file_name((string)($data['file_name'] ?? '')),
            'file_type' => strtoupper((string)($data['file_type'] ?? '')),
            'mime_type' => (string)($data['mime_type'] ?? ''),
            'file_size' => (int)($data['file_size'] ?? 0),
            'uploaded_by_user_id' => (int)($data['uploaded_by_user_id'] ?? 0),
            'uploaded_at' => (string)($data['uploaded_at'] ?? $now),
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($row['attachment_id'] <= 0 || $row['organization_id'] <= 0) {
            if ($row['organization_id'] <= 0) {
                return 0;
            }
        }

        if ($row['attachment_id'] > 0) {
            $existingId = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE attachment_id = %d LIMIT 1",
                $row['attachment_id']
            ));

            if ($existingId > 0) {
                $update = $row;
                unset($update['created_at']);
                $wpdb->update($table, $update, ['id' => $existingId]);
                return $existingId;
            }
        }

        $wpdb->insert($table, $row);
        return (int)$wpdb->insert_id;
    }

    public static function upsert_attachment(array $data): void
    {
        self::insert_document($data);
    }

    public static function list_documents(string $hubSlug, int $organizationId): array
    {
        global $wpdb;

        $table = self::table_name();
        if ($organizationId <= 0 || self::table_missing()) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT *
               FROM {$table}
              WHERE organization_id = %d
                AND hub_slug = %s
                AND deleted_at IS NULL
              ORDER BY uploaded_at DESC, id DESC
              LIMIT 100",
            $organizationId,
            $hubSlug
        ), ARRAY_A) ?: [];
    }

    public static function get_document(int $documentId): ?array
    {
        global $wpdb;

        $table = self::table_name();
        if ($documentId <= 0 || self::table_missing()) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL LIMIT 1",
            $documentId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private static function backfill_legacy_attachments(): void
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 500,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_dtu_storage',
                    'value' => 'wordpress',
                ],
            ],
        ]);

        foreach ($attachments as $attachmentId) {
            $attachmentId = (int)$attachmentId;
            $path = (string)get_attached_file($attachmentId);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $orgId = (int)get_post_meta($attachmentId, '_dtu_organization_id', true);

            self::upsert_attachment([
                'organization_id' => $orgId,
                'organization_name' => (string)get_post_meta($attachmentId, '_dtu_organization_name', true),
                'attachment_id' => $attachmentId,
                'hub_slug' => (string)get_post_meta($attachmentId, '_dtu_hub', true),
                'user_slug' => (string)get_post_meta($attachmentId, '_dtu_user', true),
                'storage_provider' => 'wordpress',
                'storage_path' => $path,
                'file_name' => basename($path) ?: get_the_title($attachmentId),
                'file_type' => $ext === 'pdf' ? 'PDF' : ($ext === 'docx' ? 'DOCX' : strtoupper($ext)),
                'mime_type' => (string)get_post_mime_type($attachmentId),
                'file_size' => is_readable($path) ? (int)filesize($path) : 0,
                'uploaded_by_user_id' => (int)get_post_meta($attachmentId, '_dtu_uploaded_by_user_id', true) ?: (int)get_post_field('post_author', $attachmentId),
                'uploaded_at' => get_post_time('Y-m-d H:i:s', true, $attachmentId),
            ]);
        }
    }

    private static function table_missing(): bool
    {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table;
    }

    private static function drop_legacy_attachment_unique_index(): void
    {
        global $wpdb;

        $table = self::table_name();
        if (self::table_missing()) {
            return;
        }

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'attachment_id'", ARRAY_A);
        foreach ($indexes ?: [] as $index) {
            if ((int)($index['Non_unique'] ?? 1) === 0) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX attachment_id");
                break;
            }
        }
    }
}
