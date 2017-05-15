<?php
/**
 * Attachment
 *
 * @package my-eyes-are-up-here
 * @author interconnect/it
 */

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
	protected $faces = array();

	/**
	 * Hotspots
	 *
	 * @var array
	 */
	protected $hotspots = array();

	/**
	 * Init
	 */
	public static function init() {
		$self = new self;

		// Current attachment data.
		add_filter( 'get_attached_file', array( $self, 'set_attachment_id' ), 10, 2 );
		add_filter( 'update_attached_file', array( $self, 'set_attachment_id' ), 10, 2 );

		// Image resize dimensions.
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
		// 5 minutes per image should be PLENTY.
		@set_time_limit( 5 * MINUTE_IN_SECONDS );

		// Resize thumbnail.
		$file     = get_attached_file( $attachment_id );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( is_wp_error( $metadata ) ) {
			return array( 'id' => $attachment_id, 'error' => $metadata->get_error_message() );
		}

		if ( empty( $metadata ) ) {
			return array( 'id' => $attachment_id, 'error' => __( 'Unknown failure reason', 'my-eyes-are-up-here' ) );
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
		$this->faces    = (array) get_post_meta( $attachment_id, 'faces', true );
		$this->hotspots = (array) get_post_meta( $attachment_id, 'hotspots', true );

		return $file;
	}

	/**
	 * Alters the crop location of the GD image editor class by detecting faces
	 * and centering the crop around them
	 *
	 * @param array $payload Payload.
	 * @param int $orig_w Original width.
	 * @param int $orig_h Original height.
	 * @param int $dest_w width.
	 * @param int $dest_h height.
	 * @param bool $crop Crop.
	 *
	 * @return array
	 */
	public function crop( $payload, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
		$hotspots = array_filter( array_merge( $this->faces, $this->hotspots ) );
		if ( ! $crop || empty( $hotspots ) ) {
			return $payload;
		}

		if ( is_array( $payload ) ) {
			list( $dest_x, $dest_y, $src_x, $src_y, $new_w, $new_h, $src_w, $src_h ) = $payload;
		}

		// Get faces area.
		$hotspot_src_x     = $hotspot_src_y = PHP_INT_MAX;
		$hotspot_src_max_x = $hotspot_src_max_w = 0;
		$hotspot_src_max_y = $hotspot_src_max_h = 0;

		// Create bounding box.
		foreach ( $hotspots as $hotspot ) {
			$hotspot = array_map( 'absint', $hotspot );

			// Left and top most x,y.
			if ( $hotspot_src_x > $hotspot['x'] ) {
				$hotspot_src_x = $hotspot['x'];
			}

			if ( $hotspot_src_y > $hotspot['y'] ) {
				$hotspot_src_y = $hotspot['y'];
			}

			// Right and bottom most x,y.
			if ( $hotspot_src_max_x < $hotspot['x'] ) {
				$hotspot_src_max_x = $hotspot['x'];
			}

			if ( $hotspot_src_max_y < $hotspot['y'] ) {
				$hotspot_src_max_y = $hotspot['y'];
			}
		}

		$hotspot_src_w = $hotspot_src_max_x - $hotspot_src_x;
		$hotspot_src_h = $hotspot_src_max_y - $hotspot_src_y;

		// Crop the largest possible portion of the original image that we can size to $dest_w x $dest_h.
		$aspect_ratio = $orig_w / $orig_h;

		// Preserve settings already filtered in.
		if ( null === $payload ) {
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
		if ( 0 == $src_x ) {
			$src_y = ( $hotspot_src_y + $hotspot_src_h / 2 ) - $crop_h / 2;
			$src_y = min( max( 0, $src_y ), $orig_h - $crop_h );
		}

		if ( 0 == $src_y ) {
			$src_x = ( $hotspot_src_x + $hotspot_src_w / 2 ) - $crop_w / 2;
			$src_x = min( max( 0, $src_x ), $orig_w - $crop_w );
		}

		return array( 0, 0, (int) $src_x, (int) $src_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
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
			<div class="post-thumbnail-preview">
				<div><strong>' . __( 'Thumb Previews', 'my-eyes-are-up-here' ) . '</strong></div>';

		foreach ( self::get_cropped_sizes() as $size => $atts ) {
			$src = wp_get_attachment_image_src( $attachment->ID, $size );
			$button .= '<div class="preview-wrap"><img src="' . $src[0] . '?v=' . time() . '" alt="' . $size . '" data-size="' . $size . '"></div>';
		}

		$button .= '
			</div>
			<div class="face-detection face-detect-panel">';

		if ( $faces ) {
			$button .= sprintf( '<button class="button face-detection-activate has-faces" type="button" data-attachment-id="%d">%s</button>',
				$attachment->ID,
				__( 'Forget found faces', 'my-eyes-are-up-here' )
			);
		} else {
			$button .= sprintf( '<button class="button face-detection-activate" type="button" data-attachment-id="%d">%s</button>',
				$attachment->ID,
				__( 'Detect faces', 'my-eyes-are-up-here' )
			);
		}

		$button .= '<span class="status"></span>';
		$button .= sprintf( '<p class="description">%s</p>',
			__( "Please note this is basic face detection and won't find everything. Use hotspots to highlight any that were missed.",
				'my-eyes-are-up-here' )
		);
		$button .= '<div class="found-faces"></div>';

		if ( false && $faces ) {
			$button .= '<p class="detected-faces">';
			$button .= sprintf( __( '%d %s found, thumbnails regenerated to fit them into crop area.',
				'my-eyes-are-up-here' ),
				count( $faces ),
				_n( 'face', 'faces', count( $faces ), 'my-eyes-are-up-here' )
			);
			$button .= '</p>';
		}

		$button .= '
			</div>
			<div class="image-hotspots face-detect-panel">';

		if ( $hotspots ) {
			$button .= sprintf( '<button class="button add-hotspots has-hotspots" type="button" data-attachment-id="%d">%s</button>',
				$attachment->ID,
				__( 'Edit hotspots', 'my-eyes-are-up-here' )
			);
		} else {
			$button .= sprintf( '<button class="button add-hotspots" type="button" data-attachment-id="%d">%s</button>',
				$attachment->ID,
				__( 'Add hotspots', 'my-eyes-are-up-here' )
			);
		}

		$button .= '<span class="status"></span>';
		$button .= sprintf( '<p class="description">%s</p>',
			__( 'Manually add hotspots that you want to avoid cropping.', 'my-eyes-are-up-here' )
		);

		if ( false && $hotspots ) {
			$button .= '<p class="added-hotspots">';
			$button .= sprintf( __( '%d %s found, thumbnails regenerated to fit them into crop area.',
				'my-eyes-are-up-here' ),
				count( $hotspots ),
				_n( 'hotspot', 'hotspots', count( $hotspots ), 'my-eyes-are-up-here' )
			);
			$button .= '</p>';
		}

		$button .= '
			</div>
			<div class="face-detection-crop-preview"></div>
			<div class="face-detection-image"' . $data_atts . '></div>
		</div>
		<div class="hide-if-js">
			<p>' . __( 'This plugin requires javascript to work', 'my-eyes-are-up-here' ) . '</p>
		</div>';

		$form_fields['face_detection'] = array(
			'label' => __( 'Face detection', 'my-eyes-are-up-here' ),
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

		$sizes      = array();
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
				$sizes[ $size ] = array(
					'width'  => $width,
					'height' => $height,
					'crop'   => $crop,
				);
			}
		}

		return $sizes;
	}
}

MEAUH_Attachment::init();
