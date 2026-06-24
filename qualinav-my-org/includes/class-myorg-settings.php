<?php
/**
 * My Org — settings model.
 *
 * Single source of truth for the section registry and the admin-configurable
 * options stored against each one
 * (enabled, label, slug, description). All reads go through here so the
 * dashboard template, the front-end router, and the admin screen stay in sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Qualinav_My_Org_Settings {

    /** Front-end slug the My Org hub always lives at. */
    const MY_ORG_SLUG = 'my-org';

    /** Option key holding the per-section overrides. */
    const OPTION = 'qualinav_my_org_settings';

    /**
     * Static registry of the sections My Org orchestrates. Values here are the
     * built-in defaults; the admin screen can override label/slug/description
     * and toggle `enabled`. `icon` is a file in assets/images/icons. `admin`
     * lists the underlying plugin's own settings screens (admin.php?page=…)
     * so the hub can deep-link to them.
     */
    public static function registry() {
        return array(
            'data_hub' => array(
                'label'       => 'Data Hub',
                'slug'        => 'data-hub',
                'description' => 'Manage your data, build dashboard reports, and publish run charts for your team to review.',
                'icon'        => 'my-data.svg',
                'enabled'     => 1,
                'admin'       => array(
                    'QAPI Dashboard' => 'qaqd-dashboard-help',
                    'Drive Storage'  => 'data-hub-drive',
                ),
            ),
            'qi_projects' => array(
                'label'       => 'QI Projects',
                'slug'        => 'qi-projects',
                'description' => 'Plan your quality improvement projects and keep the team on the same page.',
                'icon'        => 'qi-projects.svg',
                'enabled'     => 1,
                'admin'       => array(),
            ),
            'assessments' => array(
                'label'       => 'Assessments',
                'slug'        => 'org-assessments',
                'description' => 'Run a Readiness check before a specific initiative, or an ORG Assessment to review culture, leadership, finance, and operations.',
                'icon'        => 'org-assessment.svg',
                'enabled'     => 1,
                'admin'       => array(
                    'Assessments & Readiness' => 'cah-assessments',
                ),
            ),
            'regulatory_readiness' => array(
                'label'       => 'Regulatory Readiness',
                'slug'        => 'org-assessments',
                'hash'        => 'readiness',
                'description' => 'Review TJC survey readiness and track action areas before your next regulatory visit.',
                'icon'        => 'org-assessment.svg',
                'enabled'     => 1,
                'admin'       => array(
                    'Assessments & Readiness' => 'cah-assessments',
                ),
            ),
            'safety_huddles' => array(
                'label'       => 'Safety Huddles',
                'slug'        => 'safety-huddles',
                'description' => 'Open the team huddle workspace for daily safety checks, follow-ups, and shared awareness.',
                'icon'        => '',
                'enabled'     => 1,
                'admin'       => array(),
            ),
        );
    }

    /** Sub-key under the option holding the admin-defined custom sections. */
    const CUSTOM_KEY = '__custom';

    /**
     * Registry merged with stored admin overrides, followed by any custom
     * sections the admin has added. Every entry is normalized to the same
     * shape so the dashboard and router can iterate uniformly:
     *   enabled, label, description, icon, href, is_custom (+ slug for built-ins).
     */
    public static function all() {
        $stored = get_option( self::OPTION, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        $sections = array();

        // Built-in sections.
        foreach ( self::registry() as $key => $def ) {
            $row = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();
            $def['enabled']     = isset( $row['enabled'] ) ? (int) $row['enabled'] : (int) $def['enabled'];
            $def['label']       = isset( $row['label'] ) && '' !== $row['label'] ? $row['label'] : $def['label'];
            $def['slug']        = isset( $row['slug'] ) && '' !== $row['slug'] ? $row['slug'] : $def['slug'];
            $def['description'] = isset( $row['description'] ) && '' !== $row['description'] ? $row['description'] : $def['description'];
            $def['href']        = home_url( '/' . trim( $def['slug'], '/' ) . '/' );
            if ( ! empty( $def['hash'] ) ) {
                $def['href'] .= '#' . sanitize_key( $def['hash'] );
            }
            $def['is_custom']   = false;
            $sections[ $key ]   = $def;
        }

        // Custom sections.
        foreach ( self::get_custom( $stored ) as $row ) {
            $key = 'custom__' . $row['id'];
            $sections[ $key ] = array(
                'enabled'     => (int) $row['enabled'],
                'label'       => $row['label'],
                'description' => $row['description'],
                'icon'        => '', // custom tiles use the built-in fallback glyph
                'href'        => self::resolve_url( $row['url'] ),
                'is_custom'   => true,
                'url'         => $row['url'],
                'admin'       => array(),
            );
        }

        return $sections;
    }

    /** Normalized list of custom-section rows from the stored option. */
    public static function get_custom( $stored = null ) {
        if ( null === $stored ) {
            $stored = get_option( self::OPTION, array() );
        }
        if ( ! is_array( $stored ) || empty( $stored[ self::CUSTOM_KEY ] ) || ! is_array( $stored[ self::CUSTOM_KEY ] ) ) {
            return array();
        }
        $out = array();
        foreach ( $stored[ self::CUSTOM_KEY ] as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $id = isset( $row['id'] ) && '' !== $row['id'] ? sanitize_key( $row['id'] ) : '';
            if ( '' === $id ) {
                continue;
            }
            $out[] = array(
                'id'          => $id,
                'enabled'     => empty( $row['enabled'] ) ? 0 : 1,
                'label'       => isset( $row['label'] ) ? (string) $row['label'] : '',
                'url'         => isset( $row['url'] ) ? (string) $row['url'] : '',
                'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
            );
        }
        return $out;
    }

    /**
     * Resolve a custom section's target. Absolute URLs pass through; anything
     * else is treated as a site-relative slug under the home URL.
     */
    public static function resolve_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return home_url( '/' );
        }
        if ( preg_match( '#^https?://#i', $url ) ) {
            return esc_url_raw( $url );
        }
        return home_url( '/' . trim( $url, '/' ) . '/' );
    }

    public static function get_section( $key ) {
        $all = self::all();
        return isset( $all[ $key ] ) ? $all[ $key ] : null;
    }

    public static function is_enabled( $key ) {
        $s = self::get_section( $key );
        return $s ? (bool) $s['enabled'] : false;
    }

    /** Resolved front-end URL for a section (built-in slug or custom URL). */
    public static function section_url( $key ) {
        $s = self::get_section( $key );
        return $s && ! empty( $s['href'] ) ? $s['href'] : home_url( '/' );
    }

    /**
     * Sanitize callback for the option (used by the Settings API). Only known
     * sections and known keys survive; slugs are normalized.
     */
    public static function sanitize( $input ) {
        $clean = array();
        if ( ! is_array( $input ) ) {
            return $clean;
        }
        foreach ( array_keys( self::registry() ) as $key ) {
            $row = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
            $clean[ $key ] = array(
                'enabled'     => empty( $row['enabled'] ) ? 0 : 1,
                'label'       => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
                'slug'        => isset( $row['slug'] ) ? sanitize_title( $row['slug'] ) : '',
                'description' => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
            );
        }

        // Custom sections: keep only rows that have at least a label or a
        // target, assign a stable id, and normalize the URL/slug.
        $clean[ self::CUSTOM_KEY ] = array();
        if ( ! empty( $input[ self::CUSTOM_KEY ] ) && is_array( $input[ self::CUSTOM_KEY ] ) ) {
            foreach ( $input[ self::CUSTOM_KEY ] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
                $url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
                if ( '' === $label && '' === $url ) {
                    continue;
                }
                if ( '' !== $url && ! preg_match( '#^https?://#i', $url ) ) {
                    $url = sanitize_title( $url ); // treat as a site-relative slug
                } elseif ( '' !== $url ) {
                    $url = esc_url_raw( $url );
                }
                $id = isset( $row['id'] ) && '' !== $row['id']
                    ? sanitize_key( $row['id'] )
                    : substr( md5( $label . microtime() . wp_rand() ), 0, 10 );
                $clean[ self::CUSTOM_KEY ][] = array(
                    'id'          => $id,
                    'enabled'     => empty( $row['enabled'] ) ? 0 : 1,
                    'label'       => $label,
                    'url'         => $url,
                    'description' => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
                );
            }
        }
        return $clean;
    }
}
