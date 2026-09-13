<?php
/**
 * Default template: shown after a successful password reset.
 *
 * $data->login_url  (string) URL to the site's login page.
 *
 * Override on a per-site basis by copying this file to
 * `carkeek-password-reset/reset-complete.php` in the active theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="carkeek-password-reset carkeek-password-reset--complete">
	<p><?php esc_html_e( 'Your password has been reset.', 'carkeek-password-reset' ); ?></p>
	<p>
		<a class="button" href="<?php echo esc_url( $data->login_url ); ?>">
			<?php esc_html_e( 'Log In', 'carkeek-password-reset' ); ?>
		</a>
	</p>
</div>
