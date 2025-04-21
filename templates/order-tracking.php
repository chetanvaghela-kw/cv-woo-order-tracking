<?php
/**
 * Order tracking template
 *
 * @package WooOrderTracking
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="woo-tracking-container">
	<form class="woo-tracking-form" method="get">
		<h2><?php esc_html_e( 'Track Your Order', 'woo-order-tracking' ); ?></h2>
		<p><?php esc_html_e( 'Enter your tracking number below to track your order.', 'woo-order-tracking' ); ?></p>
		
		<div class="form-row">
			<label for="tracking_number"><?php esc_html_e( 'Tracking Number', 'woo-order-tracking' ); ?></label>
			<input type="text" name="tracking_number" id="tracking_number" 
				value="<?php echo esc_attr( $tracking_number ); ?>" required>
		</div>
		
		<div class="form-row">
			<label for="email"><?php esc_html_e( 'Email Address (optional)', 'woo-order-tracking' ); ?></label>
			<input type="email" name="email" id="email" value="<?php echo esc_attr( $email ); ?>">
		</div>
		
		<div class="form-row">
			<button type="submit" class="button"><?php esc_html_e( 'Track', 'woo-order-tracking' ); ?></button>
		</div>
	</form>
	
	<?php if ( ! empty( $tracking_number ) ) : ?>
		<div class="woo-tracking-results">
			<?php
			// Look for order with this tracking number (HPOS compatible)
			$orders = wc_get_orders(
				array(
					'meta_key'   => '_tracking_number',
					'meta_value' => $tracking_number,
					'limit'      => 1,
					'return'     => 'ids',
				)
			);

			if ( ! empty( $orders ) ) :
				$order_id = $orders[0];
				$order    = wc_get_order( $order_id );
				?>
				
				<h3><?php esc_html_e( 'Order Information', 'woo-order-tracking' ); ?></h3>
				
				<table class="woo-tracking-table">
					<tr>
						<th><?php esc_html_e( 'Order Number', 'woo-order-tracking' ); ?></th>
						<td><?php echo esc_html( $order->get_order_number() ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tracking Number', 'woo-order-tracking' ); ?></th>
						<td><?php echo esc_html( $tracking_number ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Order Date', 'woo-order-tracking' ); ?></th>
						<td><?php echo esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'woo-order-tracking' ); ?></th>
						<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
					</tr>
				</table>
				
				<h3><?php esc_html_e( 'Order Progress', 'woo-order-tracking' ); ?></h3>
				
				<div class="tracking-progress">
					<?php
					// Create a tracking progress bar based on order status.
					$status   = $order->get_status();
					$statuses = array(
						'pending'    => 1,
						'processing' => 2,
						'shipped'    => 3,
						'completed'  => 4,
					);

					$current_status = isset( $statuses[ $status ] ) ? $statuses[ $status ] : 0;

					// Allow other plugins to modify current status level.
					$current_status = apply_filters( 'woo_order_tracking_status_level', $current_status, $status, $order );
					?>
					
					<div class="progress-bar">
						<div class="progress-step <?php echo $current_status >= 1 ? 'active' : ''; ?>">
							<div class="step-icon">1</div>
							<div class="step-label"><?php esc_html_e( 'Order Received', 'woo-order-tracking' ); ?></div>
						</div>
						<div class="progress-step <?php echo $current_status >= 2 ? 'active' : ''; ?>">
							<div class="step-icon">2</div>
							<div class="step-label"><?php esc_html_e( 'Processing', 'woo-order-tracking' ); ?></div>
						</div>
						<div class="progress-step <?php echo $current_status >= 3 ? 'active' : ''; ?>">
							<div class="step-icon">3</div>
							<div class="step-label"><?php esc_html_e( 'Shipped', 'woo-order-tracking' ); ?></div>
						</div>
						<div class="progress-step <?php echo $current_status >= 4 ? 'active' : ''; ?>">
							<div class="step-icon">4</div>
							<div class="step-label"><?php esc_html_e( 'Delivered', 'woo-order-tracking' ); ?></div>
						</div>
					</div>
				</div>
				
				<?php if ( $order->has_shipping_address() ) : ?>
					<h3><?php esc_html_e( 'Shipping Address', 'woo-order-tracking' ); ?></h3>
					<address>
						<?php echo wp_kses_post( $order->get_formatted_shipping_address() ); ?>
					</address>
				<?php endif; ?>
				
				<h3><?php esc_html_e( 'Order Items', 'woo-order-tracking' ); ?></h3>
				<table class="woo-tracking-items">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'woo-order-tracking' ); ?></th>
							<th><?php esc_html_e( 'Quantity', 'woo-order-tracking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $order->get_items() as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item->get_name() ); ?></td>
								<td><?php echo esc_html( $item->get_quantity() ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				
			<?php else : ?>
				<div class="woo-tracking-not-found">
					<p><?php esc_html_e( 'No order found with the provided tracking number. Please check and try again.', 'woo-order-tracking' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>