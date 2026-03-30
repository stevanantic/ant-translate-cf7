=== Polyglot Translate for Contact Form 7 ===
Contributors: polyglottranslate
Tags: translation, contact form 7, cf7, multilingual
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 3.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Translate Contact Form 7 forms, email templates, and messages with Polyglot Translate.

== Description ==

Polyglot Translate for Contact Form 7 integrates with Polyglot Translate to translate your Contact Form 7 form fields, email templates, validation messages, and success/error messages.

**Features:**

* Translate form body content (labels, placeholders, buttons)
* Translate email subject and body templates
* Translate CF7 messages (success, validation errors)
* Automatic scanning of CF7 forms
* Full integration with Polyglot Translate scanning and editor

**Requirements:**

* Polyglot Translate (free)
* Contact Form 7

== Installation ==

1. Install and activate Polyglot Translate (free base plugin).
2. Install and activate Contact Form 7.
3. Upload the `polyglot-translate-cf7` folder to `/wp-content/plugins/`.
4. Activate through the Plugins menu, or install directly from Polyglot Translate Settings > Addons.
5. Run a site scan to discover CF7 form strings.

== Changelog ==

= 3.1.0 =
* **Changed:** Full Polyglot rebrand — all prefixes renamed, main file renamed.
* **Added:** Auto-migration of old option keys and Flamingo submission meta.

= 3.0.0 =
* **Added:** One-Click Translate integration, Flamingo language tagging, mail_components safety net.
* **Changed:** Static cache, LRU eviction, Mail_2 active check.

= 1.1.0 =
* **Added:** AJAX response message translation (`wpcf7_ajax_json_echo`).
* **Added:** CF7 Conditional Fields plugin support (`wpcf7cf`).

= 1.0.0 =
* Initial release

== Frequently Asked Questions ==

= Do I need a Pro license? =
No. Polyglot Translate for Contact Form 7 is a free addon. You only need the free Polyglot Translate base plugin and Contact Form 7.

= How do I translate my forms? =
After activating the addon and running a scan (Dashboard > Rescan), your CF7 forms will appear in the Translate Manager under the Interface tab.
