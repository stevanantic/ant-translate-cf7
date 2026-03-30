# Polyglot Translate for Contact Form 7 - Addon

## Overview
CF7 integration addon. Version 3.1.0. FREE addon (no license required).
Translates: form fields, mail templates (with CF7 tag safety), messages, AJAX responses.
Requires: Polyglot Translate core + Contact Form 7 5.2+.

## Bootstrap
- Hooks `plugins_loaded` at priority 25
- Checks `POLYGLOT_VERSION` defined
- Registers: `polyglot_register_addon('cf7', ...)`
- NO license gate: loads hooks whenever CF7 (`WPCF7_VERSION`) is present

## Key Classes
- `PGT_CF7_License` - License handler (for settings panel display, not gating)

## Files
- `includes/hooks.php` - All CF7 translation hooks
- `includes/class-cf7-license.php` - License handler

## Migration (v3.1.0)
- Renames `ant_st_addon_lic_cf7` → `polyglot_addon_lic_cf7`
- Renames `ant_st_cf7_field_map` → `polyglot_cf7_field_map`
- Migrates Flamingo `_ant_st_submission_language` postmeta (batched 500)

## Integration with Core
- Core excludes `wpcf7_contact_form` post type when addon not registered
- CF7 tag placeholders preserved during translation (tag safety)
