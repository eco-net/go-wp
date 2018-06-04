<?php

add_action('init', 'w2dc_register_listing_single_product_type');
function w2dc_register_listing_single_product_type() {
	class WC_Product_Listing_Single extends WC_Product {

		public $level_id = null;
		public $raiseup_price = 0;

		public function __construct($product) {
			
			$this->product_type = 'listing_single';

			parent::__construct($product);

			if (get_post_meta($this->id, '_listings_level', true))
				$this->level_id = get_post_meta($this->id, '_listings_level', true);

			if (get_post_meta($this->id, '_raiseup_price', true))
				$this->raiseup_price = get_post_meta($this->id, '_raiseup_price', true);
		}
		
		public function get_virtual($context = 'view') {
			return true;
		}
		
		public function get_downloadable($context = 'view') {
			return true;
		}
	}
}

class w2dc_listing_single_product {
	
	public function __construct() {
		add_filter('product_type_selector', array($this, 'add_listing_single_product'));
		add_action('admin_footer', array($this, 'listing_single_custom_js'));
		add_filter('woocommerce_product_data_tabs', array($this, 'hide_attributes_data_panel'));
		add_action('woocommerce_product_options_pricing', array($this, 'add_raiseup_price'));
		add_filter('woocommerce_product_data_panels', array($this, 'new_product_tab_content'));
		add_action('woocommerce_process_product_meta_listing_single', array($this, 'save_listing_single_tab_content'));
		
		add_filter('w2dc_create_option', array($this, 'create_price'), 10, 2);
		add_filter('w2dc_raiseup_option', array($this, 'raiseup_price'), 10, 2);
		add_filter('w2dc_renew_option', array($this, 'renew_price'), 10, 2);
		add_filter('w2dc_level_upgrade_option', array($this, 'upgrade_price'), 10, 3);

		add_filter('w2dc_submitlisting_level_price', array($this, 'levels_price_front_table_row'), 10, 2);
		
		add_filter('w2dc_level_table_header', array($this, 'levels_price_table_header'));
		add_filter('w2dc_level_table_row', array($this, 'levels_price_table_row'), 10, 2);
		
		add_filter('w2dc_level_upgrade_meta', array($this, 'levels_upgrade_meta'), 10, 2);
		add_action('w2dc_upgrade_meta_html', array($this, 'levels_upgrade_meta_html'), 10, 2);
		
		// Woocommerce Dashboard - Order Details
		add_filter('woocommerce_order_item_permalink', array($this, 'disable_listing_product_permalink'), 10, 3);
		add_action('woocommerce_order_item_meta_start', array($this, 'listing_in_order_table'), 10, 3);
		add_action('woocommerce_after_order_itemmeta', array($this, 'listing_in_order_table'), 10, 3);
		add_filter('woocommerce_display_item_meta', array($this, 'listing_in_subscriptions_table'), 10, 3);
		// Woocommerce Checkout
		add_filter('woocommerce_get_item_data', array($this, 'listing_in_checkout'), 10, 2);
		add_action('woocommerce_before_calculate_totals', array($this, 'checkout_listing_raiseup_price'));
		add_action('woocommerce_before_calculate_totals', array($this, 'checkout_listing_upgrade_price'));
		// Woocommerce add order item meta
		add_action('woocommerce_new_order_item', array($this, 'add_order_item_meta'), 10, 3);
		add_action('woocommerce_order_again_cart_item_data', array($this, 'order_again_cart_item_data'), 10, 3);
		// when guest user creates new profile after he created a listing
		add_filter('woocommerce_new_customer_data', array($this, 'update_user_info'));
		// when guest user logs in after he created a listing
		add_filter('woocommerce_checkout_customer_id', array($this, 'reassign_user'));
		// add subscription meta to order
		//add_action('woocommerce_checkout_update_order_meta', array($this, 'add_subscription_order_meta'), 10, 2);
		// tell WCS, that cart may contain subscription
		add_filter('woocommerce_is_subscription', array($this, 'is_subscription'), 10, 3);
		add_filter('woocommerce_subscriptions_product_period', array($this, 'subscriptions_product_period'), 10, 2);
		add_filter('woocommerce_subscriptions_product_period_interval', array($this, 'subscriptions_product_period_interval'), 10, 2);
		//add_action('woocommerce_cart_loaded_from_session', array($this, 'apply_subscription_schemes' ), 4);
		
		add_filter('w2dc_listing_creation_front', array($this, 'create_activation_order'));
		add_filter('w2dc_listing_renew', array($this, 'renew_listing_order'), 10, 3);
		add_filter('w2dc_listing_raiseup', array($this, 'listing_raiseup_order'), 10, 3);
		add_filter('w2dc_listing_upgrade', array($this, 'listing_upgrade_order'), 10, 3);

		// add subscription button and process subscription
		add_action('w2dc_dashboard_listing_options', array($this, 'add_subscription_button'));
		add_action('w2dc_dashboard_controller_construct', array($this, 'create_subscription_onclick'));
		
		//add_filter('woocommerce_payment_complete_order_status', array($this, 'complete_payment'), 10, 2);
		add_action('woocommerce_order_status_completed', array($this, 'complete_status'), 10);
	}

	public function add_listing_single_product($types){
		$types['listing_single'] = __('Directory listing', 'W2DC');
	
		return $types;
	}
	
	public function listing_single_custom_js() {
		if ('product' != get_post_type())
			return;
	
		?><script type='text/javascript'>
				jQuery(document).ready( function($) {
					$('.options_group.pricing').addClass('show_if_listing_single').show();
					$('.options_group.show_if_downloadable').addClass('hide_if_listing_single').hide();
					$('.general_tab').addClass('active').show();
					$('.listings_tab').removeClass('active');
					$('#general_product_data').show();
					$('#listing_single_product_data').hide();
					$('._tax_status_field').parent().addClass('show_if_listing_single');
					if ($('#product-type option:selected').val() == 'listing_single') {
						$('.show_if_listing_single').show();
						$('.hide_if_listing_single').hide();
					}
				});
			</script><?php
	}
	
	public function hide_attributes_data_panel($tabs) {
		// Other default values for 'attribute' are; general, inventory, shipping, linked_product, variations, advanced
		$tabs['inventory']['class'][] = 'hide_if_listing_single';
		$tabs['shipping']['class'][] = 'hide_if_listing_single';
		$tabs['linked_product']['class'][] = 'hide_if_listing_single';
		$tabs['variations']['class'][] = 'hide_if_listing_single';
		$tabs['attribute']['class'][] = 'hide_if_listing_single';
		$tabs['advanced']['class'][] = 'hide_if_listing_single';
	
		$tabs['listings'] = array(
				'label'	=> __('Listings level', 'W2DC'),
				'target' => 'listing_single_product_data',
				'class'	=> array('show_if_listing_single', 'show_if_listing_single', 'advanced_options'),
		);
		return $tabs;
	}

	public function add_raiseup_price() {
		woocommerce_wp_text_input(array('id' => '_raiseup_price', 'label' => __('Listings raise up price', 'W2DC') . ' (' . get_woocommerce_currency_symbol() . ')', 'data_type' => 'price', 'wrapper_class' => 'show_if_listing_single'));
	}
	
	public function new_product_tab_content() {
		global $w2dc_instance;
		?>
			<div id="listing_single_product_data" class="panel woocommerce_options_panel">
					<div class="options_group">
						<?php
						$options = array();
						foreach ($w2dc_instance->levels->levels_array as $level)
							$options[$level->id] = __('Single listing of level "'.esc_attr($level->name).'"', 'W2DC');
	
						woocommerce_wp_radio(array('id' => '_listings_level', 'options' => $options, 'label' => __('Choose the level of listing for this product type', 'W2DC')));
						?>
					</div>
			</div>
			<?php 
	}

	public function save_listing_single_tab_content($post_id) {
		update_post_meta($post_id, '_listings_level', (isset($_POST['_listings_level']) ? wc_clean($_POST['_listings_level']) : ''));

		update_post_meta($post_id, '_raiseup_price', (isset($_POST['_raiseup_price']) ? wc_clean($_POST['_raiseup_price']) : ''));
	}
	
	public function create_price($link_text, $listing) {
		if ($product = $this->get_product_by_level_id($listing->level->id)) {
			return  $link_text .' - ' . w2dc_format_price(w2dc_recalcPrice($product->get_price()));
		}
	}
	
	public function raiseup_price($link_text, $listing) {
		if ($product = $this->get_product_by_level_id($listing->level->id)) {
			return  $link_text .' - ' . w2dc_format_price(w2dc_recalcPrice($product->raiseup_price));
		}
	}
	
	public function renew_price($link_text, $listing) {
		if ($product = $this->get_product_by_level_id($listing->level->id)) {
			return  $link_text .' - ' . w2dc_format_price(w2dc_recalcPrice($product->get_price()));
		}
	}
	
	public function upgrade_price($link_text, $old_level, $new_level) {
		return $link_text .' - ' . (isset($old_level->upgrade_meta[$new_level->id]) ? w2dc_format_price(w2dc_recalcPrice($old_level->upgrade_meta[$new_level->id]['price'])) : w2dc_format_price(0));
	}

	public function levels_price_front_table_row($price, $level) {
		if (!($product = $this->get_product_by_level_id($level->id)) || w2dc_recalcPrice($product->get_price()) == 0) {
			return 0;
		} else {
			return $product->get_price_html();
		}

	}
	
	public function levels_price_table_header($columns) {
		$w2dc_columns['price'] = __('Price', 'W2DC');
	
		return array_slice($columns, 0, 2, true) + $w2dc_columns + array_slice($columns, 2, count($columns)-2, true);
	}
	
	public function levels_price_table_row($items_array, $level) {
		if (!($product = $this->get_product_by_level_id($level->id)) || (get_option('w2dc_payments_free_for_admins') && current_user_can('manage_options'))) {
			$w2dc_columns['price'] = '<span class="w2dc-payments-free">' . __('FREE', 'W2DC') . '</span>';
		} else {
			$w2dc_columns['price'] = $product->get_price_html();
		}
	
		return array_slice($items_array, 0, 1, true) + $w2dc_columns + array_slice($items_array, 1, count($items_array)-1, true);;
	}
	
	public function levels_upgrade_meta($upgrade_meta, $level) {
		global $w2dc_instance;
	
		if (w2dc_getValue($_GET, 'page') == 'w2dc_manage_upgrades') {
			$results = array();
			foreach ($w2dc_instance->levels->levels_array AS $_level) {
				if (($price = w2dc_getValue($_POST, 'level_price_' . $level->id . '_' . $_level->id)) && is_numeric($price)) {
					$results[$_level->id]['price'] = $price;
				} else {
					$results[$_level->id]['price'] = 0;
				}
			}
	
			foreach ($upgrade_meta AS $level_id=>$meta) {
				if (isset($results[$level_id])) {
					$upgrade_meta[$level_id] = $results[$level_id] + $upgrade_meta[$level_id];
				}
			}
		}
	
		return $upgrade_meta;
	}
	
	public function levels_upgrade_meta_html($level1, $level2) {
		if (isset($level1->upgrade_meta[$level2->id]) && isset($level1->upgrade_meta[$level2->id]['price'])) {
			$price = $level1->upgrade_meta[$level2->id]['price'];
		} else {
			$price = 0;
		}
	
		echo get_woocommerce_currency_symbol() . '<input type="text" size="4" name="level_price_' . $level1->id . '_' . $level2->id . '" value="' . esc_attr($price) . '" /><br />';
	}

	// Woocommerce Dashboard

	public function disable_listing_product_permalink($permalink, $item, $order) {
		if (method_exists($item, 'get_product_id') && ($product = wc_get_product($item->get_product_id())) && $product->get_type() == 'listing_single') {
			return false;
		}

		return $permalink;
	}

	public function listing_in_order_table($item_id, $item, $order) {
		if (method_exists($item, 'get_product_id') && ($product = wc_get_product($item->get_product_id())) && $product->get_type() == 'listing_single') {
			if ($listing = $this->get_listing_by_item_id($item_id)) {
				$action = wc_get_order_item_meta($item_id, '_w2dc_action');

				if (is_user_logged_in() && w2dc_current_user_can_edit_listing($listing->post->ID))
					$listing_link = '<a href="' . w2dc_get_edit_listing_link($listing->post->ID) . '" title="' . esc_attr('edit listing', 'W2DC') . '">' . $listing->title() . '</a>';
				else
					$listing_link = $listing->title();
				?>
					<p>
						<?php echo __('Directory listing:', 'W2DC') . '&nbsp;' . $listing_link; ?>
						<br />
						<?php if ($action == 'activation'):
						_e('Order for listing activation', 'W2DC'); ?>
						<br />
						<?php endif; ?>
						<?php if ($action == 'renew'):
						_e('Order for listing renewal', 'W2DC'); ?>
						<br />
						<?php endif; ?>
						<?php if ($action == 'raiseup'):
						_e('Order for listing raise up', 'W2DC'); ?>
						<br />
						<?php endif; ?>
						<?php if ($action == 'upgrade'):
						_e('Order for listing upgrade', 'W2DC'); ?>
						<br />
						<?php endif; ?>
						<?php _e('Status:', 'W2DC');
						if ($listing->status == 'active')
							echo ' <span class="w2dc-badge w2dc-listing-status-active">' . __('active', 'W2DC') . '</span>';
						elseif ($listing->status == 'expired')
							echo ' <span class="w2dc-badge w2dc-listing-status-expired">' . __('expired', 'W2DC') . '</span>';
						elseif ($listing->status == 'unpaid')
							echo ' <span class="w2dc-badge w2dc-listing-status-unpaid">' . __('unpaid', 'W2DC') . '</span>';
						elseif ($listing->status == 'stopped')
							echo ' <span class="w2dc-badge w2dc-listing-status-stopped">' . __('stopped', 'W2DC') . '</span>';
						?>
						<br />
						<?php _e('Expiration Date:', 'W2DC'); ?>&nbsp;
						<?php if ($listing->level->eternal_active_period) _e('Eternal active period', 'W2DC'); else echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), intval($listing->expiration_date)); ?>
					</p>
					<?php 
			}
		}
	}
	
	public function listing_in_subscriptions_table($html, $item, $args) {
		if (w2dc_getValue($_GET, 'post_type') == 'shop_subscription') {
			if (method_exists($item, 'get_product_id') && ($product = wc_get_product($item->get_product_id())) && $product->get_type() == 'listing_single') {
				if ($listing = $this->get_listing_by_item_id($item->get_id())) {
					if ($listing->status == 'active')
						$listing_status = __('active', 'W2DC');
					elseif ($listing->status == 'expired')
						$listing_status = __('expired', 'W2DC');
					elseif ($listing->status == 'unpaid')
						$listing_status = __('unpaid', 'W2DC');
					elseif ($listing->status == 'stopped')
						$listing_status = __('stopped', 'W2DC');
	
					$html .= '<div style="text-align: left;">' .
							__('Directory listing:', 'W2DC') . '&nbsp;' . $listing->title() .
							'<br />' .
							__('Status:', 'W2DC') . '&nbsp;' . $listing_status .
							'<br />' .
							__('Expiration Date:', 'W2DC') . '&nbsp;' .
							(($listing->level->eternal_active_period) ? __('Eternal active period', 'W2DC') : date_i18n(get_option('date_format') . ' ' . get_option('time_format'), intval($listing->expiration_date)));
							'</div>';
				}
			}
		}
		
		return $html;
	}

	public function update_user_info($customer_data) {
		foreach (WC()->cart->cart_contents as $value) {
			$product = $value['data'];
			if (isset($value['_w2dc_anonymous_user']) && isset($value['_w2dc_listing_id']) && isset($value['_w2dc_action']) && $value['_w2dc_action'] == 'activation' && $product->get_type() == 'listing_single') {
				$listing = w2dc_getListing($value['_w2dc_listing_id']);
				if ($listing) {
					$customer_data['ID'] = $listing->post->post_author;
					return $customer_data;
				}
			}
		}

		return $customer_data;
	}
	
	public function reassign_user($user_id) {
		if ($user_id && get_userdata($user_id) !== false) {
			foreach (WC()->cart->cart_contents as $value) {
				$product = $value['data'];
				if (isset($value['_w2dc_anonymous_user']) && isset($value['_w2dc_listing_id']) && isset($value['_w2dc_action']) && $value['_w2dc_action'] == 'activation' && $product->get_type() == 'listing_single') {
					$listing = w2dc_getListing($value['_w2dc_listing_id']);
					if ($listing && $listing->post->post_author != $user_id) {
						$arg = array(
								'ID' => $listing->post->ID,
								'post_author' => $user_id,
						);
						wp_update_post($arg);
					}
				}
			}
		}
		
		return $user_id;
	}
	
	public function subscriptions_product_period($subscription_period, $product) {
		global $w2dc_instance;

		if ($product->get_type() == 'listing_single' && $product->level_id) {
			$level = $w2dc_instance->levels->getLevelById($product->level_id);
			if (!$level->eternal_active_period) {
				return $level->active_period;
			}
		}
		
		return $subscription_period;
	}
	
	public function subscriptions_product_period_interval($subscription_period_interval, $product) {
		global $w2dc_instance;

		if ($product->get_type() == 'listing_single' && $product->level_id) {
			$level = $w2dc_instance->levels->getLevelById($product->level_id);
			if (!$level->eternal_active_period) {
				return $level->active_interval;
			}
		}
		
		return $subscription_period_interval;
	}
	
	public function apply_subscription_schemes($cart) {
		
		
		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			$product = $cart_item[ 'data' ];
			/* if ($this->is_subscription_checked($product->level_id) || (isset($_POST['w2dc_enable_subscription_' . $product->level_id]) && $_POST['w2dc_enable_subscription_' . $product->level_id] == 1)) {
			$product->add_meta_data('_subscription_period', 'week', true);
			$product->add_meta_data('_subscription_period_interval', 2, true);
			} else {
				$product->add_meta_data('_subscription_period', '', true);
				$product->add_meta_data('_subscription_period_interval', 0, true);
				$product->delete_meta_data('_subscription_period');
				$product->delete_meta_data('_subscription_period_interval');
				$product->add_meta_data('_' . time(), time(), true);
			} */
		}
	}
	
	public function is_subscription($is_subscription, $product_id, $product) {
		global $w2dc_instance;
		
		//return true;
		//return false;
		
		//WC()->cart->calculate_totals();
		//WC()->cart->calculate_shipping();
		
		

		if ($product->get_type() == 'listing_single' && $product->level_id) {
			
			//var_dump($product->get_meta_data('_subscription_period'));
			
			//$product->delete_meta_data('_subscription_period');
			//$product->delete_meta_data('_subscription_period_interval');
			
			//$product->add_meta_data('_subscription_period', '', true);
			//$product->add_meta_data('_subscription_period_interval', 0, true);

			$level = $w2dc_instance->levels->getLevelById($product->level_id);
			if (!$level->eternal_active_period) {
				//var_dump($_POST['w2dc_enable_subscription_' . $product->level_id]);
				//var_dump($this->is_subscription_checked($product->level_id));
				//die();
				//if ($this->is_subscription_checked($product->level_id)) {
				//var_dump($_POST);
				
				if ($checkboxes = $this->get_subscriptions_checkboxes()) {
					foreach ($checkboxes AS $listing_id=>$is_checked) {
						if ($is_checked) {
							/* WC()->session->set('cart', null);
							WC()->session->set('cart_totals', null);
							WC()->session->set('applied_coupons', null);
							WC()->session->set('coupon_discount_totals', null);
							WC()->session->set('coupon_discount_tax_totals', null);
							WC()->session->set('removed_cart_contents', null);
							WC()->session->set('order_awaiting_payment', null); */
							
							WC()->cart->subscription_period = $level->active_period;
							WC()->cart->subscription_period_interval = $level->active_interval;
								
							return true;
						} else {
							$last_subscription = w2dc_get_last_subscription_of_listing($listing_id);
							if ($last_subscription && $last_subscription->get_status() != 'active') {
								wp_delete_post($last_subscription->get_id());
							}
						}
					}
				}
				//var_dump($_POST);
				
				/* if ($this->is_subscription_checked($product->level_id) || (isset($_POST['w2dc_enable_subscription_' . $product->level_id]) && $_POST['w2dc_enable_subscription_' . $product->level_id] == 1)) {
				//if (isset($_POST['w2dc_enable_subscription_' . $product->level_id]) && $_POST['w2dc_enable_subscription_' . $product->level_id] == 1) {
					///var_dump($product->get_meta_data());
					$product->add_meta_data('_subscription_period', $level->active_period, true);
					$product->add_meta_data('_subscription_period_interval', $level->active_interval, true);
					
					WC()->cart->subscription_period = $level->active_period;
					WC()->cart->subscription_period_interval = $level->active_interval;
					
					return true;
				} else {
					unset(WC()->cart->subscription_period);
					unset(WC()->cart->subscription_period_interval);
					
					WC()->session->set('cart', null);
					WC()->session->set('cart_totals', null);
					WC()->session->set('applied_coupons', null);
					WC()->session->set('coupon_discount_totals', null);
					WC()->session->set('coupon_discount_tax_totals', null);
					WC()->session->set('removed_cart_contents', null);
					WC()->session->set('order_awaiting_payment', null);
					
					return false;
				} */
			}
		}

		return $is_subscription;
	}

	public function listing_in_checkout($item_data, $cart_item) {
		global $w2dc_instance;
		
		$product = $cart_item['data'];
		if (isset($cart_item['_w2dc_listing_id']) && $product->get_type() == 'listing_single') {
			$listing = w2dc_getListing($cart_item['_w2dc_listing_id']);
			if ($listing) {
				$item_data[] = array(
						'name' => __('Listing name', 'W2DC'),
						'value' => $listing->title()
				);
				if (isset($cart_item['_w2dc_action'])) {
					$item_data[] = array(
							'name' => __('Listing action', 'W2DC'),
							'value' => $cart_item['_w2dc_action']
					);
				}
				
				$eternal_active_period = $listing->level->eternal_active_period;
				if ($cart_item['_w2dc_action'] == 'upgrade') {
					$new_level_id = get_post_meta($listing->post->ID, '_new_level_id', true);
					if ($new_level = $w2dc_instance->levels->getLevelById($new_level_id)) {
						$eternal_active_period = $new_level->eternal_active_period;
					}
				}

				if (
					class_exists('WC_Subscriptions') &&
					is_checkout() &&
					isset($cart_item['_w2dc_action']) &&
					in_array($cart_item['_w2dc_action'], array('activation', 'renew', 'upgrade')) &&
					!$eternal_active_period
				) {
					$last_subscription = w2dc_get_last_subscription_of_listing($listing->post->ID);
					if ($cart_item['_w2dc_action'] == 'renew' && $last_subscription && $last_subscription->get_status() == 'active') {
						echo '<div class="w2dc-enable-subsciption-option">';
						echo '<strong>' . __('subscription enabled', 'W2DC') . '</strong>';
						echo '</div>';
					} else {
						$checked = (get_option('w2dc_woocommerce_enabled_subscriptions')) ? 1 : 0;

						$checked = $this->is_subscription_checked($listing->post->ID, $checked);
						
						$checked = apply_filters('w2dc_wc_subscriptions_checked', $checked, $item_data, $cart_item);
	
						echo '<div class="w2dc-enable-subsciption-option">';
						echo '<script>
								(function($) {
									"use strict";
									$(function() {
										$(".w2dc_wc_subscription_checkbox_' . $listing->post->ID . '").change(function() {
											$(".w2dc_enable_subscription_' . $listing->post->ID . '").val($(this).is(":checked") ? 1 : 0);
											jQuery(document.body).trigger("update_checkout");
										});
									})
								})(jQuery);
						</script>';
						echo '<label><input type="checkbox" name="w2dc_enable_subscription_checkbox[' . $listing->post->ID . ']" value="1" ' . checked($checked, 1, false) . ' class="w2dc_wc_subscription_checkbox_' . $listing->post->ID . '" />&nbsp;' . __('enable subscription', 'W2DC') . '</label>';
						echo '<input type="hidden" name="w2dc_enable_subscription[' . $listing->post->ID . ']" value="' . $checked . '"  class="w2dc_enable_subscription_' . $listing->post->ID . '" />';
						echo '</div>';
					}
				}
			}
		}
		
		return $item_data;
	}
	
	public function get_subscriptions_checkboxes() {
		if (isset($_POST['post_data'])) {
			parse_str($_POST['post_data'], $post_data);
			if (isset($post_data['w2dc_enable_subscription'])) {
				return $post_data['w2dc_enable_subscription'];
			}
		} elseif (isset($_POST['w2dc_enable_subscription'])) {
			return $_POST['w2dc_enable_subscription'];
		}
		
		return false;
	}

	public function is_subscription_checked($listing_id = null, $checked = 0) {
		if (isset($_POST['post_data'])) {
			parse_str($_POST['post_data'], $post_data);
			if ($listing_id) {
				if (isset($post_data['w2dc_enable_subscription'][$listing_id]) && $post_data['w2dc_enable_subscription'][$listing_id] == 1) {
					$checked = 1;
				} elseif (isset($post_data['w2dc_enable_subscription'][$listing_id]) && $post_data['w2dc_enable_subscription'][$listing_id] == 0) {
					$checked = 0;
				}
			}
			//var_dump($post_data);
			//if ((isset($post_data['w2dc_enable_subscription_' . $level_id]) && $post_data['w2dc_enable_subscription_' . $level_id] == 1) /* || ($last_subscription && $last_subscription->get_status() == 'active') */) {
			//	$checked = 1;
			//} elseif (isset($post_data['w2dc_enable_subscription_' . $level_id]) && $post_data['w2dc_enable_subscription_' . $level_id] == 0) {
			//	$checked = 0;
			//}
			
		}
		
		return $checked;
	}

	/* public function add_subscription_order_meta($order_id, $data) {
		if (class_exists('WC_Subscriptions')) {
			$order = wc_get_order($order_id);
			if (get_class($order) == 'WC_Order') {
				$items = $order->get_items();
				foreach ($items AS $item_id=>$item) {
					if (is_a($item, 'WC_Order_Item_Product') && ($product = wc_get_product($item->get_product_id())) && $product->get_type() == 'listing_single') {
						if ($listing = $this->get_listing_by_item_id($item_id)) {
							if (w2dc_getValue($_POST, 'w2dc_enable_subscription_' . $listing->post->ID)) {
								wc_add_order_item_meta($item_id, '_w2dc_do_subscription', true);
							}
						}
					}
				}
			}
		}
	} */
	
	public function checkout_listing_raiseup_price($cart_object) {
		foreach ($cart_object->cart_contents as $value) {
			$product = $value['data'];
			if (isset($value['_w2dc_action']) && $value['_w2dc_action'] == 'raiseup' && $product->get_type() == 'listing_single') {
				$value['data']->set_price($value['data']->raiseup_price);
			}
		}
	}

	public function checkout_listing_upgrade_price($cart_object) {
		foreach ($cart_object->cart_contents as $value) {
			$product = $value['data'];
			if (isset($value['_w2dc_action']) && $value['_w2dc_action'] == 'upgrade' && $product->get_type() == 'listing_single') {
				$listing = w2dc_getListing($value['_w2dc_listing_id']);
				$new_level_id = get_post_meta($listing->post->ID, '_new_level_id', true);
				if (
					$listing &&
					isset($listing->level->upgrade_meta[$new_level_id]['price']) &&
					($price = $listing->level->upgrade_meta[$new_level_id]['price']) &&
					w2dc_recalcPrice($price) > 0
				) {
					$value['data']->set_price($price);
				}
			}
		}
	}
	
	public function add_order_item_meta($item_id, $item, $order_id) {
		if (isset($item->legacy_values['_w2dc_listing_id'])) {
			wc_add_order_item_meta($item_id, '_w2dc_listing_id', $item->legacy_values['_w2dc_listing_id']);
		}
		if (isset($item->legacy_values['_w2dc_action'])) {
			wc_add_order_item_meta($item_id, '_w2dc_action', $item->legacy_values['_w2dc_action']);
		}
	}
	
	public function order_again_cart_item_data($cart_item_data, $item, $order) {
		$items = $order->get_items();
		foreach ($items AS $item_id=>$item) {
			if (is_a($item, 'WC_Order_Item_Product') && ($product = wc_get_product($item->get_product_id())) && $product->get_type() == 'listing_single') {
				$listing_id = wc_get_order_item_meta($item_id, '_w2dc_listing_id');
				$action = wc_get_order_item_meta($item_id, '_w2dc_action');
				
				if ($listing_id && $action) {
					$cart_item_data = $cart_item_data + array(
							'_w2dc_listing_id' => $listing_id,
							'_w2dc_action' => $action
					);
				}
			}
		}
		
		return $cart_item_data;
	}
	
	public function create_listing_single_order($listing_id, $level_id, $action = 'activation', $redirect = true) {
		if ($product = $this->get_product_by_level_id($level_id)) {
			$options = array(
					'_w2dc_listing_id' => $listing_id,
					'_w2dc_action' => $action
			);

			if ($action == 'activation' && !is_user_logged_in()) {
				$options['_w2dc_anonymous_user'] = true;
			}

			if (!is_admin() && !defined('DOING_CRON')) {
				WC()->cart->add_to_cart($product->get_id(), 1, 0, array(), $options);
				if ($redirect && ($checkout_url = wc_get_checkout_url())) {
					wp_redirect($checkout_url);
					die();
				}
			} else {
				// on admin dashboard we create new order directly without cart and checkout

				if ($action == 'raiseup') {
					$product->set_price($product->raiseup_price);
				}

				if ($action == 'upgrade') {
					$listing = w2dc_getListing($listing_id);
					if ($listing && ($price = $listing->level->upgrade_meta[$level_id]['price']) && w2dc_recalcPrice($price) > 0) {
						$product->set_price($price);
					}
				}

				$user_id = get_current_user_id();
				$order = wc_create_order(array('customer_id' => $user_id));

				$order_item_id = $order->add_product($product);
				
				w2dc_set_order_address($order, $user_id);
				
				$order->calculate_totals();
				
				wc_add_order_item_meta($order_item_id, '_w2dc_listing_id', $listing_id);
				wc_add_order_item_meta($order_item_id, '_w2dc_action', $action);
				
				if (!defined('DOING_CRON')) {
					w2dc_addMessage(__('Complete the order on WooCommerce Orders page.', 'W2DC'));
				}
			}
		}
	}

	public function create_activation_order($listing) {
		if ($listing && ($product = $this->get_product_by_level_id($listing->level->id)) && w2dc_recalcPrice($product->get_price()) > 0) {
			update_post_meta($listing->post->ID, '_listing_status', 'unpaid');
			wp_update_post(array('ID' => $listing->post->ID, 'post_status' => 'pending'));
			$this->create_listing_single_order($listing->post->ID, $listing->level->id, 'activation');
		}
		return $listing;
	}
	
	public function renew_listing_order($continue, $listing, $continue_invoke_hooks) {
		if ($continue_invoke_hooks[0]) {
			if ($order = w2dc_get_last_order_of_listing($listing->post->ID)) {
				if (!$order->is_paid() && $order->get_status() == 'completed') {
					$order_url = $order->get_checkout_payment_url();
					if ($order_url && is_user_logged_in()) {
						wp_redirect($order_url);
						die();
					}
					return false;
				}
			}
	
			if (($product = $this->get_product_by_level_id($listing->level->id)) && w2dc_recalcPrice($product->get_price()) > 0) {
				$this->create_listing_single_order($listing->post->ID, $listing->level->id, 'renew');
				$continue_invoke_hooks[0] = false;
				return false;
			}
		}

		return $continue;
	}
	
	public function listing_raiseup_order($continue, $listing, $continue_invoke_hooks) {
		if ($continue_invoke_hooks[0]) {
			if (($product = $this->get_product_by_level_id($listing->level->id)) && w2dc_recalcPrice($product->raiseup_price) > 0) {
				$this->create_listing_single_order($listing->post->ID, $listing->level->id, 'raiseup');
				$continue_invoke_hooks[0] = false;
				return false;
			}
		}
		return $continue;
	}

	public function listing_upgrade_order($continue, $listing, $continue_invoke_hooks) {
		if ($continue_invoke_hooks[0]) {
			$new_level_id = get_post_meta($listing->post->ID, '_new_level_id', true);
			if ($new_level_id && ($price = $listing->level->upgrade_meta[$new_level_id]['price']) && w2dc_recalcPrice($price) > 0) {
				$this->create_listing_single_order($listing->post->ID, $new_level_id, 'upgrade');
				$continue_invoke_hooks[0] = false;
				return false;
			}
		}
		
		return $continue;
	}

	public function complete_payment($status, $order_id) {
		$this->activate_listing($status, $order_id);
	
		return $status;
	}
	
	public function complete_status($order_id) {
		$this->activate_listing('completed', $order_id);
	}
	
	public function activate_listing($status, $order_id) {
		if ($status == 'completed') {
			$order = wc_get_order($order_id);
			if (get_class($order) == 'WC_Order') {
				$items = $order->get_items();
				foreach ($items AS $item_id=>$item) {
					if (is_a($item, 'WC_Order_Item_Product') && ($product = wc_get_product($item->get_product_id())) && $product->get_type() == 'listing_single') {
						if ($listing = $this->get_listing_by_item_id($item_id)) {
							$action = wc_get_order_item_meta($item_id, '_w2dc_action');
							switch ($action) {
								case "activation":
									$listing->processActivate(false);

									/* if (class_exists('WC_Subscriptions') && wc_get_order_item_meta($item_id, '_w2dc_do_subscription')) {
										$this->create_subscription($order_id);
									} */
									break;
								case "renew":
									$listing->processActivate(false);
									
									/* if (class_exists('WC_Subscriptions')) {
										$last_subscription = w2dc_get_last_subscription_of_listing($listing->post->ID);
										if (wc_get_order_item_meta($item_id, '_w2dc_do_subscription') && (!$last_subscription || $last_subscription->get_status() != 'active')) {
											$this->create_subscription($order_id);
										}
									} */
									break;
								case "raiseup":
									$listing->processRaiseUp(false);
									break;
								case "upgrade":
									$new_level_id = get_post_meta($listing->post->ID, '_new_level_id', true);
									$listing->changeLevel($new_level_id, false);
									
									/* if (class_exists('WC_Subscriptions')) {
										$this->cancel_subscriptions($listing, __('Listing has changed level and the subscription was cancelled. New subscription was created.', 'W2DC'));
										if (wc_get_order_item_meta($item_id, '_w2dc_do_subscription')) {
											$this->create_subscription($order_id);
										}
									} */

									break;
							}
						}
					}
				}
			}
		}
	}
	
	public function add_subscription_button($listing) {
		if (class_exists('WC_Subscriptions')) {
			if (!$listing->level->eternal_active_period) {
				$last_subscription = w2dc_get_last_subscription_of_listing($listing->post->ID);

				if ($listing->status == 'active' && (!$last_subscription || $last_subscription->get_status() == 'trash')) {
					$order = w2dc_get_last_order_of_listing($listing->post->ID, array('activation', 'upgrade'));
					if ($order && $order->is_paid() && $order->get_status() == 'completed') {
						$subscription_link = strip_tags(__('add subscription', 'W2DC'));
						echo '<a href="' . w2dc_dashboardUrl(array('add_subscription' => '1', 'listing_id' => $listing->post->ID)) . '" class="w2dc-btn w2dc-btn-primary w2dc-btn-sm w2dc-dashboard-subscription-btn" title="' . esc_attr($subscription_link) . '"><span class="w2dc-glyphicon w2dc-glyphicon-repeat"></span></a>';
					}
				} elseif ($last_subscription && ($subscription_url = $last_subscription->get_view_order_url())) {
					$subscription_link = strip_tags(__('view subscription', 'W2DC'));
					echo '<a href="' . $subscription_url . '" class="w2dc-btn w2dc-btn-primary w2dc-btn-sm w2dc-dashboard-subscription-btn" title="' . esc_attr($subscription_link) . '"><span class="w2dc-glyphicon w2dc-glyphicon-repeat"></span></a>';
				}
			}
		}
	}
	
	public function create_subscription_onclick() {
		if (class_exists('WC_Subscriptions')) {
			if (w2dc_getValue($_GET, 'add_subscription') && ($listing_id = w2dc_getValue($_GET, 'listing_id')) && ($listing = w2dc_getListing($listing_id))) {
				if ($listing->status == 'active' && !$listing->level->eternal_active_period) {
					$last_subscription = w2dc_get_last_subscription_of_listing($listing->post->ID);
				
					if (!$last_subscription || $last_subscription->get_status() == 'trash') {
						$order = w2dc_get_last_order_of_listing($listing_id, array('activation', 'upgrade'));
						if ($order && $order->is_paid() && $order->get_status() == 'completed') {
							$this->cancel_subscriptions($listing);

							$this->create_subscription($order->get_id());
							w2dc_addMessage(__('Subscription was created sucessfully!', 'W2DC'));
							wp_redirect(w2dc_dashboardUrl());
							die();
						}
					} else {
						w2dc_addMessage(sprintf(__('Subscription for this listing already exists. You can manage it <a href="%s">here</a>', 'W2DC'), $last_subscription->get_view_order_url()));
					}
				}
			}
		}
	}
	
	public function get_product_by_level_id($level_id) {
		$result = get_posts(array(
				'post_type' => 'product',
				'posts_per_page' => 1,
				'tax_query' => array(array(
						'taxonomy' => 'product_type',
						'field' => 'slug',
						'terms' => array('listing_single'),
						'operator' => 'IN'
				)),
				'meta_query' => array(
						array(
								'key' => '_listings_level',
								'value' => $level_id,
								'type' => 'numeric'
						)
				)
		));
		if ($result)
			return wc_get_product($result[0]->ID);
	}
	
	function get_listing_by_item_id($item_id) {
		$listing_id = wc_get_order_item_meta($item_id, '_w2dc_listing_id');
		if ($listing_id) {
			$listing = w2dc_getListing($listing_id);
			if ($listing) {
				return $listing;
			}
		}
	}
	
	function create_subscription($order_id) {
		if (class_exists('WC_Subscriptions')) {
			$order = wc_get_order($order_id);
			$items = $order->get_items();
			foreach ($items AS $item_id=>$item) {
				if (is_a($item, 'WC_Order_Item_Product') && ($product = wc_get_product($item->get_product_id())) && $product->get_type() == 'listing_single') {
					$listing_id = wc_get_order_item_meta($item_id, '_w2dc_listing_id');

					if (($listing = w2dc_getListing($listing_id)) && !$listing->level->eternal_active_period) {
						$args = array(
								'order_id' => $order_id,
								'customer_id' => $listing->post->post_author,
								'billing_period' => $listing->level->active_period,
								'billing_interval' => $listing->level->active_interval,
								'start_date' => gmdate('Y-m-d H:i:s'),
								'customer_note' => wcs_get_objects_property($order, 'customer_note'),
						);
						$subscription = wcs_create_subscription($args);
						if (!is_wp_error($subscription)) {
							$subscription_item_id = $subscription->add_product($product);
							
							$payment_gateway = wc_get_payment_gateway_by_order($order);
							$payment_method_meta = apply_filters('woocommerce_subscription_payment_meta', array(), $order);
							if (!empty($payment_gateway) && isset($payment_method_meta[$payment_gateway->id])) {
								$payment_method_meta = $payment_method_meta[$payment_gateway->id];
							}
							
							$subscription->set_payment_method($payment_gateway, $payment_method_meta);
							
							wcs_copy_order_meta($order, $subscription, 'subscription');

							wc_add_order_item_meta($subscription_item_id, '_w2dc_listing_id', $listing_id);
							wc_add_order_item_meta($subscription_item_id, '_w2dc_action', 'renew');
							$subscription->calculate_totals();

							$dates['next_payment'] = gmdate('Y-m-d H:i:s', $listing->expiration_date);
							$subscription->update_dates($dates);
						}
					}
				}
			}
			WC_Subscriptions_Manager::activate_subscriptions_for_order($order);
		}
	}
	
	function cancel_subscriptions($listing, $notice = '') {
		if (class_exists('WC_Subscriptions')) {
			$orders = w2dc_get_all_orders_of_listing($listing->post->ID);
			if ($orders) {
				foreach ($orders AS $order) {
					$subscriptions = wcs_get_subscriptions_for_order($order);
					foreach ($subscriptions AS $subscription) {
						if ($subscription->has_status('active')) {
							$subscription->cancel_order($notice);
						}
					}
				}
			}
		}
	}
}
?>