<?php
/**
 * Default template: shown when the reset link's key is invalid or expired.
 *
 * $data->message      (string) Plain-language explanation (invalid vs. expired).
 * $data->request_url  (string) URL back to the request form (View A).
 *
 * Override on a per-site basis by copying this file to
 * `carkeek-password-reset/reset-link-invalid.php` in the active theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="carkeek-password-reset carkeek-password-reset--invalid">
	<p><?php echo esc_html( $data->message ); ?></p>
	<p>
		<a class="button" href="<?php echo esc_url( $data->request_url ); ?>">
			<?php esc_html_e( 'Request a new link', 'carkeek-password-reset' ); ?>
		</a>
	</p>
</div>
