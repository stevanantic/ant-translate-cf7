# ANT Translate for Contact Form 7

Contact Form 7 integration add-on for **ANT Translate**.

## Features

- **Form body** – Translate labels, placeholders, submit buttons, field options
- **Email templates** – Subject, body, sender, and mail_2 template translation
- **Messages** – Validation errors, success messages, CF7 display messages
- **AJAX responses** – `wpcf7_ajax_json_echo` filter for AJAX form submission messages
- **Conditional Fields** – Support for CF7 Conditional Fields plugin (`wpcf7cf`)
- **Language detection** – Detects target language from page URL or HTTP Referer (for AJAX)
- **Transient cache** – Cached form translation by content hash + language

## Requirements

- WordPress 5.8+
- PHP 7.4+
- **ANT Translate** (free) must be installed and active
- **Contact Form 7** must be installed and active
- Valid addon license key

## Installation

1. Upload `ant-translate-cf7` to `/wp-content/plugins/`
2. Activate the plugin
3. Enter your license key in **ANT Translate > Settings > Addons**
4. Run a site scan to discover CF7 form strings

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history.

### 1.1.0
- AJAX response message translation (`wpcf7_ajax_json_echo`)
- CF7 Conditional Fields plugin support (`wpcf7cf`)

### 1.0.0
- Initial release
- Form body, email templates, messages translation

## License

GPL-2.0-or-later
