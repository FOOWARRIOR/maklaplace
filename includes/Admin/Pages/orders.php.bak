<?php
/**
 * Orders page implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from container
$orderService = $this->container->get( \MaklaPlace\Core\OrderService::class );
$orders = $orderService->get_orders();
?>
<div class='wrap'><h1><?php esc_html_e( 'MaklaPlace Orders', 'maklaplace' ); ?></h1>

    <?php if ( empty( $orders ) ) : ?><p><?php esc_html_e( 'No orders found.', 'maklaplace' ); ?></p>
    <?php else: ?><table class='widefat'><thead><tr><th>#</th><th><?php esc_html_e( 'Customer', 'maklaplace' ); ?></th><th><?php esc_html_e( 'Total', 'maklaplace' ); ?></th><th><?php esc_html_e( 'Status', 'maklaplace' ); ?></th><th><?php esc_html_e( 'Date', 'maklaplace' ); ?></th></tr></thead><tbody>
                <?php foreach( $orders as $orderId => $order ) : ?><tr>
                        <td><?php echo esc_html( $orderId ); ?></td><td><?php echo esc_html( $order[ 'customer_name' ] ?? __( 'Unknown', 'maklaplace' ) ); ?></td><td><?php echo esc_html( sprintf( __( '%d DA', 'maklaplace' ), number_format( $order[ 'total_amount' ] ?? 0 ) ) ); ?></td><td>
                            <?php
                            $status = $order[ 'status' ] ?? 'pending';
                            $statusLabels = array(
                                'pending' => __( 'Pending', 'maklaplace' ),
                                'accepted' => __( 'Accepted', 'maklaplace' ),
                                'preparing' => __( 'Preparing', 'maklaplace' ),
                                'ready' => __( 'Ready', 'maklaplace' ),
                                'on_the_way' => __( 'On the Way', 'maklaplace' ),
                                'completed' => __( 'Completed', 'maklaplace' ),
                                'cancelled' => __( 'Cancelled', 'maklaplace' )
                            );
                            echo esc_html( $statusLabels[ $status ] ?? $status );
                            ?></td><td><?php echo esc_html( mysql2date( __( 'M j, Y g:i A', 'maklaplace' ), $order[ 'created_at' ] ?? '0000-00-00 00:00:00' ) ); ?></td></tr>
                <?php endforeach; ?></tbody></table>
    <?php endif; ?></div>
