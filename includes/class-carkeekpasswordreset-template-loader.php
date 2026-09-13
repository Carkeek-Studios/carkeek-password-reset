<?php
/**
 * Template loader for Carkeek Password Reset.
 *
 * Lets a theme (or another plugin) override any template by placing a
 * same-named file in a `carkeek-password-reset/` directory in the active
 * theme, so this plugin's markup doesn't need to be baked into the plugin
 * itself to look native on a given site.
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
 * CarkeekPasswordReset_Template_Loader Class
 *
 * @since 1.0
 */
class CarkeekPasswordReset_Template_Loader extends Gamajo_Template_Loader {
	/**
	 * Prefix for filter names.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $filter_prefix = 'carkeek_password_reset';

	/**
	 * Directory name where custom templates for this plugin should be found in the theme.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $theme_template_directory = 'carkeek-password-reset';

	/**
	 * Reference to the root directory path of this plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $plugin_directory = CARKEEKPASSWORDRESET_PLUGIN_DIR;

	/**
	 * Directory name where templates are found in this plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $plugin_template_directory = 'templates';
}
