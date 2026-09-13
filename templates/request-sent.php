<?php
/**
 * Default template: confirmation shown after the request form is submitted.
 *
 * Deliberately identical whether or not the submitted email matched an
 * account -- do not change this to branch on account existence.
 *
 * Override on a per-site basis by copying this file to
 * `carkeek-password-reset/request-sent.php` in the active theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="carkeek-password-reset carkeek-password-reset--sent">
	<p>
		<?php
		esc_html_e(
			'If an account exists for that email address, we\'ve sent a password reset link. Please check your inbox (and spam folder).',
			'carkeek-password-reset'
		);
		?>
	</p>
</div>
