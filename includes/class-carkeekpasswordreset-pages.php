<?php
/**
 * Resolves the reset-password and login pages this plugin points at, and
 * makes sure the reset page exists (creating it on activation if needed).
 *
 * Site-specific page slugs are never hardcoded elsewhere in this plugin —
 * every other class goes through the getters here, so a site can override
 * the slugs via the carkeek_password_reset_page_slug /
 * carkeek_password_reset_login_page_slug filters without touching code.
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
 * CarkeekPasswordReset_Pages Class
 *
 * @since 1.0
 */
class CarkeekPasswordReset_Pages {

	/**
	 * Slug for the page that hosts the [carkeek_password_reset] shortcode.
	 *
	 * @return string
	 */
	public static function get_page_slug() {
		return (string) apply_filters( 'carkeek_password_reset_page_slug', 'reset-password' );
	}

	/**
	 * Slug for the site's login page, linked to from the reset-complete message.
	 *
	 * Falls back to wp-login.php's own URL if no matching page exists, so the
	 * link is never broken even on a site that hasn't built a custom login page.
	 *
	 * @return string
	 */
	public static function get_login_url() {
		$slug = (string) apply_filters( 'carkeek_password_reset_login_page_slug', 'login' );
		$page = get_page_by_path( $slug );

		return $page ? get_permalink( $page ) : wp_login_url();
	}

	/**
	 * ID of the page hosting the [carkeek_password_reset] shortcode, or 0 if it
	 * doesn't exist yet.
	 *
	 * @return int
	 */
	public static function get_page_id() {
		$page = get_page_by_path( self::get_page_slug() );

		return $page ? $page->ID : 0;
	}

	/**
	 * URL of the page hosting the [carkeek_password_reset] shortcode, or empty
	 * string if it doesn't exist yet.
	 *
	 * @return string
	 */
	public static function get_page_url() {
		$page_id = self::get_page_id();

		return $page_id ? get_permalink( $page_id ) : '';
	}

	/**
	 * True when the current request is for the reset-password page.
	 *
	 * @return bool
	 */
	public static function is_reset_page() {
		$page_id = self::get_page_id();

		return $page_id && is_page( $page_id );
	}

	/**
	 * Activation hook: create the reset-password page (with the shortcode
	 * already inserted) if a page with the configured slug doesn't exist yet.
	 *
	 * Never overwrites or touches an existing page at that slug — if one's
	 * already there (from a previous install, or hand-created by the site
	 * owner), it's left exactly as-is.
	 *
	 * @return void
	 */
	public static function on_activation() {
		$slug = self::get_page_slug();

		if ( get_page_by_path( $slug ) ) {
			return;
		}

		wp_insert_post(
			array(
				'post_title'   => __( 'Reset Password', 'carkeek-password-reset' ),
				'post_name'    => $slug,
				'post_content' => '[carkeek_password_reset]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
	}
}
