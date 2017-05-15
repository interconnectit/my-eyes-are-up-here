<?php
/**
 * Ajax
 *
 * @package my-eyes-are-up-here
 * @author interconnect/it
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEAUH_Ajax
 */
class MEAUH_Ajax {
	const NONCE_GET_IMAGE = 'meauh-get-image';
	const NONCE_SAVE_IMAGE = 'meauh-save-image';

	/**
	 * Ajax events
	 *
	 * @var array
	 */
	protected static $events = array(
		'get_image'  => false,
		'save_image' => false,
	);

	/**
	 * Init
	 */
	public static function init() {
		$self = new self;

		foreach ( self::$events as $event => $nopriv ) {
			add_action( 'wp_ajax_meauh_' . $event, array( $self, $event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_meauh_' . $event, array( $self, $event ) );
			}
		}
	}

	/**
	 * Get an image
	 */
	public function get_image() {
		check_ajax_referer( self::NONCE_GET_IMAGE, 'nonce' );

		$attachment_id = isset( $_POST['attachment_id'] ) ?
			absint( $_POST['attachment_id'] ) :
			false;

		if ( $attachment_id && $this->is_attachment( $attachment_id ) ) {
			wp_send_json_success( array(
				'original' => wp_get_attachment_image_src( $attachment_id, 'full' ),
			) );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Save an image
	 */
	public function save_image() {
		check_ajax_referer( self::NONCE_SAVE_IMAGE, 'nonce' );

		$attachment_id = isset( $_POST['attachment_id'] ) ?
			absint( $_POST['attachment_id'] ) :
			false;

		if ( ! $this->is_attachment( $attachment_id ) ) {
			wp_send_json_error();
		}

		// WP Offload S3 Compatibility.
		$this->as3cf_compatibility( $attachment_id );

		// Save faces.
		$this->save_image_faces( $attachment_id );

		// Save hotspots.
		$this->save_image_hotspots( $attachment_id );

		// Regenerate thumbs.
		$resized = MEAUH_Attachment::regenerate( $attachment_id );
		if ( $resized ) {
			wp_send_json_success( array(
				'resized' => $resized,
			) );
		}
	}

	/**
	 * Is attachment
	 *
	 * @param int $attachment_id Attachment id.
	 *
	 * @return bool
	 */
	protected function is_attachment( $attachment_id ) {
		return $attachment_id &&
		       get_post( $attachment_id ) &&
		       'attachment' === get_post_type( $attachment_id );
	}

	/**
	 * WP Offload S3 Compatibility
	 *
	 * @param $attachment_id Attachment ID.
	 */
	protected function as3cf_compatibility( $attachment_id ) {
		if ( ! is_plugin_active( 'amazon-s3-and-cloudfront/wordpress-s3.php' ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );

		file_put_contents( $file, file_get_contents( $file ) );
	}

	/**
	 * Save image faces
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	protected function save_image_faces( $attachment_id ) {
		if ( ! empty( $_POST['faces'] ) ) {
			update_post_meta(
				$attachment_id,
				'faces',
				array_filter( $_POST['faces'], array( $this, 'filter' ) )
			);
		} else {
			delete_post_meta( $attachment_id, 'faces' );
		}
	}

	/**
	 * Save image hotspots
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	protected function save_image_hotspots( $attachment_id ) {
		if ( ! empty( $_POST['hotspots'] ) ) {
			update_post_meta(
				$attachment_id,
				'hotspots',
				array_filter( $_POST['hotspots'], array( $this, 'filter' ) )
			);
		} else {
			delete_post_meta( $attachment_id, 'hotspots' );
		}
	}

	/**
	 * Make sure we got solid data
	 *
	 * @param array $value Values to filter.
	 *
	 * @return array
	 */
	protected function filter( $value ) {
		$allowed_keys = array(
			'x',
			'y',
			'width',
		);

		$value = array_intersect_key( $value, array_flip( $allowed_keys ) );

		if ( isset( $value['x'], $value['y'], $value['width'] ) ) {
			return array_filter( $value, 'is_numeric' );
		} else {
			return array();
		}
	}
}

MEAUH_Ajax::init();
