<?php
/**
 * Funnels every "lost your password" entry point to the plugin's reset page
 * instead of wp-login.php — not just the link this plugin itself sends, but
 * any "Lost your password?" link elsewhere on the site (theme, WooCommerce,
 * Gravity Forms User Registration, bookmarks, old emails, etc.).
 *
 * wp-login.php's own lostpassword/rp/resetpass screens are left intact as a
 * fallback (never disabled) — they're just redirected away from.
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
 * CarkeekPasswordReset_Redirects Class
 *
 * @since 1.0
 */
class CarkeekPasswordReset_Redirects {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Priority 20: run after any theme/plugin lostpassword_url filter (e.g.
		// this site's theme conditionally removes WooCommerce's own
		// wc_lostpassword_url filter — see themes/listable/inc/extras.php — so
		// ours needs to win regardless of that filter's state).
		add_filter( 'lostpassword_url', array( __CLASS__, 'filter_lostpassword_url' ), 20, 1 );

		add_action( 'login_form_lostpassword', array( __CLASS__, 'redirect_lostpassword' ) );
		add_action( 'login_form_rp', array( __CLASS__, 'redirect_reset' ) );
		add_action( 'login_form_resetpass', array( __CLASS__, 'redirect_reset' ) );
	}

	/**
	 * Point "Lost your password?" links at the reset page.
	 *
	 * @param string $lostpassword_url Default wp-login.php lostpassword URL.
	 * @return string
	 */
	public static function filter_lostpassword_url( $lostpassword_url ) {
		$reset_page_url = CarkeekPasswordReset_Pages::get_page_url();

		return $reset_page_url ? $reset_page_url : $lostpassword_url;
	}

	/**
	 * Redirect direct visits to wp-login.php?action=lostpassword to the reset page.
	 *
	 * @return void
	 */
	public static function redirect_lostpassword() {
		$reset_page_url = CarkeekPasswordReset_Pages::get_page_url();

		if ( ! $reset_page_url ) {
			return;
		}

		wp_safe_redirect( $reset_page_url );
		exit;
	}

	/**
	 * Redirect direct visits to wp-login.php?action=rp|resetpass to the reset
	 * page, preserving the login/key query args so the plugin's own cookie
	 * handoff (see CarkeekPasswordReset_Shortcode) picks up where core left off.
	 *
	 * @return void
	 */
	public static function redirect_reset() {
		$reset_page_url = CarkeekPasswordReset_Pages::get_page_url();

		if ( ! $reset_page_url ) {
			return;
		}

		$args = array();
		if ( isset( $_GET['key'], $_GET['login'] ) ) {
			$args['key']   = wp_unslash( $_GET['key'] );
			$args['login'] = wp_unslash( $_GET['login'] );
		}

		wp_safe_redirect( $args ? add_query_arg( $args, $reset_page_url ) : $reset_page_url );
		exit;
	}
}
