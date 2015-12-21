<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MEAUH_Admin' ) ):
	/**
	 * Class MEAUH_Admin
	 */
	class MEAUH_Admin {
		/**
		 * Init
		 */
		public static function init() {
			$self = new self;

			add_action( 'admin_enqueue_scripts', array( $self, 'assets' ) );
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->includes();
		}

		/**
		 * Assets
		 */
		public function assets() {
			// main script
			wp_enqueue_script(
				'meauh-main',
				MEAUH()->plugin_url() . '/assets/js/scripts.min.js',
				array( 'jquery' ),
				filemtime( MEAUH()->plugin_path() . '/assets/js/scripts.min.js' ),
				true
			);

			// main script variables
			wp_localize_script( 'meauh-main', 'meauh', array(
				'ajax_url'         => MEAUH()->ajax_url(),
				'get_image_nonce'  => wp_create_nonce( MEAUH_Ajax::NONCE_GET_IMAGE ),
				'save_image_nonce' => wp_create_nonce( MEAUH_Ajax::NONCE_SAVE_IMAGE )
			) );

			// main style
			wp_enqueue_style(
				'meauh-main',
				MEAUH()->plugin_url() . '/assets/css/main.min.css',
				array(),
				filemtime( MEAUH()->plugin_path() . '/assets/css/main.min.css' ),
				'all'
			);
		}

		/**
		 * Includes
		 */
		protected function includes() {
			require_once 'class-meauh-attachment.php';
		}
	}
endif;

MEAUH_Admin::init();