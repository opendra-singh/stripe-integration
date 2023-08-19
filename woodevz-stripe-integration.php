<?php
/**
 * Plugin Name: WooDevz Stripe Integration
 * Description: Integrates Stripe payment gateway into WooCommerce with admin approval and payment capture validation.
 * Version: 1.0.0
 * Author: Shashwat
 * Author URI: https://woodevz.com
 */

use Stripe\Apps\Secret;

 require_once plugin_dir_path(__FILE__) . 'vendor/stripe/stripe-php/init.php';

 define("Publishable_Key", get_option('swi_stripe_publishable_key'));
 define("Secret_Key", get_option('swi_stripe_secret_key'));

 add_action('init', function() {
	session_start();
 });
 add_action("admin_menu", function() {
	add_menu_page("Stripe Errors", "Stripe Errors", "manage_options", "woodevz-stripe-errors", "woodevz_stripe_errors");
 });
 add_action('woocommerce_admin_order_data_after_billing_address', 'woodevz_stripe_add_custom_order_checkbox');
 add_action('woocommerce_process_shop_order_meta', 'woodevz_stripe_save_custom_order_checkbox');
 add_action("woocommerce_review_order_before_payment", "woodevz_stripe_custom_content_before_payment_methods");
 add_action("wp_enqueue_scripts", "enqueue_script");
 add_action("woocommerce_thankyou", "woodevz_stripe_woocommerce_thankyou");
 add_action("admin_enqueue_scripts", "woodevz_stripe_admin_enqueue_script");
 add_action('plugins_loaded', 'woodevz_stripe__include_woocommerce_payment_gateway');
 add_filter('woocommerce_payment_gateways', 'woodevz_stripe_add_stripe_gateway');

 function woodevz_stripe_admin_enqueue_script($page_id) {
	 wp_register_style( 'datatable', "//cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css");
	 wp_register_style( 'bootstrap', "//cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css");
	 wp_register_script("datatable", "//cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js");
	 wp_register_script("woodevz-admin-stripe", plugin_dir_url(__FILE__) . 'admin.js');
	if ("toplevel_page_woodevz-stripe-errors" == $page_id) {
		wp_enqueue_style( 'bootstrap' );
		wp_enqueue_style( 'datatable' );
		wp_enqueue_script("datatable");
		wp_enqueue_script("woodevz-admin-stripe");		
	}
 }
 
 function enqueue_script() {
	wp_enqueue_script("stripe", "https://js.stripe.com/v3/");
	wp_enqueue_script("woodevz-stripe", plugin_dir_url(__FILE__) . 'script.js');
	wp_localize_script('woodevz-stripe', 'swi_params', array(
        'secret_key' => Secret_Key,
		'ajax_url' => admin_url('admin-ajax.php'),
        'publishable_key' => Publishable_Key,
    ));
 }

 function woodevz_stripe_errors() {
    $orders = wc_get_orders( ['meta_key'   => 'woodevz_stripe_error_message',] );
	?>

	<nav class="navbar navbar-expand-lg bg-body-tertiary mb-5">
		<div class="container-fluid">
			<a class="navbar-brand" href="#">Stripe Errors</a>
		</div>
	</nav>


	<div class="container">
		<table id="woodevz-stripe-errors">
			<thead>
				<tr>
					<td>#</td>
					<td>Order ID</td>
					<td>Message</td>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 1;
				foreach ($orders as $single) {
					?>
					<tr>
						<td><?= $i ?></td>
						<td><?= $single->get_id() ?></td>
						<td><?= get_post_meta($single->get_id(), "woodevz_stripe_error_message", true) ?></td>
					</tr>
					<?php
					$i++;
				}
				?>
			</tbody>
		</table>
	</div>
	<?php
 }

 function woodevz_stripe_save_custom_order_checkbox($order_id) {
	 if (isset($_POST['woodevz_stripe_admin_approval'])) {
		$custom_checkbox = 'yes';
		$details = json_decode(get_post_meta($order_id, "woodevz-stripe-intent-details", true), true);
		try {
			\Stripe\Stripe::setApiKey(Secret_Key);
			// Call the Stripe API method by passing the data array as the first argument
			$paymentIntent = \Stripe\PaymentIntent::create($details);

			// Access the PaymentIntent object properties if needed
			$paymentIntentID = $paymentIntent->id;
			$client_secret = $paymentIntent->client_secret;
			update_post_meta($order_id, "woodevz-stripe-intent-details", wp_json_encode([
				"paymentIntentID" => $paymentIntentID,
				"client_secret" => $client_secret,
			]));

			$paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentID);

			// Check if there was a last payment error
			if ($paymentIntent->last_payment_error) {
				$error = $paymentIntent->last_payment_error;
				update_post_meta($order_id, 'woodevz_stripe_error_message', "Payment Error: " . $error['message']);
			}

		} catch (\Stripe\Exception\ApiErrorException $e) {
			// Handle API errors
			update_post_meta($order_id, 'woodevz_stripe_error_message', $e->getMessage());
		}
	}else{
		$custom_checkbox = 'no';
	}
    update_post_meta($order_id, 'woodevz_stripe_admin_approval', $custom_checkbox);
 }

 function woodevz_stripe_add_custom_order_checkbox($order) {
	 $meta = get_post_meta($order->get_id(), 'woodevz-stripe-intent-details', true);
	 $value = get_post_meta($order->get_id(), 'woodevz_stripe_admin_approval', true);
	if (isset($meta) && !empty($meta)) {
		echo '<div class="order_custom_checkbox">';
		woocommerce_wp_checkbox(array(
			'id' => 'woodevz_stripe_admin_approval',
			'label' => __('Approve Order', 'woocommerce'),
			'desc_tip' => 'true',
			'description' => __('Check this box if you want to approve this order.', 'woocommerce'),
			'value' => $value
		));
		echo '</div>';		
	}
 }

 function woodevz_stripe_custom_content_before_payment_methods() {
	$user_id = get_current_user_id();
	?><div style="margin: 30px 0; padding: 30px 10px;" id="woodevz-stripe-payment-form"><?php
	try {
		$user = get_userdata($user_id);
		$name = $user->display_name;
		$user_email = $user->user_email;
		$_SESSION['woodevz-stripe-customer-id'] = get_user_meta($user_id, "woodevz-stripe-customer-id", true);
		if (empty($_SESSION['woodevz-stripe-customer-id'])) {
			\Stripe\Stripe::setApiKey(Secret_Key);
			$customer = \Stripe\Customer::create([
				'name' => $name,
				'email' => $user_email,
			]);
			$_SESSION['woodevz-stripe-customer-id'] = $customer->id;
			if (isset($customer['error'])) {
				update_user_meta($user_id, "woodevz_stripe_error_message", $customer['error']['message']);
			}
		}
		update_user_meta($user_id, "woodevz-stripe-customer-id", $_SESSION['woodevz-stripe-customer-id']);
		$stripe = new \Stripe\StripeClient(Secret_Key);
		$SetupIntent = $stripe->setupIntents->create([
			'customer' => $_SESSION['woodevz-stripe-customer-id'],
			'automatic_payment_methods' => ['enabled' => true],
		]);		
		?>
		<input type="hidden" id="woodevz-stripe-client-secret" value="<?= $SetupIntent->client_secret ?>">
		<input type="hidden" id="woodevz_stripe_payment_method_id" name="woodevz_stripe_payment_method_id">
		<!-- Elements to collect card information -->
		<div id="woodevz-stripe-payment-element"></div>
		<?php
	} catch (Exception $e) {
		update_user_meta($user_id, "woodevz_stripe_error_message", $e->getMessage());
		?>
		<b><span><?= $e->getMessage() ?></span></b>
		<?php
	}
	?></div><?php
 }

 function woodevz_stripe_add_stripe_gateway($gateways) {
    $gateways[] = 'SWI_Stripe_Gateway'; // Custom class name for the Stripe gateway
    return $gateways;
 }

 function woodevz_stripe_woocommerce_thankyou($order_id) {
	$order = wc_get_order($order_id);
	$customer_id = $_SESSION['woodevz-stripe-customer-id'];
	$payment_method_id = $_COOKIE['woodevz_stripe_payment_method_id'];
	$currency = $order->get_currency();
	$amount = $order->get_total();
	update_post_meta($order_id, "woodevz-stripe-intent-details", wp_json_encode([
		'amount' => $amount * 100,
		'currency' => strtolower($currency),
		'customer' => $customer_id,
		'payment_method' => $payment_method_id,
		'automatic_payment_methods' => ['enabled' => true],
		'off_session' => true,
		'confirm' => true,
	]));
	$products = [];
	// Get and Loop Over Order Items
	foreach ( $order->get_items() as $item_id => $item ) {
		$product_id = $item->get_product_id();
		$products[$product_id] = "pending";
	}
	update_post_meta($order_id, "woodevz-stripe-intent-details", wp_json_encode($products));
 }

 function woodevz_stripe__include_woocommerce_payment_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Stripe Gateway Class
    class SWI_Stripe_Gateway extends WC_Payment_Gateway {

        private $test_mode;
        private $test_publishable_key;
        private $test_secret_key;
        private $live_publishable_key;
        private $live_secret_key;

        public function __construct() {
            $this->id = 'woodevz_stripe';
            $this->method_title = __('Stripe', 'woocommerce');
            $this->method_description = __('Accept payments through Stripe', 'woocommerce');
            $this->has_fields = false;
            $this->supports = array(
                'products',
                'refunds',
            );
    
            $this->init_form_fields();
            $this->init_settings();
    
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->test_mode = $this->get_option('test_mode');
            $this->test_publishable_key = $this->get_option('test_publishable_key');
            $this->test_secret_key = $this->get_option('test_secret_key');
            $this->live_publishable_key = $this->get_option('live_publishable_key');
            $this->live_secret_key = $this->get_option('live_secret_key');
    
            // Save settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    
            // Add the payment form to the checkout page
            add_action('woocommerce_after_checkout_form', array($this, 'payment_fields'));
        }
    
        // Initialize gateway settings fields
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Stripe Payment Gateway', 'woocommerce'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Credit Card (Stripe)', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Pay securely using your credit card via Stripe.', 'woocommerce'),
                ),
                'test_mode' => array(
                    'title' => __('Test Mode', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Test Mode', 'woocommerce'),
                    'default' => 'yes',
                ),
                'test_publishable_key' => array(
                    'title' => __('Test Publishable Key', 'woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'default' => get_option('swi_stripe_publishable_key'), // Populate with the saved value
                ),
                'test_secret_key' => array(
                    'title' => __('Test Secret Key', 'woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'default' => get_option('swi_stripe_secret_key'), // Populate with the saved value
                ),
                'live_publishable_key' => array(
                    'title' => __('Live Publishable Key', 'woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'default' => get_option('swi_stripe_publishable_key'), // Populate with the saved value
                ),
                'live_secret_key' => array(
                    'title' => __('Live Secret Key', 'woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'default' => get_option('swi_stripe_secret_key'), // Populate with the saved value
                ),
            );

            // Additional code to populate other settings fields with the saved values
            foreach ($this->form_fields as $field_key => $field) {
                if (strpos($field_key, 'test_') === 0 || strpos($field_key, 'live_') === 0) {
                    $this->form_fields[$field_key]['default'] = get_option('swi_stripe_' . $field_key);
                }
            }
        }
    
        // Output the payment form on the checkout page
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
        }

        // Process payment and return the result
        public function process_payment($order_id) {
			$order = wc_get_order($order_id);
			$order->payment_complete();
			$order->update_status('pending-payment', __('Payment successful', 'woocommerce'));
			WC()->cart->empty_cart();

			// Return a success response
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			);
        }

        // Save gateway settings when the user clicks on "Save changes" in the WooCommerce admin dashboard
        public function process_admin_options() {
            parent::process_admin_options();

            // Additional code to save the test and live keys
            $test_mode = $this->get_option('test_mode');
            if ($test_mode === 'yes') {
                update_option('swi_stripe_publishable_key', $this->get_option('test_publishable_key'));
                update_option('swi_stripe_secret_key', $this->get_option('test_secret_key'));
            } else {
                update_option('swi_stripe_publishable_key', $this->get_option('live_publishable_key'));
                update_option('swi_stripe_secret_key', $this->get_option('live_secret_key'));
            }
            
            // Additional code to save other settings fields
            foreach ($this->form_fields as $field_key => $field) {
                if (strpos($field_key, 'test_') === 0 || strpos($field_key, 'live_') === 0) {
                    update_option('swi_stripe_' . $field_key, $this->get_option($field_key));
                }
            }
        }
    }
 }
