<?php
/**
 * Plugin Name: WooCommerce Order Tracking
 * Plugin URI: https://yourwebsite.com/woo-order-tracking
 * Description: Generates tracking numbers for orders and provides tracking functionality.
 * Version: 1.0.0
 * Author: Chetan Vaghela
 * Author URI: https://yourwebsite.com
 * Text Domain: woo-order-tracking
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 *
 * @package WooOrderTracking
 *
 * Woo: 12345:342928dfsfsd08923489
 * WC requires at least: 7.0.0
 * WC tested up to: 8.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WooCommerce is active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

// Define plugin constants.
define( 'WOO_ORDER_TRACKING_VERSION', '1.0.0' );
define( 'WOO_ORDER_TRACKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_ORDER_TRACKING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class WOO_Order_Tracking {
	/**
	 * The single instance of the class
	 *
	 * @var WOO_Order_Tracking
	 */
	protected static $instance = null;

	/**
	 * Webhook URL for external site
	 *
	 * @var WOO_Order_Tracking
	 */
	private $webhook_url = '';

	/**
	 * Webhook API KEY for external site
	 *
	 * @var WOO_Order_Tracking
	 */
	private $webhook_api_key = '';

	/**
	 * Main instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {

		// Declare HPOS compatibility.
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			}
		);

		$this->init_hooks();
		$this->includes();

		// Load settings.
		$this->webhook_url     = get_option( 'woo_order_tracking_webhook_url', '' );
		$this->webhook_api_key = get_option( 'woo_order_tracking_webhook_api_key', '' );
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Hook into WooCommerce order creation.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'wot_generate_tracking_number_classic' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'wot_generate_tracking_number' ) );

		// Hook into WooCommerce order status changes.
		add_action( 'woocommerce_order_status_changed', array( $this, 'wot_order_status_changed' ), 10, 4 );

		// Add admin settings.
		add_action( 'admin_menu', array( $this, 'wot_add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'wot_register_settings' ) );

		add_action( 'init', array( $this, 'wot_register_post_statuses' ) );
		add_action( 'wc_order_statuses', array( $this, 'wot_wc_order_statuses_callback' ) );

		// Add tracking number to order emails.
		add_action( 'woocommerce_email_after_order_table', array( $this, 'wot_add_tracking_to_emails' ), 10, 4 );

		// Add shortcode for tracking page.
		add_shortcode( 'woo_order_tracking', array( $this, 'wot_tracking_shortcode' ) );

		// Add tracking meta box to order admin page.
		add_action( 'add_meta_boxes', array( $this, 'wot_add_tracking_meta_box' ) );

		// Save custom field from admin.
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'wot_save_tracking_meta_box' ), 10, 2 );

		// Register custom order data.
		add_action( 'woocommerce_init', array( $this, 'wot_register_order_tracking_custom_order_field' ) );
	}

	/**
	 * Register custom order field for HPOS compatibility
	 */
	public function wot_register_order_tracking_custom_order_field() {
		if ( class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			// Register our custom field with HPOS.
			add_filter(
				'woocommerce_order_data_store_cpt_get_orders_query',
				function ( $query, $query_vars ) {
					if ( ! empty( $query_vars['_tracking_number'] ) ) {
						$query['meta_query'][] = array(
							'key'   => '_tracking_number',
							'value' => esc_attr( $query_vars['_tracking_number'] ),
						);
					}
					return $query;
				},
				10,
				2
			);
		}
	}

	/**
	 * Include required files
	 */
	private function includes() {
		// Include template functions.
		require_once WOO_ORDER_TRACKING_PATH . 'includes/templates.php';

		// Include admin functions if in admin.
		if ( is_admin() ) {
			require_once WOO_ORDER_TRACKING_PATH . 'includes/admin-functions.php';
		}
	}

	/**
	 * Generate tracking number for new order
	 *
	 * @param int      $order_id The order id.
	 * @param array    $posted_data The order data.
	 * @param WC_Order $order The order object.
	 */
	public function wot_generate_tracking_number_classic( $order_id, $posted_data, $order ) {

		// Generate unique tracking number.
		$tracking_number = $this->create_tracking_number( $order_id );

		// Store tracking number with the order (HPOS compatible).
		$order->update_meta_data( '_tracking_number', $tracking_number );
		$order->save();

		// Send tracking info to webhook.
		$this->wot_send_to_webhook( $order_id, $tracking_number );

		return $tracking_number;
	}

	/**
	 * Generate tracking number for new order
	 *
	 * @param WC_Order $order The order object.
	 */
	public function wot_generate_tracking_number( $order ) {

		$order_id = $order->get_id();
		// Generate unique tracking number.
		$tracking_number = $this->create_tracking_number( $order_id );

		// Store tracking number with the order (HPOS compatible).
		$order->update_meta_data( '_tracking_number', $tracking_number );
		$order->save();

		// Send tracking info to webhook.
		$this->wot_send_to_webhook( $order_id, $tracking_number );

		return $tracking_number;
	}

	/**
	 * Create a unique tracking number
	 *
	 * @param int $order_id The order ID.
	 */
	private function create_tracking_number( $order_id ) {
		// Create a tracking number based on order ID and timestamp.
		$prefix          = apply_filters( 'woo_order_tracking_prefix', 'TRK' );
		$tracking_number = $prefix . '-' . $order_id . '-' . time();

		return apply_filters( 'woo_order_tracking_number', $tracking_number, $order_id );
	}

	/**
	 * Send order tracking info to webhook
	 *
	 * @param int    $order_id The order ID.
	 * @param string $tracking_number The tracking number.
	 */
	public function wot_send_to_webhook( $order_id, $tracking_number = null ) {
		if ( empty( $this->webhook_url ) && empty( $this->webhook_api_key ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Get tracking number if not provided.
		if ( is_null( $tracking_number ) ) {
			$tracking_number = $order->get_meta( '_tracking_number', true );
		}

		// Prepare data for webhook.
		$data = array(
			'order_id'        => $order_id,
			'tracking_number' => $tracking_number,
			'status'          => $order->get_status(),
			'customer_email'  => $order->get_billing_email(),
			'order_total'     => $order->get_total(),
			'currency'        => $order->get_currency(),
			'date_created'    => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
			'items'           => array(),
		);

		// Add order items.
		foreach ( $order->get_items() as $item_id => $item ) {
			$product         = $item->get_product();
			$data['items'][] = array(
				'product_id' => $item->get_product_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'total'      => $item->get_total(),
			);
		}

		// Send data to webhook.
		$response = wp_remote_post(
			$this->webhook_url,
			array(
				'body'    => wp_json_encode( array_merge( $data, array( 'api_key' => $this->webhook_api_key ) ) ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $this->webhook_api_key,
				),
				'timeout' => 30,
			)
		);
		// Log response.
		if ( is_wp_error( $response ) ) {
			error_log( 'WooCommerce Order Tracking webhook error: ' . $response->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Handle order status changes
	 *
	 * @param int      $order_id The order ID.
	 * @param string   $status_from The previous order status.
	 * @param string   $status_to The new order status.
	 * @param WC_Order $order The order object.
	 */
	public function wot_order_status_changed( $order_id, $status_from, $status_to, $order ) {
		// Send updated status to webhook.
		$this->wot_send_to_webhook( $order_id );
	}

	/**
	 * Add settings page to admin menu
	 */
	public function wot_add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Order Tracking Settings', 'woo-order-tracking' ),
			__( 'Order Tracking', 'woo-order-tracking' ),
			'manage_woocommerce',
			'woo-order-tracking',
			array( $this, 'wot_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function wot_register_settings() {
		register_setting( 'woo_order_tracking_options', 'woo_order_tracking_webhook_url' );
		register_setting( 'woo_order_tracking_options', 'woo_order_tracking_webhook_api_key' );
		register_setting( 'woo_order_tracking_options', 'woo_order_tracking_template' );
	}


	/**
	 * Register woocommerce status
	 */
	public function wot_register_post_statuses() {
		register_post_status(
			'wc-shipped',
			array(
				'label'                     => _x( 'Shipped', 'Order status', 'woo-order-tracking' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>', 'woo-order-tracking' ),
			)
		);
	}

	/**
	 * Add woocommerce status
	 */
	public function wot_wc_order_statuses_callback( $order_statuses ) {
		$new_order_statuses = array();

		foreach ( $order_statuses as $key => $label ) {
			$new_order_statuses[ $key ] = $label;

			if ( 'wc-processing' === $key ) {
				$new_order_statuses['wc-shipped'] = _x( 'Shipped', 'Order status', 'woo-order-tracking' );
			}
		}

		return $new_order_statuses;
	}

	/**
	 * Settings page content
	 */
	public function wot_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'WooCommerce Order Tracking Settings', 'woo-order-tracking' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'woo_order_tracking_options' ); ?>
				<?php do_settings_sections( 'woo_order_tracking_options' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php echo esc_html__( 'Webhook URL', 'woo-order-tracking' ); ?></th>
						<td>
							<input type="text" name="woo_order_tracking_webhook_url" class="regular-text"
								value="<?php echo esc_attr( get_option( 'woo_order_tracking_webhook_url' ) ); ?>" />
							<p class="description">
								<?php echo esc_html__( 'Enter the URL where order tracking data should be sent.', 'woo-order-tracking' ); ?>
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php echo esc_html__( 'Webhook API KEY', 'woo-order-tracking' ); ?></th>
						<td>
							<input type="password" name="woo_order_tracking_webhook_api_key" class="regular-text"
								value="<?php echo esc_attr( get_option( 'woo_order_tracking_webhook_api_key' ) ); ?>" />
							<p class="description">
								<?php echo esc_html__( 'Enter the API key.', 'woo-order-tracking' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add tracking number to order emails
	 *
	 * @param WC_Order $order The order object.
	 * @param bool     $sent_to_admin Whether the email was sent to the admin.
	 * @param bool     $plain_text Whether the email was sent as plain text.
	 * @param WC_Email $email The email object.
	 */
	public function wot_add_tracking_to_emails( $order, $sent_to_admin, $plain_text, $email = null ) {
		$tracking_number = $order->get_meta( '_tracking_number', true );

		if ( ! empty( $tracking_number ) ) {
			if ( $plain_text ) {
				echo "\n\n" . __( 'Tracking Number:', 'woo-order-tracking' ) . ' ' . $tracking_number . "\n";
			} else {
				echo '<h2>' . __( 'Tracking Information', 'woo-order-tracking' ) . '</h2>';
				echo '<p><strong>' . __( 'Tracking Number:', 'woo-order-tracking' ) . '</strong> ' . $tracking_number . '</p>';
				echo '<p><a href="' . esc_url( home_url( '/order-tracking/?tracking_number=' . $tracking_number ) ) . '">'
					. __( 'Track Your Order', 'woo-order-tracking' ) . '</a></p>';
			}
		}
	}

	/**
	 * Shortcode for order tracking page
	 */
	public function wot_tracking_shortcode( $atts ) {
		// Enqueue styles.
		wp_enqueue_style( 'woo-order-tracking', WOO_ORDER_TRACKING_URL . 'assets/css/tracking.css', array(), WOO_ORDER_TRACKING_VERSION );

		// Get tracking number from URL or form submission.
		$tracking_number = isset( $_GET['tracking_number'] ) ? sanitize_text_field( $_GET['tracking_number'] ) : '';
		$email           = isset( $_GET['email'] ) ? sanitize_email( $_GET['email'] ) : '';

		// Start output buffering.
		ob_start();

		// Include tracking template.
		include WOO_ORDER_TRACKING_PATH . 'templates/order-tracking.php';

		return ob_get_clean();
	}

	/**
	 * Add tracking meta box to order admin page
	 */
	public function wot_add_tracking_meta_box() {

		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		add_meta_box(
			'woo_order_tracking_meta_box',
			__( 'Order Tracking', 'woo-order-tracking' ),
			array( $this, 'render_tracking_meta_box' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * Render tracking meta box content
	 */
	public function render_tracking_meta_box( $post_or_order_object ) {
		// Get order object regardless of HPOS or CPT
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! $order ) {
			return;
		}

		// Add nonce for security
		wp_nonce_field( 'woo_order_tracking_meta_box', 'woo_order_tracking_meta_box_nonce' );

		// Get tracking number
		$tracking_number = $order->get_meta( '_tracking_number', true );

		?>
		<p>
			<label for="tracking_number"><?php echo esc_html__( 'Tracking Number:', 'woo-order-tracking' ); ?></label>
			<input type="text" id="tracking_number" name="tracking_number" value="<?php echo esc_attr( $tracking_number ); ?>" class="widefat" />
		</p>
		<p>
			<button type="button" class="button" id="generate_tracking_number"><?php echo esc_html__( 'Generate Tracking Number', 'woo-order-tracking' ); ?></button>
		</p>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#generate_tracking_number').on('click', function() {
					var trackingNumber = 'TRK-<?php echo $order->get_id(); ?>-' + Math.floor(Date.now() / 1000);
					$('#tracking_number').val(trackingNumber);
				});
			});
		</script>
		<?php
	}

	/**
	 * Save tracking meta box data
	 */
	public function wot_save_tracking_meta_box( $order_id, $order ) {
		// Check if our nonce is set and verify it.
		if ( ! isset( $_POST['woo_order_tracking_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['woo_order_tracking_meta_box_nonce'], 'woo_order_tracking_meta_box' ) ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			return;
		}

		// Save tracking number.
		if ( isset( $_POST['tracking_number'] ) ) {
			$old_tracking_number = $order->get_meta( '_tracking_number', true );
			$new_tracking_number = sanitize_text_field( $_POST['tracking_number'] );

			$order->update_meta_data( '_tracking_number', $new_tracking_number );
			$order->save();

			// If tracking number changed, send update to webhook.
			if ( $old_tracking_number !== $new_tracking_number ) {
				$this->wot_send_to_webhook( $order_id, $new_tracking_number );
			}
		}
	}
}

// Initialize the plugin.
function woo_order_tracking() {
	return WOO_Order_Tracking::instance();
}

// Start the plugin.
woo_order_tracking();

// Create required files and folders on activation.
register_activation_hook( __FILE__, 'woo_order_tracking_activate' );
function woo_order_tracking_activate() {
	// Create necessary directories.
}