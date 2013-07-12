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

Changelog
=========

- 0.2:
  jQuery option for speed
*/

if ( ! defined( 'FACE_DETECT_PATH' ) )
	define( 'FACE_DETECT_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'FACE_DETECT_URL' ) )
	define( 'FACE_DETECT_URL', plugins_url( '', __FILE__ ) );

// track attachment being modified
add_action( 'plugins_loaded', array( 'WP_Detect_Faces', 'instance' ) );

class WP_Detect_Faces {

	/**
	 * @var int|null Reference to currently edited attachment post
	 */
	public static $attachment_id;
	
	/**
	 * @var bool Switches on/off the PHP based face detection,
	 * 			 recommended to use JS as MUCH it's quicker
	 */
	public static $use_php = false;
	
	/**
	 * @var placeholder for current faces array
	 */
	public $faces;
	
	
	/**
	 * Reusable object instance.
	 *
	 * @type object
	 */
	protected static $instance = null;

	/**
	 * Creates a new instance. Called on 'after_setup_theme'.
	 * May be used to access class methods from outside.
	 *
	 * @see    __construct()
	 * @return void
	 */
	public static function instance() {
		null === self :: $instance AND self :: $instance = new self;
		return self :: $instance;
	}
	
	
	public function __construct() {
		
		add_action( 'init', array( $this, 'init' ) );
		
	}

	public function init() {

		// get current attachment ID
		add_filter( 'get_attached_file', array( $this, 'set_attachment_id' ), 10, 2 );
		add_filter( 'update_attached_file', array( $this, 'set_attachment_id' ), 10, 2 );

		// use our extended class
		if ( self::$use_php )
			add_filter( 'wp_image_editors', array( $this, 'image_editors' ), 11, 1 );

		// set up js interface
		if ( ! self::$use_php ) {
			
			// javascript
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			
			// ajax callbacks
			// - get large image
			add_action( 'wp_ajax_facedetect_get_image', array( $this, 'get_image' ) );
			// - save faces
			add_action( 'wp_ajax_facedetect_save_faces', array( $this, 'save_faces' ) );
			
			// add button
			add_filter( 'attachment_fields_to_edit', array( $this, 'edit_fields' ), 10, 2 );
			
		}
	}
	
	
	public function admin_scripts() {
		
		wp_register_script( 'facedetection-ccv', FACE_DETECT_URL . '/jquery-facedetection/js/facedetection/ccv.js', array( 'jquery' ) );
		wp_register_script( 'facedetection-face', FACE_DETECT_URL . '/jquery-facedetection/js/facedetection/face.js', array( 'jquery', 'facedetection-ccv' ) );
		wp_register_script( 'jquery-facedetection', FACE_DETECT_URL . '/jquery-facedetection/js/jquery.facedetection.js', array( 'facedetection-face' ) );
		wp_register_script( 'facedetection', FACE_DETECT_URL . '/js/face-detect.js', array( 'jquery-facedetection' ), '0.2', true );
		wp_localize_script( 'facedetection', 'facedetection', array(
			'ajax_url' 				=> admin_url( '/admin-ajax.php' ),
			'get_image_nonce' 		=> wp_create_nonce( 'fd_get_image' ),
			'save_faces_nonce' 		=> wp_create_nonce( 'fd_save_faces' )
		) );
		
		// load our scripts
		wp_enqueue_script( 'facedetection' );
		
	}
	
	
	public function get_image() {
		check_ajax_referer( 'fd_get_image', 'fd_get_image_nonce' );
		
		$response = array( 'img' => false );
		
		$att_id = isset( $_POST[ 'attachment_id' ] ) ? intval( $_POST[ 'attachment_id' ] ) : false;
		
		if ( $att_id )
			$response = array( 'img' => wp_get_attachment_image_src( $att_id, 'full' ) );
		
		$this->send_json( $response );
	}
	
	
	public function save_faces() {
		check_ajax_referer( 'fd_save_faces', 'fd_save_faces_nonce' );
		
		$response = array();
		
		$att_id = isset( $_POST[ 'attachment_id' ] ) ? intval( $_POST[ 'attachment_id' ] ) : false;
		
		// faces
		$this->faces = $_POST[ 'faces' ];
		update_post_meta( $att_id, 'faces', $this->faces );
		
		// regenerate thumbs
		$resized = $this->regenerate_thumbs( $att_id );
		
		if ( ! empty( $resized ) )
			$response[ 'resized' ] = $resized;
		
		$this->send_json( $response );
	}
	
	
	public function regenerate_thumbs( $attachment_id ) {
		global $_wp_additional_image_sizes;
		
		// image resize dimensions
		add_filter( 'image_resize_dimensions', array( $this, 'face_crop' ), 10, 6 );
		
		$file = get_attached_file( $attachment_id );
		
		$imagedata = wp_get_attachment_metadata( $attachment_id );
		
		$sizes = get_intermediate_image_sizes();
		$resized = array();
		
		foreach( $sizes as $size ) {
			if ( in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) ) {
				$width  = intval( get_option( $size . '_size_w' ) );
				$height = intval( get_option( $size . '_size_h' ) );
				$crop 	= get_option( $size . '_crop' );
			} else {
				$width  = $_wp_additional_image_sizes[ $size ][ 'width' ];
				$height = $_wp_additional_image_sizes[ $size ][ 'height' ];
				$crop  	= $_wp_additional_image_sizes[ $size ][ 'crop' ];
			}
			if ( $crop && $new_size = image_make_intermediate_size( $file, $width, $height, true ) )
				$resized[ $size ] = $new_size;
		}
		
		return $resized;
	}
	
	
	function edit_fields( $form_fields, $attachment ) {
		
		$faces = get_post_meta( $attachment->ID, 'faces', true );
		
		$button = '<button class="button face-detection-activate hide-if-no-js" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Detect Faces' ) . '</button> <span class="status"></span>';
		
		if ( $faces ) {
			$button .= ' <p class="detected-faces">' . count( $faces ) . ' faces found, thumbnails regenerated to fit them into crop area.</p>';
		}
	
		$form_fields[ 'face_detection' ] = array(
			'label' => __( 'Face detection' ),
			'input' => 'html',
			'html' => $button
		);

		return $form_fields;
	}
	
	
	public function send_json( $response ) {
		header( 'Content-type: application/json' );
		echo json_encode( $response );
		exit;
	}
	
	
	/**
	 * Alters the crop location of the GD image editor class by detecting faces
	 * and centering the crop around them
	 *
	 * @param array $output The parameters for imagecopyresampled()
	 * @param int $orig_w Original width
	 * @param int $orig_h Original Height
	 * @param int $dest_w Target width
	 * @param int $dest_h Target height
	 * @param bool $crop   Whether to crop image or not
	 *
	 * @return array
	 */
	public function face_crop( $output, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {

		// only need to detect if cropping
		if ( $crop ) {

			// if we have a face or two
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
					if ( $face_src_max_x < $face[ 'x' ] + $face[ 'width' ] ) $face_src_max_x = $face[ 'x' ] + $face[ 'width' ];
					if ( $face_src_max_y < $face[ 'y' ] + $face[ 'width' ] ) $face_src_max_y = $face[ 'y' ] + $face[ 'width' ];
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
