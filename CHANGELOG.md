# ANT Translate CF7 – Changelog

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
