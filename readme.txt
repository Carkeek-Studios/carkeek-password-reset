=== Carkeek Password Reset ===
Contributors:      pattyok
Tags:               password reset, shortcode, login
Requires at least:  6.1
Tested up to:        6.7
Requires PHP:        7.0
Stable tag:          1.0.0
License:             GPL-2.0-or-later
License URI:         https://www.gnu.org/licenses/gpl-2.0.html

Shortcode-based password reset flow that runs on a themed page instead of wp-login.php.

== Description ==

Adds a `[carkeek_password_reset]` shortcode that provides a full "forgot password" flow -- request a reset email, then set a new password -- on any normal WordPress page, instead of wp-login.php.

Built entirely on WordPress core's own `retrieve_password()`, `check_password_reset_key()`, and `reset_password()` functions. Emails are core-default and are not customized.

== Installation ==

1. Upload/symlink the plugin into `wp-content/plugins/carkeek-password-reset`.
2. Activate. A page at the `reset-password` slug is created automatically with the shortcode already inserted, if one doesn't already exist.

== Changelog ==

= 1.0.0 =
* Initial release.
