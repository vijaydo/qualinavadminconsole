<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Branding
{
    public static function get_brand_for_organization($organization_id)
    {
        global $wpdb;

        $organization_id = absint($organization_id);
        $table = QN_DB::brand_settings_table();
        if (!$organization_id || !QN_DB::table_exists($table)) {
            return self::get_default_brand();
        }

        if (self::is_key_value_table($table)) {
            return self::get_default_brand();
        }

        $row = null;
        if (QN_DB::column_exists($table, 'organization_id')) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE organization_id = %d ORDER BY id DESC LIMIT 1", $organization_id)
            );
        }

        if (!$row) {
            $hospital = QN_Organizations::get_hospital($organization_id);
            if ($hospital && !empty($hospital['brandsetting_id']) && QN_DB::column_exists($table, 'id')) {
                $row = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint($hospital['brandsetting_id']))
                );
            }
        }

        return $row ? self::normalize_brand_row($row) : self::get_default_brand();
    }

    public static function get_default_brand()
    {
        $brand = self::base_default_brand();
        $table = QN_DB::brand_settings_table();

        if (QN_DB::table_exists($table) && self::is_key_value_table($table)) {
            $brand = array_merge($brand, self::get_key_value_brand($table));
        }

        return $brand;
    }

    private static function base_default_brand()
    {
        return array(
            'id' => null,
            'organization_id' => null,
            'primary_color' => '#003B5C',
            'secondary_color' => '#007C89',
            'accent_color' => '#14B8A6',
            'sidebar_color' => '#072B49',
            'background_color' => '#F7FAFC',
            'card_color' => '#FFFFFF',
            'text_color' => '#102A43',
            'logo_url' => '',
            'favicon_url' => '',
            'font_family' => '',
            'button_radius' => '12px',
            'card_radius' => '18px',
        );
    }

    public static function output_css_variables($organization_id = null)
    {
        $brand = $organization_id ? self::get_brand_for_organization($organization_id) : self::get_default_brand();
        $css = self::css_variables($brand);

        echo '<style id="qualinav-brand-vars">:root{' . esc_html($css) . '}</style>';
    }

    public static function css_variables($brand)
    {
        $brand = wp_parse_args($brand, self::get_default_brand());
        $primary = sanitize_hex_color($brand['primary_color']) ?: '#003B5C';
        $secondary = sanitize_hex_color($brand['secondary_color']) ?: '#007C89';
        $accent = sanitize_hex_color($brand['accent_color']) ?: '#14B8A6';
        $card = sanitize_hex_color($brand['card_color']) ?: '#FFFFFF';
        $text = sanitize_hex_color($brand['text_color']) ?: '#102A43';

        return sprintf(
            '--qn-primary:%1$s;--qn-secondary:%2$s;--qn-accent:%3$s;--qn-sidebar:%4$s;--qn-bg:%5$s;--qn-card:%6$s;--qn-text:%7$s;--qn-radius-button:%8$s;--qn-radius-card:%9$s;--qn-on-primary:%10$s;--qn-on-secondary:%11$s;--qn-on-accent:%12$s;--qn-readable-primary:%13$s;--qn-readable-secondary:%14$s;--qn-readable-accent:%15$s;',
            esc_html($primary),
            esc_html($secondary),
            esc_html($accent),
            esc_html($brand['sidebar_color']),
            esc_html($brand['background_color']),
            esc_html($card),
            esc_html($text),
            esc_html($brand['button_radius']),
            esc_html($brand['card_radius']),
            esc_html(self::contrast_text($primary)),
            esc_html(self::contrast_text($secondary)),
            esc_html(self::contrast_text($accent)),
            esc_html(self::readable_on_background($primary, $card, $text)),
            esc_html(self::readable_on_background($secondary, $card, $text)),
            esc_html(self::readable_on_background($accent, $card, $text))
        );
    }

    private static function readable_on_background($foreground, $background, $fallback)
    {
        return self::contrast_ratio($foreground, $background) >= 4.5 ? $foreground : $fallback;
    }

    private static function contrast_text($background)
    {
        return self::contrast_ratio('#000000', $background) >= self::contrast_ratio('#FFFFFF', $background)
            ? '#111827'
            : '#FFFFFF';
    }

    private static function contrast_ratio($one, $two)
    {
        $l1 = self::relative_luminance($one);
        $l2 = self::relative_luminance($two);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function relative_luminance($hex)
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000000';
        }

        $channels = array(
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        );

        foreach ($channels as $index => $channel) {
            $channels[$index] = $channel <= 0.03928
                ? $channel / 12.92
                : pow(($channel + 0.055) / 1.055, 2.4);
        }

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    public static function sanitize_brand_data($data)
    {
        $sanitized = array();
        $color_fields = array('primary_color', 'secondary_color', 'accent_color', 'sidebar_color', 'background_color', 'card_color', 'text_color');

        foreach ($color_fields as $field) {
            if (isset($data[$field])) {
                $color = sanitize_hex_color($data[$field]);
                if ($color) {
                    $sanitized[$field] = $color;
                }
            }
        }

        foreach (array('logo_url', 'favicon_url') as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = esc_url_raw($data[$field]);
            }
        }

        if (isset($data['font_family'])) {
            $sanitized['font_family'] = sanitize_text_field($data['font_family']);
        }

        foreach (array('button_radius', 'card_radius') as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = preg_replace('/[^0-9a-z.% -]/i', '', sanitize_text_field($data[$field]));
            }
        }

        return $sanitized;
    }

    public static function update_brand_for_organization($organization_id, $data)
    {
        global $wpdb;

        $organization_id = absint($organization_id);
        $table = QN_DB::brand_settings_table();
        if (!$organization_id || !QN_DB::table_exists($table)) {
            return new WP_Error('qn_missing_brand_table', __('The brand settings table does not exist.', 'qualinav-admin-console'), array('status' => 500));
        }

        $before = self::get_brand_for_organization($organization_id);
        $sanitized = self::sanitize_brand_data($data);

        if (self::is_key_value_table($table)) {
            if (empty($sanitized)) {
                return new WP_Error('qn_no_valid_brand_fields', __('No supported brand fields were provided.', 'qualinav-admin-console'), array('status' => 400));
            }

            foreach ($sanitized as $key => $value) {
                $existing = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT setting_key FROM {$table} WHERE setting_key = %s AND plugin_scope = %s LIMIT 1",
                        $key,
                        'shared'
                    )
                );

                if ($existing) {
                    $wpdb->update(
                        $table,
                        array('setting_value' => $value),
                        array('setting_key' => $key, 'plugin_scope' => 'shared')
                    );
                } else {
                    $wpdb->insert(
                        $table,
                        array(
                            'setting_key' => $key,
                            'setting_value' => $value,
                            'plugin_scope' => 'shared',
                        )
                    );
                }
            }

            $after = self::get_brand_for_organization($organization_id);
            QN_Audit_Log::log('brand_updated', 'brandsetting', null, $before, $after, $organization_id);

            return $after;
        }

        $write_data = QN_DB::filter_existing_columns($table, $sanitized);

        if (QN_DB::column_exists($table, 'organization_id')) {
            $write_data['organization_id'] = $organization_id;
        }

        if (empty($write_data)) {
            return new WP_Error('qn_no_valid_brand_fields', __('No supported brand fields were provided.', 'qualinav-admin-console'), array('status' => 400));
        }

        $existing_id = !empty($before['id']) ? absint($before['id']) : 0;
        if ($existing_id && QN_DB::column_exists($table, 'id')) {
            $wpdb->update($table, $write_data, array('id' => $existing_id));
        } else {
            $wpdb->insert($table, $write_data);
            $existing_id = $wpdb->insert_id;
        }

        $after = self::get_brand_for_organization($organization_id);
        QN_Audit_Log::log('brand_updated', 'brandsetting', $existing_id, $before, $after, $organization_id);

        return $after;
    }

    private static function normalize_brand_row($row)
    {
        $brand = self::base_default_brand();
        $fields = array_keys($brand);

        foreach ($fields as $field) {
            if (isset($row->{$field}) && $row->{$field} !== null && $row->{$field} !== '') {
                $brand[$field] = $row->{$field};
            }
        }

        if (isset($row->id)) {
            $brand['id'] = absint($row->id);
        }

        if (isset($row->organization_id)) {
            $brand['organization_id'] = absint($row->organization_id);
        }

        return $brand;
    }

    private static function is_key_value_table($table)
    {
        return QN_DB::column_exists($table, 'setting_key')
            && QN_DB::column_exists($table, 'setting_value')
            && QN_DB::column_exists($table, 'plugin_scope');
    }

    private static function get_key_value_brand($table)
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT setting_key, setting_value FROM {$table} WHERE plugin_scope = %s",
                'shared'
            )
        );

        $raw = array();
        foreach ($rows as $row) {
            $raw[$row->setting_key] = $row->setting_value;
        }

        $brand = array();
        $map = array(
            'primary_color' => 'primary_color',
            'secondary_color' => 'secondary_color',
            'accent_color' => 'accent_color',
            'sidebar_color' => 'sidebar_color',
            'background_color' => 'background_color',
            'card_color' => 'card_color',
            'text_color' => 'text_color',
            'logo_url' => 'logo_url',
            'favicon_url' => 'favicon_url',
            'font_family' => 'font_family',
            'button_radius' => 'button_radius',
            'card_radius' => 'card_radius',
        );

        foreach ($map as $brand_key => $setting_key) {
            if (isset($raw[$setting_key]) && $raw[$setting_key] !== '') {
                $brand[$brand_key] = $raw[$setting_key];
            }
        }

        if (empty($brand['background_color']) && !empty($raw['bg_color'])) {
            $brand['background_color'] = $raw['bg_color'];
        }

        if (empty($brand['card_color']) && !empty($raw['bg_color'])) {
            $brand['card_color'] = $raw['bg_color'];
        }

        if (empty($brand['text_color']) && !empty($raw['content_color'])) {
            $brand['text_color'] = $raw['content_color'];
        }

        if (empty($brand['logo_url'])) {
            foreach (array('logo', 'brand_logo', 'site_logo', 'login_logo', 'logo_image') as $logo_key) {
                if (!empty($raw[$logo_key])) {
                    $brand['logo_url'] = $raw[$logo_key];
                    break;
                }
            }
        }

        if (empty($brand['logo_url']) && !empty($raw['logo_id'])) {
            $logo_url = wp_get_attachment_image_url(absint($raw['logo_id']), 'full');
            if ($logo_url) {
                $brand['logo_url'] = $logo_url;
            }
        }

        if (empty($brand['sidebar_color']) && !empty($raw['login_panel_bg'])) {
            $brand['sidebar_color'] = $raw['login_panel_bg'];
        }

        if (empty($brand['sidebar_color']) && !empty($raw['header_bg_color'])) {
            $brand['sidebar_color'] = $raw['header_bg_color'];
        }

        foreach (array('button_radius', 'card_radius') as $radius_key) {
            if (!empty($brand[$radius_key]) && is_numeric($brand[$radius_key])) {
                $brand[$radius_key] .= 'px';
            }
        }

        return $brand;
    }
}
