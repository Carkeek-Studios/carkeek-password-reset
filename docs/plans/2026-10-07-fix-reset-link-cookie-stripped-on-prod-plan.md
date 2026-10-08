---
title: "fix: Reset link falls back to request form on production (handoff cookie stripped)"
type: fix
status: active
date: 2026-10-07
---

# fix: Reset link falls back to request form on production

## Overview

On goodbusinessnetwork.org, clicking the emailed reset link lands on `/reset-password/`, the
`key`/`login` query args disappear, and the page shows the **email request form** instead of the
**set new password** form. It works locally. Excluding the page from cache did not help.

## Diagnosis (verified 2026-10-07)

**The disappearing query args are by design.** `CarkeekPasswordReset_Shortcode::handle_requests()`
(`includes/class-carkeekpasswordreset-shortcode.php:83-87`) copies `login:key` into a
`carkeek-pwreset-{COOKIEHASH}` cookie and 302s to the clean URL. `render()` then reads that cookie
to decide which view to show. **The bug is that on production the cookie never reaches PHP on the
follow-up request**, so `render()` falls through to `request-form`.

Evidence:

1. **Prod stack:** Cloudflare, then Cloudways Varnish (`x-cache: MISS`), plus the Breeze plugin.
   Local has none of these.
2. **Cookie test:** I sent `GET /reset-password/` with
   `Cookie: carkeek-pwreset-<md5(siteurl)>=nobody:BADKEY`, trying 7 siteurl variants. If PHP
   received the cookie, `render()` would show "This password reset link is invalid". **Every
   variant returned the email request form.** The edge layer strips unrecognized cookies before
   they reach PHP. Excluding the URL from cache doesn't change that: cookie stripping and cache
   exclusion are separate parts of the Varnish config, and Breeze's own exclusions don't affect
   Cloudways' server-level Varnish.
3. **Second issue: ProfilePress (`wp-user-avatar`) is active on prod but not local.**
   `PasswordResetTag` hooks `wp` (which runs before our `template_redirect`) and calls
   `FormProcessor::check_password_reset_key()` on every request that has `key` + `login`. For a
   valid key it returns and our code continues. For an invalid or expired key it redirects to
   `ppress_password_reset_url() . '?error=invalidkey'` (or `expiredkey`), which resolves to our
   page. Confirmed:
   `GET /reset-password/?key=DUMMY&login=x` returns `302` to `/reset-password/?error=invalidkey`.
   Our `render()` ignores `error`, so an expired link also shows the bare request form with no
   explanation.

## Proposed Solution

**Drop the cookie handoff. Render the set-password form directly on the emailed-link request.**
Carry `login`/`key` through the POST as hidden fields, and protect the URL with headers and an
early `history.replaceState`.

This works on any host, with no cookie allowlists or Varnish rules to depend on. The plugin is
meant to be reused across Carkeek client sites on different hosts, so that matters.

### Why not just rename the cookie?

Renaming it to a `wordpress_*` or `wp-resetpass-*` style name might get it past Cloudways' VCL. But
every host's allowlist differs (Pantheon, WP Engine, Kinsta, and Cloudways all use different
lists), and none of them can be tested locally. That approach would break again on the next host.
Record it as the fallback if the approach below gets rejected (see Alternatives).

### What the cookie was protecting, and how to keep that protection

wp-login.php uses the cookie so the key never sits in the URL of a rendered page. That keeps it
out of (a) browser history, (b) `Referer` headers sent to third-party assets, and (c) analytics
`page_location`. This page loads GTM, Instagram, Elementor, and other third-party scripts, so (c)
is a real risk. Mitigations, all on the reset page only:

| Leak | Mitigation |
|---|---|
| Referer to third parties | Send a `Referrer-Policy: no-referrer` header (`send_headers` / `wp_headers` when `is_reset_page()`) |
| GTM / GA `page_location` | Print an inline `history.replaceState(null, '', cleanUrl)` at `wp_head` priority `-9999` (before GTM's `<head>` snippet), so the URL is already clean when `gtm.js` fires |
| Browser history | The same `replaceState` replaces the history entry |
| Any cache storing the page | `nocache_headers()` and `DONOTCACHEPAGE` on the reset page (defensive; the page is already excluded) |

The key still shows up in server and CDN access logs for the first request. That is no different
from today, because the emailed link already contains the key and core's wp-login.php has the same
exposure.

## Implementation

### `includes/class-carkeekpasswordreset-shortcode.php`

- [x] **`handle_requests()`**: delete the `$_GET['key'], $_GET['login']` cookie+redirect block.
      When `is_reset_page()`, call `nocache_headers()`, define `DONOTCACHEPAGE`, and send
      `Referrer-Policy: no-referrer`.
- [x] **New `get_login_key()`** (replaces `get_cookie_login_key()`): returns `[login, key]` from
      POST `rp_login`/`rp_key` if present, otherwise from GET `login`/`key`, otherwise `null`.
      `render()` and `process_setpass_submission()` both use this one helper, so the lookup lives
      in one place (DRY).
- [x] **`process_setpass_submission()`**: read login/key via `get_login_key()` from POST. Drop the
      `hash_equals( $key, $_POST['rp_key'] )` cookie-vs-POST check, since the POSTed key now *is*
      the key and `check_password_reset_key()` validates it. Keep the nonce check. Remove both
      `clear_reset_cookie()` calls.
- [x] **`render()`**:
  - Keep the `reset=complete` branch first.
  - **New:** if `$_GET['error']` is `invalidkey` or `expiredkey`, render `reset-link-invalid`
    with the matching message. This handles ProfilePress's redirect, and wp-login.php uses the
    same convention.
  - If `get_login_key()` returns a pair, run `check_password_reset_key()`. Show
    `reset-link-invalid` on error, otherwise `reset-form` with `rp_key`, a new `rp_login`, and
    `errors`.
  - Leave the `reset=requested` and default branches unchanged.
- [x] Delete `COOKIE_PREFIX`, `cookie_name()`, `start_reset_cookie()`, `clear_reset_cookie()`.
- [x] **New `print_url_scrub()`** on `wp_head` at priority `-9999`: when `is_reset_page()` and
      `$_GET['key']` is set, print
      `<script>history.replaceState(null,'',<?php echo wp_json_encode( $clean_url ); ?>);</script>`,
      where `$clean_url = remove_query_arg( array( 'key', 'login' ) )`. Check how
      `duracelltomi-google-tag-manager` hooks `wp_head` and confirm our priority runs first.
- [x] Update the class docblock at the top of the file. It currently describes the cookie handoff.

### `templates/reset-form.php`

- [x] Add `<input type="hidden" name="rp_login" value="<?php echo esc_attr( $data->rp_login ); ?>">`
      next to the existing `rp_key` hidden field.
- [x] Set the form `action` to the clean page URL (`CarkeekPasswordReset_Pages::get_page_url()`),
      not the current URL. **Do not name the fields `key` or `login`.** ProfilePress reads
      `$_REQUEST['key']`/`['login']`, which includes POST data, so those names would trigger its
      redirect on submit.

### Docs

- [x] `README.md` "Security notes": replace the cookie-handoff bullet with the
      header/replaceState approach, and say why (host cookie stripping).
- [x] Note in `README.md` that `?error=invalidkey|expiredkey` is handled, for compatibility with
      ProfilePress and other plugins.

`includes/class-carkeekpasswordreset-redirects.php` and `class-carkeekpasswordreset-mailer.php`
don't change. They already pass `key`/`login` through on the URL.

## Implementation Notes (2026-10-07)

**Deviation from the plan: `?error=` became `?reset=invalidkey|expiredkey`.** During local testing,
`?error=invalidkey` never reached `render()`. WordPress core unsets `$_GET['error']` in
`WP::parse_request()` (`wp-includes/class-wp.php:282-290`), so ProfilePress's `?error=` redirect
can't be read reliably. Instead, `handle_requests()` now runs on **`wp` at priority 9**, ahead of
ProfilePress's `wp`:10 `check_password_reset_key()`. It rejects bad emailed-link keys itself and
redirects to `?reset=invalidkey|expiredkey`, which matches the plugin's existing `reset=` convention.
This also drops the bad key from the URL, and ProfilePress never gets the chance to redirect.

Verified locally against sgbn.local, using a real key minted for a throwaway user (since deleted):

- link: shows the set-password form, `Referrer-Policy: no-referrer`, `no-store`, and the
  replaceState script printed first in `<head>`
- mismatched passwords: error shown, form kept
- tampered `rp_key`: invalid-link message
- bad nonce: form re-shown, password not changed
- success: `302` to `?reset=complete`, new password verified
- reused link and garbage link: `302` to `?reset=invalidkey`, invalid message
- `?reset=expiredkey`: expired message
- default view, `reset=requested`, and request-form POST: working
- `wp-login.php?action=rp`: redirected to the page with key/login

Still to do: the prod-only acceptance items below.

## Acceptance Criteria

- [ ] Emailed link (valid key) shows the **set new password** form on prod (goodbusinessnetwork.org)
      and locally.
- [x] After the page loads, the address bar shows `/reset-password/` with no `key`/`login`.
- [x] The response for the link request includes `Referrer-Policy: no-referrer` and
      `Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private` (or stricter).
- [ ] GTM preview / GA4 DebugView: `page_location` for the reset page has **no** `key` param.
- [x] Mismatched passwords re-show the set-password form with the error, and the form still works
      (hidden fields are preserved).
- [x] A successful submit redirects to `?reset=complete` and the new password works at login.
- [ ] Expired or invalid link, with ProfilePress active (prod): shows "link is invalid/expired"
      instead of the bare email form.
- [x] Expired or invalid link, without ProfilePress (local): same message.
- [x] Reusing an already-used link shows the invalid message (`reset_password()` clears the key).
- [x] The request form, `?reset=requested`, and `wp-login.php?action=lostpassword|rp` redirects
      still work.

## Test Plan

1. **Local (LocalWP):** run through every acceptance criterion. To mirror prod's ProfilePress
   behavior, also hit `/reset-password/?error=invalidkey` directly.
2. **Prod smoke test, before and after:**
   `curl -sI "https://goodbusinessnetwork.org/reset-password/?key=x&login=y"` should still
   `302` to `?error=invalidkey` (from ProfilePress), and that URL should now show the invalid-link
   message.
3. **Prod end-to-end:** request a reset for a test account, click the link, and confirm the
   set-password form, a clean URL, and a successful login. Use Cloudflare's "Purge Everything" and
   Breeze/Varnish purge before testing.

## Risks

- **`replaceState` running after GTM**: if a tag manager snippet is printed earlier than
  `wp_head -9999` (for example hard-coded in `header.php`), `page_location` could still capture the
  key. Mitigation: check the rendered HTML order on prod, and add a GA4 / GTM variable filter that
  strips `key` as a backstop.
- **ProfilePress changes behavior later** (for example, starts consuming valid keys): we'd see
  invalid-link messages. Longer term: decide whether ProfilePress's lost-password features are
  still needed on this site, or point its `set_lost_password_url` setting at this page explicitly.
- **Security regression check:** the nonce plus `check_password_reset_key()` on POST is the same
  validation core's `resetpass` uses. Without the cookie, a POST needs a valid key, which is
  equivalent to holding the emailed link.

## Alternatives Considered

1. **Rename the cookie to a host-allowlisted prefix** (`wordpress_…` / `wp-resetpass-…`): smallest
   diff, keeps the key off rendered URLs entirely. Rejected as the primary fix because it depends
   on each host's VCL and can't be verified locally. **Fallback** if the replaceState approach is
   unacceptable. Make the name filterable (`carkeek_password_reset_cookie_name`) and confirm on
   Cloudways with the curl cookie test from the Diagnosis section.
2. **Cloudways Varnish exclusion for the URL or cookie** (Application Settings, then Varnish): a
   host-panel workaround that fixes only this site. Could serve as a temporary unblock while the
   code fix ships, but not as the fix.
3. **Server-side transient keyed by a random token in the URL:** the token in the URL leaks exactly
   like the key does, so it adds complexity for no gain.

## Sources

- Plugin code: `includes/class-carkeekpasswordreset-shortcode.php:83-87` (redirect),
  `:252-286` (cookie set, clear, read), `:177-227` (render branches)
- Original plan: `docs/plans/2026-09-13-feat-password-reset-shortcode-plan.md`
- ProfilePress 4.17.6: `src/ShortcodeParser/PasswordResetTag.php:12` (`wp` hook),
  `src/ShortcodeParser/FormProcessor.php:331-347` (`check_password_reset_key` redirect)
- [Cloudways: exclude URL from Varnish](https://support.cloudways.com/how-to-exclude-url-from-varnish/)
- [Varnish WordPress tutorial: cookie pass patterns](https://www.varnish.org/docs/tutorials/configuring-varnish-wordpress/)
