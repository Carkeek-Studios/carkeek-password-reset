<?php
/**
 * Default template: set new password form (View B).
 *
 * $data->rp_login    (string)   User login, echoed back as a hidden field.
 * $data->rp_key      (string)   Reset key, echoed back as a hidden field and
 *                               re-checked with check_password_reset_key() on submit.
 * $data->errors      (string[]) Validation errors from this request's submission,
 *                               if any (empty password, passwords don't match).
 * $data->form_action (string)   Clean reset page URL (no key/login query args).
 *
 * Override on a per-site basis by copying this file to
 * `carkeek-password-reset/reset-form.php` in the active theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="carkeek-password-reset carkeek-password-reset--setpass">
	<?php foreach ( $data->errors as $error ) : ?>
		<p class="carkeek-password-reset__error"><?php echo esc_html( $error ); ?></p>
	<?php endforeach; ?>

	<form method="post" action="<?php echo esc_url( $data->form_action ); ?>">
		<fieldset>
			<label for="carkeek-pwreset-pass1"><?php esc_html_e( 'New Password', 'carkeek-password-reset' ); ?></label>
			<div class="field">
				<input type="password" class="input-text" id="carkeek-pwreset-pass1" name="pass1" required="required" autocomplete="new-password" />
			</div>
		</fieldset>

		<fieldset>
			<label for="carkeek-pwreset-pass2"><?php esc_html_e( 'Confirm Password', 'carkeek-password-reset' ); ?></label>
			<div class="field">
				<input type="password" class="input-text" id="carkeek-pwreset-pass2" name="pass2" required="required" autocomplete="new-password" />
			</div>
		</fieldset>

		<input type="hidden" name="rp_login" value="<?php echo esc_attr( $data->rp_login ); ?>" />
		<input type="hidden" name="rp_key" value="<?php echo esc_attr( $data->rp_key ); ?>" />
		<?php wp_nonce_field( 'carkeek_pwreset_setpass', 'carkeek_pwreset_setpass_nonce' ); ?>

		<button type="submit" class="button"><?php esc_html_e( 'Reset Password', 'carkeek-password-reset' ); ?></button>
	</form>
</div>
