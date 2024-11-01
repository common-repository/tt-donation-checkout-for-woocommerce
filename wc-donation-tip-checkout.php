<?php
session_start();
/*
Plugin Name: TT Donation Checkout for WooCommerce
Plugin URI: https://terrytsang.com/product/tt-woocommerce-donation-checkout/
Description: Allow customers to add donation/tip amount for WooCommerce checkout
Version: 1.0.0
Author: Terry Tsang
Author URI: https://www.terrytsang.com
*/

/*  Copyright 2022 Terry Tsang (email: terrytsang811@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
    
// Define plugin name
define('wc_plugin_name_donation_tip_checkout', 'TT Donation Checkout for WooCommerce');

// Define plugin version
define('wc_version_donation_tip_checkout', '1.0.0');


// Checks if the WooCommerce plugins is installed and active.
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
	if(!class_exists('WooCommerce_Donation_Tip_Checkout')){
		class WooCommerce_Donation_Tip_Checkout{

			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;

			/**
			 * Gets things started by adding an action to initialize this plugin once
			 * WooCommerce is known to be active and initialized
			 */
			public function __construct(){
				load_plugin_textdomain('woocommerce-donation-checkout', false, dirname(plugin_basename(__FILE__)) . '/languages/');
				
				WooCommerce_Donation_Tip_Checkout::$plugin_prefix = 'wc_donation_tip_checkout_';
				WooCommerce_Donation_Tip_Checkout::$plugin_basefile = plugin_basename(__FILE__);
				WooCommerce_Donation_Tip_Checkout::$plugin_url = plugin_dir_url(WooCommerce_Donation_Tip_Checkout::$plugin_basefile);
				WooCommerce_Donation_Tip_Checkout::$plugin_path = trailingslashit(dirname(__FILE__));
				
				$this->options_donation_tip_checkout = array(
					'donation_tip_checkout_enabled' => '',
					'donation_tip_checkout_message' => 'As a charity we rely upon your donations, so please support us by donating what you can.',
					'donation_tip_checkout_button' => 'Donate',
					'donation_tip_checkout_label' => 'Your Donation',
					'donation_tip_checkout_default_amount' => 0,
					'donation_tip_checkout_taxable' => false,
					'donation_tip_checkout_min_amount' => 0,
				);
	
				$this->saved_options_donation_tip_checkout = array();
				
				add_action('woocommerce_init', array(&$this, 'init'));
			}

			/**
			 * Initialize extension when WooCommerce is active
			 */
			public function init(){
				
				//add menu link for the plugin (backend)
				add_action( 'admin_menu', array( &$this, 'add_menu_donation_tip_checkout' ) );

				add_action( 'admin_enqueue_scripts', array( &$this, 'wc_donation_admin_scripts' ) );
				
				if(get_option('donation_tip_checkout_enabled'))
				{
					add_action( 'woocommerce_cart_calculate_fees', array( &$this, 'woo_add_donation_tip') );
					add_action( 'woocommerce_before_checkout_form', array( &$this, 'woo_donation_tip_form') );
				}
			}
			
			public function wc_donation_admin_scripts($hook){
				/* Register admin stylesheet. */
				wp_register_style( 'admin-donation-stylesheet', plugins_url('/lib/chosen/css/chosen.css', __FILE__) );
				wp_register_style( 'admin-stylesheet', plugins_url('/lib/chosen/css/style.css', __FILE__) );
				wp_register_style( 'admin-css', plugins_url('/assets/css/admin.css', __FILE__) );
				wp_register_script( 'admin-js', plugins_url('/assets/js/script.js', __FILE__), array( 'jquery-ui-core', 'jquery-ui-datepicker' ), 1, true);
				wp_register_script( 'chosen-jquery', plugins_url('/lib/chosen/js/chosen.jquery.js', __FILE__), array( 'jquery', 'jquery-ui-datepicker' ), '1', true);

				wp_enqueue_style( 'admin-donation-stylesheet' );
				wp_enqueue_style( 'admin-stylesheet' );
				wp_enqueue_style( 'admin-css' );
				wp_enqueue_script( 'admin-js' );
				wp_enqueue_script( 'chosen-jquery' );
			}
			
			public function woo_donation_tip_form() {
				global $woocommerce, $post, $wpdb;
				
				$donation_tip_checkout_message  = get_option( 'donation_tip_checkout_message') ? get_option( 'donation_tip_checkout_message' ) : 'As a charity we rely upon your donations, so please support us by donating what you can.';
				$donation_tip_checkout_button	= get_option( 'donation_tip_checkout_button' ) ? get_option( 'donation_tip_checkout_button' ) : 'Donate';
				$donation_tip_checkout_label	= get_option( 'donation_tip_checkout_label' ) ? get_option( 'donation_tip_checkout_label' ) : 'Add Donation';
				$donation_tip_checkout_default_amount  = get_option( 'donation_tip_checkout_default_amount' ) ? get_option( 'donation_tip_checkout_default_amount' ) : '0';
				
				if(!isset($_SESSION['donation_tip_amount']))
					$_SESSION['donation_tip_amount'] = '';

				$donation_tip_amount				= isset($_POST['donation_tip_amount']) ? sanitize_text_field( $_POST['donation_tip_amount'] ) : sanitize_text_field( $_SESSION['donation_tip_amount'] );
				$donation_tip_checkout_min_amount	= get_option('donation_tip_checkout_min_amount');
				
				// check and validate
				if ( isset( $_POST['donation_tip_amount'] ) ){
					if( empty( $_POST['donation_tip_amount'] ) ) {
						echo '<ul class="woocommerce-message"><li>'.__( 'You have removed the donation/tip from the order', "woocommerce-donation-checkout").'</li></ul>';

						unset($_SESSION['donation_tip_amount']);
					} else {
						if( $_POST['donation_tip_amount'] <  $donation_tip_checkout_min_amount){
							echo '<ul class="woocommerce-error"><li>'.__( 'Sorry, the minimum amount is ', "woocommerce-donation-checkout" ).get_woocommerce_currency_symbol().' '. esc_attr( $donation_tip_checkout_min_amount ).'</li></ul>';
						} else {
							$_SESSION['donation_tip_amount'] = sanitize_text_field( $_POST['donation_tip_amount'] );
							echo '<div class="woocommerce-message">'.__( 'You have updated the cart total.', "woocommerce-donation-checkout" ).'</div>';
						}
					}
				}

				$info_message = '';
				
				
			?>
				<div class="woocommerce-info"><?php echo esc_attr( $info_message ); ?> <a href="#" class="showdonationtip"><?php echo esc_attr( $donation_tip_checkout_message ); ?></a></div>
	
				<form class="checkout_donation_tip" method="post">
	
				<p class="form-row form-row-first">
					<input type="text" name="donation_tip_amount" class="input-text" placeholder="<?php _e( 'Amount', "woocommerce-donation-checkout" ); ?>" id="donation_tip_amount" value="<?php if($donation_tip_amount > 0) { echo esc_attr( $donation_tip_amount ); } else { echo esc_attr( $donation_tip_checkout_default_amount ); } ?>" style="width:100px" />
					<div class="clear">&nbsp;</div>
					<input type="submit" class="button" name="apply_donation_tip" value="<?php echo esc_attr( $donation_tip_checkout_button ); ?>" />
				</p>
			
				<div class="clear"></div>
				</form>
			<?php 
			
			}
			
			/**
			 * Add donation/tip at checkout page
			 */
			public function woo_add_donation_tip() {
				global $woocommerce;
				
				$donation_tip_checkout_enabled  = get_option( 'donation_tip_checkout_enabled') ? get_option( 'donation_tip_checkout_enabled' ) : 0;
				$donation_tip_checkout_message  = get_option( 'donation_tip_checkout_message') ? get_option( 'donation_tip_checkout_message' ) : 'As a charity we rely upon your donations, so please support us by donating what you can.';
				$donation_tip_checkout_button	= get_option( 'donation_tip_checkout_button' ) ? get_option( 'donation_tip_checkout_button' ) : 'Donation';
				$donation_tip_checkout_label	= get_option( 'donation_tip_checkout_label' ) ? get_option( 'donation_tip_checkout_label' ) : 'Add Donation';
				$donation_tip_checkout_default_amount  = get_option( 'donation_tip_checkout_default_amount' ) ? get_option( 'donation_tip_checkout_default_amount' ) : '0';
				$donation_tip_checkout_taxable	= get_option( 'donation_tip_checkout_taxable' ) ? get_option( 'donation_tip_checkout_taxable' ) : false;
				$donation_tip_checkout_min_amount	= get_option( 'donation_tip_checkout_minorder' ) ? get_option( 'donation_tip_checkout_minorder' ) : '0';
				
				//get cart total from session
				$session_cart = $woocommerce->session->cart;
				if(count($session_cart) > 0) {
					$total = 0;
					foreach($session_cart as $cart_product)
						$total = $total + $cart_product['line_subtotal'];
				}
				
				if ( is_admin() && ! defined( 'DOING_AJAX' ) )
					return;
			
				//add extra fee
				$donation_tip_amount  = '';
				if($donation_tip_checkout_enabled){
					$donation_tip_amount  = isset($_SESSION['donation_tip_amount']) ? sanitize_text_field( $_SESSION['donation_tip_amount'] ) : 0;

					if($donation_tip_amount > 0) {
						$woocommerce->cart->add_fee( __($donation_tip_checkout_label, 'woocommerce'), $donation_tip_amount, $donation_tip_checkout_taxable );
					}
				}
			}
			
			/**
			 * Add a menu link to the woocommerce section menu
			 */
			function add_menu_donation_tip_checkout() {
				$wc_page = 'woocommerce';
				$comparable_settings_page = add_submenu_page( $wc_page , __( 'TT Donation Checkout', "woocommerce-donation-checkout" ), __( 'TT Donation Checkout', "woocommerce-donation-checkout" ), 'manage_options', 'wc-donation-tip-checkout', array(
						&$this,
						'settings_page_donation_tip_checkout'
				));
				
				//add_action( 'admin_print_styles-' . $comparable_settings_page, array( &$this, 'tsang_plugin_admin_styles') );
			}
			
			/**
			 * Create the settings page content
			 */
			public function settings_page_donation_tip_checkout() {
			
				// If form was submitted
				if ( isset( $_POST['submitted'] ) )
				{
					check_admin_referer( "woocommerce-donation-checkout" );
	
					$this->saved_options_donation_tip_checkout['donation_tip_checkout_enabled'] = ! isset( $_POST['donation_tip_checkout_enabled'] ) ? '1' : sanitize_text_field( $_POST['donation_tip_checkout_enabled'] );
					$this->saved_options_donation_tip_checkout['donation_tip_checkout_message'] = ! isset( $_POST['donation_tip_checkout_message'] ) ? 'As a charity we rely upon your donations, so please support us by donating what you can.' : sanitize_text_field( $_POST['donation_tip_checkout_message'] );
					$this->saved_options_donation_tip_checkout['donation_tip_checkout_button'] = ! isset( $_POST['donation_tip_checkout_button'] ) ? 'Donation' : sanitize_text_field( $_POST['donation_tip_checkout_button'] );
					$this->saved_options_donation_tip_checkout['donation_tip_checkout_label'] = ! isset( $_POST['donation_tip_checkout_label'] ) ? 'Add Donation' : sanitize_text_field( $_POST['donation_tip_checkout_label'] );
					$this->saved_options_donation_tip_checkout['donation_tip_checkout_default_amount'] = ! isset( $_POST['donation_tip_checkout_default_amount'] ) ? 0 : sanitize_text_field( $_POST['donation_tip_checkout_default_amount'] );
					$this->saved_options_donation_tip_checkout['donation_tip_checkout_taxable'] = ! isset( $_POST['donation_tip_checkout_taxable'] ) ? false : sanitize_text_field( $_POST['donation_tip_checkout_taxable'] );
					$this->saved_options_donation_tip_checkout['donation_tip_checkout_min_amount'] = ! isset( $_POST['donation_tip_checkout_min_amount'] ) ? 0 : sanitize_text_field( $_POST['donation_tip_checkout_min_amount'] );
						
					foreach($this->options_donation_tip_checkout as $field => $value)
					{
						$option_donation_tip_checkout = get_option( $field );
			
						if($option_donation_tip_checkout != $this->saved_options_donation_tip_checkout[$field])
							update_option( $field, $this->saved_options_donation_tip_checkout[$field] );
					}
						
					// Show message
					echo '<div id="message" class="updated fade"><p>' . __( 'WooCommerce Donation/Tip Checkout options saved.', "woocommerce-donation-checkout" ) . '</p></div>';
				}
			
				$donation_tip_checkout_enabled	= get_option( 'donation_tip_checkout_enabled' );
				$donation_tip_checkout_message	= get_option( 'donation_tip_checkout_message' ) ? get_option( 'donation_tip_checkout_message' ) : 'As a charity we rely upon your donations, so please support us by donating what you can.';
				$donation_tip_checkout_button	= get_option( 'donation_tip_checkout_button' ) ? get_option( 'donation_tip_checkout_button' ) : 'Donation';
				$donation_tip_checkout_label	= get_option( 'donation_tip_checkout_label' ) ? get_option( 'donation_tip_checkout_label' ) : 'Add Donation';
				$donation_tip_checkout_default_amount  = get_option( 'donation_tip_checkout_default_amount' ) ? get_option( 'donation_tip_checkout_default_amount' ) : '0';
				$donation_tip_checkout_taxable	= get_option( 'donation_tip_checkout_taxable' ) ? get_option( 'donation_tip_checkout_taxable' ) : false;
				$donation_tip_checkout_min_amount	= get_option( 'donation_tip_checkout_min_amount' ) ? get_option( 'donation_tip_checkout_min_amount' ) : '0';
				
				$checked_enabled = '';
				$checked_taxable = '';
			
				if($donation_tip_checkout_enabled)
					$checked_enabled = 'checked="checked"';
				
				if($donation_tip_checkout_taxable)
					$checked_taxable = 'checked="checked"';

			
				$actionurl = sanitize_url( $_SERVER['REQUEST_URI'] );
				$nonce = wp_create_nonce( "woocommerce-donation-checkout" );
			
			
				// Configuration Page
			
				?>
				<div id="icon-options-general" class="icon32"></div>
				<h3><?php _e( 'TT Donation Checkout', "woocommerce-donation-checkout"); ?></h3>

						<form action="<?php echo esc_url( $actionurl ); ?>" method="post">
						<table>
								<tbody>
									<tr>
										<td colspan="2">
											<table class="widefat fixed" cellspacing="2" cellpadding="2" border="0">
												<tr>
													<td width="25%"><?php _e( 'Enable', "woocommerce-donation-checkout" ); ?></td>
													<td>
														<input class="checkbox" name="donation_tip_checkout_enabled" id="donation_tip_checkout_enabled" value="0" type="hidden">
														<input class="checkbox" name="donation_tip_checkout_enabled" id="donation_tip_checkout_enabled" value="1" <?php echo esc_attr( $checked_enabled ); ?> type="checkbox">
													</td>
												</tr>
												<tr>
													<td><?php _e( 'Donation/Tip Message', "woocommerce-donation-checkout" ); ?></td>
													<td>
														<textarea id="donation_tip_checkout_message" name="donation_tip_checkout_message" cols="50" rows="5" /><?php echo esc_attr( $donation_tip_checkout_message ); ?></textarea>
													</td>
												</tr>
												<tr>
													<td><?php _e( 'Button Label', "woocommerce-donation-checkout" ); ?></td>
													<td>
														<input type="text" id="donation_tip_checkout_button" name="donation_tip_checkout_button" value="<?php echo esc_attr( $donation_tip_checkout_button ); ?>" size="30" />
													</td>
												</tr>
												<tr>
													<td><?php _e( 'Title', "woocommerce-donation-checkout" ); ?></td>
													<td>
														<input type="text" id="donation_tip_checkout_label" name="donation_tip_checkout_label" value="<?php echo esc_attr( $donation_tip_checkout_label ); ?>" size="30" />
													</td>
												</tr>
												<tr>
													<td><?php _e( 'Default Amount', "woocommerce-donation-checkout" ); ?></td>
													<td>
														<?php echo get_woocommerce_currency_symbol(); ?>&nbsp;<input type="text" id="donation_tip_checkout_default_amount" name="donation_tip_checkout_default_amount" value="<?php echo esc_attr( $donation_tip_checkout_default_amount ); ?>" size="10" />
													</td>
												</tr>
												<tr>
													<td><?php _e( 'Minumum Donation/Tip Amount', "woocommerce-donation-checkout" ); ?></td>
													<td>
															<?php echo get_woocommerce_currency_symbol(); ?>&nbsp;<input type="text" id="donation_tip_checkout_minorder" name="donation_tip_checkout_min_amount" value="<?php echo esc_attr( $donation_tip_checkout_min_amount ); ?>" size="10" />
													</td>
												</tr>
												<tr>
													<td><?php _e( 'Maximum Donation/Tip Amount  <small style="color:#cccccc">(PRO)</small>', "woocommerce-donation-checkout" ); ?></td>
													<td>
															<?php echo get_woocommerce_currency_symbol(); ?>&nbsp;<input type="text" id="donation_tip_checkout_minorder" name="donation_tip_checkout_min_amount" value="" placeholder="PRO VERSION" size="13" disabled/>
													</td>
												</tr>
												<tr>
													<td><?php _e( 'Predefined Donation Amount List <small style="color:#cccccc">(PRO)</small>', "woocommerce-donation-checkout" ); ?></td>
													<td>
															<?php echo get_woocommerce_currency_symbol(); ?>&nbsp;<input type="text" id="donation_tip_checkout_select_options" name="donation_tip_checkout_select_options" value="" placeholder="PRO VERSION" size="20" disabled />&nbsp;<small>a list of amount separated with comma(,), e.g: 5,10,20</small>
													</td>
												</tr>
												<tr>
													<td width="25%"><?php _e( 'Taxable', "woocommerce-donation-checkout" ); ?></td>
													<td>
														<input class="checkbox" name="donation_tip_checkout_taxable" id="donation_tip_checkout_taxable" value="0" type="hidden">
														<input class="checkbox" name="donation_tip_checkout_taxable" id="donation_tip_checkout_taxable" value="1" <?php echo esc_attr( $checked_taxable ); ?> type="checkbox">
													</td>
												</tr>
												<tr>
													<td width="25%"><?php _e( 'Show at Cart Page  <small style="color:#cccccc">(PRO)</small>', "woocommerce-donation-checkout" ); ?></td>
													<td>
														<input class="checkbox" name="donation_tip_checkout_cart" id="donation_tip_checkout_cart" value="0" type="hidden">
														<input class="checkbox" name="donation_tip_checkout_cart" id="donation_tip_checkout_cart" value="" <?php echo esc_attr( $checked_taxable ); ?> type="checkbox" disabled> <span style="color: #999">PRO VERSION</span>
													</td>
												</tr>

												
											</table>
										</td>
									</tr>
									<tr><td>&nbsp;</td></tr>
									<tr>
										<td colspan=2">
											<input class="button-primary" type="submit" name="Save" value="<?php _e('Save Options', "woocommerce-donation-checkout"); ?>" id="submitbutton" />
											<input type="hidden" name="submitted" value="1" /> 
											<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										</td>
									</tr>
								</tbody>
						</table>
						</form>


					<br />
				<hr />
				<div style="height:30px"></div>
				<div class="center woocommerce-BlankState">
					<p><img src="<?php echo plugin_dir_url( __FILE__ ) ?>logo-terrytsang.png" title="Terry Tsang" alt="Terry Tsang" /></p>
					<h2 class="woocommerce-BlankState-message">Hi, I'm Terry from <a href="https://3minimonsters.com" target="_blank">3 Mini Monsters</a>. I have built WooCommerce plugins since 10 years ago and always find ways to make WooCommerce experience better through my products and articles. Thanks for using my plugin and do share around if you love this.</h2>

					<a class="woocommerce-BlankState-cta button" target="_blank" href="https://terrytsang.com/products">Check out our WooCommerce plugins</a>

					<a class="woocommerce-BlankState-cta button-primary button" href="https://terrytsang.com/product/tt-woocommerce-donation-checkout-pro" target="_blank">Upgrade to TT Donation Checkout PRO</a>

					
				</div>

				<br /><br /><br />

				<div class="components-card is-size-medium woocommerce-marketing-recommended-extensions-card woocommerce-marketing-recommended-extensions-card__category-coupons woocommerce-admin-marketing-card">
					<div class="components-flex components-card__header is-size-medium"><div>
						<span class="components-truncate components-text"></span>
						<div style="margin: 20px 20px">Try my other plugins to power up your online store and bring more sales/leads to you.</div>
					</div>
				</div>

				<div class="components-card__body is-size-medium">
					<div class="woocommerce-marketing-recommended-extensions-card__items woocommerce-marketing-recommended-extensions-card__items--count-6">
						<a href="https://terrytsang.com/product/tt-woocommerce-discount-option-pro/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-custom-checkout.png" title="TT Discount Option PRO for WooCommerce" alt="WooCommerce Discount Option" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Discount Option</h4>
								<p style="color:#333333;">Add a fixed fee/percentage discount based on minimum order amount, products, categories, date range and day.</p>
							</div>
						</a>

						<a href="https://terrytsang.com/product/tt-woocommerce-one-page-checkout-pro/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-onepage-checkout.png" title="TT One-Page Checkout PRO for WooCommerce" alt="WooCommerce One-Page Checkout" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT One-Page Checkout</h4>
								<p style="color:#333333;">Combine cart and checkout at one page to simplify entire WooCommerce checkout process.</p>
							</div>
						</a>

						<a href="https://terrytsang.com/product/tt-woocommerce-extra-fee-option-pro/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-custom-checkout.png" title="TT Extra Fee Option PRO fro WooCommerce" alt="WooCommerce Extra Fee Options" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Extra Fee Option</h4>
								<p style="color:#333333;">Add a discount based on minimum order amount, product categories, products and date range.</p>
							</div>
						</a>

						<!-- <a href="https://terrytsang.com/product/tt-woocommerce-coming-soon/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php //echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-coming-soon-product.png" title="WooCommerce Coming Soon Product" alt="WooCommerce Coming Soon Product" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Coming Soon</h4>
								<p style="color:#333333;">Display countdown clock at coming-soon product page.</p>
							</div>
						</a> -->

						<!-- <a href="https://terrytsang.com/product/tt-woocommerce-product-badge/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php //echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-product-badge.png" title="WooCommerce Product Badge" alt="WooCommerce Product Badge" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Product Badge</h4>
								<p style="color:#333333;">Add product badges liked Popular, Sales, Featured to the product.</p>
							</div>
						</a> -->

						<!-- <a href="https://terrytsang.com/product/tt-woocommerce-product-catalog/" target="_blank" class="woocommerce-marketing-recommended-extensions-item">
							<div class="woocommerce-admin-marketing-product-icon">
								<img src="<?php //echo plugin_dir_url( __FILE__ ) ?>assets/images/icon-woocommerce-product-catalog.png" title="WooCommerce Product Catalog" alt="WooCommerce Product Catalog" width="50" height="50" />
							</div>
							<div class="woocommerce-marketing-recommended-extensions-item__text">
								<h4>TT Product Catalog</h4>
								<p style="color:#333333;">Hide Add to Cart / Checkout button and turn your website into product catalog.</p>
							</div>
						</a> -->

					
					</div>
				</div>
					

				
			<?php
			}
			
			function update_checkout(){
			?>
				<script>
				jQuery(document).ready(function($){
				$('.payment_methods input.input-radio').live('click', function(){
					$('#billing_country, #shipping_country, .country_to_state').trigger('change');
				})
				});
				</script>
			<?php
			}
			
			/**
			 * Get the setting options
			 */
			function get_options() {
				
				foreach($this->options_donation_tip_checkout as $field => $value)
				{
					$array_options[$field] = get_option( $field );
				}
					
				return $array_options;
			}
			
			
		}//end class
			
	}//if class does not exist
	
	$woocommerce_donation_tip_checkout = new WooCommerce_Donation_Tip_Checkout();
}
else{
	add_action('admin_notices', 'wc_donation_tip_checkout_error_notice');
	function wc_donation_tip_checkout_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__(wc_plugin_name_donation_tip_checkout.' requires <a href="http://www.woocommerce.com/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">WooCommerce</a> first.').'</p></div>';
		}
	}
}

?>