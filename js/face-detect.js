;(function($){
	
	$( document ).on( 'click', '.face-detection-activate', function(e) {
		e.preventDefault();
		
		var $statusbox = $( this ).next(),
			attachment_id = $( this ).data( 'attachment-id' );
		
		// update status - loading full image
		$statusbox.css( {
			paddingLeft: '20px',
			background: 'url(/wp-admin/images/wpspin_light.gif) no-repeat left center',
			backgroundSize: 'contain'
		} ).html( 'Loading full image' );
		
		// request full image
		$.post( facedetection.ajax_url, {
			action: 'facedetect_get_image',
			fd_get_image_nonce: facedetection.get_image_nonce,
			attachment_id: attachment_id
		}, function( rsp ) {
			if ( rsp && rsp.img ) {
				var image = new Image();
				image.src = rsp.img[ 0 ];
				
				console.log( rsp, image );
				
				$( image )
					.attr( 'id', 'facedetect-image' )
					.css( { position: 'absolute', top: '-9999px', left: '-9999px' } )
					.appendTo( 'body' )
					.load( function() {
						
						// update status - finding faces
						$statusbox.html( 'Looking for faces' );
				
						// face detection
						$( '#facedetect-image' ).faceDetection( {
							confidence: 0,
							start: function( img ) {}, // doesn't work yet
							complete: function( img, faces ) {
								// update status - found faces
								console.log( faces );
								$statusbox.html( 'Found ' + faces.length + ' faces, resizing thumbnails' );
								
								if ( ! faces.length ) {
									console.log( 'no faces...' );
									return;
								}
								
								// save data & regen
								$.post( facedetection.ajax_url, {
									action: 'facedetect_save_faces',
									fd_save_faces_nonce: facedetection.save_faces_nonce,
									attachment_id: attachment_id,
									faces: faces
								}, function( rsp ) {
									if ( rsp && rsp.resized ) {	
										// update status - thumbs regenerated
										console.log( rsp.resized );
										$statusbox.css( {
											paddingLeft: 0,
											background: 'none'
										} ).html( 'Thumbnails resized' );
									} else {
										console.log( 'no regenerated thumbs', rsp );
										$statusbox.css( {
											paddingLeft: 0,
											background: 'none'
										} ).html( 'No thumbnails were resized, only cropped thumbnails will be regenerated' );
									}
								}, 'json' );
								
								// cleanup
								$( '#facedetect-image' ).remove();
							},
							error: function( img, code, message  ) {
								// update status - error, message
								console.log( 'error', message );
								$statusbox.css( {
									paddingLeft: 0,
									background: 'none'
								} ).html( 'Error (' + code + '): ' + message );
								
								// cleanup
								$( '#facedetect-image' ).remove();
							}
						} );
						
					} );
				
				
			} else {
				console.log( 'no image url' );
			}
		}, 'json' );
		
	} );
	
})(jQuery);