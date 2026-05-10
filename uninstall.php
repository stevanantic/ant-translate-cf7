<?php
/**
 * Uninstall Polyglot Translate for Contact Form 7 — clean up CF7-addon-specific
 * options and transients.
 *
 * IMPORTANT: This does NOT delete Contact Form 7 data (forms, submissions).
 * Only options created by THIS addon (field map, settings, license).
 *
 * @package PGT_Translate_CF7
 * @license GPL-2.0-or-later
 * @since   3.1.1
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Persistent options.
delete_option( 'polyglot_cf7_settings' );
delete_option( 'polyglot_cf7_field_map' );
delete_option( 'polyglot_cf7_migrated_v31' );
delete_option( 'polyglot_addon_lic_cf7' );

// 2. Sweep any polyglot_cf7_* transients.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_polyglot_cf7_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_polyglot_cf7_' ) . '%'
    )
);

// 3. Persistent object-cache cleanup.
if ( function_exists( 'wp_cache_delete' ) ) {
    wp_cache_delete( 'polyglot_cf7_settings', 'options' );
    wp_cache_delete( 'polyglot_cf7_field_map', 'options' );
    wp_cache_delete( 'alloptions', 'options' );
}
