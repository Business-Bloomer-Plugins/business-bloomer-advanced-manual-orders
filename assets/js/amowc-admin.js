/* global jQuery, amowc, woocommerce_admin_meta_boxes */
jQuery( function ( $ ) {
	'use strict';

	var i18n = amowc.i18n;

	/* ---------- The plugin's REST routes ---------- */

	function api( method, path, data ) {
		return $.ajax( {
			url: amowc.rest + path,
			method: method,
			data: data,
			dataType: 'json',
			headers: { 'X-WP-Nonce': amowc.restNonce }
		} );
	}

	function fail( xhr ) {
		window.alert( xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : i18n.error );
	}

	// selectWoo lists fed by a route that takes a search term.
	function source( path, extra ) {
		return {
			url: amowc.rest + path,
			dataType: 'json',
			delay: 250,
			headers: { 'X-WP-Nonce': amowc.restNonce },
			data: function ( params ) {
				return $.extend( { term: params.term || '' }, extra ? extra( params ) : {} );
			},
			processResults: function ( data ) {
				return data;
			}
		};
	}

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
			ajax: source( 'orders' )
		} );

		// Registered customers and past guests, by name, email, phone or company.
		$form.find( '#amowc_customer' ).selectWoo( {
			width: '100%',
			allowClear: true,
			placeholder: $form.find( '#amowc_customer' ).data( 'placeholder' ),
			minimumInputLength: 0,
			ajax: source( 'customers' )
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

		api( 'POST', 'settings/currency-selector', { enabled: on ? 1 : 0 } ).done( function () {
			$( '.amowc-currency' ).prop( 'hidden', ! on );
			$( '.amowc-currency-off' ).prop( 'hidden', on );
		} ).fail( fail );
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

	// Products added with WooCommerce's own "Add item(s)" are priced for the address on screen too.
	$.ajaxPrefilter( function ( options ) {
		if ( 'string' === typeof options.data && /action=woocommerce_add_order_item(&|$)/.test( options.data ) && ! /(^|&)country=/.test( options.data ) ) {
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

	// Core requests after which totals are stale. The plugin's own requests recalculate when they're done.
	var stale = /(^|&)action=(woocommerce_add_order_item|woocommerce_remove_order_item|woocommerce_save_order_items)(&|$)/;

	$( document ).ajaxComplete( function ( e, xhr, settings ) {
		if ( amowc.autoTotals && xhr && 200 === xhr.status && 'string' === typeof settings.data && stale.test( settings.data ) ) {
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
			ajax: $.extend( source( 'products', function ( params ) {
				return { page: params.page || 1 };
			} ), { cache: true } )
		} );

		$select.on( 'select2:select', function ( e ) {
			var data = $.extend( {}, taxableAddress(), { product: e.params.data.id } );

			// Unsaved edits to the items, as WooCommerce sends them.
			if ( 'true' === $items.find( 'button.cancel-action' ).attr( 'data-reload' ) ) {
				data.items = $( 'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]' ).serialize();
			}

			block();
			api( 'POST', 'orders/' + orderId + '/items', data ).done( function ( res ) {
				$items.find( '.inside' ).empty().append( res.html );
				$( '#woocommerce-order-notes ul.order_notes' ).replaceWith( res.notes_html );
				$items.trigger( 'wc_order_items_reloaded' );
				if ( amowc.autoTotals ) {
					recalculate();
				}
				$items.find( '.amowc-product-search' ).selectWoo( 'focus' );
			} ).fail( fail ).always( unblock );
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
		api( 'POST', 'orders/' + orderId + '/currency', { currency: $( this ).val() } ).fail( fail ).always( function () {
			unblock();
			$items.trigger( 'wc_order_items_reload' );
		} );
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
		api( 'GET', 'orders/' + orderId + '/shipping-rates', shippingAddress() ).done( function ( res ) {
			var $rates = $ship.find( '.amowc-rates' ).empty();
			var many = res.packages.length > 1;

			$.each( res.packages, function ( n, pkg ) {
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
		} ).fail( fail ).always( unblock );
	} );

	$ship.on( 'click', '.amowc-add-shipping', function () {
		var data = shippingAddress();

		$ship.find( 'fieldset' ).each( function () {
			data[ 'rates[' + $( this ).data( 'index' ) + ']' ] = $( this ).find( 'input:checked' ).val();
		} );

		closeModal( $ship );
		block();

		api( 'POST', 'orders/' + orderId + '/shipping', data ).done( function () {
			unblock();
			recalculate();
		} ).fail( function ( xhr ) {
			unblock();
			fail( xhr );
		} );
	} );

	/* ---------- Payment link: copy it, or email it ---------- */

	var $payLink = $( '.wc-order-status label a[href*="pay_for_order"]' );
	if ( $payLink.length ) {
		var $status = $payLink.closest( '.wc-order-status' );

		if ( navigator.clipboard ) {
			var $copy = $( '<button type="button" class="button-link amowc-link amowc-pay-action amowc-copy-link"></button>' ).text( i18n.copyLink );
			$status.append( $copy );
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

		var $email = $( '<button type="button" class="button-link amowc-link amowc-pay-action amowc-email-link"></button>' ).text( i18n.emailLink ).attr( 'data-tip', i18n.tipEmailLink );
		$status.append( $email );
		if ( $.fn.tipTip ) {
			$email.tipTip( { attribute: 'data-tip', fadeIn: 50, fadeOut: 50, delay: 200 } );
		}
		$email.on( 'click', function ( e ) {
			e.preventDefault();
			if ( amowc.email && ! window.confirm( i18n.emailConfirm.replace( '%s', amowc.email ) ) ) {
				return;
			}
			$email.prop( 'disabled', true );
			api( 'POST', 'orders/' + orderId + '/email-pay-link' ).done( function ( res ) {
				$( '#woocommerce-order-notes ul.order_notes' ).replaceWith( res.notes_html );
				$email.text( i18n.emailed.replace( '%s', res.email ) );
				setTimeout( function () {
					$email.text( i18n.emailLink );
				}, 3000 );
			} ).fail( fail ).always( function () {
				$email.prop( 'disabled', false );
			} );
		} );
	}

	/* ---------- Customer: registered customers and past guests, addresses filled in ---------- */

	var $customer = $( '#customer_user' );

	function fillAddress( type, address ) {
		if ( ! address ) {
			return;
		}
		// Country first, so the state field matches it.
		$( '#_' + type + '_country' ).val( address.country || '' ).trigger( 'change' );
		$.each( address, function ( key, value ) {
			if ( 'country' !== key ) {
				$( ':input#_' + type + '_' + key ).val( value ).trigger( 'change' );
			}
		} );
	}

	if ( $customer.length && $customer.hasClass( 'select2-hidden-accessible' ) ) {
		// WooCommerce prints the saved customer with HTML entities, like "&ndash;", that its own
		// select shows decoded. Decode them as text, so nothing in a name can turn into markup.
		$customer.find( 'option' ).each( function () {
			$( this ).text( $( '<textarea>' ).html( $( this ).text() ).val() );
		} );

		$customer.selectWoo( 'destroy' ).selectWoo( {
			width: '100%',
			allowClear: true,
			placeholder: $customer.data( 'placeholder' ),
			minimumInputLength: 0,
			ajax: source( 'customers' )
		} );

		$customer.on( 'select2:selecting', function ( e ) {
			var picked = e.params.args.data;
			if ( ! picked.billing ) {
				return;
			}

			// Show both addresses, filled in.
			$( 'a.edit_address' ).each( function () {
				if ( $( this ).closest( '.order_data_column' ).find( 'div.edit_address' ).is( ':hidden' ) ) {
					$( this ).trigger( 'click' );
				}
			} );
			fillAddress( 'billing', picked.billing );
			fillAddress( 'shipping', picked.shipping );

			// A guest has no account: the order stays a guest order, with their addresses.
			if ( 0 === String( picked.id ).indexOf( 'o:' ) ) {
				e.preventDefault();
				$customer.val( null ).trigger( 'change.select2' ).selectWoo( 'close' );
			}
		} );

		var $label = $customer.closest( 'p' ).find( 'label' ).first();
		if ( $label.length ) {
			var $tip = $( '<span class="amowc-help-tip" tabindex="0"></span>' ).attr( { 'data-tip': i18n.tipCustomer, 'aria-label': i18n.tipCustomer } );
			$label.contents().first().after( $tip );
			helpTips( $label );
		}
	}
} );
