<?php
/*
Plugin Name: My eyes are up here
Plugin URI: https://github.com/interconnectit/my-eyes-are-up-here
Description: Detects faces during thumbnail cropping and moves the crop position accordingly
Author: Robert O'Rourke @ interconnect/it
Version: 1.0.1
Author URI: http://interconnectit.com

Thanks to Marko Heijnen for feedback
https://github.com/markoheijnen

Changelog
=========

- 0.4:
	Bugfixes, play nicely with other plugins/themes that modify image sizes

- 0.3:
	Hotspots!

- 0.2:
	jQuery option for speed

*/

defined( 'FACE_DETECT_PATH' ) or define( 'FACE_DETECT_PATH', plugin_dir_path( __FILE__ ) );
defined( 'FACE_DETECT_URL'  ) or define( 'FACE_DETECT_URL',  plugins_url( '', __FILE__ ) );

// track attachment being modified
add_action( 'plugins_loaded', array( 'WP_Detect_Faces', 'instance' ) );

class WP_Detect_Faces {

	/**
	 * @var int|null Reference to currently edited attachment post
	 */
	public static $attachment_id;

	/**
	 * @var placeholder for current faces array
	 */
	public $faces = array();
	public $hotspots = array();


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

		// image resize dimensions
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'reset' ), 10, 2 );
		add_filter( 'image_resize_dimensions', array( $this, 'crop' ), 11, 6 );

		// javascript
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// ajax callbacks
		// - get large image
		add_action( 'wp_ajax_facedetect_get_image', array( $this, 'get_image' ) );
		// - save faces
		add_action( 'wp_ajax_facedetect_save', array( $this, 'save' ) );

		// add button
		add_filter( 'attachment_fields_to_edit', array( $this, 'edit_fields' ), 10, 2 );
	}


	public function admin_scripts() {

		wp_register_script( 'facedetection-ccv', FACE_DETECT_URL . '/jquery-facedetection/js/facedetection/ccv.js', array( 'jquery' ) );
		wp_register_script( 'facedetection-face', FACE_DETECT_URL . '/jquery-facedetection/js/facedetection/face.js', array( 'jquery', 'facedetection-ccv' ) );
		wp_register_script( 'jquery-facedetection', FACE_DETECT_URL . '/jquery-facedetection/js/jquery.facedetection.js', array( 'facedetection-face' ) );
		wp_register_script( 'facedetection', FACE_DETECT_URL . '/js/face-detect.js', array( 'jquery-facedetection' ), '0.2', true );
		wp_localize_script( 'facedetection', 'facedetection', array(
			'ajax_url' 				=> admin_url( '/admin-ajax.php' ),
			'get_image_nonce' 		=> wp_create_nonce( 'fd_get_image' ),
			'save_nonce' 			=> wp_create_nonce( 'fd_save' )
		) );

		// load our scripts
		wp_enqueue_script( 'facedetection' );

		// stylesheet
		wp_enqueue_style( 'facedetection', FACE_DETECT_URL . '/css/admin.css' );

	}


	public function get_image() {
		check_ajax_referer( 'fd_get_image', 'fd_get_image_nonce' );

		$response = array( 'original' => false );

		$att_id = isset( $_POST[ 'attachment_id' ] ) ? intval( $_POST[ 'attachment_id' ] ) : false;

		if ( $att_id )
			$response = array(
				'original' => wp_get_attachment_image_src( $att_id, 'full' )
			);

		$this->send_json( $response );
	}


	public function save() {
		check_ajax_referer( 'fd_save', 'fd_save_nonce' );

		$response = array();

		$att_id = isset( $_POST[ 'attachment_id' ] ) ? intval( $_POST[ 'attachment_id' ] ) : false;

		// faces
		if ( isset( $_POST[ 'faces' ] ) ) {
			if ( $_POST[ 'faces' ] )
				update_post_meta( $att_id, 'faces', $_POST[ 'faces' ] );
			else
				delete_post_meta( $att_id, 'faces' );
		}

		// hotspots
		if ( isset( $_POST[ 'hotspots' ] ) ) {
			if ( $_POST[ 'hotspots' ] )
				update_post_meta( $att_id, 'hotspots', $_POST[ 'hotspots' ] );
			else
				delete_post_meta( $att_id, 'hotspots' );
		}

		// regenerate thumbs
		$resized = $this->regenerate_thumbs( $att_id );

		if ( ! empty( $resized ) )
			$response[ 'resized' ] = $resized;

		$this->send_json( $response );
	}


	public function get_cropped_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = array();

		$size_names = get_intermediate_image_sizes();

		foreach( $size_names as $size ) {
			if ( in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) ) {
				$width  = intval( get_option( $size . '_size_w' ) );
				$height = intval( get_option( $size . '_size_h' ) );
				$crop 	= get_option( $size . '_crop' );
			} else {
				$width  = $_wp_additional_image_sizes[ $size ][ 'width' ];
				$height = $_wp_additional_image_sizes[ $size ][ 'height' ];
				$crop  	= $_wp_additional_image_sizes[ $size ][ 'crop' ];
			}
			if ( $crop )
				$sizes[ $size ] = array( 'width' => $width, 'height' => $height, 'crop' => $crop );
		}

		return $sizes;
	}


	public function regenerate_thumbs( $attachment_id ) {
	
		// this sets up the faces & hotspots arrays
		$file = get_attached_file( $attachment_id );

		// 5 minutes per image should be PLENTY
		@set_time_limit( 900 );

		// resize thumbs
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( is_wp_error( $metadata ) )
			return array( 'id' => $attachment_id, 'error' => $metadata->get_error_message() );
		if ( empty( $metadata ) )
			return array( 'id' => $attachment_id, 'error' => __( 'Unknown failure reason.' ) );

		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$sizes = $this->get_cropped_sizes();
		$resized = array();

		foreach( $sizes as $size => $atts ) {
			$resized[ $size ] = wp_get_attachment_image_src( $attachment_id, $size );
		}

		return $resized;
	}


	public function edit_fields( $form_fields, $attachment ) {

		if ( ! wp_attachment_is_image( $attachment->ID ) ) {
			return $form_fields;
		}
	
		$faces = get_post_meta( $attachment->ID, 'faces', true );
		$hotspots = get_post_meta( $attachment->ID, 'hotspots', true );

		$data_atts = '';
		if ( $faces )
			$data_atts .= ' data-faces="' . esc_attr( json_encode( $faces ) ) . '"';
		if ( $hotspots )
			$data_atts .= ' data-hotspots="' . esc_attr( json_encode( $hotspots ) ) . '"';

		$button = '
		<div class="face-detection-ui hide-if-no-js">
			<div class="post-thumbnail-preview alignright">
				<div><strong>' . __( 'Thumb Previews' ) . '</strong></div>';

		foreach( $this->get_cropped_sizes() as $size => $atts ) {
			$src = wp_get_attachment_image_src( $attachment->ID, $size );
			$button .= '<div class="preview-wrap"><img src="' . $src[ 0 ] . '?v=' . time() . '" alt="' . $size . '" data-size="' . $size . '" /></div>';
		}

		$button .= '
			</div>
			<div class="face-detection face-detect-panel">';

		if ( $faces )
			$button .= '<button class="button face-detection-activate has-faces" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Forget found faces' ) . '</button>';
		else
			$button .= '<button class="button face-detection-activate" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Detect faces' ) . '</button>';

		$button .= '
				<span class="status"></span>
				<p class="description">' . __( 'Please note this is basic face detection and won\'t find everything. Use hotspots to highlight any that were missed.' ) . '</p>
				<div class="found-faces"></div>';

		if ( false && $faces )
			$button .= ' <p class="detected-faces">' . count( $faces ) . ' ' . _n( 'face', 'faces', count( $faces ) ) . ' found, thumbnails regenerated to fit them into crop area.</p>';

		$button .= '
			</div>
			<div class="image-hotspots face-detect-panel">';

		if ( $hotspots )
			$button .= '<button class="button add-hotspots has-hotspots" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Edit hotspots' ) . '</button>';
		else
			$button .= '<button class="button add-hotspots" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Add hotspots' ) . '</button>';

		$button .= '
				<span class="status"></span>
				<p class="description">' . __( 'Manually add hotspots that you want to avoid cropping.' ) . '</p>';

		if ( false && $hotspots )
			$button .= ' <p class="added-hotspots">' . count( $hotspots ) . ' ' . _n( 'hotspot', 'hotspots', count( $hotspots ) ) . ' found, thumbnails were regenerated to fit them into crop area.</p>';

		$button .= '
			</div>
			<div class="face-detection-crop-preview"></div>
			<div class="face-detection-image"' . $data_atts . '></div>
		</div>
		<div class="hide-if-js">
			<p>' . __( 'This plugin requires javascript to work' ) . '</p>
		</div>';

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
	public function crop( $output, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {

		// only need to detect if cropping
		if ( $crop && ( ! empty( $this->faces ) || ! empty( $this->hotspots ) ) ) {

			// if we have a face or two
			$faces = array_merge( $this->faces, $this->hotspots );

			if ( count( $faces ) ) {

				if ( is_array( $output ) ) {
					list( $dest_x, $dest_y, $src_x, $src_y, $new_w, $new_h, $src_w, $src_h ) = $output;
				}

				// get faces area
				$face_src_x = 9999999999999;
				$face_src_y = 9999999999999;
				$face_src_max_x = $face_src_max_w = 0;
				$face_src_max_y = $face_src_max_h = 0;

				// create bounding box
				foreach( $faces as $face ) {
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

				// preserve settings already filtered in
				if ( $output === null ) {
					$new_w = min($dest_w, $orig_w);
					$new_h = min($dest_h, $orig_h);

					if ( !$new_w ) {
						$new_w = intval($new_h * $aspect_ratio);
					}

					if ( !$new_h ) {
						$new_h = intval($new_w / $aspect_ratio);
					}
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
				return array( 0, 0, $src_x, $src_y, $new_w, $new_h, $crop_w, $crop_h );
			}

		}

		return $output;
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

		// get existing data
		$faces = get_post_meta( $attachment_id, 'faces', true );
		if ( ! empty( $faces ) )
			$this->faces = $faces;
		$hotspots = get_post_meta( $attachment_id, 'hotspots', true );
		if ( ! empty( $hotspots ) )
			$this->hotspots = $hotspots;

		return $file;
	}


	/**
	 * Resets the faces and hotspots array ready for the next attachment
	 *
	 * @param array $metadata
	 * @param int $attachment_id
	 *
	 * @return array
	 */
	public function reset( $metadata, $attachment_id ) {
		$this->faces = array();
		$this->hotspots = array();
		return $metadata;
	}
}
