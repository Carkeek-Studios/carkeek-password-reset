# Carkeek Password Reset

A shortcode-based password reset flow that runs on a themed page instead of `wp-login.php`, built entirely on WordPress core's own password-reset functions (`retrieve_password()`, `check_password_reset_key()`, `reset_password()`). No new reset logic, no custom email templates — this is a styled front end over core.

## Usage

1. Activate the plugin. On activation, if no page exists at the `reset-password` slug, one is created automatically with the `[carkeek_password_reset]` shortcode already inserted.
2. Point "Lost your password?" links at that page — or don't; the plugin already filters `lostpassword_url` and redirects `wp-login.php?action=lostpassword|rp|resetpass` there for you.
3. That's it. The shortcode shows an email-request form by default, and switches to a "set new password" form when visited via the emailed reset link.

## Overriding markup for a specific site

Every template lives in `templates/` and can be overridden without touching this plugin: copy the file into a `carkeek-password-reset/` directory in the active theme (e.g. `wp-content/themes/your-theme/carkeek-password-reset/request-form.php`).

| Template | Shown when |
|---|---|
| `request-form.php` | Default view: the email-request form. |
| `request-sent.php` | After the request form is submitted — always the same message, regardless of whether the account exists. |
| `reset-form.php` | The reset link was valid: new-password / confirm-password form. |
| `reset-link-invalid.php` | The reset link's key is invalid or expired. |
| `reset-complete.php` | After a successful password reset. |

## Filters

- `carkeek_password_reset_page_slug` (default `reset-password`) — the page the shortcode/flow lives on.
- `carkeek_password_reset_login_page_slug` (default `login`) — page linked to from the reset-complete message; falls back to `wp_login_url()` if that page doesn't exist.

## Security notes

- The request form's response never reveals whether the submitted email matched an account — same wording, same behavior either way.
- The "set new password" step keeps the reset key out of the page URL/history/Referer headers, using the same cookie-handoff pattern `wp-login.php` uses internally.
- Both forms are nonce-protected.

See `docs/plans/` for the original planning document and full technical rationale.
