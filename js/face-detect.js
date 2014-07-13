window.hotspots = {};

// prevent errors while logging to browsers that support it
if ( ! window.console )
	window.console = { log: function(){ } };

;(function($){

	"use strict"

	function hotspots() {

		var t = this;

		$.extend( t, {

			_construct: function() {

				// bind behaviour to buttons
				$( document ).on( 'click', '.face-detection-activate', function() {
					t.set_context( this );
					t.get_image( t.detect_faces );
				} );
				$( document ).on( 'click', '.add-hotspots', function() {
					t.set_context( this );
					t.get_image( t.add_hotspots );
				} );

			},

			attachment_id: null,
			el: null,
			image: null,
			hidden: null,
			images: null,
			$context: null,
			$status_box: null,
			hotspots: [],
			faces: [],

			set_context: function( el ) {
				t.el = el;
				t.attachment_id = $( el ).data( 'attachment-id' );
				t.$ui = $( el ).parents( '.face-detection-ui' );
				t.$context = $( el ).parents( '.face-detect-panel' );
				t.$status_box = t.$context.find( '.status' );
			},

			// request full image
			get_image: function( callback ) {
				callback = callback || function(){ return false; };

				if ( t.image && t.$ui.find( '.face-detection-image img' ).length )
					return callback();

				t.update_status( 'Loading full size image', true );
				$.post( facedetection.ajax_url, {
					action: 'facedetect_get_image',
					fd_get_image_nonce: facedetection.get_image_nonce,
					attachment_id: t.attachment_id
				}, function( rsp ) {
					if ( rsp && rsp.original ) {

						// set our image
						t.image = new Image();

						// save for later
						t.images = rsp;

						// set source to original uncropped image
						t.image.src = rsp.original[0];

						$( t.image )
							.appendTo( '.face-detection-image' )
							.load( function() {
								t.update_status( 'Image loaded' );

								// add our large off-screen sampler for pixastic etc...
								if ( ! $( '.face-detect-large-hidden' ).length )
									$( 'body' ).append( '<img class="face-detect-large-hidden" src="" alt="" />' );
								$( '.face-detect-large-hidden' ).attr( 'src', rsp.original[0] );

								// show current data
								t.show_existing( $( '.face-detection-image' ).data( 'hotspots' ) );
								t.show_existing( $( '.face-detection-image' ).data( 'faces' ), 'face' );

								return callback();
							} );

					}
				}, 'json' );

				return false;
			},

			update_status: function( status, loading ) {
				loading = loading || false;
				t.$status_box.html( status );
				if ( loading )
					t.$status_box.addClass( 'loading' );
				else
					t.$status_box.removeClass( 'loading' );
			},

			detect_faces: function() {

				// Remove the previous copy, end up with one for every button press otherwise.
				$( '.face-detect-large-hidden-copy' ).remove();

				var $found_box = t.$context.find( '.found-faces' ),
					image = $( '.face-detect-large-hidden' ).get( 0 ),
					image_copy = $( image )
									.clone()
									.removeClass( 'face-detect-large-hidden' )
									.addClass( 'face-detect-large-hidden-copy' )
									.appendTo( 'body' )
									.get( 0 );

				if ( $( t.el ).hasClass( 'has-faces' ) ) {

					$( image_copy ).remove();
					//$found_box.html( '' );
					$( t.el )
						.removeClass( 'has-faces' )
						.html( 'Detect faces' );

					$( '.face-detection-image' )
						.data( 'faces', '' )
						.find( '.face' )
						.remove();

					return t.save( { faces: 0 } );
				}

				// face detection
				return $( image_copy ).faceDetection( {
					confidence: 0.05,
					start: function( img ) {
						t.update_status( 'Looking for faces', true );
					}, // doesn't work yet
					complete: function( img, faces ) {
						// update status - found faces
						console.log( 'img:', img, 'faces:', faces );

						t.faces = faces;

						if ( ! t.faces.length ) {
							t.update_status( 'No faces were found' );
							return;
						}

						// allow removal of found faces
						$( t.el )
							.addClass( 'has-faces' )
							.html( 'Forget found faces' );

						t.update_status( 'Found ' + t.faces.length + ' faces, re-cropping thumbnails', true );

						t.show_existing( t.faces, 'face' );

						// cleanup
						$( image_copy ).remove();

						// save data & regen
						t.save( { faces: t.faces } );

					},
					error: function( img, code, message  ) {
						// update status - error, message
						console.log( 'error', message, img );
						t.update_status( 'Error (' + code + '): ' + message );
					}
				} );

			},

			show_existing: function( data, type ) {
				type = type || 'normal';

				var width = $( t.image ).width(),
					correction = t.images.original[1] / width,
					hotspot_width;

				if ( data && data !== '' ) {
					$.each( data, function( i, hotspot ) {
						t.add_hotspot( {
							x: (hotspot.x / correction), // + ((hotspot_width/correction)/2),
							y: (hotspot.y / correction), // + ((hotspot_width/correction)/2),
							width: hotspot.width / correction,
							type: type
						} );
					} );
				}
			},

			add_hotspots: function() {

				var width = $( t.image ).width(),
					hotspot_width = width * .15,
					correction = t.images.original[1] / width;

				// activate hotspots
				if ( ! $( '.face-detection-image' ).hasClass( 'active' ) ) {

					// edit button
					$( t.el )
						.addClass( 'active' )
						.html( 'Finish adding hotspots' );

					t.$ui.find( 'button' ).not( t.el ).attr( 'disabled', 'disabled' );

					t.update_status( 'Click on the image below to add hotspots. Clicking a hotspot will remove it.' );

					// bind hotspot toggling
					$( t.image ).on( 'click.hotspots', t.hotspot_click );

					$( '.face-detection-image' ).addClass( 'active' );

				// deactivate & save
				} else {

					// edit button
					$( t.el )
						.removeClass( 'active' )
						.html( 'Edit hotspots' );

					// remove hotspot toggling
					$( t.image ).off( 'click.hotspots' );

					t.hotspots = [];
					$( '.face-detection-image .hotspot' ).not( '.face' ).each( function() {
						t.hotspots.push( {
							width: Math.round( $( this ).width() * correction ),
							x: Math.round( ( $( this ).position().left ) * correction ),
							y: Math.round( ( $( this ).position().top ) * correction )
						} );
					} );

					$( '.face-detection-image' ).removeClass( 'active' );

					if ( ! t.hotspots.length )
						t.hotspots = 0;

					// save data
					t.save( { hotspots: t.hotspots } );

				}

			},

			hotspot_click: function( e ) {

				var width = $( t.image ).width(),
					hotspot_maxwidth = 150,
					hotspot_width = width * .15 > hotspot_maxwidth ? hotspot_maxwidth : width * .15,
					hotspot_offset = hotspot_width / 2;

				// Firefox doesn't do offsetX/Y so need to do something a little more complex
				t.add_hotspot( {
					x: ( e.offsetX || e.clientX - ( $( e.target ).offset().left - window.scrollX ) ) - hotspot_offset,
					y: ( e.offsetY || e.clientY - ( $( e.target ).offset().top  - window.scrollY ) ) - hotspot_offset
				} );

			},

			add_hotspot: function( hotspot ) {

				var width = $( t.image ).width(),
					height = $( t.image ).height(),
					$parent = $( '.face-detection-image' ),
					hotspot_maxwidth = 150,
					hotspot_width = width * .15 > hotspot_maxwidth ? hotspot_maxwidth : width * .15;

				hotspot = $.extend( {
					x: 0,
					y: 0,
					width: hotspot_width, // default 15% wide, max-width 120px
					type: 'normal'
				}, hotspot );

				$( '<div class="hotspot ' + hotspot.type + '"></div>' )
					.css( {
						left: ( ( hotspot.x / width ) * 100 ) + '%',
						top: ( ( hotspot.y / height ) * 100 ) + '%',
						width: ( ( hotspot.width / width ) * 100 ) + '%',
						paddingBottom: ( ( hotspot.width / width ) * 100 ) + '%'
					} )
					.attr( 'title', hotspot.type == 'normal' ? 'Click to toggle on/off' : '' )
					.appendTo( $parent )
					.click( function() {
						if ( ! $( this ).hasClass( 'face' ) && $parent.hasClass( 'active' ) )
							$( this ).remove();
					} );

			},

			// show a cropped thumbnail preview
			preview: function() {

				var $previews = $( '.post-thumbnail-preview img' ),
					previews_length = $previews.length;

				t.update_status( 'Updating preview', true );

				$previews.each( function( i ) {
					if ( ! t.images[ $( this ).data( 'size' ) ] )
						return;
					$( this )
						.fadeTo( 300, .25 )
						.attr( 'src', t.images[ $( this ).data( 'size' ) ][0] + '?t=' + new Date().getTime() )
						.load( function() {
							$( this ).fadeTo( 300, 1 );
							if ( i == previews_length - 1 )
								t.update_status( '' );
						} );
				} );

			},

			save: function( data ) {

				t.update_status( 'Re-cropping thumbnails', true );

				t.$ui.find( 'button' ).attr( 'disabled', 'disabled' );

				$.post( facedetection.ajax_url, $.extend( {
					action: 'facedetect_save',
					fd_save_nonce: facedetection.save_nonce,
					attachment_id: t.attachment_id
				}, data ), function( rsp ) {
					if ( rsp && rsp.resized ) {

						t.update_status( 'Thumbnails re-cropped' );

						$.extend( t.images, rsp.resized );

						t.preview();

					} else {

						t.update_status( 'No thumbnails were re-cropped' );

					}

					t.$ui.find( 'button' ).removeAttr( 'disabled' );
				}, 'json' );

			}


		} );

		// initialise
		t._construct();

		return t;

	}

	// initialise
	window.hotspots = new hotspots();

})(jQuery);
