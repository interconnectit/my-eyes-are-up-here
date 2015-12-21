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
		}

		/**
		 * Assets
		 */
		public function assets() {
			// main script
			wp_enqueue_script(
				'meauh-main',
				$this->plugin_url() . '/assets/js/scripts.min.js',
				array( 'jquery' ),
				filemtime( $this->plugin_path() . '/assets/js/scripts.min.js' ),
				true
			);

			// main script variables
			wp_localize_script( 'detect-faces-main', 'detectFaces', array(
				'ajax_url'         => $this->ajax_url(),
				'nonce_get_image'  => wp_create_nonce( self::NONCE_GET_IMAGE ),
				'nonce_save_image' => wp_create_nonce( self::NONCE_SAVE_IMAGE )
			) );

			// main style
			wp_enqueue_style(
				'meauh-main',
				$this->plugin_url() . '/assets/css/main.min.css',
				array(),
				filemtime( $this->plugin_path() . '/assets/css/main.min.css' ),
				'all'
			);
		}
	}
endif;

MEAUH_Admin::init();