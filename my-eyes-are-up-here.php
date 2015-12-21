<?php
/**
 * Plugin Name: My Eyes Are Up Here
 * Plugin URI: https://github.com/interconnectit/my-eyes-are-up-here
 * Description: Detects faces during thumbnail cropping and moves the crop position accordingly.
 * Version: 1.0.1
 * Author: interconnect/it
 * Author URI: http://interconnectit.com
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MyEyesAreUpHere' ) ):
	/**
	 * Class MyEyesAreUpHere
	 */
	final class MyEyesAreUpHere {
		// requests
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
		}

		/**
		 * Determine request type
		 *
		 * @param string $type
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
		 * @return string|void
		 */
		public function ajax_url() {
			return admin_url( 'admin-ajax.php', 'relative' );
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
	}
endif;

/**
 * Get instance
 *
 * @return MyEyesAreUpHere
 */
function MEAUH() {
	return MyEyesAreUpHere::instance();
}

// Global for backwards compatibility
$GLOBALS['meauh'] = MEAUH();