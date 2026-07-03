<?php
/**
 * Polyglot Translate for Contact Form 7 — string discovery.
 *
 * Makes CF7 forms first-class citizens in the Polyglot translation catalog:
 *  - Collects every translatable string of a form (template labels/placeholders/
 *    buttons, validation + status messages, mail subject/body) into a single
 *    per-form context key `cf7:<form_id>`.
 *  - Persists that context via the core scanner so the form is discoverable in
 *    the Translation Editor WITHOUT requiring a frontend visit first.
 *  - Re-collects on form save (`wpcf7_save_contact_form`) and enqueues a rescan,
 *    closing the gap documented at `includes/class-scan-queue.php:96`.
 *  - Provides a single source of truth (`polyglot_cf7_collect_form_strings()`)
 *    reused by the One-Click bulk catalog so the bulk path and the editor agree.
 *
 * @package PGT_Translate_CF7
 * @since   3.2.0
 * @license GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Strip CF7 mail/form tags and HTML, returning clean translatable lines.
 *
 * @param string $raw       Raw template / mail text.
 * @param bool   $multiline When true, split on newlines into separate strings.
 * @return string[] Clean strings (deduped, trimmed, min-length filtered).
 */
function polyglot_cf7_clean_strings(string $raw, bool $multiline): array
{
    // Remove CF7 tags like [your-email], [submit "Send"], [_site_title].
    $plain = preg_replace('/\[[^\]]+\]/', '', $raw);
    $plain = wp_strip_all_tags((string) $plain);

    $lines = $multiline ? preg_split('/[\r\n]+/', $plain) : [$plain];
    $out   = [];
    foreach ($lines as $line) {
        $line = polyglot_cf7_normalize_string((string) $line);
        if (polyglot_cf7_is_translatable_string($line)) {
            $out[] = $line;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Whether a string is worth offering for translation.
 *
 * Drops empties, sub-2-char fragments, and strings with no letters
 * (separators like "--", leftover "( )", bare numbers).
 *
 * @param string $s Candidate string.
 * @return bool
 */
function polyglot_cf7_is_translatable_string(string $s): bool
{
    if ($s === '' || mb_strlen($s, 'UTF-8') < 2) {
        return false;
    }
    return (bool) preg_match('/\p{L}/u', $s);
}

/**
 * Canonicalize a string the same way the frontend capture path does
 * (whitespace collapse + HTML-entity decode + counter-suffix parametrize),
 * so per-form `cf7:<id>` entries match the runtime-rendered text — translations
 * resolve correctly and dedupe against any frontend-captured copy.
 *
 * @param string $s Raw string.
 * @return string Canonical string.
 */
function polyglot_cf7_normalize_string(string $s): string
{
    $s = trim((string) preg_replace('/\s+/', ' ', $s));
    if (function_exists('polyglot_decode_entities')) {
        $s = polyglot_decode_entities($s);
    }
    if (function_exists('polyglot_parametrize_counter_suffix')) {
        $s = polyglot_parametrize_counter_suffix($s);
    }
    return $s;
}

/**
 * Extract the user-visible text carried INSIDE CF7 form tags.
 *
 * Field placeholders/defaults (`[text* name placeholder "Ime"]`), submit button
 * labels (`[submit "Pošalji"]`), and select/checkbox/radio option labels
 * (`[select menu "USA" "Canada"]`) all live inside the tag brackets, so the
 * plain-text pass (which strips `[...]`) never sees them. We use CF7's own
 * form-tag parser (WPCF7_FormTag->labels/->values) instead of regex — labels
 * already resolve the `value|label` pipe syntax to the display text.
 *
 * @param WPCF7_ContactForm $cf7 Contact form object.
 * @return string[] Translatable strings carried inside form tags.
 */
function polyglot_cf7_collect_tag_strings($cf7): array
{
    if (!is_object($cf7) || !method_exists($cf7, 'scan_form_tags')) {
        return [];
    }

    $out = [];
    foreach ($cf7->scan_form_tags() as $tag) {
        // labels: display text (pipe-aware). values: raw values / placeholders.
        $candidates = [];
        if (isset($tag->labels) && is_array($tag->labels)) {
            $candidates = array_merge($candidates, $tag->labels);
        }
        if (isset($tag->values) && is_array($tag->values)) {
            $candidates = array_merge($candidates, $tag->values);
        }
        foreach ($candidates as $val) {
            if (is_string($val)) {
                $val = polyglot_cf7_normalize_string($val);
                if (polyglot_cf7_is_translatable_string($val)) {
                    $out[] = $val;
                }
            }
        }
    }

    return array_values(array_unique($out));
}

/**
 * Collect all translatable strings for a single CF7 form.
 *
 * Single source of truth for both the editor catalog (per-form context) and
 * the One-Click bulk translate catalog.
 *
 * @param int $form_id Contact form post ID.
 * @return string[] Flat, deduped list of translatable source strings.
 */
function polyglot_cf7_collect_form_strings(int $form_id): array
{
    if (!function_exists('wpcf7_contact_form')) {
        return [];
    }

    $cf7 = wpcf7_contact_form($form_id);
    if (!$cf7) {
        return [];
    }

    $strings = [];

    // 1. Form template — free HTML text BETWEEN tags (labels written as plain
    //    text, headings, help text). Tag brackets are stripped here.
    $form_text = $cf7->prop('form');
    if (is_string($form_text) && $form_text !== '') {
        $strings = array_merge($strings, polyglot_cf7_clean_strings($form_text, true));
    }

    // 1b. Text carried INSIDE form tags — placeholders, submit button labels,
    //     select/checkbox/radio option labels, etc. (the most visible strings).
    $strings = array_merge($strings, polyglot_cf7_collect_tag_strings($cf7));

    // 2. Messages — validation, success, spam, etc. (each is a single string).
    $messages = $cf7->prop('messages');
    if (is_array($messages)) {
        foreach ($messages as $msg) {
            if (is_string($msg)) {
                $msg = polyglot_cf7_normalize_string($msg);
                if (polyglot_cf7_is_translatable_string($msg)) {
                    $strings[] = $msg;
                }
            }
        }
    }

    // 3. Mail + Mail(2) — only human-visible subject/body, CF7 tags stripped.
    foreach (['mail', 'mail_2'] as $mail_prop) {
        $mail = $cf7->prop($mail_prop);
        if (!is_array($mail)) {
            continue;
        }
        if ($mail_prop === 'mail_2' && empty($mail['active'])) {
            continue;
        }
        foreach (['subject', 'body'] as $field) {
            if (!isset($mail[$field]) || !is_string($mail[$field])) {
                continue;
            }
            // Subject is a single line; body may be multi-line.
            $strings = array_merge(
                $strings,
                polyglot_cf7_clean_strings($mail[$field], $field === 'body')
            );
        }
    }

    return array_values(array_unique($strings));
}

/**
 * Context key for a CF7 form.
 *
 * @param int $form_id Contact form post ID.
 * @return string e.g. "cf7:42"
 */
function polyglot_cf7_context_key(int $form_id): string
{
    return 'cf7:' . $form_id;
}

/**
 * Ensure the core scanner class is loaded. Core loads it only on
 * admin/AJAX/CLI requests (`polyglot-translate.php:161`); the CF7 save hook can
 * fire on a REST request too, so load it defensively (same pattern core uses).
 *
 * @return bool True when PGT_Site_Scanner is available.
 */
function polyglot_cf7_ensure_scanner(): bool
{
    if (class_exists('PGT_Site_Scanner')) {
        return true;
    }
    if (defined('POLYGLOT_INC') && file_exists(POLYGLOT_INC . 'class-site-scanner.php')) {
        require_once POLYGLOT_INC . 'class-site-scanner.php';
    }
    return class_exists('PGT_Site_Scanner');
}

/**
 * Persist a single form's strings into the scanner catalog.
 *
 * Unlike the generic `save_context_strings()` (which merges and never removes),
 * this REPLACES the form's context with its current authoritative string set so
 * renamed/removed fields are pruned instead of inflating the total forever. A
 * dirty-check skips the write entirely when nothing changed, so re-indexing on
 * every tab render costs no DB writes for unchanged forms.
 *
 * @param int $form_id Contact form post ID.
 * @return int Number of strings stored.
 */
function polyglot_cf7_index_form(int $form_id): int
{
    if (!polyglot_cf7_ensure_scanner() || !defined('POLYGLOT_OPTION_CONTEXTS') || !function_exists('polyglot_atomic_option_update')) {
        return 0;
    }

    $strings = polyglot_cf7_collect_form_strings($form_id);
    $ctx_key = polyglot_cf7_context_key($form_id);

    // Dirty-check against the RAW stored value (avoids the read-time date-format
    // filtering in get_context_strings() causing false "changed" comparisons).
    $contexts = get_option(POLYGLOT_OPTION_CONTEXTS, []);
    $existing = (is_array($contexts) && isset($contexts[$ctx_key]) && is_array($contexts[$ctx_key]))
        ? $contexts[$ctx_key]
        : [];

    $a = $strings;
    $b = $existing;
    sort($a);
    sort($b);
    if ($a === $b) {
        return count($strings); // No change — skip the atomic write.
    }

    polyglot_atomic_option_update(
        POLYGLOT_OPTION_CONTEXTS,
        function ($ctx) use ($ctx_key, $strings) {
            if (!is_array($ctx)) {
                $ctx = [];
            }
            if (empty($strings)) {
                unset($ctx[$ctx_key]);
            } else {
                $ctx[$ctx_key] = array_values($strings);
            }
            return $ctx;
        }
    );

    return count($strings);
}

/**
 * Index every published CF7 form. Idempotent — safe to call repeatedly.
 *
 * @param int $limit Max forms to scan (guards against huge installs).
 * @return int Total strings indexed across all forms.
 */
function polyglot_cf7_index_all_forms(int $limit = 200): int
{
    $form_ids = get_posts([
        'post_type'      => 'wpcf7_contact_form',
        'post_status'    => 'publish',
        'numberposts'    => $limit,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'suppress_filters' => true,
    ]);

    $total = 0;
    foreach ($form_ids as $form_id) {
        $total += polyglot_cf7_index_form((int) $form_id);
    }

    return $total;
}

/* ==========================================================================
 * Re-index on form save + enqueue rescan.
 *
 * Closes the gap noted at includes/class-scan-queue.php:96 — CF7 was the only
 * documented addon never wiring its content-change hook.
 * ========================================================================== */

add_action('wpcf7_save_contact_form', function ($contact_form) {
    $form_id = 0;
    if (is_object($contact_form) && method_exists($contact_form, 'id')) {
        $form_id = (int) $contact_form->id();
    }
    if ($form_id <= 0) {
        return;
    }

    polyglot_cf7_index_form($form_id);

    // Let the core incremental scanner know this content changed.
    if (class_exists('PGT_Scan_Queue') && method_exists('PGT_Scan_Queue', 'enqueue')) {
        PGT_Scan_Queue::enqueue($form_id, polyglot_cf7_context_key($form_id), 'update');
    }
}, 20, 1);

/**
 * Remove a form's context when the form is deleted, so stale strings don't
 * linger in the catalog. CF7 deletes forms as ordinary posts (no dedicated
 * after-delete action), so we hook the generic post-delete and filter by type.
 */
add_action('before_delete_post', function ($post_id) {
    $post = get_post((int) $post_id);
    if (!$post || $post->post_type !== 'wpcf7_contact_form') {
        return;
    }
    if (!function_exists('polyglot_atomic_option_update') || !defined('POLYGLOT_OPTION_CONTEXTS')) {
        return;
    }
    $ctx_key = polyglot_cf7_context_key((int) $post_id);
    polyglot_atomic_option_update(POLYGLOT_OPTION_CONTEXTS, function ($contexts) use ($ctx_key) {
        if (is_array($contexts) && isset($contexts[$ctx_key])) {
            unset($contexts[$ctx_key]);
        }
        return is_array($contexts) ? $contexts : [];
    });

    // Also drop any per-string overrides for this form so no orphans linger.
    if (defined('POLYGLOT_OPTION_OVERRIDES')) {
        polyglot_atomic_option_update(POLYGLOT_OPTION_OVERRIDES, function ($overrides) use ($ctx_key) {
            if (is_array($overrides) && isset($overrides[$ctx_key])) {
                unset($overrides[$ctx_key]);
            }
            return is_array($overrides) ? $overrides : [];
        });
    }
}, 10, 1);
