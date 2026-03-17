# V4 Integration Plan — ANT Translate for Contact Form 7

> **Version:** 1.0 | **Created:** 2026-03-17 | **Current addon version:** 2.0.0
> **Status:** PLANNED
>
> **Goal:** Close feature gaps with WPML/TranslatePress, integrate with ATC V4,
> and ensure every CF7 form, mail template, and validation message translates
> flawlessly — including AJAX responses, pipes, quizzes, and Flamingo.
>
> **Principle:** On-the-fly translation (no form duplication), safe tag masking,
> lazy language detection. Free addon — no license required.

---

## Table of Contents

0. [Current State Assessment](#0-current-state-assessment)
1. [Phase 1: Critical Fixes & Hardening](#phase-1-critical-fixes--hardening)
2. [Phase 2: One-Click Translate Integration](#phase-2-one-click-translate-integration)
3. [Phase 3: Enhanced Mail Translation](#phase-3-enhanced-mail-translation)
4. [Phase 4: Pipe & Quiz Support](#phase-4-pipe--quiz-support)
5. [Phase 5: Flamingo Language Tagging](#phase-5-flamingo-language-tagging)
6. [Phase 6: Performance & Caching](#phase-6-performance--caching)
7. [Phase 7: Compatibility & Testing](#phase-7-compatibility--testing)
8. [Competitor Gap Analysis](#competitor-gap-analysis)
9. [Master Checklist](#master-checklist)

---

## 0. Current State Assessment

### What Works Well (v2.0.0)

| Area | Coverage | Notes |
|------|----------|-------|
| Form labels/placeholders/buttons | Full | `wpcf7_form_elements` filter + DOM parse |
| Select/radio/checkbox option text | Full | Via DOM text node translation |
| Mail subject + body | Full | Tag masking + safe translate |
| Mail sender display name | Full | Parses "Name <email>" format |
| Mail_2 (autoresponder) | Full | Same as primary mail |
| Validation messages | Full | `wpcf7_contact_form_property_messages` |
| Success/error messages | Full | `wpcf7_display_message` fallback |
| AJAX response messages | Full | `wpcf7_feedback_response` (CF7 5.2+) |
| Per-field validation errors | Full | `invalid_fields[].message` in response |
| Backward compat (CF7 < 5.2) | Full | `wpcf7_ajax_json_echo` fallback |
| CF7 tag masking | Full | `[tags]` → `{{ANT_CF7_TAG_N}}` |
| Language detection for AJAX/REST | Full | HTTP_REFERER fallback |
| Request-level cache | Full | Static array per request |
| Double-translation prevention | Full | Static flag between hooks |
| Editor mode guard | Full | Skips admin (non-AJAX) context |

### What's MISSING

| # | Gap | Impact | Competitor Parity |
|---|-----|--------|-------------------|
| 1 | No One-Click integration | CF7 strings not in one-click flow | ANT V4 |
| 2 | No lazy language detection | Hooks registered at load time | Pattern issue |
| 3 | Pipe "before" values not separately translated | Display labels in dropdowns may miss | WPML ✅ |
| 4 | Quiz questions not translated | Quiz module unusable in secondary lang | WPML ✅ |
| 5 | No Flamingo language tagging | Submissions not segregated by language | WPML ✅ Polylang ✅ |
| 6 | `wpcf7_mail_components` hook unused | Mail translation only via property filter, misses dynamic content | WPML ✅ |
| 7 | Acceptance checkbox text translation | Content attribute in form tag not explicitly handled | WPML ✅ |
| 8 | No cache size limit | Unbounded static cache | Performance concern |
| 9 | File upload labels not explicitly tested | May work via DOM, needs verification | WPML ✅ |
| 10 | CF7 Conditional Fields plugin | Group labels/content not verified | Partial |
| 11 | No version header for CF7 compat | Missing `CF7 requires at least` | Compat |

---

## Phase 1: Critical Fixes & Hardening

> **Goal:** Align with WC/Elementor addon patterns from audit learnings.
> **Effort:** 1 session | **Risk:** Low | **Priority:** CRITICAL

### Task 1.1: Lazy Language Detection

Move `ant_st_cf7_should_translate()` from registration-time guard to per-callback check.
Cache result in static variable (evaluated once per request).

### Task 1.2: Add `wpcf7_mail_components` Hook

Currently mail translation happens only via `wpcf7_contact_form_property_mail`.
Add `wpcf7_mail_components` as a safety net — this fires right before `wp_mail()`,
catching any dynamic content that was added after property filters.

```php
add_filter('wpcf7_mail_components', function ($components, $form, $mail) {
    if (!ant_st_cf7_should_translate()) return $components;

    if (isset($components['subject']) && is_string($components['subject'])) {
        $components['subject'] = ant_st_cf7_safe_translate_mail_text($components['subject']);
    }
    if (isset($components['body']) && is_string($components['body'])) {
        $components['body'] = ant_st_cf7_safe_translate_mail_text($components['body']);
    }
    // Never touch: recipient, additional_headers, attachments
    return $components;
}, 20, 3);
```

### Task 1.3: Cache Size Limit

Add 2000-entry limit with LRU eviction to `ant_st_cf7_translate()`.

### Task 1.4: Version Bump + CF7 Compat Header

```
* Version: 3.0.0
* CF7 requires at least: 5.2
```

### Phase 1 Checklist
- [ ] 1.1 Lazy language detection with static cache
- [ ] 1.2 `wpcf7_mail_components` safety net hook
- [ ] 1.3 Cache size limit (2000 entries)
- [ ] 1.4 Version bump + CF7 compat header

---

## Phase 2: One-Click Translate Integration

> **Goal:** CF7 form strings participate in One-Click Translate flow.
> **Effort:** 1 session | **Risk:** Low | **Priority:** HIGH

### Task 2.1: Register CF7 Strings with One-Click Catalog

Hook into `ant_st_one_click_catalog_strings` to inject:
1. All form labels/placeholders/buttons from `_form` meta
2. All validation/status messages from `_messages` meta
3. Mail subject and body text (with tags stripped)
4. Acceptance checkbox text

```php
add_filter('ant_st_one_click_catalog_strings', function (array $strings, array $seen) {
    $forms = get_posts([
        'post_type'   => 'wpcf7_contact_form',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
    ]);

    foreach ($forms as $form_id) {
        $cf7 = wpcf7_contact_form($form_id);
        if (!$cf7) continue;

        // Extract text from form template (labels, placeholders).
        $form_text = $cf7->prop('form');
        // Strip CF7 tags, keep text.
        $plain = preg_replace('/\[[^\]]+\]/', '', $form_text);
        $plain = wp_strip_all_tags($plain);
        foreach (preg_split('/[\r\n]+/', $plain) as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) >= 2) {
                $key = md5($line);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $strings[] = ['text' => $line, 'context' => 'cf7:' . $form_id, 'priority' => 2];
                }
            }
        }

        // Messages.
        $messages = $cf7->prop('messages');
        if (is_array($messages)) {
            foreach ($messages as $msg) {
                if (is_string($msg) && $msg !== '') {
                    $key = md5($msg);
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $strings[] = ['text' => $msg, 'context' => 'cf7:messages', 'priority' => 2];
                    }
                }
            }
        }

        // Mail subject + body (strip tags first).
        foreach (['mail', 'mail_2'] as $mail_prop) {
            $mail = $cf7->prop($mail_prop);
            if (!is_array($mail)) continue;
            foreach (['subject', 'body'] as $field) {
                if (isset($mail[$field]) && is_string($mail[$field])) {
                    $text = preg_replace('/\[[^\]]+\]/', '', $mail[$field]);
                    $text = trim(wp_strip_all_tags($text));
                    if ($text !== '' && mb_strlen($text) >= 3) {
                        $key = md5($text);
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $strings[] = ['text' => $text, 'context' => 'cf7:mail', 'priority' => 3];
                        }
                    }
                }
            }
        }
    }

    return $strings;
}, 10, 2);
```

### Phase 2 Checklist
- [ ] 2.1 Register CF7 strings with One-Click
- [ ] Verify strings appear in One-Click progress

---

## Phase 3: Enhanced Mail Translation

> **Goal:** Bulletproof mail translation — both property-level and component-level.
> **Effort:** 1 session | **Risk:** Low | **Priority:** HIGH

### Task 3.1: Mail Components Hook (done in Phase 1.2)

### Task 3.2: Sender Name Edge Cases

Current `ant_st_cf7_translate_sender_name()` handles `"Name <email>"` format.
Also handle:
- `[_site_title] <email>` (CF7 tag as name)
- Plain email (no name part) — skip

### Task 3.3: Mail_2 Active Check

Only translate mail_2 components when `$mail['active']` is true.

### Phase 3 Checklist
- [ ] 3.1 wpcf7_mail_components hook
- [ ] 3.2 Sender name edge cases
- [ ] 3.3 Mail_2 active check

---

## Phase 4: Pipe & Quiz Support

> **Goal:** Translate pipe display values and quiz questions.
> **Effort:** 1 session | **Risk:** Medium | **Priority:** MEDIUM

### Task 4.1: Pipe "Before" Value Translation

CF7 pipes: `"Option A|value_a"` — display "Option A", submit "value_a".
Currently DOM translation handles the rendered `<option>` text, which translates "Option A".
But the pipe "after" value must stay unchanged for form processing.

**Verify:** Current DOM-based translation correctly translates only the displayed text.
The pipe system is handled internally by CF7 — by the time HTML renders, only "before" values
appear in the DOM. Our DOM translation should work correctly. **Verify with test.**

### Task 4.2: Quiz Question Translation

CF7 quiz uses pipes: `"What is 1+1?|2"`. The question is the "before" value.
In the rendered HTML, the question appears as text. Our DOM translation should
handle this. **Verify with test.**

Quiz answer validation uses the pipe "after" value, which is never shown in HTML.
So translation should not affect validation. **Verify.**

### Phase 4 Checklist
- [ ] 4.1 Verify pipe display values translate correctly
- [ ] 4.2 Verify quiz questions translate, answers validate

---

## Phase 5: Flamingo Language Tagging

> **Goal:** Tag CF7 submissions with language for Flamingo segregation.
> **Effort:** 1 session | **Risk:** Low | **Priority:** MEDIUM

### Task 5.1: Add Language Meta to Flamingo Submissions

```php
add_action('wpcf7_submit', function ($form, $result) {
    if (!class_exists('Flamingo_Inbound_Message')) return;
    if (!function_exists('ant_st_current_lang')) return;

    $lang = ant_st_current_lang();
    if ($lang === '') return;

    // Store language in the submission meta.
    // Flamingo stores submissions as posts — we can add meta after save.
    add_action('flamingo_add_inbound_message', function ($message_id) use ($lang) {
        if ($message_id > 0) {
            update_post_meta($message_id, '_ant_st_submission_language', $lang);
        }
    });
}, 10, 2);
```

### Phase 5 Checklist
- [ ] 5.1 Add language meta to Flamingo submissions
- [ ] Verify meta is saved and queryable

---

## Phase 6: Performance & Caching

> **Goal:** Minimize overhead on form-heavy pages.
> **Effort:** 30 min | **Risk:** Low | **Priority:** LOW

### Task 6.1: Cache Size Limit (done in Phase 1.3)

### Task 6.2: Skip Translation for Empty/Short Strings

Add guard in `ant_st_cf7_translate()`:
```php
if (mb_strlen($text, 'UTF-8') < 2) return $text;
```

### Phase 6 Checklist
- [ ] 6.1 Cache limit
- [ ] 6.2 Short string guard

---

## Phase 7: Compatibility & Testing

> **Goal:** Verify compatibility and create test matrix.
> **Effort:** 1 session | **Risk:** Low | **Priority:** MEDIUM

### Test Matrix

```
[ ] Text field — label + placeholder translated
[ ] Email field — label + placeholder translated
[ ] URL field — label + placeholder translated
[ ] Textarea — label + placeholder translated
[ ] Select dropdown — label + all options translated
[ ] Checkbox — label + all options translated
[ ] Radio — label + all options translated
[ ] Acceptance checkbox — label text translated
[ ] File upload — label translated
[ ] Submit button — text translated
[ ] Quiz — question translated, answer validates
[ ] Pipe values — display translated, submitted value unchanged
[ ] Success message — translated in AJAX response
[ ] Validation error — translated in AJAX response
[ ] Per-field error — translated in AJAX response
[ ] Mail subject — translated with tags preserved
[ ] Mail body — translated with tags preserved
[ ] Mail_2 subject/body — same as primary mail
[ ] Sender display name — translated, email preserved
[ ] Special mail tags — preserved ([_site_title], [_date], etc.)
[ ] Multiple forms on one page — each translated independently
[ ] Form in popup (Elementor) — translated correctly
[ ] Primary language — no translation overhead
[ ] CF7 editor — no translation (admin context)
```

### Phase 7 Checklist
- [ ] Run through complete test matrix
- [ ] Document any issues

---

## Competitor Gap Analysis

### After V4: Feature Comparison

| Feature | WPML | TranslatePress | Weglot | Polylang | **ANT v4** |
|---------|------|----------------|--------|----------|------------|
| Form labels | ✅ (per form) | ✅ (visual) | ✅ (auto) | ✅ (per form) | **✅** |
| Placeholders | ✅ | ✅ | ✅ | ✅ | **✅** |
| Select/radio/checkbox | ✅ | ✅ | ✅ | ✅ | **✅** |
| Submit button | ✅ | ✅ | ✅ | ✅ | **✅** |
| Validation messages | ✅ | ✅ (gettext) | ✅ (DOM) | ✅ | **✅** |
| Success message | ✅ | ✅ (gettext) | ✅ (DOM) | ✅ | **✅** |
| **Mail subject/body** | ✅ | **NO** | **NO** | ✅ | **✅** |
| **Mail_2 (autoresponder)** | ✅ | **NO** | **NO** | ✅ | **✅** |
| Acceptance text | ✅ | ✅ | ✅ | ✅ | **✅** |
| Quiz Q&A | ✅ | Partial | Partial | ✅ | **✅** (verify) |
| Pipe display values | ✅ | ✅ | ✅ | ✅ | **✅** (verify) |
| Flamingo language tag | ✅ (auto) | No | No | ✅ (auto) | **✅** (Phase 5) |
| One-Click Translate | ❌ | ✅ | ✅ | ❌ | **✅** (Phase 2) |
| No form duplication | ❌ | ✅ | ✅ | ❌ | **✅** |
| Free addon | ❌ | ✅ | ❌ | ❌ | **✅** |

### Key ANT Advantages Over Competitors

1. **Mail translation WITHOUT form duplication** — TranslatePress and Weglot CANNOT translate mail templates. WPML/Polylang require duplicating the entire form. We do it on-the-fly with tag masking.
2. **Free addon** — no license cost for CF7 integration.
3. **One-Click Translate** — CF7 strings included in full-site translation.
4. **Tag masking safety** — CF7 `[tags]` never reach translation APIs.

---

## Master Checklist

### Phase 1: Fixes & Hardening
- [ ] 1.1 Lazy language detection
- [ ] 1.2 wpcf7_mail_components hook
- [ ] 1.3 Cache size limit
- [ ] 1.4 Version bump

### Phase 2: One-Click
- [ ] 2.1 Register CF7 strings

### Phase 3: Mail
- [ ] 3.2 Sender edge cases
- [ ] 3.3 Mail_2 active check

### Phase 4: Pipes & Quiz
- [ ] 4.1 Verify pipe translation
- [ ] 4.2 Verify quiz translation

### Phase 5: Flamingo
- [ ] 5.1 Language tagging

### Phase 6: Performance
- [ ] 6.2 Short string guard

### Phase 7: Testing
- [ ] Full test matrix

---

## Implementation Order

```
Session 1: Phase 1 (Fixes) + Phase 2 (One-Click) + Phase 3 (Mail)  — 2-3 hours
Session 2: Phase 4 (Verify) + Phase 5 (Flamingo) + Phase 6 (Perf)  — 1-2 hours
Session 3: Phase 7 (Testing)                                         — 1-2 hours
```

**Total estimated:** 3 sessions / ~6 hours

**Critical path:** Phase 1 → Phase 2 → Phase 3
