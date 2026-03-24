<?php
/**
 * Plugin Name:       Polyglot Translate for Contact Form 7
 * Plugin URI:        https://polyglot-translate.cloud/wordpress-plugin/addons/cf7/
 * Description:       Contact Form 7 integration for Polyglot Translate – translates form fields, mail templates (with CF7 tag safety), messages, and AJAX responses.
 * Version:           3.1.0
 * CF7 requires at least: 5.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Polyglot Translate
 * Author URI:        https://polyglot-translate.cloud/
 * License:           GPL-2.0-or-later
 * Text Domain:       polyglot-translate-cf7
 * Domain Path:       /languages
 *
 * Requires Plugins:  polyglot-translate
 *
 * @package PGT_Translate_CF7
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POLYGLOT_CF7_VERSION', '3.1.0');
define('POLYGLOT_CF7_FILE', __FILE__);
define('POLYGLOT_CF7_DIR', plugin_dir_path(__FILE__));
define('POLYGLOT_CF7_URL', plugin_dir_url(__FILE__));

/* --------------------------------------------------------------------------
 * Migration: Rename old option keys from ant_st_* → polyglot_* on first load.
 * ----------------------------------------------------------------------- */
if (!function_exists('polyglot_cf7_maybe_migrate')) {
    function polyglot_cf7_maybe_migrate(): void
    {
        if (get_option('polyglot_cf7_migrated_v31')) {
            return;
        }

        // Migrate license key (free addon, but option may exist).
        $old_lic = get_option('ant_st_addon_lic_cf7');
        if ($old_lic !== false) {
            update_option('polyglot_addon_lic_cf7', $old_lic);
            delete_option('ant_st_addon_lic_cf7');
        }

        // Migrate field map option.
        $old_map = get_option('ant_st_cf7_field_map');
        if ($old_map !== false) {
            update_option('polyglot_cf7_field_map', $old_map);
            delete_option('ant_st_cf7_field_map');
        }

        // Migrate Flamingo submission language meta (batch, max 500).
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = (int) $wpdb->query(
            "UPDATE {$wpdb->postmeta} SET meta_key = '_polyglot_submission_language' WHERE meta_key = '_ant_st_submission_language' LIMIT 500"
        );

        if ($rows === 0) {
            update_option('polyglot_cf7_migrated_v31', 1);
        }
    }
}

/**
 * Initialize after all plugins loaded.
 */
add_action('plugins_loaded', function () {
    // i18n: load translations early.
    load_plugin_textdomain('polyglot-translate-cf7', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Check dependencies – core plugin must be active.
    if (!defined('POLYGLOT_VERSION')) {
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('Polyglot Translate for Contact Form 7', 'polyglot-translate-cf7') . '</strong> ';
            echo esc_html__('requires', 'polyglot-translate-cf7') . ' ';
            echo '<strong>' . esc_html__('Polyglot Translate', 'polyglot-translate-cf7') . '</strong> ';
            echo esc_html__('to be installed and active.', 'polyglot-translate-cf7');
            echo '</p></div>';
        });
        return;
    }

    // Run migration from old ant_st_* options.
    polyglot_cf7_maybe_migrate();

    // Register as addon (free – no paid license required).
    if (function_exists('polyglot_register_addon')) {
        polyglot_register_addon('cf7', 'Polyglot Translate for Contact Form 7', POLYGLOT_CF7_VERSION);
    }

    // Register license fields so the Settings > Addons card shows "Licensed".
    require_once POLYGLOT_CF7_DIR . 'includes/class-cf7-license.php';
    $license = PGT_CF7_License::get_instance();

    add_filter('polyglot_translate_addon_license_fields', function ($fields) use ($license) {
        $fields['cf7'] = [
            'license_obj' => $license,
        ];
        return $fields;
    });

    // CF7 addon is free: load translation hooks whenever CF7 is present.
    if (defined('WPCF7_VERSION')) {
        require_once POLYGLOT_CF7_DIR . 'includes/hooks.php';
    } else {
        // CF7 not active — show notice.
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>' . esc_html__('Polyglot Translate for Contact Form 7', 'polyglot-translate-cf7') . '</strong>: ';
            echo esc_html__('Contact Form 7 is not active. The addon will remain dormant until CF7 is activated.', 'polyglot-translate-cf7');
            echo '</p></div>';
        });
    }
}, 25);
