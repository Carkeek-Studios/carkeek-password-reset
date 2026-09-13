---
title: Custom-Styled Password Reset Shortcode (Replaces wp-login.php Flow)
type: feat
status: active
date: 2026-09-13
---

# Custom-Styled Password Reset Shortcode

## Overview

Gravity Forms User Registration handles account creation on GBN (sgbn) and other Carkeek Studios client sites, but its password-reset story is limited: there's no first-class way to run "forgot password" on a themed, on-brand page. This plan adds a small, reusable plugin — **Carkeek Password Reset** — that wraps WordPress core's own password-reset machinery in a single shortcode, so the entire forgot-password flow (request email → click link → set new password) happens on a normal themed page instead of `wp-login.php`.

Built once, used on any Carkeek Studios site: the plugin lives in the shared plugin ecosystem at `aashared/carkeek-password-reset/` — its own repo, following the same pattern as `aashared/carkeek-blocks` — and is symlinked into each site's `wp-content/plugins/` (GBN first, others as needed), distributed/updated the same way the other Carkeek plugins are (Git Updater headers).

No new password-reset logic is invented. The plugin is a thin, styled front end over WordPress core's `retrieve_password()`, `check_password_reset_key()`, and `reset_password()` — the same functions `wp-login.php` itself calls (verified directly against the GBN install's `wp-includes/user.php` and `wp-login.php`, see Sources). Emails stay 100% core-default (not customizable at this stage, per requirement).

## Problem Statement / Motivation

- Gravity Forms User Registration doesn't offer a themed password-reset page.
- WordPress's built-in flow dumps the user on `wp-login.php`, which doesn't match the site's design system (referred to by the client as "the popup").
- Account-enumeration safety matters: the confirmation message must be identical whether or not the submitted email matches an existing account, and no email should go out for a non-existent account.
- This need isn't specific to GBN — it's generic enough (and likely to recur) to build once as a shared plugin rather than a one-off.

## Proposed Solution

**One shortcode, one page, two views** — mirroring the reference UX from Ultimate Member's password-reset shortcode (cited by the client), which self-switches between "request" and "set new password" views on a single page rather than needing two separate shortcodes/pages.

`[carkeek_password_reset]`

- **View A — Request** (default): one field, **Email Address**. On submit:
  - Calls core `retrieve_password( $email )`. Return value (`true` or `WP_Error`) is **never** shown to the user or used to change the response — this is what prevents account enumeration. If a matching account exists, core sends its normal password-reset email itself (via `wp_mail()` inside `retrieve_password()`); if not, nothing is sent.
  - Regardless of outcome, render the same generic message: *"If an account exists for that email address, we've sent a password reset link. Please check your inbox (and spam folder)."*
- **View B — Set New Password**: rendered when the page is visited via the emailed reset link (mirrors core's own `wp-login.php?action=rp` handling in `wp-login.php:938-1002`, using the same cookie handoff — see Technical Considerations). Two fields: **New Password**, **Confirm Password**. On submit:
  - Re-validates the key via `check_password_reset_key()`.
  - Checks the two password fields match and aren't blank/whitespace-only (same checks core does).
  - Calls core `reset_password( $user, $new_pass )`, which also clears the used key (`wp_set_password()` blanks `user_activation_key` — `wp-includes/pluggable.php:3113` — so the link can't be replayed).
  - Shows a success message with a link to the site's `login` page.

Markup mirrors each site's existing account-form conventions (on GBN: `<fieldset><label>…</label><div class="field"><input class="input-text" …></div></fieldset>`, `<a class="button">` submits — see `themes/listable/job_manager/account-signin.php`) so the form looks native without custom CSS. Since the plugin is meant to be portable across sites with different themes, keep the base markup semantic/unstyled-by-default (plain `<label>`/`<input>`/`<button>`) and layer GBN-specific classes via a small, overridable template rather than hardcoding them — see Technical Considerations.

## Technical Considerations

### Grounding: exact core functions used (read directly from the GBN install)

| Function | Location | Behavior |
|---|---|---|
| `retrieve_password( $user_login = '' )` | `wp-includes/user.php:3243` | Looks up by email or login, generates a reset key, emails the **core default** message (includes a `wp-login.php?login=…&key=…&action=rp` link — see below), returns `true`/`WP_Error`. |
| `get_password_reset_key( $user )` | `wp-includes/user.php:3081` | Called internally by `retrieve_password()`; not called directly by this plugin. |
| `check_password_reset_key( $key, $login )` | `wp-includes/user.php:3157` | Returns `WP_User` on valid/unexpired key, `WP_Error` (`invalid_key` / `expired_key`) otherwise. Expiration is filterable via `password_reset_expiration` (default `DAY_IN_SECONDS`). |
| `reset_password( $user, $new_pass )` | `wp-includes/user.php:3492` | Sets the new password via `wp_set_password()`, which also blanks `user_activation_key`, invalidating the link. Fires `password_reset` / `after_password_reset`. |

These are core `wp-includes/user.php` pluggable-style functions available on every standard WordPress install, so nothing here is GBN-specific.

### Redirecting the emailed link to our page, not wp-login.php

The reset link is built *inside* `retrieve_password()` before the `retrieve_password_message` filter runs (`wp-includes/user.php:3417`), but that filter receives the full message string and can return a modified one. The plugin hooks `retrieve_password_message` and replaces the `wp-login.php?login=…&key=…&action=rp` URL with the reset page's own permalink carrying the same `login` and `key` query args. Because the plugin needs to work on more than one site, the reset page isn't hardcoded — resolve it via a `carkeek_password_reset_page_url` filter, defaulting to `get_page_by_path( 'reset-password' )` (this slug is already the established convention on GBN — referenced as `{reset_password}` in `plugins/gbn-directory/includes/class-gbnd-emails.php:266`), falling back to `home_url()` if that page doesn't exist so the plugin never fatals on a site that hasn't set the slug up yet.

### Matching core's own key-handling security pattern (view B)

`wp-login.php`'s `resetpass`/`rp` handler (`wp-login.php:938-1002`) deliberately does **not** keep `key`/`login` in the URL for the password-entry step — on first load it moves them into a short-lived cookie (`wp-resetpass-{COOKIEHASH}`) and redirects to a clean URL, then the POST includes a hidden `rp_key` field checked against the cookie via `hash_equals()`. This avoids the reset key leaking through browser history, Referer headers, or server/proxy access logs. This plugin replicates the same pattern for View B rather than passing the key through the page's own URL/form state.

### Enumeration safety

- View A's response text and HTTP behavior are identical whether or not the account exists — this is the core requirement and is a one-line rule (never branch UI on `retrieve_password()`'s return value), not something needing extra library support.
- No timing-equalization is planned (e.g., artificial delay when no email is sent) — out of scope per the "simple plugin" brief; GBN already has Wordfence installed site-wide (`wp-content/wflogs/`), which provides general brute-force/rate-limit coverage at the login layer. (Other sites this plugin ships to may not have Wordfence — note this as a per-site consideration, not something the plugin itself needs to solve.)

### CSRF hardening

Both views include a `wp_nonce_field()` (View A: `carkeek_pwreset_request`; View B: `carkeek_pwreset_setpass`), verified on POST before calling into core. Core's own `wp-login.php` reset form relies solely on the cookie handshake above and has no nonce; adding one here is cheap extra hardening since the site now trusts an installable page rather than a login gate.

### Site-wide funneling (not just our own link)

To make sure *every* "lost your password" entry point lands on the new page (not just links we control):
- Filter `lostpassword_url` to point at the reset page, so WooCommerce/theme/GF-provided "Lost your password?" links pick it up automatically (note: on GBN, `themes/listable/inc/extras.php:476` already touches this filter for WooCommerce — the new filter must stack with, not replace, that behavior; verify order during implementation).
- Redirect `wp-login.php?action=lostpassword` and `wp-login.php?action=rp|resetpass` (via `login_form_lostpassword` / `login_form_rp` / `login_form_resetpass` action hooks) to the reset page, preserving `login`/`key` query args for the rp case, so anyone who lands on the old core URLs directly (bookmarks, old emails, third-party links) is bounced to the styled page instead.

### Portability across sites

- No hard dependency on GBN-specific plugins (`gbn-directory`, `gravityformsuserregistration`, etc.) — the plugin only touches WordPress core user APIs.
- Site-specific bits (reset-page slug, login-page slug, form markup/classes) go through filters/a template-override mechanism (mirroring `carkeek-blocks`' `class-carkeekblocks-template-loader.php` pattern — a theme or another plugin can override the shortcode's template by dropping a same-named file in its own `carkeek-password-reset/` template directory), not hardcoded GBN assumptions.

## System-Wide Impact

- **Interaction graph:** Shortcode render → (View A) form POST → `retrieve_password()` → core's `wp_mail()` → (later) user clicks emailed link → cookie handoff (mirroring `wp-login.php`) → (View B) form POST → `check_password_reset_key()` → `reset_password()` → `wp_set_password()` (clears key, fires `wp_set_password`/`password_reset`/`after_password_reset` actions — nothing else on GBN currently hooks those, confirmed via repo grep).
- **Error propagation:** All core-side errors (`WP_Error` from `retrieve_password()`, `check_password_reset_key()`) are caught and converted to either the generic View A message (never surfaced) or plain View B validation messages (invalid/expired link, passwords don't match) — never raw `WP_Error` text, which can hint at account existence.
- **State lifecycle risk:** A reset key is single-use by construction (core clears `user_activation_key` on `wp_set_password()`); no plugin-side state to orphan.
- **API surface parity:** `wp-login.php`'s native lostpassword/resetpass screens remain functional as a fallback (core isn't disabled) but are redirected away from per the funneling above — verify no other integration (GF User Registration, WooCommerce account page) hard-links to `wp-login.php?action=lostpassword` in a way that bypasses the redirect hook.
- **Integration test scenarios:**
  1. Existing account, correct email → generic message shown, real email received, link works, password changes, old link becomes unusable after use.
  2. Non-existent email → identical generic message shown, **no email sent** (verify via mail log, not just UI).
  3. Reset link older than 24h (`password_reset_expiration` default) → View B shows "expired" message, not core's raw error.
  4. Tampered/garbage `key` or `login` in the URL → View B shows "invalid" message, no user lookup leaks.
  5. Direct visit to `wp-login.php?action=lostpassword` and to a valid `…&action=rp` URL → both redirect to the new page with equivalent state preserved.

## Acceptance Criteria

- [ ] New plugin created at `aashared/carkeek-password-reset/carkeek-password-reset.php`, following the `carkeek-blocks` template: Git Updater headers (`GitHub Plugin URI`, `Primary Branch`), singleton bootstrap class `CarkeekPasswordReset`, `includes/class-carkeekpasswordreset-*.php` file/class naming (see `aashared/carkeek-blocks/includes/`), own GitHub repo (e.g. `pattyok/carkeek-password-reset`).
- [ ] Symlinked into GBN at `app/public/wp-content/plugins/carkeek-password-reset`, matching how `carkeek-blocks` / `carkeek-blocks-custom-links` are already symlinked in from `aashared/`. No change needed to GBN's `wp-content/.gitignore` whitelist — the symlink target lives outside that repo entirely, same as the other `aashared` plugins.
- [ ] `[carkeek_password_reset]` shortcode renders View A (email field) by default.
- [ ] View A: submitting any email (existing or not) always shows the same confirmation copy; no branching on `retrieve_password()`'s return value in the response.
- [ ] Email is sent only for existing accounts (i.e., relies entirely on core `retrieve_password()` — no custom "does this user exist" pre-check that could itself leak timing/behavior differences beyond what core already does).
- [ ] Reset email's link points at the reset page (not `wp-login.php`), via a `retrieve_password_message` filter, defaulting to the `reset-password` page slug already referenced in GBN's `class-gbnd-emails.php`, overridable per-site via `carkeek_password_reset_page_url`.
- [ ] View B replicates core's cookie-based key handoff (no `key`/`login` surviving in the page URL/history once loaded) and hidden `rp_key` re-check on POST.
- [ ] View B validates: key present & valid (not expired/invalid), passwords match, password not blank/whitespace-only — each with a plain-language message, none of which reveal account existence beyond "this link isn't valid."
- [ ] Successful reset calls core `reset_password()` and shows a success message linking to the site's `login` page (page slug also filterable).
- [ ] `lostpassword_url` filter and `wp-login.php` action=lostpassword/rp/resetpass redirects implemented, confirmed not to break GBN's existing WooCommerce `lostpassword_url` filter in `themes/listable/inc/extras.php:476`.
- [ ] `wp_nonce_field()`/`wp_verify_nonce()` on both View A and View B form submissions.
- [ ] Base markup is theme-agnostic (plain semantic HTML) with a template-override mechanism, so a second site with a different theme doesn't need code changes to look native.
- [ ] Manual QA on GBN: all 5 integration scenarios in "System-Wide Impact" above pass on a real (or Mailhog-style local) email flow.
- [ ] Confirms a page exists at slug `reset-password` with the `[carkeek_password_reset]` shortcode inserted on GBN (create it if it doesn't already exist — it's referenced but its existence hasn't been verified in this planning pass, see Dependencies).

## Success Metrics

- Password-reset requests and completions happen entirely on-site (zero visits to `wp-login.php` for this flow, verifiable via analytics/redirect logging if desired — not required for v1).
- No support/security reports of account-existence being inferable from the reset form's response.
- Plugin installs on a second Carkeek Studios site with zero code changes — only a page + shortcode + (optionally) filter overrides.

## Dependencies & Risks

- **Unverified page assumption:** This plan assumes a page with slug `reset-password` (and `login`) already exists on GBN, based on the `{reset_password}` / `{login}` placeholders already wired into `plugins/gbn-directory/includes/class-gbnd-emails.php:264-266`. This wasn't confirmed against the live database during planning (no DB CLI access in this pass) — confirm at implementation start; create the page if missing.
- **`lostpassword_url` filter ordering (GBN-specific):** `themes/listable/inc/extras.php:476` already removes WooCommerce's own `lostpassword_url` filter — the new plugin's filter needs to be verified against that removal so the two don't fight. Other sites may have their own conflicting filters; treat this as a per-site smoke-test item, not a one-time check.
- **Dormant `login-with-ajax` modal (GBN-specific):** `themes/listable/plugins/login-with-ajax/modal/widget_out.php` exists in the theme but no lostpassword/retrieve_password references were found tied to it — likely unused, but worth a quick manual check that it isn't the "popup" providing a competing lost-password flow somewhere on the site.
- **No rate limiting beyond what the host site provides:** Acceptable per the "simple plugin" brief; flagged here rather than built, per the discussion above. Sites without Wordfence (or similar) have no brute-force protection on this form beyond what WordPress core itself does.

## Sources & References

### Internal (read directly from the GBN install/repo)

- `wp-includes/user.php:3081` (`get_password_reset_key`), `:3157` (`check_password_reset_key`), `:3243` (`retrieve_password`), `:3492` (`reset_password`)
- `wp-includes/pluggable.php:3099-3131` (`wp_set_password`, confirms key is cleared on reset)
- `wp-login.php:938-1002` (core's own `resetpass`/`rp` cookie-handoff pattern to mirror)
- `plugins/gbn-directory/includes/class-gbnd-emails.php:264-266` (existing `{login}` / `{reset_password}` / `{account_link}` page-slug placeholders — establishes the page-slug convention this plan defaults to)
- `aashared/carkeek-blocks/carkeek-blocks.php` and `aashared/carkeek-blocks/includes/class-carkeekblocks-*.php` (naming/structure precedent for this new shared plugin: Git Updater headers, singleton bootstrap, `class-carkeekblocks-*` file/class convention, `class-carkeekblocks-template-loader.php` for the override mechanism)
- `aashared/CLAUDE.md` (documents the shared-plugin ecosystem and the requirement to preserve Git Updater headers)
- `themes/listable/job_manager/account-signin.php` (GBN's existing account-form markup conventions: `fieldset` / `.field` / `.input-text` / `a.button` — used as the *default* override template, not baked into the plugin core)
- `themes/listable/inc/extras.php:476` (existing `lostpassword_url` filter removal for WooCommerce on GBN — must coordinate with)
- `wp-content/wflogs/` (confirms Wordfence is active on GBN — cited as GBN's existing brute-force/rate-limit layer)

### External (secondary confirmation only — primary source was the GBN install's own core code)

- [Build a Custom WordPress User Flow — Part 3: Password Reset (Envato Tuts+)](https://code.tutsplus.com/build-a-custom-wordpress-user-flow-part-3-password-reset--cms-23811t) — confirms the general request/verify/complete shape using these same core functions.
- Ultimate Member's single-shortcode, dual-view password-reset UX — cited by the client as the reference pattern this plan's "one shortcode, two views" design follows, without adopting Ultimate Member itself.
