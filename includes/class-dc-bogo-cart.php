<?php
/**
 * Cart class
 *
 * @package DC_BOGO_Coupons
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class DC_BOGO_Cart.
 */
class DC_BOGO_Cart {

	/**
	 * Applied rule IDs for this request.
	 *
	 * @var array
	 */
	private $applied_rule_ids = [];

	public $bogo_discount_totals = [];

	/**
	 * In-progress rules data (for multi-step deals).
	 *
	 * @var array
	 */
	public $in_progress_rules_data = [];

	/**
	 * Previous in-progress rules data (captured before calc).
	 *
	 * @var array
	 */
	public $previous_in_progress_rules = [];

	public $force_progress_output = false;

	/**
	 * Last qualified messages (for AJAX popper animation).
	 *
	 * @var array
	 */
	public $last_qualified_messages = [];

	public $add_to_cart_messages = [];

	public $cart_updated = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_bogo_rules' ] );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save_applied_rules_to_order' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'increment_rule_usage_counts' ] );
		add_action( 'before_delete_post', [ $this, 'delete_linked_coupon_on_rule_delete' ] );
		add_filter( 'woocommerce_cart_coupon_html', [ $this, 'filter_bogo_coupon_html' ], 10, 3 );

		add_action( 'woocommerce_cart_item_removed', [ $this, 'clear_bogo_session_data' ] );
		add_action( 'woocommerce_cart_item_restored', [ $this, 'clear_bogo_session_data' ] );
		// add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'clear_bogo_session_data' ] );

		add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'get_cart_item_from_session' ], 10, 3 );
	}

	/**
	 * Restore custom data from session.
	 *
	 * @param array $cart_item The cart item data.
	 * @param array $values    The session values.
	 * @param string $key      The cart item key.
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $values, $key ) {
		if ( isset( $values['dc_bogo_auto_added'] ) ) {
			$cart_item['dc_bogo_auto_added'] = $values['dc_bogo_auto_added'];
		}
		return $cart_item;
	}

	/**
	 * Clear BOGO session data.
	 */
	public function clear_bogo_session_data() {
		if ( isset( WC()->session ) && WC()->session ) {
			WC()->session->set( 'dc_bogo_auto_added_rules', [] );
		}
	}

	/**
	 * Filter the coupon HTML in the cart totals to show the real discount value.
	 */
	public function filter_bogo_coupon_html( $coupon_html, $coupon, $discount_amount_html ) {
		$rule_id = get_post_meta( $coupon->get_id(), '_dc_bogo_rule_id', true );
		if ( $rule_id && isset( $this->bogo_discount_totals[ $rule_id ] ) ) {
			$discount_value = $this->bogo_discount_totals[ $rule_id ];
			if ( $discount_value > 0 ) {
				$new_discount_html = '- ' . wc_price( $discount_value );
				$coupon_html = str_replace( $discount_amount_html, $new_discount_html, $coupon_html );
			}
		}
		return $coupon_html;
	}

	/**
	 * Clear applied rule data from session.
	 */
	public function clear_applied_rules() {
		// Guard session access in case WC session isn't initialized (e.g., some AJAX contexts)
		if ( isset( WC()->session ) && WC()->session ) {
			WC()->session->set( 'dc_bogo_applied_rules', [] );
			WC()->session->set( 'dc_bogo_in_progress_rules_session', [] );
		}
		$this->applied_rule_ids = [];
	}

	/**
	 * Clear previous BOGO notices from the session to prevent duplicates.
	 */
	private function clear_previous_bogo_notices() {
		if ( ! WC()->session || ! WC()->session->has_session() ) {
			return;
		}

		$notice_types = [ 'success', 'notice', 'error' ];
		foreach ( $notice_types as $type ) {
			$notices = WC()->session->get( 'wc_notices', [] );
			if ( empty( $notices[ $type ] ) ) {
				continue;
			}

			$bogo_phrases      = [ 'deal!', 'Applied', 'Deal(s) Applied', 'for free' ];
			$notices_to_keep = [];

			foreach ( $notices[ $type ] as $notice ) {
				$is_bogo_notice = false;
				foreach ( $bogo_phrases as $phrase ) {
					if ( false !== strpos( $notice['notice'], $phrase ) ) {
						$is_bogo_notice = true;
						break;
					}
				}
				if ( ! $is_bogo_notice ) {
					$notices_to_keep[] = $notice;
				}
			}
			$notices[ $type ] = $notices_to_keep;
			WC()->session->set( 'wc_notices', $notices );
		}
	}

	private function reset_bogo_discounts( $cart ) {
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['dc_bogo_original_price'] ) ) {
				$cart_item['data']->set_price( $cart_item['dc_bogo_original_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['dc_bogo_discount_applied'] );
				unset( $cart->cart_contents[ $cart_item_key ]['dc_bogo_original_price'] );
				
			}
		}
	}

	/**
	 * Get all active and date-valid BOGO rules.
	 *
	 * @return WP_Post[]
	 */
	private function get_valid_rules() {
		$cached_rule_ids = get_transient( 'dc_bogo_active_rules_ids' );

		if ( false === $cached_rule_ids ) {
			$today = gmdate( 'Y-m-d' );
			$args  = [
				'post_type'      => 'dc_bogo_rule',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids', // Only get IDs for caching
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_dc_bogo_priority', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'          => 'ASC',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					[
						'key'     => '_dc_bogo_is_active',
						'value'   => 'yes',
						'compare' => '=',
					],
					[
						'relation' => 'OR',
						[
							'key'     => '_dc_bogo_start_date',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => '_dc_bogo_start_date',
							'value'   => '',
							'compare' => '=',
						],
						[
							'key'     => '_dc_bogo_start_date',
							'value'   => $today,
							'compare' => '<=',
							'type'    => 'DATE',
						],
					],
					[
						'relation' => 'OR',
						[
							'key'     => '_dc_bogo_end_date',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => '_dc_bogo_end_date',
							'value'   => '',
							'compare' => '=',
						],
						[
							'key'     => '_dc_bogo_end_date',
							'value'   => $today,
							'compare' => '>=',
							'type'    => 'DATE',
						],
					],
				],
			];
			$cached_rule_ids = get_posts( $args );
			set_transient( 'dc_bogo_active_rules_ids', $cached_rule_ids, 5 * MINUTE_IN_SECONDS );
		}

		if ( empty( $cached_rule_ids ) ) {
			return [];
		}

		// Fetch full post objects from cached IDs
		return get_posts( [
			'post_type'      => 'dc_bogo_rule',
			'posts_per_page' => -1,
			'post__in'       => $cached_rule_ids,
			'orderby'        => 'post__in',
		] );
	}

	/**
	 * Find all rules that could potentially apply to the current cart.
	 *
	 * @param WC_Cart   $cart The WooCommerce cart object.
	 * @param WP_Post[] $rules Array of valid rule posts.
	 * @return array
	 */
	private function find_candidate_rules( $cart, $rules ) {
		$candidates         = [];
		$cart_subtotal      = $cart->get_subtotal();
		$applied_coupons    = $cart->get_applied_coupons();
		$current_user       = wp_get_current_user();
		$current_user_roles = (array) $current_user->roles;
		if ( ! $current_user->ID ) {
			$current_user_roles[] = 'guest';
		}

		foreach ( $rules as $rule_post ) {
			$rule_id    = $rule_post->ID;
			$offer_name = get_post_meta( $rule_id, '_dc_bogo_offer_name', true ) ?: $rule_post->post_title;

			// Condition checks
			$min_subtotal = get_post_meta( $rule_id, '_dc_bogo_min_subtotal', true );
			if ( ! empty( $min_subtotal ) && $cart_subtotal < $min_subtotal ) {
				continue;
			}
			$max_subtotal = get_post_meta( $rule_id, '_dc_bogo_max_subtotal', true );
			if ( ! empty( $max_subtotal ) && $cart_subtotal > $max_subtotal ) {
				continue;
			}

			$allowed_roles = get_post_meta( $rule_id, '_dc_bogo_allowed_roles', true ) ?: [];
			if ( ! empty( $allowed_roles ) && ! (bool) array_intersect( $current_user_roles, $allowed_roles ) ) {
				continue;
			}

			$apply_mode  = get_post_meta( $rule_id, '_dc_bogo_apply_mode', true );
			$coupon_code = get_post_meta( $rule_id, '_dc_bogo_coupon_code', true );
			if ( 'coupon' === $apply_mode ) {
				if ( empty( $coupon_code ) || ! in_array( strtolower( $coupon_code ), array_map( 'strtolower', $applied_coupons ), true ) ) {
					continue;
				}
			}

			// Usage limit checks
			$limit_total = (int) get_post_meta( $rule_id, '_dc_bogo_limit_total', true );
			if ( $limit_total > 0 && (int) get_post_meta( $rule_id, '_dc_bogo_total_uses_count', true ) >= $limit_total ) {
				continue;
			}
			$limit_per_customer = (int) get_post_meta( $rule_id, '_dc_bogo_limit_per_customer', true );
			if ( $limit_per_customer > 0 && is_user_logged_in() && (int) get_user_meta( get_current_user_id(), '_dc_bogo_uses_' . $rule_id, true ) >= $limit_per_customer ) {
				continue;
			}

			// Exclusivity check
			$is_exclusive = 'yes' === get_post_meta( $rule_id, '_dc_bogo_exclusive', true );
			if ( $is_exclusive && count( array_filter( $applied_coupons, fn( $c ) => strtolower( $c ) !== strtolower( $coupon_code ) ) ) > 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				continue;
			}

			// Main BOGO logic check
			$buy_match              = get_post_meta( $rule_id, '_dc_bogo_buy_match', true );
			$buy_ids                = get_post_meta( $rule_id, "_dc_bogo_buy_{$buy_match}", true ) ?: [];
			$matching_buy_items     = $this->find_matching_cart_items( $cart, $buy_match, $buy_ids, false, false );
			$total_matching_buy_qty = array_sum( wp_list_pluck( $matching_buy_items, 'quantity' ) );

			$buy_qty_needed   = (int) get_post_meta( $rule_id, '_dc_bogo_buy_qty', true ) ?: 1;
			$get_qty_per_deal = (int) get_post_meta( $rule_id, '_dc_bogo_get_qty', true ) ?: 1;
			$bogo_type        = get_post_meta( $rule_id, '_dc_bogo_type', true );
			$is_same_pool     = ( 'buy_x_get_x_same' === $bogo_type );

			$min_items_for_deal = $is_same_pool ? ( $buy_qty_needed + $get_qty_per_deal ) : $buy_qty_needed;

			// Handle "in-progress" state for reminders
			if ( $total_matching_buy_qty < $min_items_for_deal ) {
				if ( 'yes' === get_post_meta( $rule_id, '_dc_bogo_show_cart_reminder', true ) && $total_matching_buy_qty > 0 ) {
					$this->add_in_progress_rule( $rule_id, $offer_name, $buy_match, $buy_ids, $total_matching_buy_qty, $min_items_for_deal );
				}
				continue; // Not qualified yet
			}

			// Rule is qualified, add to candidates
			$candidates[ $rule_id ] = [
				'rule_id'        => $rule_id,
				'offer_name'     => $offer_name,
				'buy_match'      => $buy_match,
				'buy_ids'        => $buy_ids,
				'is_repeatable'  => 'yes' === get_post_meta( $rule_id, '_dc_bogo_apply_multiple', true ),
				'times_to_apply' => 1, // Default, will be updated for repeatable rules
				'is_same_pool'   => $is_same_pool,
				'get_qty_per_deal' => $get_qty_per_deal,
				'discount_type'  => get_post_meta( $rule_id, '_dc_bogo_discount_type', true ),
				'discount_val'   => (float) get_post_meta( $rule_id, '_dc_bogo_discount_val', true ),
				'apply_cheapest' => 'yes' === get_post_meta( $rule_id, '_dc_bogo_apply_cheapest', true ),
				'coupon_code'    => $coupon_code,
				'is_exclusive'   => $is_exclusive,
				'priority'       => (int) get_post_meta( $rule_id, '_dc_bogo_priority', true ),
			];

			// Calculate how many times a repeatable rule can apply
			if ( $candidates[ $rule_id ]['is_repeatable'] ) {
				$items_per_cycle = $min_items_for_deal > 0 ? $min_items_for_deal : 1;
				$candidates[ $rule_id ]['times_to_apply'] = floor( $total_matching_buy_qty / $items_per_cycle );
			}

			// Estimate discount for selection strategy
			$candidates[ $rule_id ]['estimated_total_discount'] = $this->estimate_rule_discount( $cart, $candidates[ $rule_id ] );
		}

		return $candidates;
	}

	/**
	 * Selects the best rule from a list of candidates based on the admin-defined strategy.
	 *
	 * @param array $candidates The array of candidate rules.
	 * @return array|null The best rule or null if no candidates.
	 */
	private function select_best_rule( $candidates ) {
		if ( empty( $candidates ) ) {
			return null;
		}

		$strategy = get_option( 'dc_bogo_selection_strategy', 'max_discount' );

		usort( $candidates, function ( $a, $b ) use ( $strategy ) {
			if ( 'max_discount' === $strategy ) {
				return $b['estimated_total_discount'] <=> $a['estimated_total_discount'] ?: $a['priority'] <=> $b['priority'];
			}
			if ( 'min_discount' === $strategy ) {
				return $a['estimated_total_discount'] <=> $b['estimated_total_discount'] ?: $a['priority'] <=> $b['priority'];
			}
			// 'closest' strategy (not fully implemented, defaults to priority)
			return $a['priority'] <=> $b['priority'];
		} );

		return reset( $candidates );
	}

	public function apply_bogo_rules( $cart ) {
		static $dc_bogo_rules_already_ran = false;
		if ( $dc_bogo_rules_already_ran ) {
			return;
		}
		$dc_bogo_rules_already_ran = true;

		$this->clear_previous_bogo_notices();
		$this->bogo_discount_totals   = [];
		$this->in_progress_rules_data = [];

		if ( ( is_admin() || ( ! is_cart() && ! is_checkout() ) ) && ! ( defined( 'WC_DOING_AJAX' ) && WC_DOING_AJAX ) ) {
			return;
		}

		$this->reset_bogo_discounts( $cart );

		if ( isset( WC()->session ) && WC()->session ) {
			$this->previous_in_progress_rules = WC()->session->get( 'dc_bogo_in_progress_rules_session', [] );
		}

		// Step 1: Get all potentially valid rules
		$rules = $this->get_valid_rules();

		if ( empty( $rules ) ) {
			$this->cleanup_auto_added_items( $cart, [] );
			$this->save_session_data();
			return;
		}

		// Step 2: Find all rules that are candidates for application
		$candidates = $this->find_candidate_rules( $cart, $rules );

		// Step 3: Select the single best rule based on strategy
		$selected_rule = $this->select_best_rule( $candidates );

		// If no rule is selected, clean up and exit
		if ( empty( $candidates ) ) {
			$this->cleanup_auto_added_items( $cart, [] );
			$this->save_session_data();
			return;
		}

		// Step 4: Handle auto-adding items to the cart if required by the selected rule
		$this->handle_auto_add_get_item( $cart, $selected_rule );

		// Step 5: Apply the discount for the selected rule
		$this->apply_discount_for_rule( $cart, $selected_rule );

		// Step 6: Clean up any auto-added items from rules that were NOT applied
		$this->cleanup_auto_added_items( $cart, $this->applied_rule_ids );

		// Step 7: Save session data for next page load
		$this->save_session_data();
	}

	/**
	 * Handles the logic for automatically adding a "get" item to the cart.
	 *
	 * @param WC_Cart $cart The WooCommerce cart object.
	 * @param array   $selected_rule The chosen rule to apply.
	 */
	private function handle_auto_add_get_item( $cart, $selected_rule ) {
		$rule_id_for_add = $selected_rule['rule_id'];
		$auto_add_get_item = get_post_meta( $rule_id_for_add, '_dc_bogo_auto_add_get_product', true );
		$get_match_for_add = get_post_meta( $rule_id_for_add, '_dc_bogo_get_match', true );

		$auto_added_rules = WC()->session->get( 'dc_bogo_auto_added_rules', [] );

		if ( 'yes' === $auto_add_get_item && 'products' === $get_match_for_add ) {
			$get_products_for_add = get_post_meta( $rule_id_for_add, '_dc_bogo_get_products', true );
			if ( ! empty( $get_products_for_add ) ) {
				$product_to_add_id = $get_products_for_add[0];

				$product_obj = wc_get_product( $product_to_add_id );
				if ( $product_obj && $product_obj->is_type( 'variable' ) ) {
					$default_attributes = $product_obj->get_default_attributes();
					if ( ! empty( $default_attributes ) ) {
						$data_store = WC_Data_Store::load( 'product' );
						$variation_id = $data_store->find_matching_product_variation( $product_obj, $default_attributes );
						if ( $variation_id ) {
							$product_to_add_id = $variation_id;

						}
					} else {
						$variations = $product_obj->get_available_variations();
						if ( ! empty( $variations ) ) {
							$product_to_add_id = $variations[0]['variation_id'];

						}
					}
				}

				$qty_needed = $selected_rule['get_qty_per_deal'] * $selected_rule['times_to_apply'];


				$product_obj = wc_get_product( $product_to_add_id );
				$can_auto_add = true;
				
				if ( ! $product_obj ) {

					$can_auto_add = false;
				} elseif ( ! $product_obj->is_in_stock() ) {

					$can_auto_add = false;
				} elseif ( ! $product_obj->has_enough_stock( $qty_needed ) ) {

					$can_auto_add = false;
				}

				if ( $can_auto_add ) {
					$buy_qty_needed = (int) get_post_meta( $rule_id_for_add, '_dc_bogo_buy_qty', true );
					$get_qty_per_deal = (int) $selected_rule['get_qty_per_deal'];
					
					if ( $buy_qty_needed >= $get_qty_per_deal ) {
						$is_overlap = false;
						$buy_match = $selected_rule['buy_match'];
						$buy_ids = $selected_rule['buy_ids'];

						if ( 'products' === $buy_match ) {
							if ( in_array( $product_to_add_id, $buy_ids ) ) {
								$is_overlap = true;
							}
						}

						if ( $is_overlap ) {
							$total_buy_needed = $selected_rule['times_to_apply'] * $buy_qty_needed;
							$total_get_needed = $qty_needed;
							$required_total = $total_buy_needed + $total_get_needed;
							
							$available = $selected['current_qty'];
							
							$shortfall = max( 0, $required_total - $available );
							if ( $shortfall > 0 ) {
								$qty_needed += $shortfall;
							}
						}
					}

					$existing_auto_added_key = false;

					foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
						if ( isset( $cart_item['dc_bogo_auto_added'] ) && $cart_item['dc_bogo_auto_added'] == $rule_id_for_add ) {
							if ( $cart_item['product_id'] == $product_to_add_id || $cart_item['variation_id'] == $product_to_add_id ) {
								$existing_auto_added_key = $cart_item_key;
								break;
							}
						}
					}

					if ( ! $existing_auto_added_key ) {

						$new_key = $cart->add_to_cart( $product_to_add_id, $qty_needed, 0, [], [ 'dc_bogo_auto_added' => $rule_id_for_add ] );
						if ( $new_key ) {
							$added_product = wc_get_product( $product_to_add_id );
							/* translators: %s: Product title */
							$this->add_to_cart_messages[] = sprintf( __( "'%s' has been added to your cart.", 'dc-bogo-coupons' ), $added_product ? $added_product->get_name() : '' );
							if ( ! in_array( $rule_id_for_add, $auto_added_rules ) ) {
								$auto_added_rules[] = $rule_id_for_add;
								WC()->session->set( 'dc_bogo_auto_added_rules', $auto_added_rules );
							}
							$this->cart_updated = true;
						}
					} else {
						$current_qty = $cart->cart_contents[ $existing_auto_added_key ]['quantity'];

						
						if ( $current_qty != $qty_needed ) {

							$cart->set_quantity( $existing_auto_added_key, $qty_needed );
							$this->cart_updated = true;
						} else {

						}
					}
				}
			}
		}
	}

	/**
	 * Applies the discount for a single, selected BOGO rule.
	 *
	 * @param WC_Cart $cart The WooCommerce cart object.
	 * @param array   $selected_rule The chosen rule to apply.
	 */
	private function apply_discount_for_rule( $cart, $selected_rule ) {
		$rule_id    = $selected_rule['rule_id'];
		$offer_name = $selected_rule['offer_name'];
		
		$get_match = get_post_meta( $rule_id, '_dc_bogo_get_match', true );
		$get_ids   = get_post_meta( $rule_id, "_dc_bogo_get_{$get_match}", true ) ?: [];

		$items_to_discount_pool = $selected_rule['is_same_pool'] ? $this->find_matching_cart_items( $cart, $selected_rule['buy_match'], $selected_rule['buy_ids'], false, true ) : $this->find_matching_cart_items( $cart, $get_match, $get_ids, true, true );

		// Sort discountable items based on price and auto-add status
		uasort( $items_to_discount_pool, fn( $a, $b ) => $this->sort_discount_pool_items( $a, $b, $rule_id ) );

		$total_items_to_discount = $selected_rule['times_to_apply'] * $selected_rule['get_qty_per_deal'];
		if ( $selected_rule['is_same_pool'] ) {
			$temp_pool = $items_to_discount_pool;
			$buy_items_to_reserve = ( (int) get_post_meta( $rule_id, '_dc_bogo_buy_qty', true ) ) * $selected_rule['times_to_apply'];
			uasort( $temp_pool, function ( $a, $b ) {
				return $b['price'] <=> $a['price'];
			} );
			foreach ( $temp_pool as $key => $item ) {
				if ( ! empty( $item['is_auto_added'] ) ) {
					continue;
				}
				if ( $buy_items_to_reserve <= 0 ) {
					break;
				}
				$qty_to_reserve = min( $item['quantity'], $buy_items_to_reserve );
				if ( isset( $items_to_discount_pool[ $key ] ) ) {
					$items_to_discount_pool[ $key ]['quantity'] -= $qty_to_reserve;
					if ( $items_to_discount_pool[ $key ]['quantity'] <= 0 ) {
						unset( $items_to_discount_pool[ $key ] );
					}
				}
				$buy_items_to_reserve -= $qty_to_reserve;
			}
		}

		$discount_type = $selected_rule['discount_type'];
		$discount_val  = $selected_rule['discount_val'];
		$rule_applied = false;
		$total_discount_for_rule = 0;
		foreach ( $items_to_discount_pool as $item_key => $item_data ) {
			if ( $total_items_to_discount <= 0 ) {
				break;
			}
			$units_to_discount = min( $item_data['quantity'], $total_items_to_discount );
			$discount_applied = $this->apply_discount_to_cart_item( $cart, $item_key, $units_to_discount, $discount_type, $discount_val, $rule_id );
			$total_discount_for_rule += $discount_applied;
			$total_items_to_discount -= $units_to_discount;
			$rule_applied = true;
		}

		if ( $rule_applied ) {
			$this->applied_rule_ids[] = $rule_id;
			if ( $total_discount_for_rule > 0 ) {
				$this->bogo_discount_totals[ $rule_id ] = $total_discount_for_rule;
			}

			do_action( 'dc_bogo_rule_applied', $rule_id, $total_discount_for_rule, [ $rule_id => $offer_name ] );

			if ( $selected_rule['is_exclusive'] ) {
				$notice_text = __( 'BOGO deal applied. Other coupons have been removed.', 'dc-bogo-coupons' );
				$notices     = wc_get_notices( 'notice' );
				$notice_exists = false;
				foreach ( $notices as $notice ) {
					if ( $notice['notice'] === $notice_text ) {
						$notice_exists = true;
						break;
					}
				}
				if ( ! $notice_exists ) {
					wc_add_notice( $notice_text, 'notice' );
				}
				foreach ( $cart->get_applied_coupons() as $coupon ) {
					if ( strtolower( $coupon ) !== strtolower( $selected_rule['coupon_code'] ) ) {
						$cart->remove_coupon( $coupon );
					}
				}
			}
		}
	}

	/**
	 * Removes auto-added items from the cart if their parent rule was not applied.
	 *
	 * @param WC_Cart $cart The WooCommerce cart object.
	 * @param array   $applied_rule_ids A list of rule IDs that were successfully applied.
	 */
	private function cleanup_auto_added_items( $cart, $applied_rule_ids ) {
		$auto_added_items_in_cart = [];
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['dc_bogo_auto_added'] ) ) {
				$auto_added_items_in_cart[ $cart_item_key ] = $cart_item['dc_bogo_auto_added'];
			}
		}

		foreach ( $auto_added_items_in_cart as $item_key => $rule_id_that_added ) {
			if ( ! in_array( $rule_id_that_added, $applied_rule_ids, true ) ) {
				$cart->remove_cart_item( $item_key );
			}
		}
	}

	/**
	 * Saves BOGO data to the WC session.
	 */
	private function save_session_data() {
		if ( isset( WC()->session ) && WC()->session ) {
			WC()->session->set( 'dc_bogo_applied_rules', $this->applied_rule_ids );
			WC()->session->set( 'dc_bogo_in_progress_rules_session', $this->in_progress_rules_data );
		}
	}

	/**
	 * Adds a rule to the in-progress list for frontend reminders.
	 */
	private function add_in_progress_rule( $rule_id, $offer_name, $buy_match, $buy_ids, $current_qty, $required_qty ) {
		$items_still_needed   = $required_qty - $current_qty;
		$qualifying_item_name = 'qualifying item(s)';

		if ( 'categories' === $buy_match && ! empty( $buy_ids ) ) {
			$term = get_term( $buy_ids[0], 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$qualifying_item_name = $term->name;
			}
		} elseif ( 'products' === $buy_match && ! empty( $buy_ids ) ) {
			$product = wc_get_product( $buy_ids[0] );
			if ( $product ) {
				$qualifying_item_name = $product->get_name();
			}
		}

		$this->in_progress_rules_data[ $rule_id ] = [
			'offer_name'           => $offer_name,
			'items_still_needed'   => $items_still_needed,
			'qualifying_item_name' => $qualifying_item_name,
			'current_qty'          => $current_qty,
			'required_qty'         => $required_qty,
			'buy_match'            => $buy_match,
			'buy_ids'              => $buy_ids,
			'priority'             => (int) get_post_meta( $rule_id, '_dc_bogo_priority', true ),
		];
	}

	/**
	 * Sort callback for the discount pool. Prioritizes auto-added items, then sorts by price.
	 */
	private function sort_discount_pool_items( $a, $b, $rule_id ) {
		if ( $a['is_auto_added'] !== $b['is_auto_added'] ) {
			return $b['is_auto_added'] <=> $a['is_auto_added']; // true (1) comes before false (0)
		}
		if ( 'yes' === get_post_meta( $rule_id, '_dc_bogo_apply_cheapest', true ) ) {
			return $a['price'] <=> $b['price'];
		}
		return $b['price'] <=> $a['price'];
	}

	/**
	 * Estimates the total discount a rule would provide.
	 */
	private function estimate_rule_discount( $cart, $rule_data ) {
		$get_match = get_post_meta( $rule_data['rule_id'], '_dc_bogo_get_match', true );
		$get_ids   = get_post_meta( $rule_data['rule_id'], "_dc_bogo_get_{$get_match}", true ) ?: [];
		$pool      = $this->find_matching_cart_items( $cart, $get_match, $get_ids, true, true );

		uasort( $pool, fn( $a, $b ) => $this->sort_discount_pool_items( $a, $b, $rule_data['rule_id'] ) );

		$units_needed = (int) $rule_data['times_to_apply'] * (int) $rule_data['get_qty_per_deal'];
		$est_total    = 0.0;

		foreach ( $pool as $item ) {
			if ( $units_needed <= 0 ) {
				break;
			}
			$take = min( $item['quantity'], $units_needed );
			$orig = (float) $item['price'];
			$per  = 0;

			switch ( $rule_data['discount_type'] ) {
				case 'free':
					$per = $orig;
					break;
				case 'percent':
					$per = $orig * ( $rule_data['discount_val'] / 100 );
					break;
				case 'fixed':
					$per = $rule_data['discount_val'];
					break;
			}
			$est_total += min( $orig, $per ) * $take;
			$units_needed -= $take;
		}
		return $est_total;
	}

	/**
	 * Helper: Find matching items in the cart.
	 */
	public function find_matching_cart_items( $cart, $match_type, $item_ids, $exclude_discounted = false, $include_auto_added = false ) {
		$matching_items = [];

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

			// Exclude auto-added items from being considered as "buy" items.
			if ( ! $include_auto_added && isset( $cart_item['dc_bogo_auto_added'] ) ) {
				continue;
			}

			// If it's already had a BOGO discount, it can't be a "Get" item, unless it was auto-added
			if ( $exclude_discounted && isset( $cart_item['dc_bogo_discount_applied'] ) ) {
				continue;
			}

			$product    = $cart_item['data'];
			$product_id = $product->get_id();
			$quantity   = $cart_item['quantity'];
			$price      = $product->get_price();
			$matches    = false;

			switch ( $match_type ) {
				case 'products':
					if ( in_array( $product_id, $item_ids ) || in_array( $product->get_parent_id(), $item_ids ) ) {
						$matches = true;
					}
					break;
				case 'categories':
					$term_ids = $product->get_category_ids();
					if ( ! empty( array_intersect( $term_ids, $item_ids ) ) ) {
						$matches = true;
					}
					break;

			}

			if ( $matches ) {
				$matching_items[ $cart_item_key ] = [
					'key'        => $cart_item_key,
					'product_id' => $product_id,
					'quantity'      => $quantity,
					'price'         => (float) $price,
					'is_auto_added' => isset( $cart_item['dc_bogo_auto_added'] ),
				];
			}
		}
		return $matching_items;
	}

	/**
	 * Helper: Apply the actual discount to a cart item.
	 * This is complex because we need to handle partial quantities.
	 */
	private function apply_discount_to_cart_item( $cart, $item_key, $units_to_discount, $discount_type, $discount_val, $rule_id ) {

		if ( ! isset( $cart->cart_contents[ $item_key ] ) ) {
			return 0;
		}

		$cart_item = $cart->cart_contents[ $item_key ];
		$product   = $cart_item['data'];

		// If the item was auto-added, it should always be free.
		if ( isset( $cart_item['dc_bogo_auto_added'] ) ) {
			$discount_type = 'free';
		}

		$original_price = isset( $cart_item['dc_bogo_original_price'] ) ? (float) $cart_item['dc_bogo_original_price'] : (float) $product->get_price();

		if ( ! isset( $cart_item['dc_bogo_original_price'] ) ) {
			$cart->cart_contents[ $item_key ]['dc_bogo_original_price'] = $original_price;
		}

		$new_price           = $original_price;
		$discount_per_item = 0;

		switch ( $discount_type ) {
			case 'free':
				$discount_per_item = $original_price;
				break;
			case 'percent':
				$discount_per_item = $original_price * ( $discount_val / 100 );
				break;
			case 'fixed':
				$discount_per_item = $discount_val;
				break;
		}
		$discount_per_item = min( $original_price, $discount_per_item );
		$new_price           = $original_price - $discount_per_item;

		$new_price = max( 0, $new_price );

		if ( $units_to_discount >= $cart_item['quantity'] ) {
			$cart->cart_contents[ $item_key ]['data']->set_price( $new_price );
			$cart->cart_contents[ $item_key ]['dc_bogo_discount_applied'] = $rule_id;
		} else {
			$discounted_units = $units_to_discount;
			$normal_units     = $cart_item['quantity'] - $units_to_discount;

			$total_discounted_price = $new_price * $discounted_units;
			$total_normal_price     = $original_price * $normal_units;

			$averaged_price = ( $total_discounted_price + $total_normal_price ) / $cart_item['quantity'];

			$cart->cart_contents[ $item_key ]['data']->set_price( $averaged_price );
			$cart->cart_contents[ $item_key ]['dc_bogo_discount_applied'] = $rule_id;
		}

		return $discount_per_item * $units_to_discount;
	}

	public function delete_linked_coupon_on_rule_delete( $post_id ) {
		if ( 'dc_bogo_rule' === get_post_type( $post_id ) ) {
			dc_bogo_coupons_init()->admin->sync_woocommerce_coupon( $post_id, 'auto', '', 'no' );
			delete_transient( 'dc_bogo_active_rules_ids' );
		}
	}

	/**
	 * Save the applied BOGO rule IDs to the order meta.
	 */
	public function save_applied_rules_to_order( $order, $data ) {
		$applied_rules = WC()->session->get( 'dc_bogo_applied_rules' );
		if ( ! empty( $applied_rules ) && is_array( $applied_rules ) ) {
			$order->update_meta_data( '_dc_bogo_applied_rules', $applied_rules );
		}
	}

	/**
	 * Increment usage counts for BOGO rules after an order is processed.
	 */
	public function increment_rule_usage_counts( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$applied_rules = $order->get_meta( '_dc_bogo_applied_rules' );

		if ( ! empty( $applied_rules ) && is_array( $applied_rules ) ) {
			$user_id = $order->get_user_id();

			foreach ( $applied_rules as $rule_id ) {
				// Increment total usage count
				$total_uses = (int) get_post_meta( $rule_id, '_dc_bogo_total_uses_count', true );
				update_post_meta( $rule_id, '_dc_bogo_total_uses_count', $total_uses + 1 );

				// Increment per-customer usage count if it's a logged-in user
				if ( $user_id ) {
					$customer_uses = (int) get_user_meta( $user_id, '_dc_bogo_uses_' . $rule_id, true );
					update_user_meta( $user_id, '_dc_bogo_uses_' . $rule_id, $customer_uses + 1 );
				}
			}
		}
	}
}
