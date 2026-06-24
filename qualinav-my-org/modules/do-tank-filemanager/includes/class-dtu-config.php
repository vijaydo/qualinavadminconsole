<?php
// class-dtu-config.php

defined('ABSPATH') || exit;

class DTU_Config {

    // Key used in wp_options to store the folder ID
    const OPTION_ROOT_FOLDER = 'dtu_root_folder_id';

    /**
     * Get the Drive root folder ID for this site.
     */
    public static function get_root_folder(): ?string {
        $id = get_option(self::OPTION_ROOT_FOLDER, '');
        $id = is_string($id) ? trim($id) : '';

        return $id !== '' ? $id : null;
    }

    /**
     * Store root folder ID into wp_options
     */
    public static function set_root_folder(string $id): void {
        update_option(self::OPTION_ROOT_FOLDER, trim($id));
    }
}
