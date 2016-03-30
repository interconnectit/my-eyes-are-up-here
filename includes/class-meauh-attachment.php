<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEAUH_Attachment
 */
class MEAUH_Attachment {
	/**
	 * Faces
	 *
	 * @var array
	 */
	public $faces = array();

	/**
	 * Hotspots
	 *
	 * @var array
	 */
	public $hotspots = array();

	/**
	 * Init
	 */
	public static function init() {
		$self = new self;

		// Current attachment data.
		add_filter( 'get_attached_file', array( $self, 'set_attachment_id' ), 10, 2 );
		add_filter( 'update_attached_file', array( $self, 'set_attachment_id' ), 10, 2 );

		// Image resize dimensions.
		add_filter( 'wp_generate_attachment_metadata', array( $self, 'reset' ), 10, 2 );
		add_filter( 'image_resize_dimensions', array( $self, 'crop' ), 11, 6 );

		// Add button.
		add_filter( 'attachment_fields_to_edit', array( $self, 'edit_fields' ), 10, 2 );
	}

	/**
	 * Regenerate thumbnails
	 *
	 * @param int $attachment_id Attachment id.
	 *
	 * @return array
	 */
	public static function regenerate( $attachment_id ) {
		// Sets up the faces & hotspots arrays.
		$file = get_attached_file( $attachment_id );

		// 5 minutes per image should be PLENTY.
		@set_time_limit( 900 );

		// Resize thumbs.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( is_wp_error( $metadata ) ) {
			return array( 'id' => $attachment_id, 'error' => $metadata->get_error_message() );
		}
		if ( empty( $metadata ) ) {
			return array( 'id' => $attachment_id, 'error' => __( 'Unknown failure reason.' ) );
		}

		// If this fails, then it just means that nothing was changed.
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$sizes   = self::get_cropped_sizes();
		$resized = array();

		foreach ( $sizes as $size => $atts ) {
			$resized[ $size ] = wp_get_attachment_image_src( $attachment_id, $size );
		}

		return $resized;
	}

	/**
	 * Hacky use of attached_file filters to get current attachment ID being resized
	 * Used to store face location and dimensions
	 *
	 * @param string $file File name.
	 * @param int $attachment_id Attachment id.
	 *
	 * @return string
	 */
	public function set_attachment_id( $file, $attachment_id ) {
		$faces = get_post_meta( $attachment_id, 'faces', true );
		if ( $faces ) {
			$this->faces = $faces;
		}

		$hotspots = get_post_meta( $attachment_id, 'hotspots', true );
		if ( $hotspots ) {
			$this->hotspots = $hotspots;
		}

		return $file;
	}

	/**
	 * Resets the faces and hotspots array
	 *
	 * @param array $metadata Meta data.
	 *
	 * @return array
	 */
	public function reset( $metadata ) {
		$this->faces    = array();
		$this->hotspots = array();

		return $metadata;
	}

	/**
	 * Alters the crop location of the GD image editor class by detecting faces
	 * and centering the crop around them
	 *
	 * @param array $output Output.
	 * @param int $orig_w Original width.
	 * @param int $orig_h Original height.
	 * @param int $dest_w width.
	 * @param int $dest_h height.
	 * @param bool $crop Crop.
	 *
	 * @return array
	 */
	public function crop( $output, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {

		// Only need to detect if cropping.
		if ( $crop && ( ! empty( $this->faces ) || ! empty( $this->hotspots ) ) ) {

			// If we have a face or two.
			$faces = array_merge( $this->faces, $this->hotspots );

			if ( count( $faces ) ) {

				if ( is_array( $output ) ) {
					list( $dest_x, $dest_y, $src_x, $src_y, $new_w, $new_h, $src_w, $src_h ) = $output;
				}

				// Get faces area.
				$face_src_x     = 9999999999999;
				$face_src_y     = 9999999999999;
				$face_src_max_x = $face_src_max_w = 0;
				$face_src_max_y = $face_src_max_h = 0;

				// Create bounding box.
				foreach ( $faces as $face ) {
					// Left and top most x,y.
					if ( $face_src_x > $face['x'] ) {
						$face_src_x = $face['x'];
					}
					if ( $face_src_y > $face['y'] ) {
						$face_src_y = $face['y'];
					}

					// Right and bottom most x,y.
					if ( $face_src_max_x < $face['x'] + $face['width'] ) {
						$face_src_max_x = $face['x'] + $face['width'];
					}
					if ( $face_src_max_y < $face['y'] + $face['width'] ) {
						$face_src_max_y = $face['y'] + $face['width'];
					}
				}

				$face_src_w = $face_src_max_x - $face_src_x;
				$face_src_h = $face_src_max_y - $face_src_y;

				// Crop the largest possible portion of the original image that we can size to $dest_w x $dest_h.
				$aspect_ratio = $orig_w / $orig_h;

				// Preserve settings already filtered in.
				if ( $output === null ) {
					$new_w = min( $dest_w, $orig_w );
					$new_h = min( $dest_h, $orig_h );

					if ( ! $new_w ) {
						$new_w = intval( $new_h * $aspect_ratio );
					}

					if ( ! $new_h ) {
						$new_h = intval( $new_w / $aspect_ratio );
					}
				}

				$size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

				$crop_w = round( $new_w / $size_ratio );
				$crop_h = round( $new_h / $size_ratio );

				$src_x = floor( ( $orig_w - $crop_w ) / 2 );
				$src_y = floor( ( $orig_h - $crop_h ) / 2 );

				// Bounding box.
				if ( $src_x === 0 ) {
					$src_y = ( $face_src_y + ( $face_src_h / 2 ) ) - ( $crop_h / 2 );
					$src_y = min( max( 0, $src_y ), $orig_h - $crop_h );
				}

				if ( $src_y === 0 ) {
					$src_x = ( $face_src_x + ( $face_src_w / 2 ) ) - ( $crop_w / 2 );
					$src_x = min( max( 0, $src_x ), $orig_w - $crop_w );
				}

				return array( 0, 0, $src_x, $src_y, $new_w, $new_h, $crop_w, $crop_h );
			}
		}

		return $output;
	}

	/**
	 * Edit fields
	 *
	 * @param array $form_fields Form fields.
	 * @param stdClass $attachment Attachment.
	 *
	 * @return mixed
	 */
	public function edit_fields( array $form_fields, $attachment ) {
		if ( ! wp_attachment_is_image( $attachment->ID ) ) {
			return $form_fields;
		}

		$faces    = get_post_meta( $attachment->ID, 'faces', true );
		$hotspots = get_post_meta( $attachment->ID, 'hotspots', true );

		$data_atts = '';
		if ( $faces ) {
			$data_atts .= ' data-faces="' . esc_attr( json_encode( $faces ) ) . '"';
		}
		if ( $hotspots ) {
			$data_atts .= ' data-hotspots="' . esc_attr( json_encode( $hotspots ) ) . '"';
		}

		$button = '
		<div class="face-detection-ui hide-if-no-js">
			<div class="post-thumbnail-preview alignright">
				<div><strong>' . __( 'Thumb Previews' ) . '</strong></div>';

		foreach ( self::get_cropped_sizes() as $size => $atts ) {
			$src = wp_get_attachment_image_src( $attachment->ID, $size );
			$button .= '<div class="preview-wrap"><img src="' . $src[0] . '?v=' . time() . '" alt="' . $size . '" data-size="' . $size . '" /></div>';
		}

		$button .= '
			</div>
			<div class="face-detection face-detect-panel">';

		if ( $faces ) {
			$button .= '<button class="button face-detection-activate has-faces" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Forget found faces' ) . '</button>';
		} else {
			$button .= '<button class="button face-detection-activate" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Detect faces' ) . '</button>';
		}

		$button .= '
				<span class="status"></span>
				<p class="description">' . __( 'Please note this is basic face detection and won\'t find everything. Use hotspots to highlight any that were missed.' ) . '</p>
				<div class="found-faces"></div>';

		if ( false && $faces ) {
			$button .= ' <p class="detected-faces">' .
			           count( $faces ) . ' ' .
			           _n( 'face', 'faces', count( $faces ) ) .
			           ' found, thumbnails regenerated to fit them into crop area.</p>';
		}

		$button .= '
			</div>
			<div class="image-hotspots face-detect-panel">';

		if ( $hotspots ) {
			$button .= '<button class="button add-hotspots has-hotspots" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Edit hotspots' ) . '</button>';
		} else {
			$button .= '<button class="button add-hotspots" type="button" data-attachment-id="' . $attachment->ID . '">' . __( 'Add hotspots' ) . '</button>';
		}

		$button .= '
				<span class="status"></span>
				<p class="description">' . __( 'Manually add hotspots that you want to avoid cropping.' ) . '</p>';

		if ( false && $hotspots ) {
			$button .= ' <p class="added-hotspots">' .
			           count( $hotspots ) . ' ' .
			           _n( 'hotspot', 'hotspots', count( $hotspots ) ) .
			           ' found, thumbnails were regenerated to fit them into crop area.</p>';
		}

		$button .= '
			</div>
			<div class="face-detection-crop-preview"></div>
			<div class="face-detection-image"' . $data_atts . '></div>
		</div>
		<div class="hide-if-js">
			<p>' . __( 'This plugin requires javascript to work' ) . '</p>
		</div>';

		$form_fields['face_detection'] = array(
			'label' => __( 'Face detection' ),
			'input' => 'html',
			'html'  => $button,
		);

		return $form_fields;
	}

	/**
	 * Get cropped sizes
	 *
	 * @return array
	 */
	protected static function get_cropped_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = array();

		$size_names = get_intermediate_image_sizes();

		foreach ( $size_names as $size ) {
			if ( in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) ) {
				$width  = intval( get_option( $size . '_size_w' ) );
				$height = intval( get_option( $size . '_size_h' ) );
				$crop   = get_option( $size . '_crop' );
			} else if ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				$width  = $_wp_additional_image_sizes[ $size ]['width'];
				$height = $_wp_additional_image_sizes[ $size ]['height'];
				$crop   = $_wp_additional_image_sizes[ $size ]['crop'];
			}
			if ( $crop ) {
				$sizes[ $size ] = array( 'width' => $width, 'height' => $height, 'crop' => $crop );
			}
		}

		return $sizes;
	}
}

MEAUH_Attachment::init();
