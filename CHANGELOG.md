# ANT Translate CF7 – Changelog

## [3.0.0] – 2026-03-17

### Added
- **One-Click Translate integration** — CF7 form labels, messages, and mail
  template text injected into core One-Click flow via
  `ant_st_one_click_catalog_strings` filter. Per-request static cache.
- **`wpcf7_mail_components` hook** — safety net that translates mail subject
  and body right before `wp_mail()`, catching dynamic content added by other
  plugins after property filters.
- **Flamingo language tagging** — CF7 submissions tagged with
  `_ant_st_submission_language` meta for language-based filtering.
- **CF7 compat header** — `CF7 requires at least: 5.2`.
- **V4 Integration Plan** — `V4-INTEGRATION-CF7.md` (7 phases, 24 test cases).

### Changed
- **`ant_st_cf7_should_translate()` static cache** — evaluated once per request.
- **Cache limit** — 2000 entries with LRU eviction + short string guard (< 2 chars).
- **Mail_2 active check** — only translates autoresponder when `active` is true.
- Version bumped to 3.0.0.

## [1.1.0] – 2026-02-15

### Added
- **AJAX response message translation** — `wpcf7_ajax_json_echo` filter translates `$response['message']` and `invalid_fields[].message` for AJAX form submissions.
- **Conditional Fields plugin support** — `wpcf7cf_form_conditions` filter when CF7 Conditional Fields (`wpcf7cf_init` or `WPCF7CF_VERSION`) is active.

## [1.0.0] – 2026-02-15

### Initial Release
- Contact Form 7 form body translation (labels, placeholders, buttons).
- Email subject and body template translation.
- CF7 validation and success message translation.
- Automatic integration with ANT Translate scanner.
