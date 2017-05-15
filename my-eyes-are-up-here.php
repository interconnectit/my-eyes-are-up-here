<?php
/**
 * Plugin Name: My Eyes Are Up Here
 * Plugin URI: https://github.com/interconnectit/my-eyes-are-up-here
 * Description: Detects faces during thumbnail cropping and moves the crop position accordingly.
 * Version: 1.1.9
 * Author: interconnect/it
 * Author URI: http://interconnectit.com
 *
 * Text Domain: my-eyes-are-up-here
 * Domain Path: /languages/
 *
 * @package my-eyes-are-up-here
 * @author interconnect/it
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MyEyesAreUpHere
 */
final class MyEyesAreUpHere {
	const REQUEST_ADMIN = 'admin';
	const REQUEST_AJAX = 'ajax';

	/**
	 * Instance
	 *
	 * @var MyEyesAreUpHere
	 */
	private static $_instance;

	/**
	 * Get instance
	 *
	 * @return MyEyesAreUpHere
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Determine request type
	 *
	 * @param string $type Request type.
	 *
	 * @return bool
	 */
	public function is_request( $type ) {
		switch ( $type ) {
			case self::REQUEST_ADMIN:
				return is_admin();

			case self::REQUEST_AJAX:
				return defined( 'DOING_AJAX' );
		}
	}

	/**
	 * Get plugin path
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get plugin URL
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get ajax URL
	 *
	 * @return string
	 */
	public function ajax_url() {
		return admin_url( 'admin-ajax.php', 'relative' );
	}

	/**
	 * Load localisation
	 */
	public function localisation() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'my-eyes-are-up-here' );

		load_textdomain( 'my-eyes-are-up-here', WP_LANG_DIR . '/my-eyes-are-up-here/my-eyes-are-up-here-' . $locale . '.mo' );
		load_plugin_textdomain( 'my-eyes-are-up-here', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Includes
	 */
	protected function includes() {
		require_once 'includes/class-meauh-ajax.php';

		if ( $this->is_request( self::REQUEST_ADMIN ) ) {
			require_once 'includes/class-meauh-admin.php';
		}
	}

	/**
	 * Init hooks
	 */
	protected function init_hooks() {
		add_action( 'init', array( $this, 'localisation' ), 0 );
	}
}

/**
 * Get instance
 *
 * @return MyEyesAreUpHere
 */
function meauh() {
	return MyEyesAreUpHere::instance();
}

// Global for backwards compatibility.
$GLOBALS['meauh'] = meauh();
