<?php
/**
 * The [carkeek_password_reset] shortcode: a single shortcode that
 * self-switches between two views on one page —
 *
 *  - Request:          an email field; always shows the same confirmation
 *                       message regardless of whether the account exists
 *                       (this is the enumeration-safety guarantee).
 *  - Set new password: shown when the page is visited via the emailed reset
 *                       link. The login/key pair is read straight from the
 *                       link's query args and carried through the form as
 *                       hidden fields.
 *
 * Unlike wp-login.php, there's no cookie handoff: edge caches (e.g. Cloudways
 * Varnish) strip cookies they don't recognize, which silently dropped the
 * handoff on production. Instead, on the reset page the key is kept out of
 * Referer headers (Referrer-Policy: no-referrer), out of browser history and
 * analytics page_location (history.replaceState printed before any other
 * wp_head output), and out of page caches (nocache_headers()).
 *
 * A bad key in the emailed link is rejected up front with a redirect to
 * `?reset=invalidkey|expiredkey`, at `wp` priority 9 -- ahead of ProfilePress,
 * which hooks `wp` at 10 and would otherwise bounce bad keys to its own
 * `?error=invalidkey` URL (a param core's WP::parse_request() can unset from
 * $_GET, so it can't be relied on here).
 *
 * Form submissions are processed on that same `wp` hook (before any theme
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
		// `wp` rather than template_redirect, and before priority 10: see the
		// ProfilePress note in the class docblock.
		add_action( 'wp', array( __CLASS__, 'handle_requests' ), 9 );
		// Earlier than anything else in <head> (GTM4WP's earliest is priority 1),
		// so tag managers never see the key in document.location.
		add_action( 'wp_head', array( __CLASS__, 'print_url_scrub' ), -9999 );
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
	 * Handle form submissions on this shortcode's page, and send the headers
	 * that keep the reset key out of caches and Referer headers.
	 * Runs before any output, so it's free to redirect.
	 *
	 * @return void
	 */
	public static function handle_requests() {
		if ( ! CarkeekPasswordReset_Pages::is_reset_page() ) {
			return;
		}

		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		header( 'Referrer-Policy: no-referrer' );

		// Emailed link with a bad key: drop the key from the URL and show the
		// invalid/expired message.
		if ( 'GET' === $_SERVER['REQUEST_METHOD'] && isset( $_GET['key'], $_GET['login'] ) ) {
			list( $login, $key ) = self::get_login_key();
			$user = check_password_reset_key( $key, $login );

			if ( is_wp_error( $user ) ) {
				$reset = 'expired_key' === $user->get_error_code() ? 'expiredkey' : 'invalidkey';
				wp_safe_redirect( add_query_arg( 'reset', $reset, CarkeekPasswordReset_Pages::get_page_url() ) );
				exit;
			}
			return;
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

		$login_key = self::get_login_key();
		if ( ! $login_key ) {
			return;
		}

		list( $login, $key ) = $login_key;
		$user = check_password_reset_key( $key, $login );

		if ( is_wp_error( $user ) ) {
			return; // render() re-derives the same invalid/expired message from the posted login/key.
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

		wp_safe_redirect( add_query_arg( 'reset', 'complete', CarkeekPasswordReset_Pages::get_page_url() ) );
		exit;
	}

	/**
	 * Shortcode render callback -- picks the view based on the login/key pair
	 * (from the emailed link or the set-password POST) and the `reset`/`error`
	 * query args left behind by redirects.
	 *
	 * @param array $atts Shortcode attributes (unused).
	 * @return string
	 */
	public static function render( $atts ) {
		$loader = new CarkeekPasswordReset_Template_Loader();

		if ( isset( $_GET['reset'] ) && 'complete' === $_GET['reset'] ) {
			return self::render_template(
				$loader,
				'reset-complete',
				array( 'login_url' => CarkeekPasswordReset_Pages::get_login_url() )
			);
		}

		// Bad emailed-link key, already rejected by handle_requests().
		if ( isset( $_GET['reset'] ) && in_array( $_GET['reset'], array( 'invalidkey', 'expiredkey' ), true ) ) {
			return self::render_link_invalid( $loader, 'expiredkey' === $_GET['reset'] );
		}

		$login_key = self::get_login_key();

		if ( $login_key ) {
			list( $login, $key ) = $login_key;
			$user = check_password_reset_key( $key, $login );

			if ( is_wp_error( $user ) ) {
				return self::render_link_invalid( $loader, 'expired_key' === $user->get_error_code() );
			}

			return self::render_template(
				$loader,
				'reset-form',
				array(
					'rp_login'    => $login,
					'rp_key'      => $key,
					'errors'      => self::$view_b_errors,
					'form_action' => CarkeekPasswordReset_Pages::get_page_url(),
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
	 * Render the invalid/expired-link view.
	 *
	 * @param CarkeekPasswordReset_Template_Loader $loader  Template loader instance.
	 * @param bool                                  $expired True for an expired key, false for any other invalid key.
	 * @return string
	 */
	private static function render_link_invalid( $loader, $expired ) {
		$message = $expired
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

	/**
	 * Read the login/key pair from the set-password POST (rp_login/rp_key) or,
	 * failing that, the emailed link's query args (login/key).
	 *
	 * The POST fields deliberately aren't named login/key: ProfilePress checks
	 * $_REQUEST['key'] / $_REQUEST['login'] on every request and would treat
	 * the submission as another reset-link visit.
	 *
	 * @return array{0:string,1:string}|null
	 */
	private static function get_login_key() {
		if ( isset( $_POST['rp_login'], $_POST['rp_key'] ) ) {
			return array( wp_unslash( $_POST['rp_login'] ), wp_unslash( $_POST['rp_key'] ) );
		}

		if ( isset( $_GET['login'], $_GET['key'] ) ) {
			return array( wp_unslash( $_GET['login'] ), wp_unslash( $_GET['key'] ) );
		}

		return null;
	}

	/**
	 * When the page is loaded from the emailed link, swap the URL for the
	 * clean page URL before any other <head> script (analytics, tag managers)
	 * can read document.location, and so the key doesn't land in history.
	 *
	 * @return void
	 */
	public static function print_url_scrub() {
		if ( ! isset( $_GET['key'] ) || ! CarkeekPasswordReset_Pages::is_reset_page() ) {
			return;
		}

		$clean_url = remove_query_arg( array( 'key', 'login' ) );
		?>
		<script>window.history.replaceState(null, '', <?php echo wp_json_encode( $clean_url ); ?>);</script>
		<?php
	}
}
