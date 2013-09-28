<?php
class WC_Gateway_Dibs_Invoice extends WC_Gateway_Dibs {
	
	/**
     * Class for Dibs Invoice payment.
     *
     */
     
	public function __construct() {
		global $woocommerce;
		
		parent::__construct();
		
		$this->id					= 'dibs_invoice';
		$this->method_title 		= __('DIBS Invoice', 'klarna');
        $this->icon 				= apply_filters( 'woocommerce_dibs_invoice_icon', plugins_url(basename(dirname(__FILE__))."/images/dibs.png") );
        $this->has_fields 			= false;
        $this->log 					= $woocommerce->logger();
		$this->paymentwindow_url 	= 'https://sat1.dibspayment.com/dibspaymentwindow/entrypoint';
        
		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
		$this->title 			= ( isset( $this->settings['title'] ) ) ? $this->settings['title'] : '';
		$this->description 		= ( isset( $this->settings['description'] ) ) ? $this->settings['description'] : '';
		$this->merchant_id 		= ( isset( $this->settings['merchant_id'] ) ) ? $this->settings['merchant_id'] : '';
		$this->key_hmac 		= html_entity_decode($this->settings['key_hmac']);
		/*$this->capturenow 		= ( isset( $this->settings['capturenow'] ) ) ? $this->settings['capturenow'] : '';*/
		$this->invoice_fee_id	= ( isset( $this->settings['invoice_fee_id'] ) ) ? $this->settings['invoice_fee_id'] : '';
		$this->language 		= ( isset( $this->settings['language'] ) ) ? $this->settings['language'] : '';
		$this->testmode			= ( isset( $this->settings['testmode'] ) ) ? $this->settings['testmode'] : '';	
		$this->debug			= ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : '';
		
		
		
		// Dibs currency codes http://tech.dibs.dk/toolbox/currency_codes/
		$this->dibs_currency = array(
			'DKK' => '208', // Danish Kroner
			'EUR' => '978', // Euro
			'USD' => '840', // US Dollar $
			'GBP' => '826', // English Pound £
			'SEK' => '752', // Swedish Kroner
			'AUD' => '036', // Australian Dollar
			'CAD' => '124', // Canadian Dollar
			'ISK' => '352', // Icelandic Kroner
			'JPY' => '392', // Japanese Yen
			'NZD' => '554', // New Zealand Dollar
			'NOK' => '578', // Norwegian Kroner
			'CHF' => '756', // Swiss Franc
			'TRY' => '949', // Turkish Lire
		);
		
		// Invoice fee
		if ( $this->invoice_fee_id == "") $this->invoice_fee_id = 0;
		
		if ( $this->invoice_fee_id > 0 ) :
			
			// Version check - 1.6.6 or 2.0
			if ( function_exists( 'get_product' ) ) {
				$product = get_product($this->invoice_fee_id);
			} else {
				$product = new WC_Product( $this->invoice_fee_id );
			}
		
			if ( $product->exists() ) :
				
				// We manually calculate the tax percentage here
				$this->invoice_fee_tax_percentage = number_format( (( $product->get_price() / $product->get_price_excluding_tax() )-1)*100, 2, '.', '');
				$this->invoice_fee_price 		= $product->get_price();
				$this->invoice_fee_price_ex_tax = $product->get_price_excluding_tax();
				$this->invoice_fee_title 		= $product->get_title();
				
			else :
			
				$this->invoice_fee_price = 0;	
							
			endif;
		
		else :
		
		$this->invoice_fee_price = 0;
		
		endif;
		
		
		// Check if the currency is supported
		if ( !isset($this->dibs_currency[get_option('woocommerce_currency')]) ) {
			$this->enabled = "no";
		} else {
			$this->enabled = $this->settings['enabled'];
		}
		
		
		// Actions
		//add_action( 'woocommerce_api_wc_gateway_dibs', array($this, 'check_callback') );
		add_action('valid-dibs-callback', array(&$this, 'successful_request') );
		add_action('woocommerce_receipt_dibs_invoice', array(&$this, 'receipt_page'));
		
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
		wp_enqueue_script( 'wc-add-dibs-invoice-fee', plugins_url( 'js/invoice-fee.js' , __FILE__ ), array('wc-checkout'), false, true );
		
		
	} // End construct
	
	
	/**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {
    
    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'woothemes' ), 
							'type' => 'checkbox', 
							'label' => __( 'Enable DIBS Invoice', 'woothemes' ), 
							'default' => 'yes'
						), 
			'title' => array(
							'title' => __( 'Title', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ), 
							'default' => __( 'DIBS Invoice', 'woothemes' )
						),
			'description' => array(
							'title' => __( 'Description', 'woothemes' ), 
							'type' => 'textarea', 
							'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ), 
							'default' => __("Pay via DIBS Invoice.", 'woothemes')
						),
			'merchant_id' => array(
							'title' => __( 'DIBS Merchant ID', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'Please enter your DIBS Merchant ID; this is needed in order to take payment.', 'woothemes' ), 
							'default' => ''
						),
			'key_hmac' => array(
							'title' => __( 'HMAC Key (k)', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'Please enter your DIBS HMAC Key (k).', 'woothemes' ), 
							'default' => ''
						),
			'language' => array(
								'title' => __( 'Language', 'woothemes' ), 
								'type' => 'select',
								'options' => array('en'=>'English', 'da'=>'Danish', 'de'=>'German', 'es'=>'Spanish', 'fi'=>'Finnish', 'fo'=>'Faroese', 'fr'=>'French', 'it'=>'Italian', 'nl'=>'Dutch', 'no'=>'Norwegian', 'pl'=>'Polish (simplified)', 'sv'=>'Swedish', 'kl'=>'Greenlandic'),
								'description' => __( 'Set the language in which the page will be opened when the customer is redirected to DIBS.', 'woothemes' ), 
								'default' => 'sv'
							),
			'invoice_fee_id' => array(
								'title' => __( 'Invoice Fee', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Create a hidden (simple) product that acts as the invoice fee. Enter the ID number in this textfield. Leave blank to disable. ', 'woothemes' ), 
								'default' => ''
							),
							/*
			'capturenow' => array(
							'title' => __( 'Instant capture (capturenow)', 'woothemes' ), 
							'type' => 'checkbox', 
							'label' => __( 'If checked the order amount is immediately transferred from the customer’s account to the shop’s account. Contact DIBS when using this function.', 'woothemes' ), 
							'default' => 'no'
						),
						*/
			'testmode' => array(
							'title' => __( 'Test Mode', 'woothemes' ), 
							'type' => 'checkbox', 
							'label' => __( 'Enable DIBS Test Mode. Read more about the <a href="http://tech.dibs.dk/10_step_guide/your_own_test/" target="_blank">DIBS test process here</a>.', 'woothemes' ), 
							'default' => 'yes'
						),
			'debug' => array(
								'title' => __( 'Debug', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( 'Enable logging (<code>woocommerce/logs/dibs.txt</code>)', 'woothemes' ), 
								'default' => 'no'
							)
			);
    
    } // End init_form_fields()

    
	/**
	 * Admin Panel Options 
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

    	?>
    	<h3><?php _e('DIBS Invoice', 'woothemes'); ?></h3>
    	<p><?php _e('Link to docs perhaps?.', 'woothemes'); ?></p>
    	<table class="form-table">
    	<?php
    		if ( isset($this->dibs_currency[get_option('woocommerce_currency')]) ) {
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			} else { ?>
				<tr valign="top">
				<th scope="row" class="titledesc">DIBS Invoice disabled</th>
				<td class="forminp">
				<fieldset><legend class="screen-reader-text"><span>DIBS Invoice disabled</span></legend>
				<?php _e('DIBS does not support your store currency.', 'woocommerce'); ?><br>
				</fieldset></td>
				</tr>
			<?php
			} // End check currency
    	?>
		</table><!--/.form-table-->
    	<?php
    } // End admin_options()

    
    /**
	 * There are no payment fields for dibs, but we want to show the description if set.
	 **/
    function payment_fields() {
    	if ($this->description) echo wpautop(wptexturize($this->description));
    }

   
	/**
	 * Generate the dibs button link
	 **/
    public function generate_dibs_form( $order_id ) {
		global $woocommerce;
		
		$order = new WC_Order( $order_id );
		
		
		$args =
			array(
				// Merchant
				'merchant' => $this->merchant_id,
				
				// Paytype
				'paytype' => 'ALL_INVOICES',
				
				// Order
				'amount' => strval($order->order_total * 100),
				
				
				// Currency
				'currency' => $this->dibs_currency[get_option('woocommerce_currency')],	
				
		);
		
		
		// Payment Method - Payment Window
		
				
		$args['orderID'] = $order_id;
				
		// Language
		if ($this->language == 'no') $this->language = 'nb';
	
		$args['language'] = $this->language;
							
		// URLs
		// Callback URL doesn't work as in the other gateways. DIBS erase everyting after a '?' in a specified callback URL
		// We also need to make the callback url the accept/return url. If we use $this->get_return_url( $order ) the HMAC calculation doesn't add up 
		$args['callbackUrl'] = trailingslashit(site_url('/woocommerce/dibscallback'));
		//$args['acceptReturnUrl'] = trailingslashit(site_url('/woocommerce/dibscallback'));
		
		$args['acceptReturnUrl'] = preg_replace( '/\\?.*/', '', $this->get_return_url( $order ) );
		$args['cancelreturnurl'] = trailingslashit(site_url('/woocommerce/dibscancel'));
				
		// Address info
		$args['billingFirstName'] = $order->billing_first_name;
		$args['billingLastName'] = $order->billing_last_name;
		$args['billingAddress'] = $order->billing_address_1;
		$args['billingAddress2'] = $order->billing_address_2;
		$args['billingPostalPlace'] = $order->billing_city;
		$args['billingPostalCode'] = $order->billing_postcode;
		$args['billingEmail'] = $order->billing_email;
		$args['billingMobile'] =  str_replace('+','-', '', $order->billing_phone);
			
		// Testmode
		if ( $this->testmode == 'yes' ) {
			$args['test'] = '1';
		}
		
		// Instant capture
		/*
		if ( $this->capturenow == 'yes' ) {
			$args['capturenow'] = '1';
		}
		*/
		
		
		
		$args['oiTypes'] = 'UNITCODE;QUANTITY;DESCRIPTION;AMOUNT;VATAMOUNT;ITEMID';
		
		// Cart Contents
		$item_loop = 1;
		if (sizeof($order->get_items())>0) : foreach ($order->get_items() as $item) :
			
			$tmp_sku = '';
			
			if ( function_exists( 'get_product' ) ) {
			
				// Version 2.0
				$_product = $order->get_product_from_item($item);
			
				// Get SKU or product id
				if ( $_product->get_sku() ) {
					$tmp_sku = $_product->get_sku();
				} else {
					$tmp_sku = $_product->id;
				}
				
			} else {
			
				// Version 1.6.6
				$_product = new WC_Product( $item['id'] );
			
				// Get SKU or product id
				if ( $_product->get_sku() ) {
					$tmp_sku = $_product->get_sku();
				} else {
					$tmp_sku = $item['id'];
				}
				
			}
		
			if ($_product->exists() && $item['qty']) :
	
				$tmp_product = 'st;' . $item['qty'] . ';' . $item['name'] . ';' . number_format($order->get_item_total( $item, false ), 2, '.', '')*100 . ';' . $order->get_line_tax($item)*100 . ';' . $tmp_sku;
				$args['oiRow'.$item_loop] = $tmp_product;
	
				$item_loop++;

			endif;
		endforeach; endif;

		// Shipping Cost
		if ($order->get_shipping()>0) :
			
			$tmp_shipping = 'st;' . '1' . ';' . __('Shipping cost', 'dibs') . ';' . $order->order_shipping*100 . ';' . $order->order_shipping_tax*100 . ';' . '0';

			$args['oiRow'.$item_loop] = $tmp_shipping;
			
			$item_loop++;

		endif;

		// Discount
		if ($order->get_order_discount()>0) :
			
			$tmp_discount = 'st;' . '1' . ';' . __('Discount', 'dibs') . ';' . -$order->get_order_discount() . ';' . '0' . ';' . '0';

			$args['oiRow'.$item_loop] = $tmp_discount;

		endif;
		
		
		// Invoice fee
		if ( $this->invoice_fee_price > 0 ) {
			//$product = get_product($this->invoice_fee_id);
			
			
			$args['invoiceFee'] = number_format($this->invoice_fee_price_ex_tax, 2, '.', '')*100;
			$args['invoiceFeeVAT'] = round( $this->invoice_fee_tax_percentage, 1)*100;
			//$args['invoiceFeeAmount'] = number_format($this->invoice_fee_price, 2, '.', '')*100;
		}
		
			
		// HMAC
		$formKeyValues = $args;
		require_once('calculateMac.php');
		$logfile = '';
		// Calculate the MAC for the form key-values to be posted to DIBS.
  		$MAC = calculateMac($formKeyValues, $this->key_hmac, $logfile);
  		
  		// Add MAC to the $args array
  		$args['MAC'] = $MAC;
		
		/*	
		echo '<pre>';
		print_r($args);
		echo '</pre>';
		die();
		*/
		// Prepare the form
		$fields = '';
		$tmp_log = '';
		foreach ($args as $key => $value) {
			$fields .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
			
			// Debug preparing
			$tmp_log .= $key . '=' . $value . "\r\n";
		}
		
		// Debug
		if ($this->debug=='yes') :	
        	$this->log->add( 'dibs', 'Sending values to DIBS: ' . $tmp_log );	
        endif;
		
		
		// Print out and send the form
		
		return '<form action="'.$this->paymentwindow_url.'" method="post" id="dibs_invoice_payment_form">
				' . $fields . '
				<input type="submit" class="button-alt" id="submit_dibs_invoice_payment_form" value="'.__('Pay via dibs', 'woothemes').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{ 
								message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to dibs to make payment.', 'woothemes').'", 
								overlayCSS: 
								{ 
									background: "#fff", 
									opacity: 0.6 
								},
								css: { 
							        padding:        20, 
							        textAlign:      "center", 
							        color:          "#555", 
							        border:         "3px solid #aaa", 
							        backgroundColor:"#fff", 
							        cursor:         "wait",
							        lineHeight:		"32px"
							    } 
							});
						jQuery("#submit_dibs_invoice_payment_form").click();
					});
				</script>
			</form>';
		
	}


	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id ) {
		
		$order = new WC_order( $order_id );
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
		);
		
	}

	
	/**
	 * receipt_page
	 **/
	function receipt_page( $order ) {
		
		echo '<p>'.__('Thank you for your order, please click the button below to pay with DIBS.', 'woothemes').'</p>';
		
		echo $this->generate_dibs_form( $order );
		
	}

	

	/**
	* Successful Payment!
	**/
	function successful_request( $posted ) {
		
		
		// Debug
		if ($this->debug=='yes') :
			
			$tmp_log = '';
			
			foreach ( $posted as $key => $value ) {
				$tmp_log .= $key . '=' . $value . "\r\n";
			}
		
        	$this->log->add( 'dibs', 'Returning values from DIBS: ' . $tmp_log );	
        endif;

		
		// Payment Window callback
		if ( isset($posted["transaction"]) && !empty($posted['orderID']) && is_numeric($posted['orderID']) ) {	
				
			$order_id = $posted['orderID'];
			
			$order = new WC_Order( $order_id );
			
			
			// Check order not already completed or processing 
			// (to avoid multiple callbacks from DIBS - IPN & return-to-shop callback
	        if ( $order->status == 'completed' || $order->status == 'processing' ) {
		        
		        if ( $this->debug == 'yes' ) {
		        	$this->log->add( 'dibs', 'Aborting, Order #' . $order_id . ' is already complete.' );
		        }
		        
		        wp_redirect( add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id')))) ); 
		        exit;
		    }
			
			// Verify HMAC
			require_once('calculateMac.php');
			$logfile = '';
  			$MAC = calculateMac($posted, $this->key_hmac, $logfile);
  			
  			// Debug
  			if ($this->debug=='yes') :
  				$this->log->add( 'dibs', 'HMac check...' . json_encode($posted) );
  			endif;
  	
			if($posted['MAC'] != $MAC) {
				//$order->add_order_note( __('HMAC check failed for Dibs callback with order_id: ', 'woocommerce') .$posted['transaction'] );
				$order->update_status('failed', sprintf(__('HMAC check failed for Dibs callback with order_id: %s.', 'woocommerce'), strtolower($posted['transaction']) ) );
				
				// Debug
				if ($this->debug=='yes') :
					$this->log->add( 'dibs', 'Calculated HMac: ' . $MAC );
				endif;
				
				exit;
			}
				
			switch (strtolower($posted['status'])) :
	            case 'accepted' :
	            case 'pending' :
	            
	            	// Order completed
					$order->add_order_note( sprintf(__('DIBS payment completed. DIBS transaction number: %s.', 'woocommerce'), $posted['transaction'] ));
					$order->payment_complete();
					
					
				break;
				
				case 'declined' :
				case 'error' :
				
					// Order failed
	                $order->update_status('failed', sprintf(__('Payment %s via IPN.', 'woocommerce'), strtolower($posted['transaction']) ) );
	                
	            break;
	            
	            default:
	            	// No action
	            break;
	        endswitch;
	        
	        // Return to Thank you page if this is a buyer-return-to-shop callback
			wp_redirect( add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id')))) );
			
			exit;
			
		}
		
	}
	
	
	
	
	
	/**
	* Cancel order
	* We do this since DIBS doesn't like GET parameters in callback and cancel url's
	**/
	
	function cancel_order($posted) {
		
		global $woocommerce;
		
		// Payment Window callback
		if ( isset($posted['orderID']) && is_numeric($posted['orderID']) ) {
		
			// Verify HMAC
			require_once('calculateMac.php');
			$logfile = '';
  			$MAC = calculateMac($posted, $this->key_hmac, $logfile);
  			
  			$order_id = $posted['orderID'];
  			
  			$order = new WC_Order( $order_id );
  			
  			

  			if ($posted['MAC'] == $MAC && $order->id == $order_id && $order->status=='pending') {

				// Cancel the order + restore stock
				$order->cancel_order( __('Order cancelled by customer.', 'dibs') );

				// Message
				$woocommerce->add_error( __('Your order was cancelled.', 'dibs') );

			 } elseif ($order->status!='pending') {

				$woocommerce->add_error( __('Your order is no longer pending and could not be cancelled. Please contact us if you need assistance.', 'dibs') );

			} else {

				$woocommerce->add_error( __('Invalid order.', 'dibs') );

			}

			wp_safe_redirect($woocommerce->cart->get_cart_url());
			exit;
			
		} // End Payment Window
		
	
	} // End function cancel_order()
	
	// Helper function - get Invoice fee id
	function get_dibs_invoice_fee_product() {
		return $this->invoice_fee_id;
	}
	
	// Helper function - get Invoice fee price
	function get_dibs_invoice_fee_price() {
		return $this->invoice_fee_price;
	}
	
	// Helper function - get Invoice fee title
	function get_dibs_invoice_fee_title() {
		return $this->invoice_fee_title;
	}
	
	
} // End class