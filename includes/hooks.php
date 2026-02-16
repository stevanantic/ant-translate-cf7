<?php
/**
 * ANT Translate for Contact Form 7 – translation hooks (v2.0 refactor).
 *
 * Changes from v1.x:
 *  - wpcf7_feedback_response (CF7 5.2+) as primary AJAX hook, with
 *    wpcf7_ajax_json_echo as backward-compat fallback.
 *  - Mail translation now masks CF7 [tags] before translating and restores
 *    them afterward. Only human-text fields are translated (subject, body,
 *    sender display name). Headers/recipients are skipped.
 *  - AJAX context narrowed: only translates during actual CF7 submissions
 *    (checks for _wpcf7 POST parameter).
 *  - Request-level cache for form elements (static array, not transients).
 *  - Conditional Fields placeholder block removed (was a no-op).
 *  - i18n: load_plugin_textdomain added in main file.
 *
 * @package ANT_Translate_CF7
 * @since   2.0.0
 * @license GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
 * Request-level translation cache
 * ========================================================================== */

/**
 * Translate a string with request-level caching.
 *
 * @param string $text Source text.
 * @return string Translated text.
 */
function ant_st_cf7_translate(string $text): string
{
    static $cache = [];

    if ($text === '' || !function_exists('ant_st_translate_plain')) {
        return $text;
    }

    if (isset($cache[$text])) {
        return $cache[$text];
    }

    $translated = ant_st_translate_plain($text);
    $cache[$text] = is_string($translated) && $translated !== '' ? $translated : $text;

    return $cache[$text];
}

/* ==========================================================================
 * Language detection helpers
 * ========================================================================== */

/**
 * Detect the target language slug from the HTTP Referer.
 *
 * CF7 submits via REST API (/wp-json/...) where the URL has no language
 * prefix. We fall back to HTTP_REFERER to detect the language context
 * of the page the user submitted from.
 *
 * @return bool True if the referer URL contains the target language slug.
 */
function ant_st_cf7_referer_is_target_lang(): bool
{
    if (!function_exists('ant_st_lang_slug')) {
        return false;
    }
    $slug = ant_st_lang_slug();
    if ($slug === '') {
        return false;
    }

    $referer = wp_get_referer();
    if (!is_string($referer) || $referer === '') {
        $referer = isset($_SERVER['HTTP_REFERER'])
            ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER']))
            : '';
    }
    if ($referer === '') {
        return false;
    }

    $path = (string) wp_parse_url($referer, PHP_URL_PATH);
    if ($path === '') {
        return false;
    }

    return (bool) preg_match('~/' . preg_quote($slug, '~') . '(/|$)~', $path);
}

/**
 * Return true when we should apply CF7 translation.
 *
 * Works for:
 *  - Normal page loads on the target language (/en/kontakt/)
 *  - CF7 AJAX/REST submissions (detected via HTTP_REFERER + _wpcf7 param)
 *
 * Never translates in the WP admin editor context.
 * In AJAX context, only translates if this is an actual CF7 form submission
 * (checks for _wpcf7 POST field), not random admin AJAX requests.
 */
function ant_st_cf7_should_translate(): bool
{
    // Never translate in the admin editor (form edit screen).
    if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
        return false;
    }

    if (!function_exists('ant_st_current_lang') || !function_exists('ant_st_lang_slug')) {
        return false;
    }

    // Direct URL match (normal page load on /en/).
    if (ant_st_current_lang() === ant_st_lang_slug()) {
        return true;
    }

    // REST/AJAX: only translate if this is an actual CF7 submission.
    if (wp_is_serving_rest_request() || (defined('DOING_AJAX') && DOING_AJAX)) {
        // Check for CF7 submission context.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check
        $is_cf7_submission = isset($_POST['_wpcf7']) || isset($_POST['_wpcf7_version']) || isset($_POST['_wpcf7_unit_tag']);

        if (!$is_cf7_submission) {
            // Also check REST route pattern for CF7 5.6+ REST API.
            $rest_route = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $is_cf7_submission = (strpos($rest_route, '/contact-form-7/') !== false);
        }

        if (!$is_cf7_submission) {
            return false;
        }

        return ant_st_cf7_referer_is_target_lang();
    }

    return false;
}

/* ==========================================================================
 * CF7 Tag Masking (for safe mail translation)
 *
 * Replaces [cf7-tags] with numbered placeholders before translation,
 * then restores them afterward. This prevents translation APIs from
 * mangling tag names like [your-email] or [_site_title].
 * ========================================================================== */

/**
 * Mask CF7 mail tags in a string, returning the masked string and a map.
 *
 * @param string $text Input text with CF7 [tags].
 * @return array{0: string, 1: array<string, string>} [masked_text, tag_map]
 */
function ant_st_cf7_mask_tags(string $text): array
{
    $map = [];
    $counter = 0;

    $masked = preg_replace_callback('/\[[^\]]+\]/', function ($match) use (&$map, &$counter) {
        $counter++;
        $placeholder = '{{ANT_CF7_TAG_' . $counter . '}}';
        $map[$placeholder] = $match[0];
        return $placeholder;
    }, $text);

    return [$masked ?? $text, $map];
}

/**
 * Restore CF7 mail tags from placeholders.
 *
 * @param string                   $text    Masked (and now translated) text.
 * @param array<string, string>    $tag_map Placeholder => original tag map.
 * @return string Text with CF7 tags restored.
 */
function ant_st_cf7_unmask_tags(string $text, array $tag_map): string
{
    if (empty($tag_map)) {
        return $text;
    }
    return str_replace(array_keys($tag_map), array_values($tag_map), $text);
}

/**
 * Translate a mail text field safely: mask CF7 tags, translate, unmask.
 *
 * @param string $text Mail template text.
 * @return string Translated text with CF7 tags preserved.
 */
function ant_st_cf7_safe_translate_mail_text(string $text): string
{
    if ($text === '') {
        return $text;
    }

    // Guard: skip if entire string is a single CF7 tag.
    if (preg_match('/^\[[^\]]+\]$/', trim($text))) {
        return $text;
    }

    // Guard: skip if looks like an email address.
    if (is_email($text) || (strpos($text, '@') !== false && strpos($text, ' ') === false)) {
        return $text;
    }

    // Mask CF7 tags, translate the human text, then restore tags.
    list($masked, $tag_map) = ant_st_cf7_mask_tags($text);
    $translated = ant_st_cf7_translate($masked);
    return ant_st_cf7_unmask_tags($translated, $tag_map);
}

/* ==========================================================================
 * 1. Form HTML translation (labels, placeholders, submit text)
 *
 * Uses request-level cache (static array) instead of transients to avoid
 * accumulating stale cache entries. Fast enough for per-request use.
 * ========================================================================== */

add_filter('wpcf7_form_elements', function ($elements) {
    if (!is_string($elements) || $elements === '') {
        return $elements;
    }
    if (!ant_st_cf7_should_translate()) {
        return $elements;
    }
    if (!function_exists('ant_st_translate_html_text_nodes')) {
        return $elements;
    }

    // Request-level cache keyed by content hash.
    static $html_cache = [];
    $hash = md5($elements);

    if (isset($html_cache[$hash])) {
        return $html_cache[$hash];
    }

    $out = ant_st_translate_html_text_nodes($elements);
    if (is_string($out) && $out !== '') {
        $html_cache[$hash] = $out;
        return $out;
    }

    return $elements;
}, 20);

/* ==========================================================================
 * 2. Mail template translation (subject, body only — safe fields)
 *
 * Only translates human-facing text fields. Skips recipient, headers,
 * and structural fields. CF7 mail tags are masked before translation.
 * ========================================================================== */

/**
 * Translate a CF7 mail property array safely.
 *
 * Only these fields are translated:
 *  - subject (email subject line — user-visible)
 *  - body (email body — user-visible)
 *  - sender (display name part only, not the email address)
 *
 * Fields NEVER translated:
 *  - recipient (email addresses)
 *  - additional_headers (To, CC, BCC, Reply-To lines)
 *  - use_html, exclude_blank, attachments (boolean/structural)
 *
 * @param array $prop Mail property array.
 * @return array Translated mail property.
 */
function ant_st_cf7_translate_mail_safe(array $prop): array
{
    // Translate subject (safe — user-visible, may contain CF7 tags).
    if (isset($prop['subject']) && is_string($prop['subject'])) {
        $prop['subject'] = ant_st_cf7_safe_translate_mail_text($prop['subject']);
    }

    // Translate body (safe — user-visible, contains CF7 tags).
    if (isset($prop['body']) && is_string($prop['body'])) {
        $prop['body'] = ant_st_cf7_safe_translate_mail_text($prop['body']);
    }

    // Translate sender display name only (before the < email > part).
    if (isset($prop['sender']) && is_string($prop['sender'])) {
        $prop['sender'] = ant_st_cf7_translate_sender_name($prop['sender']);
    }

    return $prop;
}

/**
 * Translate only the display name part of a "Name <email>" sender string.
 *
 * Input:  "Kontakt Forma <[_site_admin_email]>"
 * Output: "Contact Form <[_site_admin_email]>"
 *
 * @param string $sender Full sender string.
 * @return string Sender with translated display name.
 */
function ant_st_cf7_translate_sender_name(string $sender): string
{
    // Pattern: "Display Name <email@or.cf7.tag>"
    if (preg_match('/^(.+?)\s*(<.+>)$/', $sender, $m)) {
        $display_name = trim($m[1]);
        $email_part   = $m[2];

        if ($display_name !== '') {
            $display_name = ant_st_cf7_safe_translate_mail_text($display_name);
        }

        return $display_name . ' ' . $email_part;
    }

    // No angle bracket pattern — don't translate (likely just an email).
    return $sender;
}

add_filter('wpcf7_contact_form_property_mail', function ($prop, $contact_form) {
    if (!ant_st_cf7_should_translate()) {
        return $prop;
    }
    if (!is_array($prop)) {
        return $prop;
    }
    return ant_st_cf7_translate_mail_safe($prop);
}, 10, 2);

add_filter('wpcf7_contact_form_property_mail_2', function ($prop, $contact_form) {
    if (!ant_st_cf7_should_translate()) {
        return $prop;
    }
    if (!is_array($prop)) {
        return $prop;
    }
    return ant_st_cf7_translate_mail_safe($prop);
}, 10, 2);

/* ==========================================================================
 * 3. Messages property translation (validation, success, etc.)
 * ========================================================================== */

add_filter('wpcf7_contact_form_property_messages', function ($prop, $contact_form) {
    if (!ant_st_cf7_should_translate()) {
        return $prop;
    }
    if (!is_array($prop)) {
        return $prop;
    }

    $out = [];
    foreach ($prop as $key => $msg) {
        if (is_string($msg) && $msg !== '') {
            $out[$key] = ant_st_cf7_translate($msg);
        } else {
            $out[$key] = $msg;
        }
    }
    return $out;
}, 10, 2);

/* ==========================================================================
 * 4. Display message fallback (safety net)
 * ========================================================================== */

add_filter('wpcf7_display_message', function ($message, $status) {
    if (!is_string($message) || $message === '') {
        return $message;
    }
    if (!ant_st_cf7_should_translate()) {
        return $message;
    }
    return ant_st_cf7_translate($message);
}, 10, 2);

/* ==========================================================================
 * 5. AJAX/REST response translation
 *
 * PRIMARY: wpcf7_feedback_response (CF7 5.2+, non-deprecated)
 * FALLBACK: wpcf7_ajax_json_echo (deprecated but still fires in CF7 < 5.2)
 *
 * Both translate 'message' and 'invalid_fields[].message'.
 * A static flag prevents double-translation if both hooks fire.
 * ========================================================================== */

/**
 * Translate a CF7 AJAX/REST response array.
 *
 * @param array $response Response data.
 * @return array Translated response.
 */
function ant_st_cf7_translate_response(array $response): array
{
    static $already_translated = false;

    if ($already_translated) {
        return $response;
    }

    if (!ant_st_cf7_should_translate()) {
        return $response;
    }

    // Translate the main response message.
    if (isset($response['message']) && is_string($response['message']) && $response['message'] !== '') {
        $response['message'] = ant_st_cf7_translate($response['message']);
    }

    // Translate validation error messages in invalid_fields.
    if (isset($response['invalid_fields']) && is_array($response['invalid_fields'])) {
        foreach ($response['invalid_fields'] as &$field) {
            if (is_array($field) && isset($field['message']) && is_string($field['message']) && $field['message'] !== '') {
                $field['message'] = ant_st_cf7_translate($field['message']);
            }
        }
        unset($field);
    }

    $already_translated = true;
    return $response;
}

// PRIMARY: wpcf7_feedback_response (CF7 5.2+).
// This hook replaces the deprecated wpcf7_ajax_json_echo.
add_filter('wpcf7_feedback_response', function ($response, $result) {
    if (!is_array($response)) {
        return $response;
    }
    return ant_st_cf7_translate_response($response);
}, 10, 2);

// FALLBACK: wpcf7_ajax_json_echo (for CF7 < 5.2 backward compat).
// If wpcf7_feedback_response already fired, the static flag skips this.
add_filter('wpcf7_ajax_json_echo', function ($response, $result) {
    if (!is_array($response)) {
        return $response;
    }
    return ant_st_cf7_translate_response($response);
}, 10, 2);

/* ==========================================================================
 * 6. CAPTCHA/Quiz refill response (CF7 5.2+)
 *
 * Translates any user-facing text in the refill payload.
 * Usually not critical, but covers edge cases with custom captcha labels.
 * ========================================================================== */

add_filter('wpcf7_refill_response', function ($response) {
    if (!is_array($response) || !ant_st_cf7_should_translate()) {
        return $response;
    }

    // Translate any string 'message' fields in the refill (rare but possible).
    if (isset($response['message']) && is_string($response['message']) && $response['message'] !== '') {
        $response['message'] = ant_st_cf7_translate($response['message']);
    }

    return $response;
}, 10);
