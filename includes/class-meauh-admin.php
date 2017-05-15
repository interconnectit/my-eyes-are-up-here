<?php
/**
 * Admin
 *
 * @package my-eyes-are-up-here
 * @author interconnect/it
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		// Main script.
		wp_enqueue_script(
			'meauh-main',
			meauh()->plugin_url() . '/assets/js/scripts.min.js',
			array( 'jquery' ),
			filemtime( meauh()->plugin_path() . '/assets/js/scripts.min.js' ),
			true
		);

		// Main script variables.
		wp_localize_script( 'meauh-main', 'meauh', array(
			'ajax_url'         => meauh()->ajax_url(),
			'get_image_nonce'  => wp_create_nonce( MEAUH_Ajax::NONCE_GET_IMAGE ),
			'save_image_nonce' => wp_create_nonce( MEAUH_Ajax::NONCE_SAVE_IMAGE ),
		) );

		// Main style.
		wp_enqueue_style(
			'meauh-main',
			meauh()->plugin_url() . '/assets/css/main.min.css',
			array(),
			filemtime( meauh()->plugin_path() . '/assets/css/main.min.css' ),
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

MEAUH_Admin::init();
