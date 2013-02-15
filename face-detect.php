<?php
/*
Plugin Name: My eyes are up here
Plugin URI: https://github.com/interconnectit/my-eyes-are-up-here
Description: Detects faces during thumbnail cropping and moves the crop position accordingly
Author: Robert O'Rourke @ interconnect/it
Version: 0.1
Author URI: http://interconnectit.com
*/

if ( ! defined( 'FACE_DETECT_PATH' ) )
	define( 'FACE_DETECT_PATH', plugin_dir_path( __FILE__ ) );

// Image editor classes
require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';

// Face detection
require_once 'php-facedetection/FaceDetector.php';

// track attachment being modified
add_action( 'init', array( 'WP_Detect_Face', 'setup' ) );

class WP_Detect_Face {

	public static $attachment_id;

	public function setup() {

		// get current attachment ID
		add_filter( 'get_attached_file', array( __CLASS__, 'set_attachment_id' ), 10, 2 );
		add_filter( 'update_attached_file', array( __CLASS__, 'set_attachment_id' ), 10, 2 );

		// use our extended class
		add_filter( 'wp_image_editors', array( __CLASS__, 'image_editors' ), 10, 1 );

	}

	public function set_attachment_id( $file, $attachment_id ) {
		self::$attachment_id = $attachment_id;
		return $file;
	}

	public function image_editors( $editors ) {
		$editors = array_filter( $editors, function( $class ) {
			return $class !== 'WP_Image_Editor_GD';
			} );
		array_push( $editors, 'WP_Image_Editor_GD_Detect_Face' );
		return $editors;
	}

}

/**
 * Extend the GD image editor class as Face_detection relies on GD
 */
class WP_Image_Editor_GD_Detect_Face extends WP_Image_Editor_GD {

	public $fd;
	public $fd_file = 'detection.dat';
	public $faces;


	public function __construct( $file ) {
		$this->file = $file;

		// edit dims
		add_filter( 'image_resize_dimensions', array( $this, 'face_crop' ), 10, 6 );

		// memory usage is high
		add_filter( 'image_memory_limit', array( $this, 'increase_memory_limit' ), 10, 1 );
	}


	public function face_crop( $output, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
		if ( $crop ) {

			// detect face
			if ( $this->faces === null ) {
				// prepare face detector
				$this->fd = new Face_Detector( FACE_DETECT_PATH . "php-facedetection/{$this->fd_file}" );

				// detect face if we're cropping
				$this->fd->face_detect( $this->image );
				$this->faces = $this->fd->getFaces();

				// save face data for other uses eg. tagging
				if ( is_array( $this->faces ) && WP_Detect_Face::$attachment_id )
					update_post_meta( WP_Detect_Face::$attachment_id, '_faces', $this->faces );
			}

			// if we have a face
			if ( is_array( $this->faces ) ) {

				// get faces area
				$face_src_x = 9999999999999;
				$face_src_y = 9999999999999;
				$face_src_max_x = $face_src_max_w = 0;
				$face_src_max_y = $face_src_max_h = 0;

				// create bounding box
				foreach( $this->faces as $face ) {
					// left and top most x,y
					if ( $face_src_x > $face[ 'x' ] ) $face_src_x = $face[ 'x' ];
					if ( $face_src_y > $face[ 'y' ] ) $face_src_y = $face[ 'y' ];
					// right and bottom most x,y
					if ( $face_src_max_x < $face[ 'x' ] + $face[ 'w' ] ) $face_src_max_x = $face[ 'x' ] + $face[ 'w' ];
					if ( $face_src_max_y < $face[ 'y' ] + $face[ 'w' ] ) $face_src_max_y = $face[ 'y' ] + $face[ 'w' ];
				}

				$face_src_w = $face_src_max_x - $face_src_x;
				$face_src_h = $face_src_max_y - $face_src_y;

				// crop the largest possible portion of the original image that we can size to $dest_w x $dest_h
				$aspect_ratio = $orig_w / $orig_h;
				$new_w = min($dest_w, $orig_w);
				$new_h = min($dest_h, $orig_h);

				if ( !$new_w ) {
					$new_w = intval($new_h * $aspect_ratio);
				}

				if ( !$new_h ) {
					$new_h = intval($new_w / $aspect_ratio);
				}

				$size_ratio = max($new_w / $orig_w, $new_h / $orig_h);

				$crop_w = round($new_w / $size_ratio);
				$crop_h = round($new_h / $size_ratio);

				$src_x = floor( ($orig_w - $crop_w) / 2 );
				$src_y = floor( ($orig_h - $crop_h) / 2 );

				// bounding box
				if ( $src_x == 0 ) {
					$src_y = ( $face_src_y + ($face_src_h / 2) ) - ($crop_h / 2);
					$src_y = min( max( 0, $src_y ), $orig_h - $crop_h );
				}

				if ( $src_y == 0 ) {
					$src_x = ( $face_src_x + ($face_src_w / 2) ) - ($crop_w / 2);
					$src_x = min( max( 0, $src_x ), $orig_w - $crop_w );
				}

				// the return array matches the parameters to imagecopyresampled()
				// int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
				return array( 0, 0, $src_x, $src_y, $dest_w, $dest_h, $crop_w, $crop_h );
			}

		}

		return null;
	}


	public function increase_memory_limit( $limit ) {
		@set_time_limit(60);
		return '128M';
	}

}
