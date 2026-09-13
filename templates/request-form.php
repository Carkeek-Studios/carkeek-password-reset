<?php
/**
 * Default template: password reset request form (View A).
 *
 * Override on a per-site basis by copying this file to
 * `carkeek-password-reset/request-form.php` in the active theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="carkeek-password-reset carkeek-password-reset--request">
	<form method="post" action="">
		<fieldset>
			<label for="carkeek-pwreset-email"><?php esc_html_e( 'Email Address', 'carkeek-password-reset' ); ?></label>
			<div class="field">
				<input type="email" class="input-text" id="carkeek-pwreset-email" name="email" required="required" />
			</div>
		</fieldset>

		<?php wp_nonce_field( 'carkeek_pwreset_request', 'carkeek_pwreset_request_nonce' ); ?>

		<button type="submit" class="button"><?php esc_html_e( 'Send Reset Link', 'carkeek-password-reset' ); ?></button>
	</form>
</div>
