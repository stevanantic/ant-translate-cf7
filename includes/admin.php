<?php
/**
 * Polyglot Translate for Contact Form 7 — admin integration.
 *
 *  (A) A dedicated "Contact Forms" tab inside the Polyglot Translate app shell
 *      that lists every CF7 form with per-language translation progress and
 *      deep-links into the Translation Editor for each form.
 *  (B) A native panel inside the CF7 form editor screen ("Polyglot") with the
 *      form's translation status + a button that jumps straight to the editor.
 *
 * Loaded only in admin context, only when CF7 is active.
 *
 * @package PGT_Translate_CF7
 * @since   3.2.0
 * @license GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
 * Progress computation
 * ========================================================================== */

/**
 * Target languages for the CF7 admin views.
 *
 * @return array<int, array{slug:string, name:string, flag:string}>
 */
function polyglot_cf7_target_langs(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    if (!function_exists('polyglot_get_target_langs')) {
        return $cache;
    }

    foreach (polyglot_get_target_langs() as $tl) {
        $slug = $tl['slug'] ?? ($tl['code'] ?? '');
        if ($slug === '') {
            continue;
        }
        $info = function_exists('polyglot_get_language') ? polyglot_get_language($slug) : null;
        $cache[] = [
            'slug' => $slug,
            'name' => is_array($info) && !empty($info['name']) ? $info['name'] : strtoupper($slug),
            'flag' => is_array($info) && !empty($info['flag']) ? $info['flag'] : '',
        ];
    }

    return $cache;
}

/**
 * Per-language translation progress for one form context.
 *
 * @param int $form_id Contact form post ID.
 * @return array{total:int, langs:array<string, array{translated:int, pct:int}>}
 */
function polyglot_cf7_form_progress(int $form_id): array
{
    $ctx_key = function_exists('polyglot_cf7_context_key')
        ? polyglot_cf7_context_key($form_id)
        : 'cf7:' . $form_id;

    $strings = class_exists('PGT_Site_Scanner')
        ? PGT_Site_Scanner::get_context_strings($ctx_key)
        : [];
    $total = count($strings);

    $overrides_all = defined('POLYGLOT_OPTION_OVERRIDES') ? get_option(POLYGLOT_OPTION_OVERRIDES, []) : [];
    $ctx_overrides = (is_array($overrides_all) && isset($overrides_all[$ctx_key]) && is_array($overrides_all[$ctx_key]))
        ? $overrides_all[$ctx_key]
        : [];

    $out = ['total' => $total, 'langs' => []];

    foreach (polyglot_cf7_target_langs() as $lang) {
        $slug = $lang['slug'];
        $map  = function_exists('polyglot_get_lang_map') ? polyglot_get_lang_map($slug) : [];
        if (!is_array($map)) {
            $map = [];
        }

        $translated = 0;
        foreach ($strings as $s) {
            if ((isset($ctx_overrides[$s]) && $ctx_overrides[$s] !== '')
                || (isset($map[$s]) && $map[$s] !== '')) {
                $translated++;
            }
        }

        $out['langs'][$slug] = [
            'translated' => $translated,
            'pct'        => $total > 0 ? (int) round(($translated / $total) * 100) : 0,
        ];
    }

    return $out;
}

/**
 * Deep-link to the Polyglot editor for a CF7 form context.
 *
 * The editor loads the context directly via PGT_Site_Scanner::get_context_strings()
 * so this link always resolves the form's strings. Note: `cf7:<id>` contexts are
 * intentionally NOT surfaced in the generic Manager / Interface tab (their numeric
 * id is not a translatable post type, so get_all_contexts() skips them); the
 * dedicated Contact Forms tab is the canonical surface for forms. The `from=cf7`
 * marker tells the editor to send its "Back" link to the Contact Forms tab (not
 * the generic Translate manager) and to hide the prev/next/jump chrome that is
 * meaningless for a form context — a coherent round-trip.
 *
 * @param int    $form_id Contact form post ID.
 * @param string $tl      Optional target language slug to pre-select.
 * @return string Admin URL.
 */
function polyglot_cf7_editor_url(int $form_id, string $tl = ''): string
{
    $args = [
        'page'    => 'polyglot-translate',
        'tab'     => 'translate',
        'context' => polyglot_cf7_context_key($form_id),
        'from'    => 'cf7',
    ];
    if ($tl !== '') {
        $args['tl'] = $tl;
    }
    return add_query_arg($args, admin_url('admin.php'));
}

/* ==========================================================================
 * (A) "Contact Forms" tab in the Polyglot app shell
 * ========================================================================== */

// Make the tab reachable via direct URL / page refresh (core fall-back guard).
add_filter('polyglot_valid_tabs', function ($tabs) {
    if (is_array($tabs) && !in_array('cf7', $tabs, true)) {
        $tabs[] = 'cf7';
    }
    return $tabs;
});

// Sidebar entry under the main group.
add_filter('polyglot_sidebar_items', function ($items) {
    if (!is_array($items)) {
        return $items;
    }
    $items['cf7'] = [
        'icon'  => 'dashicons-email-alt',
        'label' => __('Contact Forms', 'polyglot-translate'),
        'group' => 'main',
    ];
    return $items;
}, 10, 1);

// Tab renderer.
add_filter('polyglot_render_tab_cf7', function ($html) {
    $GLOBALS['polyglot_in_app_shell'] = true;
    ob_start();
    polyglot_cf7_render_tab();
    unset($GLOBALS['polyglot_in_app_shell']);
    return ob_get_clean();
}, 10, 1);

/**
 * Render the Contact Forms management view.
 */
function polyglot_cf7_render_tab(): void
{
    if (!defined('WPCF7_VERSION')) {
        echo '<div class="pgt-notice pgt-warning">'
            . esc_html__('Contact Form 7 is not active.', 'polyglot-translate')
            . '</div>';
        return;
    }

    // Keep the catalog fresh so progress reflects the current form definitions.
    // Throttled: each form is also re-indexed on save (wpcf7_save_contact_form),
    // so a full backfill is only needed periodically to pick up forms that
    // pre-date the module. The per-form dirty-check makes this write-free when
    // nothing changed, but the collect step still loads each form post, so we
    // cap the full sweep to once per few minutes via a transient.
    if (function_exists('polyglot_cf7_index_all_forms') && !get_transient('polyglot_cf7_backfilled')) {
        polyglot_cf7_index_all_forms();
        set_transient('polyglot_cf7_backfilled', 1, 5 * MINUTE_IN_SECONDS);
    }

    $cap   = 200;
    $forms = get_posts([
        'post_type'     => 'wpcf7_contact_form',
        'post_status'   => 'publish',
        'numberposts'   => $cap,
        'orderby'       => 'title',
        'order'         => 'ASC',
        'no_found_rows' => true,
    ]);

    // Surface truncation instead of silently capping (rare: >200 forms).
    $counts      = wp_count_posts('wpcf7_contact_form');
    $total_forms = isset($counts->publish) ? (int) $counts->publish : count($forms);

    $langs = polyglot_cf7_target_langs();
    ?>
    <div class="pgt-cf7-tab">
        <div class="pgt-card pgt-mb-12">
            <div class="pgt-section-head">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                <div class="pgt-section-head__text">
                    <h2 class="pgt-section-head__title"><?php esc_html_e('Contact Form 7 Translations', 'polyglot-translate'); ?></h2>
                </div>
            </div>
            <div class="pgt-card-body">
                <p class="pgt-text-sm pgt-text-muted" style="margin-top:0;">
                    <?php esc_html_e('Your forms are translated automatically on the frontend — labels, placeholders, buttons, messages and emails. Use the list below to review or refine each one. Mail tags like [your-email] are always kept intact.', 'polyglot-translate'); ?>
                </p>
            </div>
        </div>

        <?php if ($total_forms > $cap) : ?>
            <div class="pgt-notice pgt-warning pgt-mb-12">
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: number shown, 2: total number of forms */
                    __('Showing the first %1$d of %2$d contact forms.', 'polyglot-translate'),
                    (int) $cap,
                    (int) $total_forms
                ));
                ?>
            </div>
        <?php endif; ?>

        <?php if (empty($forms)) : ?>
            <div class="pgt-card">
                <div class="pgt-card-body" style="text-align:center;padding:32px;">
                    <span class="dashicons dashicons-email-alt" style="font-size:32px;width:32px;height:32px;opacity:.4;"></span>
                    <p class="pgt-text-muted"><?php esc_html_e('No contact forms found yet.', 'polyglot-translate'); ?></p>
                    <a class="pgt-btn pgt-btn-secondary pgt-btn-sm" href="<?php echo esc_url(admin_url('admin.php?page=wpcf7-new')); ?>">
                        <?php esc_html_e('Create a contact form', 'polyglot-translate'); ?>
                    </a>
                </div>
            </div>
        <?php else : ?>
            <div class="pgt-card">
                <table class="pgt-cf7-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Form', 'polyglot-translate'); ?></th>
                            <th style="width:90px;"><?php esc_html_e('Strings', 'polyglot-translate'); ?></th>
                            <?php foreach ($langs as $lang) : ?>
                                <th style="width:150px;">
                                    <?php if ($lang['flag'] !== '') : ?><span aria-hidden="true"><?php echo esc_html($lang['flag']); ?></span> <?php endif; ?>
                                    <?php echo esc_html($lang['name']); ?>
                                </th>
                            <?php endforeach; ?>
                            <th style="width:170px;"><?php esc_html_e('Actions', 'polyglot-translate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form) :
                            $progress = polyglot_cf7_form_progress((int) $form->ID);
                            $edit_form_url = add_query_arg(
                                ['page' => 'wpcf7', 'post' => (int) $form->ID, 'action' => 'edit'],
                                admin_url('admin.php')
                            );
                            ?>
                            <tr>
                                <td class="pgt-cf7-name">
                                    <strong>
                                        <a href="<?php echo esc_url($edit_form_url); ?>" title="<?php esc_attr_e('Open this form in Contact Form 7', 'polyglot-translate'); ?>">
                                            <?php echo esc_html($form->post_title !== '' ? $form->post_title : sprintf(/* translators: %d: form ID */ __('Form #%d', 'polyglot-translate'), (int) $form->ID)); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html(number_format_i18n($progress['total'])); ?></td>
                                <?php foreach ($langs as $lang) :
                                    $lp    = $progress['langs'][$lang['slug']] ?? ['translated' => 0, 'pct' => 0];
                                    $pct   = (int) $lp['pct'];
                                    $color = $pct >= 100 ? 'green' : ($pct > 0 ? 'yellow' : 'red');
                                    ?>
                                    <td>
                                        <a href="<?php echo esc_url(polyglot_cf7_editor_url((int) $form->ID, $lang['slug'])); ?>" class="pgt-cf7-prog" title="<?php echo esc_attr(sprintf(/* translators: 1: translated count, 2: total */ __('%1$d of %2$d strings translated — click to edit', 'polyglot-translate'), (int) $lp['translated'], (int) $progress['total'])); ?>">
                                            <span class="pgt-progress pgt-progress-sm" style="display:block;">
                                                <span class="pgt-progress-fill <?php echo esc_attr($color); ?>" style="width:<?php echo esc_attr($pct); ?>%"></span>
                                            </span>
                                            <span class="pgt-cf7-prog-label pgt-text-sm"><?php echo esc_html(sprintf(/* translators: 1: translated count, 2: total, 3: percent */ __('%1$d / %2$d (%3$d%%)', 'polyglot-translate'), (int) $lp['translated'], (int) $progress['total'], $pct)); ?></span>
                                        </a>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <a class="pgt-btn pgt-btn-primary pgt-btn-sm" href="<?php echo esc_url(polyglot_cf7_editor_url((int) $form->ID)); ?>">
                                        <?php esc_html_e('Edit translations', 'polyglot-translate'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* ==========================================================================
 * Asset: small CF7-specific stylesheet (enqueued on the Polyglot admin page).
 * ========================================================================== */

add_action('admin_enqueue_scripts', function ($hook) {
    // Only on the Polyglot Translate top-level page.
    if (!is_string($hook) || strpos($hook, 'polyglot-translate') === false) {
        return;
    }
    wp_enqueue_style(
        'polyglot-cf7-admin',
        POLYGLOT_CF7_URL . 'assets/cf7-admin.css',
        [],
        POLYGLOT_CF7_VERSION
    );
}, 20, 1);

/* ==========================================================================
 * (B) Native panel in the CF7 form editor.
 * ========================================================================== */

add_filter('wpcf7_editor_panels', function ($panels) {
    if (!is_array($panels)) {
        return $panels;
    }
    $panels['polyglot-translations'] = [
        'title'    => __('Translations', 'polyglot-translate'),
        'callback' => 'polyglot_cf7_editor_panel',
    ];
    return $panels;
}, 10, 1);

/**
 * Render the Polyglot panel inside the CF7 form editor.
 *
 * @param WPCF7_ContactForm $post The contact form being edited.
 */
function polyglot_cf7_editor_panel($post): void
{
    $form_id = is_object($post) && method_exists($post, 'id') ? (int) $post->id() : 0;

    echo '<h2>' . esc_html__('Polyglot Translate', 'polyglot-translate') . '</h2>';

    if ($form_id <= 0) {
        echo '<p>' . esc_html__('Save this form first, then translations will appear here.', 'polyglot-translate') . '</p>';
        return;
    }

    // Ensure the form is indexed so the status reflects its current strings.
    if (function_exists('polyglot_cf7_index_form')) {
        polyglot_cf7_index_form($form_id);
    }

    $progress = polyglot_cf7_form_progress($form_id);
    $langs    = polyglot_cf7_target_langs();

    echo '<p>' . esc_html__('This form is translated automatically by Polyglot Translate. Review or refine each language below.', 'polyglot-translate') . '</p>';

    if (empty($langs)) {
        echo '<p><em>' . esc_html__('No target languages are configured in Polyglot Translate yet.', 'polyglot-translate') . '</em></p>';
    } else {
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($langs as $lang) {
            $lp  = $progress['langs'][$lang['slug']] ?? ['translated' => 0, 'pct' => 0];
            $row = sprintf(
                /* translators: 1: language name, 2: translated count, 3: total, 4: percent */
                esc_html__('%1$s — %2$d of %3$d strings (%4$d%%)', 'polyglot-translate'),
                esc_html($lang['name']),
                (int) $lp['translated'],
                (int) $progress['total'],
                (int) $lp['pct']
            );
            echo '<tr><th scope="row">' . ($lang['flag'] !== '' ? esc_html($lang['flag']) . ' ' : '') . esc_html($lang['name']) . '</th>';
            echo '<td><a class="button" href="' . esc_url(polyglot_cf7_editor_url($form_id, $lang['slug'])) . '">'
                . esc_html__('Edit', 'polyglot-translate') . '</a> '
                . '<span style="margin-inline-start:8px;">' . esc_html(sprintf(
                    /* translators: 1: translated count, 2: total, 3: percent */
                    __('%1$d / %2$d (%3$d%%)', 'polyglot-translate'),
                    (int) $lp['translated'],
                    (int) $progress['total'],
                    (int) $lp['pct']
                )) . '</span></td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<p><a class="button button-primary" href="' . esc_url(polyglot_cf7_editor_url($form_id)) . '">'
        . esc_html__('Open in Polyglot Translation Editor', 'polyglot-translate') . '</a></p>';
}
