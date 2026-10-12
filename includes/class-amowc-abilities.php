<?php
/**
 * Abilities API: lets AI agents and other tools take manual orders, from finding the customer
 * to emailing the payment link. They run the same code as the order screen.
 *
 * Abilities (WordPress 6.9+, exposed to REST and MCP clients):
 * - business-bloomer-advanced-manual-orders/find-customers
 * - business-bloomer-advanced-manual-orders/create-order
 * - business-bloomer-advanced-manual-orders/get-shipping-rates
 * - business-bloomer-advanced-manual-orders/set-shipping
 * - business-bloomer-advanced-manual-orders/email-pay-link
 * - business-bloomer-advanced-manual-orders/duplicate-order
 *
 * Ability results go to the connected AI service, so they never include customer names,
 * emails, phone numbers or addresses: customers are referred to by ID.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AMOWC_Abilities' ) ) {

	/**
	 * Registers the plugin's abilities.
	 */
	class AMOWC_Abilities {

		const NS = 'business-bloomer-advanced-manual-orders';

		/**
		 * Hooks.
		 */
		public function __construct() {
			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
			add_filter( 'albert/abilities/sources', array( $this, 'albert_source_label' ) );
		}

		/**
		 * Albert (AI Butler) shows this label instead of the raw prefix in its Abilities list.
		 *
		 * @param array $sources Prefix => label.
		 * @return array
		 */
		public function albert_source_label( $sources ) {
			$name                = get_file_data( AMOWC_PLUGIN_FILE, array( 'name' => 'Plugin Name' ) )['name'];
			$sources[ self::NS ] = 'Business Bloomer: ' . preg_replace( '/^Business Bloomer\s+/', '', $name );
			return $sources;
		}

		/**
		 * Shared by every Business Bloomer plugin: whichever loads first registers it.
		 */
		public function register_category() {
			if ( ! wp_has_ability_category( 'business-bloomer' ) ) {
				wp_register_ability_category(
					'business-bloomer',
					array(
						'label'       => 'Business Bloomer',
						'description' => __( 'Abilities added by Business Bloomer WooCommerce plugins.', 'business-bloomer-advanced-manual-orders' ),
					)
				);
			}
		}

		/**
		 * Ability meta: public on REST and MCP, with its annotations.
		 *
		 * @param array $annotations readonly, destructive, idempotent.
		 * @return array
		 */
		private function meta( $annotations ) {
			return array(
				'public'       => true,
				// 'public' only exists since WordPress 7.1: also set the per-channel flags for 6.9 and 7.0.
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => $annotations,
			);
		}

		/**
		 * The order_id input.
		 *
		 * @param array $more More properties.
		 * @return array
		 */
		private function order_input( $more = array() ) {
			return array(
				'type'                 => 'object',
				'required'             => array( 'order_id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'order_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				) + $more,
			);
		}

		/**
		 * An address input. Billing also takes the email.
		 *
		 * @param string $type billing or shipping.
		 * @return array
		 */
		private function address_input( $type ) {
			$fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' );
			if ( 'billing' === $type ) {
				$fields[] = 'email';
			}

			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'description'          => 'billing' === $type
					? __( 'Billing address fields to set or change. Country and state are codes, e.g. US and CA.', 'business-bloomer-advanced-manual-orders' )
					: __( 'Shipping address fields to set or change. Leave out to ship to the billing address.', 'business-bloomer-advanced-manual-orders' ),
				'properties'           => array_fill_keys( $fields, array( 'type' => 'string' ) ),
			);
		}

		/**
		 * The order summary every order ability returns.
		 *
		 * @return array
		 */
		private function order_output() {
			return array(
				'type'       => 'object',
				'properties' => array(
					'order_id'      => array( 'type' => 'integer' ),
					'order_number'  => array( 'type' => 'string' ),
					'status'        => array( 'type' => 'string' ),
					'currency'      => array( 'type' => 'string' ),
					'total'         => array( 'type' => 'number' ),
					'needs_payment' => array( 'type' => 'boolean' ),
					'customer'      => array(
						'type'        => 'string',
						'description' => __( 'Registered customer ID, or empty for a guest.', 'business-bloomer-advanced-manual-orders' ),
					),
					'items'         => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'product_id' => array( 'type' => 'integer' ),
								'name'       => array( 'type' => 'string' ),
								'quantity'   => array( 'type' => 'number' ),
								'total'      => array( 'type' => 'number' ),
							),
						),
					),
					'shipping'      => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'method' => array( 'type' => 'string' ),
								'total'  => array( 'type' => 'number' ),
							),
						),
					),
					'edit_url'      => array( 'type' => 'string' ),
					'warnings'      => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			);
		}

		/**
		 * Registers the abilities.
		 */
		public function register_abilities() {
			wp_register_ability(
				self::NS . '/find-customers',
				array(
					'label'               => __( 'Find customers', 'business-bloomer-advanced-manual-orders' ),
					'description'         => __( 'Finds registered customers and past guests by name, email, phone (any format) or company, to start a manual order for them. Personal details are never returned: each match has a "customer" value to pass to create-order, its type and country. When there is more than one match, ask for the full email or phone number.', 'business-bloomer-advanced-manual-orders' ),
					'category'            => 'business-bloomer',
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'search' ),
						'additionalProperties' => false,
						'properties'           => array(
							'search' => array(
								'type'      => 'string',
								'minLength' => 2,
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'customers' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'customer'    => array( 'type' => 'string' ),
										'type'        => array(
											'type' => 'string',
											'enum' => array( 'registered', 'guest' ),
										),
										'country'     => array( 'type' => 'string' ),
										'has_address' => array( 'type' => 'boolean' ),
										'has_email'   => array( 'type' => 'boolean' ),
									),
								),
							),
						),
					),
					'execute_callback'    => array( $this, 'find_customers' ),
					'permission_callback' => array( $this, 'can_edit_orders' ),
					'meta'                => $this->meta(
						array(
							'readonly'   => true,
							'idempotent' => true,
						)
					),
				)
			);

			wp_register_ability(
				self::NS . '/create-order',
				array(
					'label'               => __( 'Create a manual order', 'business-bloomer-advanced-manual-orders' ),
					'description'         => __( 'Creates a Pending payment order, like one made on the order screen: the customer (from find-customers) with their saved addresses, or a guest with the addresses given, the products at their current prices, taxes, and the cheapest delivery rate (local pickup only when nothing else is available) unless told otherwise. No email is sent: use email-pay-link next. Variable products need a variation ID, plus "attributes" for any "Any" attribute it has.', 'business-bloomer-advanced-manual-orders' ),
					'category'            => 'business-bloomer',
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'items' ),
						'additionalProperties' => false,
						'properties'           => array(
							'customer'        => array(
								'type'        => 'string',
								'pattern'     => '^(\d+|o:\d+)$',
								'description' => __( 'A "customer" value from find-customers. Leave out for a new guest.', 'business-bloomer-advanced-manual-orders' ),
							),
							'billing'         => $this->address_input( 'billing' ),
							'shipping'        => $this->address_input( 'shipping' ),
							'items'           => array(
								'type'     => 'array',
								'minItems' => 1,
								'items'    => array(
									'type'                 => 'object',
									'required'             => array( 'product' ),
									'additionalProperties' => false,
									'properties'           => array(
										'product'    => array(
											'type'        => array( 'integer', 'string' ),
											'description' => __( 'Product or variation ID, or SKU.', 'business-bloomer-advanced-manual-orders' ),
										),
										'quantity'   => array(
											'type'    => 'integer',
											'minimum' => 1,
											'default' => 1,
										),
										'attributes' => array(
											'type'        => 'object',
											'additionalProperties' => array( 'type' => 'string' ),
											'description' => __( 'Values for the variation\'s "Any" attributes, e.g. {"pa_size": "large"}.', 'business-bloomer-advanced-manual-orders' ),
										),
									),
								),
							),
							'currency'        => array(
								'type'        => 'string',
								'pattern'     => '^[A-Z]{3}$',
								'description' => __( 'Order currency code. Prices are not converted. Default: the store currency.', 'business-bloomer-advanced-manual-orders' ),
							),
							'shipping_method' => array(
								'type'        => 'string',
								'default'     => 'cheapest',
								'description' => __( '"cheapest" (delivery before local pickup), "none", or a rate ID from get-shipping-rates, e.g. flat_rate:1.', 'business-bloomer-advanced-manual-orders' ),
							),
						),
					),
					'output_schema'       => $this->order_output(),
					'execute_callback'    => array( $this, 'create_order' ),
					'permission_callback' => array( $this, 'can_edit_orders' ),
					'meta'                => $this->meta(
						array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						)
					),
				)
			);

			wp_register_ability(
				self::NS . '/get-shipping-rates',
				array(
					'label'               => __( 'Get shipping rates for an order', 'business-bloomer-advanced-manual-orders' ),
					'description'         => __( 'Lists the shipping rates checkout would offer for an order\'s items and address (shipping address, or billing when empty), grouped by package.', 'business-bloomer-advanced-manual-orders' ),
					'category'            => 'business-bloomer',
					'input_schema'        => $this->order_input(),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'currency' => array( 'type' => 'string' ),
							'packages' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'package' => array( 'type' => 'integer' ),
										'rates'   => array(
											'type'  => 'array',
											'items' => array(
												'type' => 'object',
												'properties' => array(
													'id'   => array( 'type' => 'string' ),
													'label' => array( 'type' => 'string' ),
													'cost' => array( 'type' => 'number' ),
												),
											),
										),
									),
								),
							),
						),
					),
					'execute_callback'    => array( $this, 'get_shipping_rates' ),
					'permission_callback' => array( $this, 'can_edit_orders' ),
					'meta'                => $this->meta(
						array(
							'readonly'   => true,
							'idempotent' => true,
						)
					),
				)
			);

			wp_register_ability(
				self::NS . '/set-shipping',
				array(
					'label'               => __( 'Set the shipping of an order', 'business-bloomer-advanced-manual-orders' ),
					'description'         => __( 'Replaces the shipping of an unpaid order with a rate from get-shipping-rates, or the cheapest one, and recalculates taxes and totals.', 'business-bloomer-advanced-manual-orders' ),
					'category'            => 'business-bloomer',
					'input_schema'        => $this->order_input(
						array(
							'method' => array(
								'type'        => 'string',
								'default'     => 'cheapest',
								'description' => __( '"cheapest" (delivery before local pickup), or a rate ID from get-shipping-rates, e.g. flat_rate:1.', 'business-bloomer-advanced-manual-orders' ),
							),
						)
					),
					'output_schema'       => $this->order_output(),
					'execute_callback'    => array( $this, 'set_shipping' ),
					'permission_callback' => array( $this, 'can_edit_orders' ),
					'meta'                => $this->meta(
						array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						)
					),
				)
			);

			wp_register_ability(
				self::NS . '/email-pay-link',
				array(
					'label'               => __( 'Email the payment link', 'business-bloomer-advanced-manual-orders' ),
					'description'         => __( 'Emails the payment link of an unpaid order to the billing email saved on it, with WooCommerce\'s own invoice email, and adds an order note. The customer can pay without logging in. Each call sends another email.', 'business-bloomer-advanced-manual-orders' ),
					'category'            => 'business-bloomer',
					'input_schema'        => $this->order_input(),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'order_id' => array( 'type' => 'integer' ),
							'sent'     => array( 'type' => 'boolean' ),
						),
					),
					'execute_callback'    => array( $this, 'email_pay_link' ),
					'permission_callback' => array( $this, 'can_edit_orders' ),
					'meta'                => $this->meta(
						array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						)
					),
				)
			);

			wp_register_ability(
				self::NS . '/duplicate-order',
				array(
					'label'               => __( 'Duplicate or reorder', 'business-bloomer-advanced-manual-orders' ),
					'description'         => __( 'Creates a new order from an existing one. "all": an exact copy (same prices, shipping, fees, coupons), with the status set in the plugin settings. "customer": an empty Pending payment order for the same customer and addresses. "products": the same products at today\'s prices, Pending payment. No emails are sent.', 'business-bloomer-advanced-manual-orders' ),
					'category'            => 'business-bloomer',
					'input_schema'        => $this->order_input(
						array(
							'copy' => array(
								'type'    => 'string',
								'enum'    => array( 'all', 'customer', 'products' ),
								'default' => 'all',
							),
						)
					),
					'output_schema'       => $this->order_output(),
					'execute_callback'    => array( $this, 'duplicate_order' ),
					'permission_callback' => array( $this, 'can_edit_orders' ),
					'meta'                => $this->meta(
						array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						)
					),
				)
			);
		}

		/**
		 * Whether the current user can create and edit orders.
		 *
		 * @return bool
		 */
		public function can_edit_orders() {
			return current_user_can( 'edit_shop_orders' );
		}

		/**
		 * An error the agent can act on, sent as a 400 response.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @return WP_Error
		 */
		private function error( $code, $message ) {
			return new WP_Error( $code, $message, array( 'status' => 400 ) );
		}

		/**
		 * A regular order by ID.
		 *
		 * @param int  $order_id Order ID.
		 * @param bool $editable Whether it must still be editable (unpaid).
		 * @return WC_Order|WP_Error
		 */
		private function order( $order_id, $editable = false ) {
			$order = wc_get_order( absint( $order_id ) );

			if ( ! $order || 'shop_order' !== $order->get_type() ) {
				return $this->error( 'amowc_order', __( 'No order found with this ID.', 'business-bloomer-advanced-manual-orders' ) );
			}
			if ( $editable && ! $order->is_editable() ) {
				return $this->error( 'amowc_order', __( 'This order can not be edited anymore: only Pending payment, On hold and draft orders can.', 'business-bloomer-advanced-manual-orders' ) );
			}

			return $order;
		}

		/**
		 * The order summary, without personal details.
		 *
		 * @param WC_Order $order    Order.
		 * @param string[] $warnings Warnings.
		 * @return array
		 */
		private function summary( $order, $warnings = array() ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'product_id' => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
					'name'       => $item->get_name(),
					'quantity'   => (float) $item->get_quantity(),
					'total'      => (float) $item->get_total(),
				);
			}

			$shipping = array();
			foreach ( $order->get_items( 'shipping' ) as $item ) {
				$shipping[] = array(
					'method' => $item->get_name(),
					'total'  => (float) $item->get_total(),
				);
			}

			return array(
				'order_id'      => $order->get_id(),
				'order_number'  => (string) $order->get_order_number(),
				'status'        => $order->get_status(),
				'currency'      => $order->get_currency(),
				'total'         => (float) $order->get_total(),
				'needs_payment' => $order->needs_payment(),
				'customer'      => $order->get_customer_id() ? (string) $order->get_customer_id() : '',
				'items'         => $items,
				'shipping'      => $shipping,
				'edit_url'      => $order->get_edit_order_url(),
				'warnings'      => array_values( $warnings ),
			);
		}

		/**
		 * Find customers.
		 *
		 * @param array $input search.
		 * @return array
		 */
		public function find_customers( $input ) {
			$customers = array();

			foreach ( amowc_find_customers( $input['search'] ) as $entry ) {
				$customers[] = array(
					'customer'    => $entry['id'],
					'type'        => 0 === strpos( $entry['id'], 'o:' ) ? 'guest' : 'registered',
					'country'     => (string) $entry['billing']['country'],
					'has_address' => '' !== (string) $entry['billing']['address_1'] || '' !== (string) $entry['shipping']['address_1'],
					'has_email'   => is_email( $entry['billing']['email'] ) ? true : false,
				);
			}

			return array( 'customers' => $customers );
		}

		/**
		 * A product from an ID or SKU, checked before anything is created.
		 *
		 * @param array $line product, quantity, attributes.
		 * @return array|WP_Error product, quantity, chosen, warning.
		 */
		private function resolve_line( $line ) {
			$ref = is_string( $line['product'] ) ? trim( $line['product'] ) : $line['product'];
			$id  = is_int( $ref ) || ctype_digit( (string) $ref ) ? (int) $ref : 0;

			if ( ! $id || ! wc_get_product( $id ) ) {
				$id = wc_get_product_id_by_sku( (string) $ref );
			}
			$product = $id ? wc_get_product( $id ) : false;

			if ( ! $product ) {
				/* translators: %s: product ID or SKU. */
				return $this->error( 'amowc_product', sprintf( __( 'No product found for "%s".', 'business-bloomer-advanced-manual-orders' ), $ref ) );
			}
			if ( $product->is_type( 'variable' ) ) {
				/* translators: 1: product name, 2: variation IDs. */
				return $this->error( 'amowc_product', sprintf( __( '"%1$s" is a variable product: use one of its variation IDs instead (%2$s).', 'business-bloomer-advanced-manual-orders' ), $product->get_name(), implode( ', ', $product->get_children() ) ) );
			}
			if ( $product->is_type( 'grouped' ) ) {
				/* translators: 1: product name, 2: product IDs. */
				return $this->error( 'amowc_product', sprintf( __( '"%1$s" is a grouped product: add its products instead (%2$s).', 'business-bloomer-advanced-manual-orders' ), $product->get_name(), implode( ', ', $product->get_children() ) ) );
			}

			// A variation with "Any" attributes needs a value for each.
			$chosen = array();
			if ( $product->is_type( 'variation' ) ) {
				$attributes = isset( $line['attributes'] ) ? (array) $line['attributes'] : array();
				$chosen     = amowc_parse_any_values( $product, http_build_query( $attributes ) );
				$parent     = wc_get_product( $product->get_parent_id() );
				$options    = $parent ? amowc_attribute_options( $parent ) : array();
				$missing    = array();

				foreach ( $product->get_variation_attributes( false ) as $name => $value ) {
					if ( '' === $value && ! isset( $chosen[ $name ] ) ) {
						$missing[] = $name . ' (' . implode( ', ', isset( $options[ $name ] ) ? $options[ $name ] : array() ) . ')';
					}
				}
				if ( $missing ) {
					/* translators: 1: product name, 2: attribute names with their values. */
					return $this->error( 'amowc_product', sprintf( __( '"%1$s" needs a value in "attributes" for: %2$s.', 'business-bloomer-advanced-manual-orders' ), wp_strip_all_tags( $product->get_formatted_name() ), implode( '; ', $missing ) ) );
				}
			}

			$warning = '';
			$status  = $product->get_parent_id() ? get_post_status( $product->get_parent_id() ) : $product->get_status();
			if ( 'publish' !== $status ) {
				/* translators: 1: product name, 2: post status, e.g. draft. */
				$warning = sprintf( __( '"%1$s" is not published (%2$s). It was added anyway, as the order screen allows.', 'business-bloomer-advanced-manual-orders' ), $product->get_name(), $status );
			} elseif ( ! $product->is_in_stock() ) {
				/* translators: %s: product name. */
				$warning = sprintf( __( '"%s" is out of stock. It was added anyway, as the order screen allows.', 'business-bloomer-advanced-manual-orders' ), $product->get_name() );
			}

			return array(
				'product'  => $product,
				'quantity' => isset( $line['quantity'] ) ? max( 1, (int) $line['quantity'] ) : 1,
				'chosen'   => $chosen,
				'warning'  => $warning,
			);
		}

		/**
		 * Create a manual order.
		 *
		 * @param array $input customer, billing, shipping, items, currency, shipping_method.
		 * @return array|WP_Error
		 */
		public function create_order( $input ) {
			$lines    = array();
			$warnings = array();

			foreach ( $input['items'] as $line ) {
				$resolved = $this->resolve_line( $line );
				if ( is_wp_error( $resolved ) ) {
					return $resolved;
				}
				$lines[] = $resolved;
				if ( $resolved['warning'] ) {
					$warnings[] = $resolved['warning'];
				}
			}

			$currency = isset( $input['currency'] ) ? $input['currency'] : '';
			if ( $currency && ! array_key_exists( $currency, get_woocommerce_currencies() ) ) {
				/* translators: %s: currency code. */
				return $this->error( 'amowc_currency', sprintf( __( '%s is not a currency WooCommerce knows.', 'business-bloomer-advanced-manual-orders' ), $currency ) );
			}

			$customer = isset( $input['customer'] ) ? $input['customer'] : '';
			if ( $customer && 0 !== strpos( $customer, 'o:' ) && ! get_userdata( absint( $customer ) ) ) {
				return $this->error( 'amowc_customer', __( 'No customer found with this ID: use a "customer" value from find-customers.', 'business-bloomer-advanced-manual-orders' ) );
			}

			$order = amowc_create_draft(
				array(
					'customer' => $customer,
					'currency' => $currency,
				)
			);

			foreach ( array( 'billing', 'shipping' ) as $type ) {
				if ( ! empty( $input[ $type ] ) ) {
					$given = array_map( 'sanitize_text_field', (array) $input[ $type ] );
					if ( isset( $given['email'] ) ) {
						$given['email'] = sanitize_email( $given['email'] );
					}
					$order->set_address( array_merge( $order->get_address( $type ), $given ), $type );
				}
			}
			$order->save();

			foreach ( $lines as $line ) {
				$added = amowc_add_product_to_order( $order, $line['product'], $line['quantity'], $line['chosen'] );
				if ( is_wp_error( $added ) ) {
					$order->delete( true );
					return $added;
				}
			}

			$order  = wc_get_order( $order->get_id() );
			$method = isset( $input['shipping_method'] ) ? $input['shipping_method'] : 'cheapest';

			if ( 'none' !== $method && wc_shipping_enabled() && $this->needs_shipping( $order ) ) {
				$destination = amowc_order_destination( $order );
				$added       = $destination['country'] ? amowc_set_shipping( $order, $destination, $method ) : $this->error( 'amowc_shipping', __( 'No shipping added: the order has no address country.', 'business-bloomer-advanced-manual-orders' ) );
				if ( is_wp_error( $added ) ) {
					$warnings[] = $added->get_error_message();
				}
			}

			$order = wc_get_order( $order->get_id() );
			$order->calculate_totals();
			$order->set_status( 'pending' );
			$order->save();

			if ( ! is_email( $order->get_billing_email() ) ) {
				$warnings[] = __( 'The order has no billing email, so email-pay-link can\'t send it yet.', 'business-bloomer-advanced-manual-orders' );
			}

			return $this->summary( $order, $warnings );
		}

		/**
		 * Whether any product on the order needs shipping.
		 *
		 * @param WC_Order $order Order.
		 * @return bool
		 */
		private function needs_shipping( $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product && $product->needs_shipping() ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Get shipping rates for an order.
		 *
		 * @param array $input order_id.
		 * @return array|WP_Error
		 */
		public function get_shipping_rates( $input ) {
			$order = $this->order( $input['order_id'] );
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			$destination = amowc_order_destination( $order );
			if ( ! $destination['country'] ) {
				return $this->error( 'amowc_shipping', __( 'The order has no address country.', 'business-bloomer-advanced-manual-orders' ) );
			}

			$packages = array();
			foreach ( amowc_get_shipping_rates( $order, $destination ) as $index => $rates ) {
				$list = array();
				foreach ( $rates as $rate_id => $rate ) {
					$list[] = array(
						'id'    => (string) $rate_id,
						'label' => $rate->get_label(),
						'cost'  => (float) $rate->get_cost(),
					);
				}
				$packages[] = array(
					'package' => (int) $index,
					'rates'   => $list,
				);
			}

			return array(
				'currency' => $order->get_currency(),
				'packages' => $packages,
			);
		}

		/**
		 * Set the shipping of an order.
		 *
		 * @param array $input order_id, method.
		 * @return array|WP_Error
		 */
		public function set_shipping( $input ) {
			$order = $this->order( $input['order_id'], true );
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			$destination = amowc_order_destination( $order );
			if ( ! $destination['country'] ) {
				return $this->error( 'amowc_shipping', __( 'The order has no address country.', 'business-bloomer-advanced-manual-orders' ) );
			}

			$added = amowc_set_shipping( $order, $destination, isset( $input['method'] ) ? $input['method'] : 'cheapest' );
			if ( is_wp_error( $added ) ) {
				return $added;
			}

			$order = wc_get_order( $order->get_id() );
			$order->calculate_totals();

			return $this->summary( $order );
		}

		/**
		 * Email the payment link.
		 *
		 * @param array $input order_id.
		 * @return array|WP_Error
		 */
		public function email_pay_link( $input ) {
			$order = $this->order( $input['order_id'] );
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			$sent = amowc_email_pay_link( $order );
			if ( is_wp_error( $sent ) ) {
				return $sent;
			}

			return array(
				'order_id' => $order->get_id(),
				'sent'     => true,
			);
		}

		/**
		 * Duplicate or reorder.
		 *
		 * @param array $input order_id, copy.
		 * @return array|WP_Error
		 */
		public function duplicate_order( $input ) {
			$order = $this->order( $input['order_id'] );
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			$copy = isset( $input['copy'] ) ? $input['copy'] : 'all';
			$new  = amowc_create_draft(
				array(
					'source' => $order,
					'copy'   => $copy,
					'status' => 'all' === $copy ? amowc_duplicate_status() : 'pending',
				)
			);

			return $this->summary( $new );
		}
	}

	if ( function_exists( 'wp_register_ability' ) ) {
		new AMOWC_Abilities();
	}
}
