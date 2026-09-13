<?php
/**
 * The [carkeek_password_reset] shortcode: a single shortcode that
 * self-switches between two views on one page —
 *
 *  - Request:          an email field; always shows the same confirmation
 *                       message regardless of whether the account exists
 *                       (this is the enumeration-safety guarantee).
 *  - Set new password: shown when the page is visited via the emailed reset
 *                       link; mirrors wp-login.php's own cookie-based
 *                       key handoff (wp-login.php:938-1002) rather than
 *                       carrying the reset key in the page URL.
 *
 * Form submissions are processed on `template_redirect` (before any theme
 * output), using a post/redirect/get pattern on every path that succeeds, so
 * nothing here ever tries to redirect after the theme has already started
 * rendering. Validation errors (wrong/missing password, expired link) are the
 * only case that doesn't redirect — they're stored on the class and rendered
 * inline by render(), later in the same request.
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
 * CarkeekPasswordReset_Shortcode Class
 *
 * @since 1.0
 */
class CarkeekPasswordReset_Shortcode {

	/**
	 * Cookie name prefix, mirroring wp-login.php's own 'wp-resetpass-' pattern.
	 */
	const COOKIE_PREFIX = 'carkeek-pwreset-';

	/**
	 * Validation errors from this request's "set new password" submission, if
	 * any. Populated by process_setpass_submission(), read by render() later
	 * in the same request.
	 *
	 * @var string[]
	 */
	private static $view_b_errors = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_requests' ) );
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function add_shortcode() {
		add_shortcode( 'carkeek_password_reset', array( __CLASS__, 'render' ) );
	}

	/**
	 * Handle the three request shapes this shortcode's page can receive.
	 * Runs before any output, so it's free to redirect.
	 *
	 * @return void
	 */
	public static function handle_requests() {
		if ( ! CarkeekPasswordReset_Pages::is_reset_page() ) {
			return;
		}

		// Emailed link: move login/key out of the URL and into a cookie, then
		// reload without them -- same reasoning as wp-login.php's own handler:
		// keeps the reset key out of browser history, Referer headers, and logs.
		if ( isset( $_GET['key'], $_GET['login'] ) ) {
			self::start_reset_cookie( wp_unslash( $_GET['login'] ), wp_unslash( $_GET['key'] ) );
			wp_safe_redirect( remove_query_arg( array( 'key', 'login' ) ) );
			exit;
		}

		if ( isset( $_POST['carkeek_pwreset_request_nonce'] ) ) {
			self::process_request_submission();
			return;
		}

		if ( isset( $_POST['carkeek_pwreset_setpass_nonce'] ) ) {
			self::process_setpass_submission();
			return;
		}
	}

	/**
	 * Handle the "email me a reset link" form.
	 *
	 * @return void
	 */
	private static function process_request_submission() {
		if ( ! wp_verify_nonce( wp_unslash( $_POST['carkeek_pwreset_request_nonce'] ), 'carkeek_pwreset_request' ) ) {
			return;
		}

		$email = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';

		// The return value is intentionally ignored: showing a different
		// result for "account exists" vs. "account doesn't exist" is exactly
		// the enumeration leak this flow exists to avoid.
		retrieve_password( $email );

		wp_safe_redirect( add_query_arg( 'reset', 'requested', CarkeekPasswordReset_Pages::get_page_url() ) );
		exit;
	}

	/**
	 * Handle the "set new password" form.
	 *
	 * @return void
	 */
	private static function process_setpass_submission() {
		if ( ! wp_verify_nonce( wp_unslash( $_POST['carkeek_pwreset_setpass_nonce'] ), 'carkeek_pwreset_setpass' ) ) {
			return;
		}

		$cookie = self::get_cookie_login_key();
		if ( ! $cookie ) {
			return;
		}

		list( $login, $key ) = $cookie;
		$user = check_password_reset_key( $key, $login );

		// The posted rp_key must match the cookie's key -- guards against a
		// forged/replayed POST that doesn't originate from this same handoff.
		if ( ! isset( $_POST['rp_key'] ) || ! hash_equals( $key, wp_unslash( $_POST['rp_key'] ) ) ) {
			$user = new WP_Error( 'invalid_key', __( 'Invalid key.' ) );
		}

		if ( is_wp_error( $user ) ) {
			self::clear_reset_cookie();
			return; // render() re-derives the same invalid/expired message from the (now cleared) cookie's last value.
		}

		$pass1 = isset( $_POST['pass1'] ) ? trim( wp_unslash( $_POST['pass1'] ) ) : '';
		$pass2 = isset( $_POST['pass2'] ) ? trim( wp_unslash( $_POST['pass2'] ) ) : '';

		if ( '' === $pass1 ) {
			self::$view_b_errors[] = __( 'Please enter a password.', 'carkeek-password-reset' );
		} elseif ( $pass1 !== $pass2 ) {
			self::$view_b_errors[] = __( 'The passwords do not match.', 'carkeek-password-reset' );
		}

		if ( self::$view_b_errors ) {
			return;
		}

		reset_password( $user, $pass1 );
		self::clear_reset_cookie();

		wp_safe_redirect( add_query_arg( 'reset', 'complete', CarkeekPasswordReset_Pages::get_page_url() ) );
		exit;
	}

	/**
	 * Shortcode render callback -- picks the view based on the reset cookie
	 * and the `reset` query arg left behind by a successful submission.
	 *
	 * @param array $atts Shortcode attributes (unused).
	 * @return string
	 */
	public static function render( $atts ) {
		$loader = new CarkeekPasswordReset_Template_Loader();

		// Checked first, regardless of cookie state: a successful "set new
		// password" submission clears the cookie before redirecting here, so
		// by the time this loads the cookie is already gone.
		if ( isset( $_GET['reset'] ) && 'complete' === $_GET['reset'] ) {
			return self::render_template(
				$loader,
				'reset-complete',
				array( 'login_url' => CarkeekPasswordReset_Pages::get_login_url() )
			);
		}

		$cookie = self::get_cookie_login_key();

		if ( $cookie ) {
			list( $login, $key ) = $cookie;
			$user = check_password_reset_key( $key, $login );

			if ( is_wp_error( $user ) ) {
				$message = 'expired_key' === $user->get_error_code()
					? __( 'This password reset link has expired. Please request a new one.', 'carkeek-password-reset' )
					: __( 'This password reset link is invalid. Please request a new one.', 'carkeek-password-reset' );

				return self::render_template(
					$loader,
					'reset-link-invalid',
					array(
						'message'     => $message,
						'request_url' => CarkeekPasswordReset_Pages::get_page_url(),
					)
				);
			}

			return self::render_template(
				$loader,
				'reset-form',
				array(
					'rp_key' => $key,
					'errors' => self::$view_b_errors,
				)
			);
		}

		if ( isset( $_GET['reset'] ) && 'requested' === $_GET['reset'] ) {
			return self::render_template( $loader, 'request-sent', array() );
		}

		return self::render_template( $loader, 'request-form', array() );
	}

	/**
	 * Render a template part to a string via the template loader.
	 *
	 * @param CarkeekPasswordReset_Template_Loader $loader Template loader instance.
	 * @param string                                $slug   Template slug (see templates/ directory).
	 * @param array                                 $data   Data made available to the template as $data->key.
	 * @return string
	 */
	private static function render_template( $loader, $slug, array $data ) {
		$loader->set_template_data( $data );
		ob_start();
		$loader->get_template_part( $slug, null, true );
		$loader->unset_template_data();

		return ob_get_clean();
	}

	/**
	 * @return string
	 */
	private static function cookie_name() {
		return self::COOKIE_PREFIX . COOKIEHASH;
	}

	/**
	 * Store login:key in a cookie scoped to the current request path, mirroring
	 * wp-login.php's own resetpass cookie handling.
	 *
	 * @param string $login User login.
	 * @param string $key   Reset key.
	 * @return void
	 */
	private static function start_reset_cookie( $login, $key ) {
		list( $path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
		setcookie( self::cookie_name(), $login . ':' . $key, 0, $path, COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Expire the reset cookie.
	 *
	 * @return void
	 */
	private static function clear_reset_cookie() {
		list( $path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
		setcookie( self::cookie_name(), ' ', time() - YEAR_IN_SECONDS, $path, COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Read the login/key pair out of the reset cookie, if present.
	 *
	 * @return array{0:string,1:string}|null
	 */
	private static function get_cookie_login_key() {
		$cookie_name = self::cookie_name();

		if ( empty( $_COOKIE[ $cookie_name ] ) || false === strpos( $_COOKIE[ $cookie_name ], ':' ) ) {
			return null;
		}

		list( $login, $key ) = explode( ':', wp_unslash( $_COOKIE[ $cookie_name ] ), 2 );

		return array( $login, $key );
	}
}
