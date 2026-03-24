# Polyglot Translate CF7 – Changelog

## [3.1.0] – 2026-03-24

### Changed
- **Full Polyglot rebrand** — all prefixes renamed from `ant_st_cf7_*` → `polyglot_cf7_*`, `ANT_CF7_*` → `PGT_CF7_*`. Text domain → `polyglot-translate-cf7`.
- **Main plugin file renamed** to `polyglot-translate-cf7.php`. Existing installations must reactivate.
- **Migration** — old option keys (`ant_st_addon_lic_cf7`, `ant_st_cf7_field_map`) and Flamingo submission meta (`_ant_st_submission_language`) auto-migrated on first load.

## [3.0.0] – 2026-03-17

### Added
- **One-Click Translate integration** — CF7 form labels, messages, and mail
  template text injected into core One-Click flow via
  `polyglot_one_click_catalog_strings` filter. Per-request static cache.
- **`wpcf7_mail_components` hook** — safety net that translates mail subject
  and body right before `wp_mail()`, catching dynamic content added by other
  plugins after property filters.
- **Flamingo language tagging** — CF7 submissions tagged with
  `_polyglot_submission_language` meta for language-based filtering.
- **CF7 compat header** — `CF7 requires at least: 5.2`.

### Changed
- **`polyglot_cf7_should_translate()` static cache** — evaluated once per request.
- **Cache limit** — 2000 entries with LRU eviction + short string guard (< 2 chars).
- **Mail_2 active check** — only translates autoresponder when `active` is true.

## [1.1.0] – 2026-02-15

### Added
- **AJAX response message translation** — `wpcf7_ajax_json_echo` filter.
- **Conditional Fields plugin support** — `wpcf7cf_form_conditions` filter.

## [1.0.0] – 2026-02-15

### Initial Release
- Contact Form 7 form body translation (labels, placeholders, buttons).
- Email subject and body template translation.
- CF7 validation and success message translation.
- Automatic integration with Polyglot Translate scanner.
