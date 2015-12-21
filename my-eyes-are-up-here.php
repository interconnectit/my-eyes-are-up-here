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
		 * Init
		 */
		public static function init() {
			$self = new self;
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
		 * Includes
		 */
		protected function includes() {
			if ( $this->is_request( self::REQUEST_AJAX ) ) {
				require_once 'class-meauh-ajax.php';
			}
		}
	}
endif;

MyEyesAreUpHere::init();