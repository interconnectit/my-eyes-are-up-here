<?php
/*
Plugin Name: My eyes are up here
Plugin URI: https://github.com/interconnectit/my-eyes-are-up-here
Description: Detects faces during thumbnail cropping and moves the crop position accordingly
Author: Robert O'Rourke @ interconnect/it
Version: 0.1
Author URI: http://interconnectit.com

Thanks to Marko Heijnen for feedback
https://github.com/markoheijnen
*/

if ( ! defined( 'FACE_DETECT_PATH' ) )
	define( 'FACE_DETECT_PATH', plugin_dir_path( __FILE__ ) );

// track attachment being modified
add_action( 'init', array( 'WP_Detect_Face', 'setup' ) );

class WP_Detect_Face {

	/**
	 * @var int|null Reference to currently edited attachment post
	 */
	public static $attachment_id;

	public function setup() {

		// get current attachment ID
		add_filter( 'get_attached_file', array( __CLASS__, 'set_attachment_id' ), 10, 2 );
		add_filter( 'update_attached_file', array( __CLASS__, 'set_attachment_id' ), 10, 2 );

		// use our extended class
		add_filter( 'wp_image_editors', array( __CLASS__, 'image_editors' ), 11, 1 );

	}

	/**
	 * Hacky use of attached_file filters to get current attachment ID being resized
	 * Used to store face location and dimensions
	 *
	 * @param string $file          File path
	 * @param int $attachment_id Attachment ID
	 *
	 * @return string    The file path
	 */
	public function set_attachment_id( $file, $attachment_id ) {
		self::$attachment_id = $attachment_id;
		return $file;
	}

	/**
	 * Inserts face detect image editor prior to the standard GD editor
	 *
	 * @param array $editors Array of image editor class names
	 *
	 * @return array    Image editor class names
	 */
	public function image_editors( $editors ) {
		// Face detection class
		if ( ! class_exists( 'Face_Detector' ) )
			require_once 'php-facedetection/FaceDetector.php';

		// Face detection image editor
		if ( ! class_exists( 'WP_Image_Editor_GD_Detect_Face' ) )
			require_once 'editors/gd-face-detect.php';

		$offset = array_search( 'WP_Image_Editor_GD', $editors );
		return array_merge(
			array_slice( $editors, 0, $offset ),
			array( 'WP_Image_Editor_GD_Detect_Face' ),
			array_slice( $editors, $offset, null )
		);
	}

}
