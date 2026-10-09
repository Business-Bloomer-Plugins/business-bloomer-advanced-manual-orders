/* global jQuery, amowc, woocommerce_admin_meta_boxes */
jQuery( function ( $ ) {
	'use strict';

	var i18n = amowc.i18n;

	/* ---------- Simple modal ---------- */

	function openModal( $modal ) {
		$modal.prop( 'hidden', false );
		$modal.find( 'select, input, button' ).filter( ':visible' ).first().trigger( 'focus' );
	}

	function closeModal( $modal ) {
		$modal.prop( 'hidden', true );
	}

	$( document ).on( 'click', '.amowc-modal .amowc-close', function () {
		closeModal( $( this ).closest( '.amowc-modal' ) );
	} );

	$( document ).on( 'click', '.amowc-modal', function ( e ) {
		if ( e.target === this ) {
			closeModal( $( this ) );
		}
	} );

	$( document ).on( 'keydown', function ( e ) {
		// Escape in an open dropdown only closes the dropdown.
		if ( 'Escape' === e.key && ! $( '.select2-container--open' ).length ) {
			closeModal( $( '.amowc-modal:not([hidden])' ) );
		}
	} );

	/* ---------- Tooltips ---------- */

	// Info icons and the Duplicate buttons, which come with their text in data-tip or title.
	function helpTips( $context ) {
		if ( ! $.fn.tipTip ) {
			return;
		}
		$context.find( 'a.wc-action-button-amowc-duplicate[title]' ).each( function () {
			$( this ).attr( 'data-tip', $( this ).attr( 'title' ) ).removeAttr( 'title' );
		} );
		$context.find( '.amowc-help-tip, a.wc-action-button-amowc-duplicate' ).not( '.amowc-tipped' ).addClass( 'amowc-tipped' )
			.tipTip( { attribute: 'data-tip', fadeIn: 50, fadeOut: 50, delay: 200 } );
	}

	$( document ).on( 'focus', '.amowc-help-tip', function () {
		$( this ).trigger( 'mouseenter' );
	} ).on( 'blur', '.amowc-help-tip', function () {
		$( this ).trigger( 'mouseleave' );
	} );

	helpTips( $( document.body ) );

	/* ---------- "Add order" form, on the orders list and the order screen ---------- */

	var $form = $( '#amowc-new-order' );

	if ( amowc.popup && $form.length ) {
		$form.find( '#amowc_copy_from' ).selectWoo( {
			width: '100%',
			allowClear: true,
			placeholder: $form.find( '#amowc_copy_from' ).data( 'placeholder' ),
			minimumInputLength: 0,
			ajax: {
				url: amowc.ajaxUrl,
				dataType: 'json',
				delay: 250,
				data: function ( params ) {
					return { action: 'amowc_search_orders', security: amowc.nonce, term: params.term || '' };
				},
				processResults: function ( data ) {
					return data;
				}
			}
		} );

		// A past order picked: repeat it all, or copy its products for the customer below.
		function copyMode() {
			var picked = !! $form.find( '#amowc_copy_from' ).val();
			var all = picked && 'all' === $form.find( 'input[name="amowc_copy"]:checked' ).val();
			$form.find( '.amowc-copy' ).prop( 'hidden', ! picked );
			$form.find( '.amowc-new-fields' ).prop( 'hidden', all );
		}
		$form.on( 'change', '#amowc_copy_from, input[name="amowc_copy"]', copyMode );

		$( document ).on( 'click', 'a.page-title-action', function ( e ) {
			var href = $( this ).attr( 'href' ) || '';
			if ( ! /action=new|post-new\.php\?post_type=shop_order/.test( href ) ) {
				return;
			}
			e.preventDefault();
			$form.find( '.amowc-skip' ).attr( 'href', href );
			openModal( $form );
		} );
	}

	/* ---------- Order preview: Duplicate next to Edit ---------- */

	$( document.body ).on( 'wc_backbone_modal_loaded', function ( e, target ) {
		if ( 'wc-modal-view-order' !== target ) {
			return;
		}
		var $footer = $( '.wc-order-preview footer' );
		$footer.find( '.wc-backbone-modal-buttons' ).prepend( $footer.find( '> .inner > .wc-action-button-amowc-duplicate' ) );
		helpTips( $footer );
	} );

	/* ---------- Currency selector on/off, same setting everywhere ---------- */

	$( document ).on( 'click', '.amowc-currency-toggle', function ( e ) {
		var on = '1' === String( $( this ).data( 'on' ) );
		e.preventDefault();

		$.post( amowc.ajaxUrl, { action: 'amowc_toggle_currency', security: amowc.nonce, on: on ? 1 : 0 } ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				window.alert( i18n.error );
				return;
			}
			$( '.amowc-currency' ).prop( 'hidden', ! on );
			$( '.amowc-currency-off' ).prop( 'hidden', on );
		} ).fail( function () {
			window.alert( i18n.error );
		} );
	} );

	if ( 'edit' !== amowc.screen || 'undefined' === typeof woocommerce_admin_meta_boxes ) {
		return;
	}

	var $items = $( '#woocommerce-order-items' );
	var orderId = woocommerce_admin_meta_boxes.post_id;

	function block() {
		if ( $.fn.block ) {
			$items.block( { message: null, overlayCSS: { background: '#fff', opacity: 0.6 } } );
		}
	}

	function unblock() {
		if ( $.fn.unblock ) {
			$items.unblock();
		}
	}

	function address( type ) {
		return {
			country: $( '#_' + type + '_country' ).val() || '',
			state: $( '#_' + type + '_state' ).val() || '',
			postcode: $( '#_' + type + '_postcode' ).val() || '',
			city: $( '#_' + type + '_city' ).val() || '',
			address_1: $( '#_' + type + '_address_1' ).val() || '',
			address_2: $( '#_' + type + '_address_2' ).val() || ''
		};
	}

	// Same address WooCommerce's Recalculate button uses.
	function taxableAddress() {
		var addr = 'shipping' === woocommerce_admin_meta_boxes.tax_based_on ? address( 'shipping' ) : { country: '' };
		if ( 'billing' === woocommerce_admin_meta_boxes.tax_based_on || ! addr.country ) {
			addr = address( 'billing' );
		}
		return { country: addr.country, state: addr.state, postcode: addr.postcode, city: addr.city };
	}

	function shippingAddress() {
		var addr = address( 'shipping' );
		return addr.country ? addr : address( 'billing' );
	}

	// Products added to the order are priced for the address on screen, even before it's saved.
	$.ajaxPrefilter( function ( options ) {
		if ( 'string' === typeof options.data && /action=(woocommerce_add_order_item|amowc_add_product)(&|$)/.test( options.data ) && ! /(^|&)country=/.test( options.data ) ) {
			options.data += '&' + $.param( taxableAddress() );
		}
	} );

	/* ---------- Automatic taxes and totals ---------- */

	// Recalculate, without the "are you sure" prompt.
	function recalculate() {
		if ( ! $items.find( 'button.calculate-action' ).length ) {
			return;
		}

		block();

		$.post(
			woocommerce_admin_meta_boxes.ajax_url,
			$.extend( {}, taxableAddress(), {
				action: 'woocommerce_calc_line_taxes',
				order_id: orderId,
				items: $( 'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]' ).serialize(),
				security: woocommerce_admin_meta_boxes.calc_totals_nonce
			} )
		).done( function ( html ) {
			$items.find( '.inside' ).empty().append( html );
			$items.trigger( 'wc_order_items_reloaded' );
			$( document.body ).trigger( 'order-totals-recalculate-success', html );
		} ).always( unblock );
	}

	// Core requests after which totals are stale.
	var stale = /(^|&)action=(woocommerce_add_order_item|woocommerce_remove_order_item|woocommerce_save_order_items|amowc_add_shipping|amowc_add_product)(&|$)/;

	$( document ).ajaxComplete( function ( e, xhr, settings ) {
		if ( ! xhr || 200 !== xhr.status || 'string' !== typeof settings.data || ! stale.test( settings.data ) ) {
			return;
		}
		if ( amowc.autoTotals || /action=amowc_add_shipping/.test( settings.data ) ) {
			recalculate();
		}
	} );

	if ( amowc.autoTotals ) {
		var timer;
		$( '#order_data' ).on( 'change', '[id^="_billing_"], [id^="_shipping_"]', function () {
			if ( ! /_(country|state|postcode|city)$/.test( this.id ) || ! $items.find( 'tr.item' ).length ) {
				return;
			}
			clearTimeout( timer );
			timer = setTimeout( recalculate, 600 );
		} );
	}

	/* ---------- "Add a product" box ---------- */

	function quickAdd() {
		var $inside = $items.find( '.inside' );
		var $row = $inside.find( '.woocommerce_order_items_wrapper' );

		if ( ! $row.length || $inside.find( '.amowc-quick-add' ).length || ! $inside.find( 'button.add-line-item' ).length ) {
			return;
		}

		var $box = $( '<div class="wc-order-data-row amowc-quick-add"><select class="amowc-product-search"></select><span class="amowc-help-tip" tabindex="0"></span></div>' );
		$box.find( '.amowc-help-tip' ).attr( { 'data-tip': i18n.tipProduct, 'aria-label': i18n.tipProduct } );
		$row.before( $box );
		helpTips( $box );

		var $select = $box.find( 'select' );
		$select.attr( 'aria-label', i18n.addProduct ).selectWoo( {
			width: '100%',
			placeholder: i18n.addProduct,
			minimumInputLength: 0,
			language: {
				searching: function () {
					return i18n.searching;
				},
				noResults: function () {
					return i18n.noResults;
				}
			},
			ajax: {
				url: amowc.ajaxUrl,
				dataType: 'json',
				delay: 250,
				cache: true,
				data: function ( params ) {
					return { action: 'amowc_search_products', security: amowc.nonce, term: params.term || '', page: params.page || 1 };
				},
				processResults: function ( data ) {
					return data;
				}
			}
		} );

		$select.on( 'select2:select', function ( e ) {
			var data = {
				action: 'amowc_add_product',
				security: amowc.nonce,
				order_id: orderId,
				product_id: e.params.data.id
			};

			// Unsaved edits to the items, as WooCommerce sends them.
			if ( 'true' === $items.find( 'button.cancel-action' ).attr( 'data-reload' ) ) {
				data.items = $( 'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]' ).serialize();
			}

			block();
			$.post( amowc.ajaxUrl, data ).done( function ( res ) {
				if ( ! res || ! res.success ) {
					window.alert( res && res.data && res.data.message ? res.data.message : i18n.error );
					return;
				}
				$items.find( '.inside' ).empty().append( res.data.html );
				$( '#woocommerce-order-notes ul.order_notes' ).replaceWith( res.data.notes_html );
				$items.trigger( 'wc_order_items_reloaded' );
				$items.find( '.amowc-product-search' ).selectWoo( 'focus' );
			} ).fail( function () {
				window.alert( i18n.error );
			} ).always( unblock );
		} );
	}

	/* ---------- Tooltips on WooCommerce's own buttons ---------- */

	var tipCount = 0;

	// Keyboard users see the tip on focus too.
	$items.on( 'focus', '.amowc-tip', function () {
		$( this ).trigger( 'mouseenter' );
	} ).on( 'blur', '.amowc-tip', function () {
		$( this ).trigger( 'mouseleave' );
	} );

	function tips() {
		var $inside = $items.find( '.inside' );
		var list = [
			[ 'button.add-line-item, button.add-order-item', i18n.tipAddItems, true ],
			[ 'button.calculate-action', i18n.tipRecalc, amowc.autoTotals ],
			[ 'button.add-order-shipping', i18n.tipShipping, amowc.shipping ],
			[ 'button.add-coupon', i18n.tipCoupon, amowc.autoTotals ],
			[ 'button.add-order-fee', i18n.tipFee, true ],
			[ 'button.add-order-tax', i18n.tipTax, true ],
			[ 'button.amowc-calc-shipping', i18n.tipCalcShip, true ]
		];

		$.each( list, function ( n, tip ) {
			if ( tip[ 2 ] ) {
				$inside.find( tip[ 0 ] ).not( '.amowc-tip' ).each( function () {
					// Screen readers get the tip as the button's description.
					var id = 'amowc-tip-' + ( ++tipCount );
					$( this ).addClass( 'amowc-tip' ).attr( { 'data-tip': tip[ 1 ], 'aria-describedby': id } )
						.after( $( '<span class="screen-reader-text"></span>' ).attr( 'id', id ).text( tip[ 1 ] ) );
				} );
			}
		} );

		if ( $.fn.tipTip ) {
			$inside.find( '.amowc-tip' ).tipTip( { attribute: 'data-tip', fadeIn: 50, fadeOut: 50, delay: 200, keepAlive: true } );
		}
	}

	// The items box is re-rendered after every change, so put the box and the tips back each time.
	function decorate() {
		quickAdd();
		tips();
	}

	decorate();
	if ( $items.length && window.MutationObserver ) {
		new MutationObserver( decorate ).observe( $items.find( '.inside' )[ 0 ], { childList: true } );
	}

	/* ---------- "Choose an action": point out the plugin's entries ---------- */

	var $actions = $( 'select[name="wc_order_action"]' );
	if ( $actions.find( 'option[value^="amowc_"]' ).length ) {
		var $help = $( '<span class="amowc-help-tip" tabindex="0"></span>' ).attr( { 'data-tip': i18n.tipActions, 'aria-label': i18n.tipActions } );
		$actions.siblings( 'button.wc-reload' ).after( $help );
		helpTips( $help.parent() );
	}

	/* ---------- Currency ---------- */

	$( '#order_data' ).on( 'change', '#amowc_currency', function () {
		block();
		$.post( amowc.ajaxUrl, {
			action: 'amowc_set_currency',
			security: amowc.nonce,
			order_id: orderId,
			currency: $( this ).val()
		} ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				window.alert( i18n.error );
			}
			$items.trigger( 'wc_order_items_reload' );
		} ).always( unblock );
	} );

	/* ---------- Shipping rates ---------- */

	var $ship = $( '<div class="amowc-modal" hidden><div class="amowc-modal__box" role="dialog" aria-modal="true"><h2></h2><div class="amowc-rates"></div><p class="description"></p><div class="amowc-modal__actions"><button type="button" class="button amowc-close"></button><button type="button" class="button button-primary amowc-add-shipping"></button></div></div></div>' );
	$ship.find( 'h2' ).text( i18n.shippingTitle );
	$ship.find( '.description' ).text( i18n.replaceNote );
	$ship.find( '.amowc-close' ).text( i18n.cancel );
	$ship.find( '.amowc-add-shipping' ).text( i18n.add );
	$( 'body' ).append( $ship );

	$items.on( 'click', 'button.amowc-calc-shipping', function () {
		block();
		$.post( amowc.ajaxUrl, $.extend( {}, shippingAddress(), {
			action: 'amowc_shipping_rates',
			security: amowc.nonce,
			order_id: orderId
		} ) ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				window.alert( res && res.data && res.data.message ? res.data.message : i18n.error );
				return;
			}

			var $rates = $ship.find( '.amowc-rates' ).empty();
			var many = res.data.packages.length > 1;

			$.each( res.data.packages, function ( n, pkg ) {
				var $set = $( '<fieldset></fieldset>' ).attr( 'data-index', pkg.index );
				if ( many ) {
					$set.append( $( '<legend></legend>' ).text( i18n.package.replace( '%d', n + 1 ) ) );
				}
				$.each( pkg.rates, function ( r, rate ) {
					var $label = $( '<label></label>' );
					$label.append( $( '<input type="radio">' ).attr( { name: 'amowc_rate_' + pkg.index, value: rate.id } ).prop( 'checked', 0 === r ) );
					$label.append( $( '<span class="amowc-rate-label"></span>' ).text( rate.label ) );
					$label.append( $( '<span class="amowc-rate-cost"></span>' ).text( rate.cost ) );
					$set.append( $label );
				} );
				$rates.append( $set );
			} );

			openModal( $ship );
		} ).fail( function () {
			window.alert( i18n.error );
		} ).always( unblock );
	} );

	$ship.on( 'click', '.amowc-add-shipping', function () {
		var data = $.extend( {}, shippingAddress(), {
			action: 'amowc_add_shipping',
			security: amowc.nonce,
			order_id: orderId
		} );

		$ship.find( 'fieldset' ).each( function () {
			data[ 'rates[' + $( this ).data( 'index' ) + ']' ] = $( this ).find( 'input:checked' ).val();
		} );

		closeModal( $ship );
		block();

		// Totals refresh through the ajaxComplete handler above.
		$.post( amowc.ajaxUrl, data ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				window.alert( res && res.data && res.data.message ? res.data.message : i18n.error );
			}
		} ).always( unblock );
	} );

	/* ---------- Payment link: copy button ---------- */

	var $payLink = $( '.wc-order-status label a[href*="pay_for_order"]' );
	if ( $payLink.length && navigator.clipboard ) {
		var $copy = $( '<button type="button" class="button-link amowc-link amowc-copy-link"></button>' ).text( i18n.copyLink );
		$payLink.closest( '.wc-order-status' ).append( $copy );
		$copy.on( 'click', function ( e ) {
			e.preventDefault();
			navigator.clipboard.writeText( $payLink.attr( 'href' ) ).then( function () {
				$copy.text( i18n.copied );
				setTimeout( function () {
					$copy.text( i18n.copyLink );
				}, 2000 );
			} );
		} );
	}
} );
