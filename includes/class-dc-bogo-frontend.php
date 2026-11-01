<?php
/**
 * Frontend class
 *
 * @package DC_BOGO_Coupons
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class DC_BOGO_Frontend.
 */
class DC_BOGO_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts_styles' ] );
		add_action( 'woocommerce_before_cart_totals', [ $this, 'display_bogo_progress_bar' ], 10 );
		add_action( 'woocommerce_before_cart', [ $this, 'display_bogo_progress_bar' ], 10 );
		add_action( 'woocommerce_before_cart_totals', [ $this, 'display_bogo_savings_message' ], 20 );
	}

	/**
	 * Display a "You saved" message for BOGO coupons.
	 */
	/**
	 * Display a "You saved" message for BOGO coupons.
	 */
	public function display_bogo_savings_message() {
	// Check if savings notice is enabled
	if ( 'yes' !== get_option( 'dc_bogo_show_savings_notice', 'yes' ) ) {
		return;
	}

	$applied_rules = WC()->session->get( 'dc_bogo_applied_rules' );
	$cart_instance = dc_bogo_coupons_init()->cart;

	if ( ! empty( $applied_rules ) && ! empty( $cart_instance->bogo_discount_totals ) ) {
		$total_saved = array_sum( $cart_instance->bogo_discount_totals );
		
		if ( $total_saved > 0 ) {
			echo '<div class="dc-bogo-savings-notice-box">';
			
			// Check if custom tag is enabled
			$use_custom_tag = 'yes' === get_option( 'dc_bogo_use_custom_tag', 'no' );
			$custom_tag_text = get_option( 'dc_bogo_custom_tag_text', 'BOGO Deal' );
			
			foreach ( $applied_rules as $rule_id ) {
				// Check if this rule gave a specific product for free
				$get_match = get_post_meta( $rule_id, '_dc_bogo_get_match', true );
				$discount_type = get_post_meta( $rule_id, '_dc_bogo_discount_type', true );
				
				if ( 'products' === $get_match && 'free' === $discount_type ) {
					$get_ids = get_post_meta( $rule_id, '_dc_bogo_get_products', true );
					if ( ! empty( $get_ids ) ) {
						$product = wc_get_product( $get_ids[0] );
						if ( $product ) {
							/* translators: %s: Product name */
							$msg = sprintf( __( 'You got <strong>&nbsp;%s&nbsp;</strong> for free!', 'dc-bogo-coupons' ), $product->get_name() );
							echo '<div class="dc-bogo-savings-item"><span class="dashicons dashicons-smiley"></span> ' . wp_kses_post( $msg ) . '</div>';
							continue;
						}
					}
				}
				
				// Fallback generic message for other types
				$offer_name = $use_custom_tag ? $custom_tag_text : get_the_title( $rule_id );
				$rule_saved = isset( $cart_instance->bogo_discount_totals[ $rule_id ] ) ? $cart_instance->bogo_discount_totals[ $rule_id ] : 0;
				if ( $rule_saved > 0 ) {
					/* translators: 1: Offer name, 2: Saved amount */
					$msg = sprintf( __( "'%1\$s' applied! You saved %2\$s", 'dc-bogo-coupons' ), $offer_name, wc_price( $rule_saved ) );
					echo '<div class="dc-bogo-savings-item"><span class="dashicons dashicons-tag"></span> ' . wp_kses_post( $msg ) . '</div>';
				}
			}
			
			// Total savings summary if multiple rules applied
			if ( count( $applied_rules ) > 1 ) {
				/* translators: %s: Total saved amount */
				echo '<div class="dc-bogo-savings-total">' . sprintf( esc_html__( 'Total Savings: %s', 'dc-bogo-coupons' ), wp_kses_post( wc_price( $total_saved ) ) ) . '</div>';
			}
			
			echo '</div>';
		}
	}
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_frontend_scripts_styles() {
		// Only load frontend assets on the cart page or on the Shop page when enabled
		$show_on_shop = get_option( 'dc_bogo_show_on_shop', 'no' );
		if ( is_cart() || ( is_shop() && 'yes' === $show_on_shop ) ) {
			wp_enqueue_style(
				'dc-bogo-frontend-css',
				plugin_dir_url( dirname( __FILE__ ) ) . 'frontend-styles.css',
				[],
				'1.0.1'
			);

			// Small frontend script to refresh the progress bar on cart/shop updates
			wp_enqueue_script(
				'dc-bogo-frontend-js',
				plugin_dir_url( dirname( __FILE__ ) ) . 'dc-bogo-frontend.js',
				[ 'jquery' ],
				'1.0.0',
				true
			);

			$just_refreshed = false;
			if ( isset( WC()->session ) && WC()->session->get( 'dc_bogo_just_refreshed' ) ) {
				$just_refreshed = true;
				WC()->session->set( 'dc_bogo_just_refreshed', false );
			}

			wp_localize_script(
				'dc-bogo-frontend-js',
				'dcBogoFrontend',
				[
					'nonce'         => wp_create_nonce( 'dc_bogo_frontend_nonce' ),
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'justRefreshed' => $just_refreshed,
					'i18n'          => [
						'progressLabel' => __( '%1$d/%2$d items added', 'dc-bogo-coupons' ),
						'qualifiedMsg'  => __( 'You have qualified for the "%s" deal!', 'dc-bogo-coupons' ),
					],
				]
			);
		}
	}

	/**
	 * Display consolidated BOGO progress bar and reminders.
	 */
	public function display_bogo_progress_bar() {
		// If admin has disabled the progress bar, do nothing.
		$show_progress_bar_setting = get_option( 'dc_bogo_show_progress_bar', 'yes' );
		if ( 'yes' !== $show_progress_bar_setting ) {
			return;
		}

		// Only render when the hook matches the admin-configured position.
		$position = get_option( 'dc_bogo_progress_position', 'before_totals' );
		$current  = current_filter();
		$map      = [
			'before_totals' => 'woocommerce_before_cart_totals',
			'before_cart'   => 'woocommerce_before_cart',
		];

		// If not forcing output via AJAX, respect the configured position.
		if ( ! dc_bogo_coupons_init()->cart->force_progress_output ) {
			if ( isset( $map[ $position ] ) && $map[ $position ] !== $current ) {
				return;
			}
		}

		        // If nothing is in progress and there are no auto-add messages, don't render anything.
				if ( empty( dc_bogo_coupons_init()->cart->in_progress_rules_data ) && empty( dc_bogo_coupons_init()->cart->add_to_cart_messages ) ) {
					return;
				}
		
				$progress_color             = get_option( 'dc_bogo_progress_color', '#17a673' );
		// Use the captured previous state from the cart instance, which preserves the state
		// BEFORE the current calculation run. This ensures we can detect changes even if
		// the session has already been updated.
		$previous_in_progress_rules = dc_bogo_coupons_init()->cart->previous_in_progress_rules;
		
				// Reset last qualified messages collector
				dc_bogo_coupons_init()->cart->last_qualified_messages = [];
				// Check for newly qualified rules and collect messages (we'll echo notices
				// server-side for non-AJAX and return messages for AJAX callers)
				if ( is_array( $previous_in_progress_rules ) ) {
					foreach ( $previous_in_progress_rules as $rule_id => $data ) {
						if ( ! isset( dc_bogo_coupons_init()->cart->in_progress_rules_data[ $rule_id ] ) ) {
							// This rule was previously in progress but is no longer, meaning it's now qualified.
							/* translators: %s: Offer name */
							$msg                                                  = sprintf( __( 'Congratulations! You\'ve qualified for the \"%s\" deal!', 'dc-bogo-coupons' ), $data['offer_name'] );
							dc_bogo_coupons_init()->cart->last_qualified_messages[] = $msg;
						}
					}
				}
		
				// Group rules by qualifying item type (e.g., category)
				$grouped_rules = [];
				if ( is_array( dc_bogo_coupons_init()->cart->in_progress_rules_data ) ) {
					foreach ( dc_bogo_coupons_init()->cart->in_progress_rules_data as $rule_id => $data ) {
						$group_key = $data['buy_match'] . '_' . implode( '-', $data['buy_ids'] );
						if ( empty( $data['buy_ids'] ) ) { // For generic "qualifying items"
							$group_key = 'generic_qualifying_items';
						}
		
						if ( ! isset( $grouped_rules[ $group_key ] ) ) {
							$grouped_rules[ $group_key ] = [
								'qualifying_item_name' => $data['qualifying_item_name'],
								'current_qty'          => 0,
								'milestones'           => [], // Each milestone is a deal
							];
						}
		
						// Aggregate current quantity for the group
						$grouped_rules[ $group_key ]['current_qty'] = max( $grouped_rules[ $group_key ]['current_qty'], $data['current_qty'] );
		
						$grouped_rules[ $group_key ]['milestones'][] = [
							'offer_name'         => $data['offer_name'],
							'required_qty'       => $data['required_qty'],
							'items_still_needed' => $data['items_still_needed'],
							'rule_id'            => $rule_id,
							'bogo_type'          => $data['bogo_type'] ?? '',
						];
					}
				}
		
			// Wrap in a container we can target and replace via AJAX
			// Expose the progress color as a CSS variable so pseudo-elements (milestone points)
			// pick up the same accent color as the fill.
				echo '<div class="dc-bogo-custom-notice-box" data-dc-bogo-progress="1" style="--dc-accent: ' . esc_attr( $progress_color ) . ';">'; // Custom box for the progress bar
		
				// Display auto-add messages if any
				$add_to_cart_messages = dc_bogo_coupons_init()->cart->add_to_cart_messages;
				if ( ! empty( $add_to_cart_messages ) ) {
					echo '<div class="dc-bogo-auto-add-messages" style="background-color: #d4edda; color: #155724; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px;">';
					foreach ( $add_to_cart_messages as $message ) {
						echo '<p style="margin: 0;">' . esc_html( $message ) . '</p>';
					}
					echo '</div>';
				}
		
				foreach ( $grouped_rules as $group_key => $group_data ) {			$qualifying_item_name = $group_data['qualifying_item_name'];
			$current_qty          = $group_data['current_qty'];

			// Sort milestones by required quantity to display them in order
			usort(
				$group_data['milestones'],
				function ( $a, $b ) {
					return $a['required_qty'] <=> $b['required_qty'];
				}
			);

			$max_required_qty = 0;
			foreach ( $group_data['milestones'] as $milestone ) {
				$max_required_qty = max( $max_required_qty, $milestone['required_qty'] );
			}

			// Choose a default milestone to show: the one with smallest 'items_still_needed', tie-breaker: required_qty, then priority if available.
			$default_index = 0;
			$best_remaining = null;
			foreach ( $group_data['milestones'] as $idx => $milestone ) {
				$remaining = isset( $milestone['items_still_needed'] ) ? (int) $milestone['items_still_needed'] : ( ( isset( $milestone['required_qty'] ) ? (int) $milestone['required_qty'] : 0 ) - $current_qty );
				$priority  = isset( $milestone['priority'] ) ? (int) $milestone['priority'] : 0;
				if ( is_null( $best_remaining ) || $remaining < $best_remaining || ( $remaining === $best_remaining && $milestone['required_qty'] < $group_data['milestones'][ $default_index ]['required_qty'] ) || ( $remaining === $best_remaining && $priority < ( isset( $group_data['milestones'][ $default_index ]['priority'] ) ? (int) $group_data['milestones'][ $default_index ]['priority'] : 0 ) ) ) {
					$best_remaining = $remaining;
					$default_index  = $idx;
				}
			}

			// Default values for progress bar
			$progress_bar_qty = $current_qty;
			$progress_bar_max = $max_required_qty > 0 ? $max_required_qty : $default_required;
			$added_get = 0; // Initialize for later use

			$selected = $group_data['milestones'][ $default_index ];
			$bogo_type = get_post_meta( $selected['rule_id'], '_dc_bogo_type', true );
			$auto_add = get_post_meta( $selected['rule_id'], '_dc_bogo_auto_add_get_product', true );

			// Check if Buy and Get criteria are the same
			$buy_match = get_post_meta( $selected['rule_id'], '_dc_bogo_buy_match', true );
			$get_match = get_post_meta( $selected['rule_id'], '_dc_bogo_get_match', true );
			$buy_ids   = get_post_meta( $selected['rule_id'], "_dc_bogo_buy_{$buy_match}", true ) ?: [];
			$get_ids   = get_post_meta( $selected['rule_id'], "_dc_bogo_get_{$get_match}", true ) ?: [];
			
			// Normalize IDs for comparison
			if ( is_array( $buy_ids ) ) sort( $buy_ids );
			if ( is_array( $get_ids ) ) sort( $get_ids );
			
			$is_same_criteria = ( $buy_match === $get_match && $buy_ids === $get_ids );

			if ( 'buy_x_get_y_diff' === $bogo_type && 'yes' !== $auto_add && ! $is_same_criteria ) {
				// Calculate how many get items have already been added and the remaining needed
				
				$buy_qty_needed = (int) get_post_meta( $selected['rule_id'], '_dc_bogo_buy_qty', true );
				$get_qty_needed = (int) get_post_meta( $selected['rule_id'], '_dc_bogo_get_qty', true );
				
				$buy_needed_counter = $buy_qty_needed;
				$added_get = 0;

				if ( WC()->cart ) {
					// First pass: Identify Buy items and consume them
					foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
						$product_id = $cart_item['product_id'];
						$variation_id = $cart_item['variation_id'];
						$qty = $cart_item['quantity'];
						
						$matches_buy = false;
						if ( 'products' === $buy_match ) {
							if ( in_array( $product_id, $buy_ids ) || in_array( $variation_id, $buy_ids ) ) {
								$matches_buy = true;
							}
						} elseif ( 'categories' === $buy_match ) {
							$terms = get_the_terms( $product_id, 'product_cat' );
							if ( $terms && ! is_wp_error( $terms ) ) {
								foreach ( $terms as $term ) {
									if ( in_array( $term->term_id, $buy_ids ) ) {
										$matches_buy = true;
										break;
									}
								}
							}
						}
						
						if ( $matches_buy && $buy_needed_counter > 0 ) {
							$used = min( $qty, $buy_needed_counter );
							$used_quantities[ $key ] = ( isset( $used_quantities[ $key ] ) ? $used_quantities[ $key ] : 0 ) + $used;
							$buy_needed_counter -= $used;
						}
					}
					
					// Second pass: Identify Get items and count remaining
					foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
						$product_id = $cart_item['product_id'];
						$variation_id = $cart_item['variation_id'];
						$qty = $cart_item['quantity'];
						
						$matches_get = false;
						if ( 'products' === $get_match ) {
							if ( in_array( $product_id, $get_ids ) || in_array( $variation_id, $get_ids ) ) {
								$matches_get = true;
							}
						} elseif ( 'categories' === $get_match ) {
							$terms = get_the_terms( $product_id, 'product_cat' );
							if ( $terms && ! is_wp_error( $terms ) ) {
								foreach ( $terms as $term ) {
									if ( in_array( $term->term_id, $get_ids ) ) {
										$matches_get = true;
										break;
									}
								}
							}
						}
						
						if ( $matches_get ) {
							$used = isset( $used_quantities[ $key ] ) ? $used_quantities[ $key ] : 0;
							$available = max( 0, $qty - $used );
							$added_get += $available;
						}
					}
				}
				
				// Update progress bar values
				// Use the specific rule's buy qty + get qty for the max, so it reflects the specific deal (e.g. 3+2=5)
				// instead of the group max (which might be 5 from another rule) + get qty (5+2=7).
				$progress_bar_max = $buy_qty_needed + $get_qty_needed;
				
				// Cap added_get at get_qty_needed
				$added_get = min( $added_get, $get_qty_needed );
				
				$progress_bar_qty += $added_get;
			}

			// Compute percent for default — scale progress to the largest required qty in this group
			$default_required = $group_data['milestones'][ $default_index ]['required_qty'];
			$scale_total = $progress_bar_max; // Use our calculated max
			$progress_percent  = 0;
			if ( $scale_total > 0 ) {
				$progress_percent = min( 100, ( $progress_bar_qty / $scale_total ) * 100 );
			}

			// Render pills (selector) for this group
			// Expose the group's maximum required qty so frontend JS can scale progress consistently
			echo '<div class="dc-bogo-group-pills" data-group-key="' . esc_attr( $group_key ) . '" data-current="' . esc_attr( $current_qty ) . '" data-qualifying-name="' . esc_attr( $qualifying_item_name ) . '" data-max-required="' . esc_attr( $max_required_qty ) . '">';
			foreach ( $group_data['milestones'] as $idx => $milestone ) {
				$is_active = $idx === $default_index ? ' active' : '';
				
				echo '<button type="button" class="dc-bogo-pill' . esc_attr( $is_active ) . '" data-rule-id="' . esc_attr( $milestone['rule_id'] ) . '" data-required="' . esc_attr( $milestone['required_qty'] ) . '" data-items-remaining="' . esc_attr( $milestone['items_still_needed'] ) . '" data-index="' . esc_attr( $idx ) . '">' . esc_html( $milestone['offer_name'] ) . '</button>';
			}
			echo '</div>';

			// Reminder/message area (shows selected milestone message)
			echo '<p class="dc-bogo-reminder-text" data-group-key="' . esc_attr( $group_key ) . '">';
			$selected = $group_data['milestones'][ $default_index ];

			// Prepare a message for when users need to manually add 'get' items.
			// Prepare a message for when users need to manually add 'get' items.
			$get_items_message = '';
			if ( isset( $selected['bogo_type'] ) && 'buy_x_get_y_diff' === $selected['bogo_type'] && 'yes' !== get_post_meta( $selected['rule_id'], '_dc_bogo_auto_add_get_product', true ) ) {
				$get_qty  = (int) get_post_meta( $selected['rule_id'], '_dc_bogo_get_qty', true );
				$get_match = get_post_meta( $selected['rule_id'], '_dc_bogo_get_match', true );
				$get_ids   = get_post_meta( $selected['rule_id'], "_dc_bogo_get_{$get_match}", true ) ?: [];
				$get_item_name = 'qualifying';
				
				if ( 'categories' === $get_match && ! empty( $get_ids ) ) {
					$term_names = [];
					foreach ( $get_ids as $term_id ) {
						$term = get_term( $term_id, 'product_cat' );
						if ( $term && ! is_wp_error( $term ) ) {
							$term_names[] = $term->name;
						}
					}
					if ( ! empty( $term_names ) ) {
						$get_item_name = implode( ', ', $term_names );
					}
				} elseif ( 'products' === $get_match && ! empty( $get_ids ) ) {
					$product_names = [];
					foreach ( $get_ids as $product_id ) {
						$product = wc_get_product( $product_id );
						if ( $product ) {
							$product_names[] = $product->get_name();
						}
					}
					if ( ! empty( $product_names ) ) {
						$get_item_name = implode( ', ', $product_names );
					}
				}
				// Use remaining needed count for get items
				$remaining_get = $selected['items_still_needed'];
				if ( $remaining_get > 0 ) {
					/* translators: 1: Quantity, 2: Item name, 3: Offer name */
					$get_items_message = sprintf( __( ' Add %1$d %2$s item(s) to claim your "%3$s" deal !', 'dc-bogo-coupons' ), $remaining_get, $get_item_name, $selected['offer_name'] );
				}
			}

		if ( $selected['items_still_needed'] > 0 ) {
			// For Buy X Get Y deals, we need to be smarter about the message
			$buy_qty_needed = (int) get_post_meta( $selected['rule_id'], '_dc_bogo_buy_qty', true );
			$get_qty_needed = (int) get_post_meta( $selected['rule_id'], '_dc_bogo_get_qty', true );
			$bogo_type = get_post_meta( $selected['rule_id'], '_dc_bogo_type', true );
			$auto_add = get_post_meta( $selected['rule_id'], '_dc_bogo_auto_add_get_product', true );
			
			// Check if this is a Buy X Get Y (different items) deal
			if ( 'buy_x_get_y_diff' === $bogo_type && 'yes' !== $auto_add && ! $is_same_criteria ) {
				// Calculate how many buy items they have vs need
				$buy_items_still_needed = max( 0, $buy_qty_needed - $current_qty );
				
				if ( $buy_items_still_needed > 0 ) {
					// They still need more buy items
					/* translators: 1: Current Qty, 2: Required Qty, 3: Item Name, 4: Needed Qty, 5: Item Name, 6: Offer Name */
					$main_message = sprintf( 
						/* translators: 1: Current quantity, 2: Required quantity, 3: Item name, 4: Items still needed, 5: Item name again, 6: Offer name */
						__( 'You have %1$d of %2$d %3$s. Add %4$d more %5$s to get the "%6$s" deal!', 'dc-bogo-coupons' ), 
						$current_qty, 
						$buy_qty_needed, 
						$qualifying_item_name, 
						$buy_items_still_needed, 
						$qualifying_item_name, 
						$selected['offer_name'] 
					);
					echo esc_html( $main_message );
				} else {
					// They have enough buy items, now need get items
					$get_match = get_post_meta( $selected['rule_id'], '_dc_bogo_get_match', true );
					$get_ids = get_post_meta( $selected['rule_id'], "_dc_bogo_get_{$get_match}", true ) ?: [];
					$get_item_name = 'qualifying';
				
				if ( 'categories' === $get_match && ! empty( $get_ids ) ) {
					$term_names = [];
					foreach ( $get_ids as $term_id ) {
						$term = get_term( $term_id, 'product_cat' );
						if ( $term && ! is_wp_error( $term ) ) {
							$term_names[] = $term->name;
						}
					}
					if ( ! empty( $term_names ) ) {
						$get_item_name = implode( ', ', $term_names );
					}
				} elseif ( 'products' === $get_match && ! empty( $get_ids ) ) {
					$product_names = [];
					foreach ( $get_ids as $product_id ) {
						$product = wc_get_product( $product_id );
						if ( $product ) {
							$product_names[] = $product->get_name();
						}
					}
					if ( ! empty( $product_names ) ) {
						$get_item_name = implode( ', ', $product_names );
					}
				}
					
					// Calculate how many get items have already been added and the remaining needed
					// We must iterate the cart to handle cases where Buy and Get items are different (or same)
					// and correctly attribute quantities.
					// Use the pre-calculated added_get
					$remaining_get = max(0, $get_qty_needed - $added_get);
					if ($remaining_get > 0) {
						/* translators: 1: Quantity, 2: Item name */
						$main_message = sprintf(
							/* translators: 1: Quantity needed, 2: Item name */
							__( 'Great! Now add %1$d %2$s item(s) to Claim.', 'dc-bogo-coupons' ),
							$remaining_get,
							$get_item_name
						);
						echo esc_html( $main_message );
					}

				}
			} else {
				// For same-item deals or auto-add deals, use the original message
				/* translators: 1: Current Qty, 2: Required Qty, 3: Needed Qty, 4: Item Name, 5: Offer Name */
				$main_message = sprintf( __( 'You have %1$d of %2$d items. Add %3$d more %4$s to get the "%5$s" deal!', 'dc-bogo-coupons' ), $current_qty, $selected['required_qty'], $selected['items_still_needed'], $qualifying_item_name, $selected['offer_name'] );
				echo esc_html( $main_message . $get_items_message );
			}
		} else {
			/* translators: %s: Offer name */
			echo esc_html( sprintf( __( 'You have qualified for the "%s" deal!', 'dc-bogo-coupons' ), $selected['offer_name'] ) );
		}
		echo '</p>';

			// Progress bar container (shows progress for selected milestone)
			echo '<div class="dc-bogo-progress-bar-container">';
			echo '<div class="dc-bogo-progress-bar" style="background:#e6eef7; position:relative;">';
			echo '<div class="dc-bogo-progress-bar-fill" style="width: ' . esc_attr( $progress_percent ) . '%; background:' . esc_attr( $progress_color ) . '"></div>';
			// Use the group max as the denominator so the bar reflects progress toward the best offer
			/* translators: 1: Current quantity, 2: Maximum quantity */
			echo '<span class="dc-bogo-progress-bar-label" style="position:absolute; left:15px; padding: 10px; z-index:99; font-size:12px; color:#223;">' . esc_html( sprintf( __( '%1$d/%2$d items added', 'dc-bogo-coupons' ), $progress_bar_qty, $progress_bar_max ) ) . '</span>';

			// Add milestones markers (visual markers for all milestones)
			foreach ( $group_data['milestones'] as $milestone ) {
				$milestone_percent = $max_required_qty > 0 ? ( $milestone['required_qty'] / $max_required_qty ) * 100 : 0;
				echo '<div class="dc-bogo-milestone" style="left: ' . esc_attr( $milestone_percent ) . '%; background:' . esc_attr( $progress_color ) . ';"; title="' . esc_attr( $milestone['offer_name'] . ' (' . $milestone['required_qty'] . ' items)' ) . '"></div>';
			}
			echo '</div>'; // .dc-bogo-progress-bar
			echo '</div>'; // .dc-bogo-progress-bar-container

			// No inline JS here — frontend behavior handled by dc-bogo-frontend.js
		}
		echo '</div>'; // .dc-bogo-custom-notice-box

		// If we have qualified messages and we are not in forced/AJAX mode,
		// surface them as WC notices. If in AJAX/forced mode, the caller will
		// receive the messages in the AJAX response.
		if ( ! empty( dc_bogo_coupons_init()->cart->last_qualified_messages ) && ! dc_bogo_coupons_init()->cart->force_progress_output ) {
			foreach ( dc_bogo_coupons_init()->cart->last_qualified_messages as $qm ) {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $qm, 'success' );
				} else {
					add_settings_error( 'dc_bogo', 'dc_bogo_qualified', $qm, 'updated' );
				}
			}
		}

		// Reset forced output flag after rendering so normal hooked calls are unaffected
		dc_bogo_coupons_init()->cart->force_progress_output = false;
	}
}
