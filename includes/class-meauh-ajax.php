<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MEAUH_Ajax' ) ):
	/**
	 * Class MEAUH_Ajax
	 */
	class MEAUH_Ajax {
		// nonces
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

			if ( $this->is_attachment( $attachment_id ) ) {
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

			if ( $this->is_attachment( $attachment_id ) ) {
				// faces
				if ( isset( $_POST['faces'] ) ) {
					if ( $_POST['faces'] ) {
						update_post_meta( $attachment_id, 'faces', $_POST['faces'] );
					} else {
						delete_post_meta( $attachment_id, 'faces' );
					}
				}

				// hotspots
				if ( isset( $_POST['hotspots'] ) ) {
					if ( $_POST['hotspots'] ) {
						update_post_meta( $attachment_id, 'hotspots', $_POST['hotspots'] );
					} else {
						delete_post_meta( $attachment_id, 'hotspots' );
					}
				}

				// regenerate thumbs
				$resized = MEAUH_Attachment::regenerate( $attachment_id );

				if ( $resized ) {
					wp_send_json_success( array(
						'resized' => $resized,
					) );
				}
			} else {
				wp_send_json_error();
			}
		}

		/**
		 * Is attachment
		 *
		 * @param int $attachment_id
		 *
		 * @return bool
		 */
		protected function is_attachment( $attachment_id ) {
			return $attachment_id &&
			       get_post( $attachment_id ) &&
			       'attachment' == get_post_type( $attachment_id );
		}
	}
endif;

MEAUH_Ajax::init();