( function ( $ ) {
	'use strict';

	function refreshBogoProgress() {
		if ( typeof dcBogoFrontend === 'undefined' || ! dcBogoFrontend.ajaxUrl ) {
			return;
		}

		$.ajax( {
			url: dcBogoFrontend.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'dc_bogo_get_progress',
				nonce: dcBogoFrontend.nonce,
			},
		} )
			.done( function ( res ) {
				if ( res && res.success && res.data ) {
					if ( res.data.refresh ) {
						$( document.body ).trigger( 'wc_update_cart' );
						return;
					}

					var html = res.data.html || '';
					var messages = res.data.messages || [];
					var $fragment = $( html );
					var $existing = $(
						'.dc-bogo-custom-notice-box[data-dc-bogo-progress="1"]'
					);
					if ( $existing.length ) {
						$existing.replaceWith( $fragment );
					} else {
						// Try to insert near the cart form; fall back to top of body
						var $insertPoint = $( '.woocommerce-cart-form' ).first();
						if ( $insertPoint.length ) {
							$insertPoint.prepend( $fragment );
						} else {
							$( 'body' ).prepend( $fragment );
						}
					}

					if ( messages.length ) {
						messages.forEach( function ( m ) {
							var $pop = $( '<div class="dc-bogo-popper">' )
								.text( m )
								.css( {
									position: 'fixed',
									right: '20px',
									bottom: '20px',
									background: '#fff',
									border: '1px solid rgba(0,0,0,0.08)',
									padding: '12px 16px',
									'border-radius': '8px',
									'box-shadow':
										'0 6px 18px rgba(0,0,0,0.08)',
									color: '#0b1726',
									'z-index': 999999,
								} );
							$( 'body' ).append( $pop );
							setTimeout( function () {
								$pop.fadeOut( 300, function () {
									$( this ).remove();
								} );
							}, 3000 );
						} );
					}
				}
			} )
			.fail( function () {
				// Silently fail; this is a non-critical enhancement.
			} );
	}

	// Refresh on common WooCommerce cart events
	$( document.body ).on(
		'updated_cart_totals updated_wc_div wc_fragments_refreshed wc_fragments_loaded',
		function () {
			refreshBogoProgress();
		}
	);

	// Also refresh on page load
	$( function () {
		refreshBogoProgress();
	} );

	// Refresh when a coupon is applied via a button click
	$( document ).on( 'click', '.coupon .button', function () {
		setTimeout( refreshBogoProgress, 250 ); // Delay to allow coupon to be processed
	} );

	// Handle pill switching for grouped progress bars
	$( document ).on( 'click', '.dc-bogo-group-pills .dc-bogo-pill', function () {
		var $btn = $( this );
		var $group = $btn.closest( '.dc-bogo-group-pills' );
		var groupKey = $group.attr( 'data-group-key' );
		var required = parseInt( $btn.attr( 'data-required' ), 10 ) || 0;
		var remaining = parseInt( $btn.attr( 'data-items-remaining' ), 10 ) || 0;
		var current = parseInt( $group.attr( 'data-current' ), 10 ) || 0;
		// Use the group's max required as the scale so the progress fill remains consistent
		var maxRequired = parseInt( $group.attr( 'data-max-required' ), 10 ) || required || 0;
		var qualName = $group.attr( 'data-qualifying-name' ) || '';

		// Toggle active class
		$btn.siblings().removeClass( 'active' );
		$btn.addClass( 'active' );

		// Update message
		var $msg = $( '.dc-bogo-reminder-text[data-group-key="' + groupKey + '"]' );
		var offerName = $btn.text() || '';
		if ( remaining > 0 ) {
			// This string is generated server-side with placeholders, so we don't need a JS version.
			// The server-side PHP will provide the correctly translated string.
			// We just re-trigger a refresh to get the new text.
			refreshBogoProgress();
		} else {
			if ( dcBogoFrontend.i18n && dcBogoFrontend.i18n.qualifiedMsg ) {
				$msg.text( dcBogoFrontend.i18n.qualifiedMsg.replace( '%s', offerName ) );
			} else {
				$msg.text( 'You have qualified for the "' + offerName + '" deal!' );
			}
		}

		// Update progress fill and label — scale by group's maxRequired so the bar reflects the
		// group's best offer as 100% and doesn't jump when switching pills.
		var percent = maxRequired > 0 ? Math.min( 100, Math.round( ( current / maxRequired ) * 100 ) ) : 0;
		var progressLabel = dcBogoFrontend.i18n.progressLabel
			.replace( '%1$d', current )
			.replace( '%2$d', maxRequired );
		var $container = $group.closest( '.dc-bogo-progress-bar-container' ).find( '.dc-bogo-progress-bar' ).first();
		var $fill = $container.find( '.dc-bogo-progress-bar-fill' );
		$fill.css( 'width', percent + '%' );
		$container.find( '.dc-bogo-progress-bar-label' ).text( progressLabel );
	} );

	$( document.body ).on( 'dc_bogo_cart_updated', function () {
		refreshBogoProgress();
	} );
} )( jQuery );
