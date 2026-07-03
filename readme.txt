=== Polyglot Translate for Contact Form 7 ===
Contributors: polyglottranslate
Tags: translation, contact form 7, cf7, multilingual
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 3.2.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Translate Contact Form 7 forms, email templates, and messages with Polyglot Translate.

== Description ==

Polyglot Translate for Contact Form 7 integrates with Polyglot Translate to translate your Contact Form 7 form fields, email templates, validation messages, and success/error messages.

**Features:**

* Translate form body content (labels, placeholders, buttons)
* Translate email subject and body templates (CF7 mail tags preserved)
* Translate CF7 messages (success, validation errors)
* Dedicated "Contact Forms" tab in Polyglot Translate with per-language progress per form
* A "Translations" panel inside the CF7 form editor linking straight to the translation editor
* Automatic discovery — forms become translatable without a frontend visit; re-indexed on save
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

= 3.2.2 =
* **Maintenance:** Version alignment and compatibility refresh for Polyglot Translate core 6.7.x.

= 3.2.1 =
* **Improved:** Clearer, more intuitive Contact Forms tab — the form name is now a link that opens the form in Contact Form 7 (the redundant "Open form" button is gone), the header icon/title alignment is fixed, and per-language progress now shows a readable "12 / 33 (36%)" count.
* **Fixed:** Coherent edit round-trip — opening a form's translations now shows a focused editor (the irrelevant Previous/Next/jump-to-page chrome is hidden for forms) and "Back" returns to the Contact Forms tab instead of the generic Translate manager.

= 3.2.0 =
* **Added:** Dedicated "Contact Forms" tab in the Polyglot Translate admin — lists every form with per-language translation progress and deep-links into the editor.
* **Added:** "Translations" panel inside the native CF7 form editor with status + a button to open the Polyglot editor for that form.
* **Added:** Form string discovery — each form is indexed into a `cf7:<id>` context so it is editable without a frontend visit; re-indexed on `wpcf7_save_contact_form` and enqueued for rescan; context removed on form delete. Captures text carried inside CF7 tags too (field placeholders, submit button labels, select/checkbox/radio option labels) via CF7's own form-tag parser, normalized (entity-decoded) to match the rendered output.
* **Changed:** One-Click bulk catalog now shares a single string collector with the editor catalog (per-form `cf7:<id>` context) so bulk and manual paths always agree.

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
