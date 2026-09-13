<?php
/**
 * Plugin Name: Carkeek Password Reset
 * Plugin URI: https://github.com/pattyok/carkeek-password-reset
 * GitHub Plugin URI: pattyok/carkeek-password-reset
 * Requires PHP: 7.0
 * Requires at least: 6.1
 * Primary Branch: main
 * Description: Shortcode-based password reset flow (request + set new password) that runs on a themed page instead of wp-login.php, built on WordPress core's own password-reset functions.
 * Author: Patty O'Hara, Carkeek Studios
 * Version: 1.0.0
 * Author URI: https://carkeekstudios.com/
 * Text Domain: carkeek-password-reset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CarkeekPasswordReset' ) ) :
	/**
	 * Main CarkeekPasswordReset Class.
	 *
	 * @since 1.0
	 */
	final class CarkeekPasswordReset {
		/**
		 * The plugin's instance
		 *
		 * @var CarkeekPasswordReset the main var
		 * @since 1.0
		 */
		private static $instance;

		/**
		 * Main CarkeekPasswordReset instance
		 *
		 * Insures only one instance exists. Also prevents needing to define globals all around.
		 *
		 * @since 1.0.0
		 * @static
		 * @return object|CarkeekPasswordReset
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof CarkeekPasswordReset ) ) {
				self::$instance = new CarkeekPasswordReset();
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->init();
			}
			return self::$instance;
		}

		/**
		 * Throw error on object clone.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @return void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheating huh?', 'carkeek-password-reset' ), '1.0' );
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheating huh?', 'carkeek-password-reset' ), '1.0' );
		}

		/**
		 * Setup plugin constants.
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function setup_constants() {
			$plugin_data    = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
			$plugin_version = $plugin_data['Version'];

			$this->define( 'CARKEEKPASSWORDRESET_VERSION', $plugin_version );
			$this->define( 'CARKEEKPASSWORDRESET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			$this->define( 'CARKEEKPASSWORDRESET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'CARKEEKPASSWORDRESET_PLUGIN_FILE', __FILE__ );
			$this->define( 'CARKEEKPASSWORDRESET_PLUGIN_BASE', plugin_basename( __FILE__ ) );
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param  string       $name  Name of the definition.
		 * @param  string|bool  $value Default value.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include required files.
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function includes() {
			require_once CARKEEKPASSWORDRESET_PLUGIN_DIR . 'includes/class-gamajo-template-loader.php';
			require_once CARKEEKPASSWORDRESET_PLUGIN_DIR . 'includes/class-carkeekpasswordreset-template-loader.php';
			require_once CARKEEKPASSWORDRESET_PLUGIN_DIR . 'includes/class-carkeekpasswordreset-pages.php';
			require_once CARKEEKPASSWORDRESET_PLUGIN_DIR . 'includes/class-carkeekpasswordreset-mailer.php';
			require_once CARKEEKPASSWORDRESET_PLUGIN_DIR . 'includes/class-carkeekpasswordreset-redirects.php';
			require_once CARKEEKPASSWORDRESET_PLUGIN_DIR . 'includes/class-carkeekpasswordreset-shortcode.php';
		}

		/**
		 * Load actions and activation/deactivation hooks.
		 *
		 * @return void
		 */
		private function init() {
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

			register_activation_hook( CARKEEKPASSWORDRESET_PLUGIN_FILE, array( 'CarkeekPasswordReset_Pages', 'on_activation' ) );

			CarkeekPasswordReset_Mailer::init();
			CarkeekPasswordReset_Redirects::init();
			CarkeekPasswordReset_Shortcode::init();
		}

		/**
		 * Loads the plugin language files.
		 *
		 * @access public
		 * @since 1.0.0
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'carkeek-password-reset', false, dirname( CARKEEKPASSWORDRESET_PLUGIN_BASE ) . '/languages/' );
		}
	}

endif;

/**
 * The main function that returns the main class instance.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php carkeek_password_reset(); ?>
 *
 * @since 1.0
 * @return object|CarkeekPasswordReset The one true Instance.
 */
function carkeek_password_reset() {
	return CarkeekPasswordReset::instance();
}

// Get plugin running.
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	// Load on plugins_loaded to avoid issues on multisite.
	add_action( 'plugins_loaded', 'carkeek_password_reset' );
} else {
	carkeek_password_reset();
}
