<?php
/**
 * Points the core password-reset email's link at this plugin's reset page
 * instead of wp-login.php.
 *
 * The email itself is left entirely to WordPress core (retrieve_password())
 * — subject, wording, and sending are all core-default and not customized
 * here. Only the wp-login.php?...&action=rp link inside the core message is
 * rewritten, by swapping its query string onto the reset page's own URL.
 *
 * @package   CarkeekPasswordReset
 * @author    Patty O'Hara, Carkeek Studios
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CarkeekPasswordReset_Mailer Class
 *
 * @since 1.0
 */
class CarkeekPasswordReset_Mailer {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'retrieve_password_message', array( __CLASS__, 'point_link_at_reset_page' ), 10, 4 );
	}

	/**
	 * Rewrite the wp-login.php reset link in core's password-reset email to
	 * point at this plugin's reset page, keeping the same login/key query args
	 * core generated.
	 *
	 * If no reset page exists yet (see CarkeekPasswordReset_Pages), the
	 * message is left untouched — the emailed link still works, it just lands
	 * on wp-login.php as normal core behavior would.
	 *
	 * @param string  $message    Email message, as built by core retrieve_password().
	 * @param string  $key        The activation key (unused directly — it's embedded in $message).
	 * @param string  $user_login The username for the user (unused directly).
	 * @param WP_User $user_data  WP_User object (unused directly).
	 * @return string
	 */
	public static function point_link_at_reset_page( $message, $key, $user_login, $user_data ) {
		$reset_page_url = CarkeekPasswordReset_Pages::get_page_url();

		if ( ! $reset_page_url ) {
			return $message;
		}

		$login_php_url = network_site_url( 'wp-login.php', 'login' );
		$pattern       = '#' . preg_quote( $login_php_url, '#' ) . '\?(\S+)#';

		return preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $reset_page_url ) {
				$query_args = array();
				wp_parse_str( $matches[1], $query_args );

				return add_query_arg( $query_args, $reset_page_url );
			},
			$message
		);
	}
}
