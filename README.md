# Polyglot Translate for Contact Form 7

Contact Form 7 integration add-on for **Polyglot Translate**. This is a **free addon** — no license key required.

## Features

- **Form body** – Translate labels, placeholders, submit buttons, field options
- **Email templates** – Subject, body, sender, and mail_2 template translation
- **Messages** – Validation errors, success messages, CF7 display messages
- **AJAX responses** – `wpcf7_feedback_response` filter for AJAX form submission messages
- **Conditional Fields** – Support for CF7 Conditional Fields plugin (`wpcf7cf`)
- **Language detection** – Detects target language from page URL or HTTP Referer (for AJAX)
- **CF7 tag safety** – Mail tags like `[your-name]` are masked during translation and restored after

## Requirements

- WordPress 5.8+
- PHP 7.4+
- **Polyglot Translate** (free) must be installed and active
- **Contact Form 7** 5.2+ must be installed and active

## Installation

1. Upload `polyglot-translate-cf7` to `/wp-content/plugins/`
2. Activate the plugin
3. Run a site scan to discover CF7 form strings

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history.

## License

GPL-2.0-or-later
