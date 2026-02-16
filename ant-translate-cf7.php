<?php
/**
 * Plugin Name:       ANT Translate for Contact Form 7
 * Plugin URI:        https://eleviosolutions.com/ant-translate-cf7/
 * Description:       Contact Form 7 integration for ANT Translate – translates form fields, mail templates (with CF7 tag safety), messages, and AJAX responses.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Elevio Solutions
 * Author URI:        https://eleviosolutions.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       ant-translate-cf7
 * Domain Path:       /languages
 *
 * Requires Plugins:  ant-translate
 *
 * @package ANT_Translate_CF7
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ANT_ST_CF7_VERSION', '2.0.0');
define('ANT_ST_CF7_FILE', __FILE__);
define('ANT_ST_CF7_DIR', plugin_dir_path(__FILE__));
define('ANT_ST_CF7_URL', plugin_dir_url(__FILE__));

/**
 * Initialize after all plugins loaded.
 */
add_action('plugins_loaded', function () {
    // i18n: load translations early.
    load_plugin_textdomain('ant-translate-cf7', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Check dependencies – core plugin must be active.
    if (!defined('ANT_ST_VERSION')) {
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('ANT Translate for Contact Form 7', 'ant-translate-cf7') . '</strong> ';
            echo esc_html__('requires', 'ant-translate-cf7') . ' ';
            echo '<strong>' . esc_html__('ANT Translate', 'ant-translate-cf7') . '</strong> ';
            echo esc_html__('to be installed and active.', 'ant-translate-cf7');
            echo '</p></div>';
        });
        return;
    }

    // Register as addon (free – no paid license required).
    if (function_exists('ant_st_register_addon')) {
        ant_st_register_addon('cf7', 'ANT Translate for Contact Form 7', ANT_ST_CF7_VERSION);
    }

    // Register license fields so the Settings > Addons card shows "Licensed".
    require_once ANT_ST_CF7_DIR . 'includes/class-cf7-license.php';
    $license = ANT_CF7_License::get_instance();

    add_filter('ant_translate_addon_license_fields', function ($fields) use ($license) {
        $fields['cf7'] = [
            'license_obj' => $license,
        ];
        return $fields;
    });

    // CF7 addon is free: load translation hooks whenever CF7 is present.
    if (defined('WPCF7_VERSION')) {
        require_once ANT_ST_CF7_DIR . 'includes/hooks.php';
    } else {
        // CF7 not active — show notice.
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>' . esc_html__('ANT Translate for Contact Form 7', 'ant-translate-cf7') . '</strong>: ';
            echo esc_html__('Contact Form 7 is not active. The addon will remain dormant until CF7 is activated.', 'ant-translate-cf7');
            echo '</p></div>';
        });
    }
}, 25);
