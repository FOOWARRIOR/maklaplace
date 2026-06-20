<?php
/**
 * Analytics service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\AnalyticsKeys;
use MaklaPlace\Helpers\OrderKeys;
use MaklaPlace\Helpers\WalletHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Collects and aggregates marketplace analytics.
 */
final class AnalyticsService {

	/**
	 * Record an order-related event.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function record_order_event( array $payload ) : void {
		$this->append_event(
			array(
				'type'       => 'order',
				'event'      => sanitize_text_field( (string) ( $payload['event'] ?? 'order' ) ),
				'order_id'   => absint( $payload['order_id'] ?? 0 ),
				'chef_user_id' => absint( $payload['chef_user_id'] ?? 0 ),
				'total'      => isset( $payload['total'] ) ? (float) $payload['total'] : 0.0,
				'status'     => sanitize_text_field( (string) ( $payload['status'] ?? '' ) ),
				'created_at' => current_time( 'mysql' ),
			)
		);

		$this->invalidate_cache();
	}

	/**
	 * Record a commission-related event.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function record_commission_event( array $payload ) : void {
		$this->append_event(
			array(
				'type'         => 'commission',
				'event'        => sanitize_text_field( (string) ( $payload['event'] ?? 'commission_added' ) ),
				'order_id'     => absint( $payload['order_id'] ?? 0 ),
				'chef_user_id' => absint( $payload['chef_user_id'] ?? 0 ),
				'commission'   => isset( $payload['commission'] ) ? (float) $payload['commission'] : 0.0,
				'balance'      => isset( $payload['balance'] ) ? (float) $payload['balance'] : 0.0,
				'created_at'   => current_time( 'mysql' ),
			)
		);

		$this->invalidate_cache();
	}

	/**
	 * Get analytics for a chef.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return array<string, mixed>
	 */
	public function get_chef_stats( int $chef_user_id ) : array {
		$cache = $this->get_cache();
		if ( isset( $cache['chefs'][ $chef_user_id ] ) ) {
			return $this->build_chef_stats( $chef_user_id, $cache['chefs'][ $chef_user_id ], $cache['orders'] ?? array() );
		}

		return $this->build_chef_stats( $chef_user_id, array(), $this->get_orders() );
	}

	/**
	 * Get platform-wide analytics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_platform_stats() : array {
		$cache = $this->get_cache();
		if ( ! empty( $cache['platform'] ) ) {
			return $this->build_platform_stats( $cache['platform'], $cache['orders'] ?? array() );
		}

		return $this->build_platform_stats( array(), $this->get_orders() );
	}

	/**
	 * Calculate time-based metrics.
	 *
	 * @param string $period daily|weekly|monthly.
	 * @param int    $chef_user_id Optional chef ID filter.
	 * @return array<string, mixed>
	 */
	public function calculate_time_based_metrics( string $period = 'monthly', int $chef_user_id = 0 ) : array {
		$period = in_array( $period, array( 'daily', 'weekly', 'monthly' ), true ) ? $period : 'monthly';
		$orders = $this->get_orders();
		$groups = array();

		foreach ( $orders as $order ) {
			$order_chef = absint( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 );
			if ( $chef_user_id > 0 && $chef_user_id !== $order_chef ) {
				continue;
			}

			$created = (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' );
			if ( '' === $created ) {
				continue;
			}

			$key = $this->group_key( $created, $period );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'orders'     => 0,
					'revenue'    => 0.0,
					'completed'  => 0,
					'cancelled'  => 0,
					'commission' => 0.0,
				);
			}

			$groups[ $key ]['orders']++;
			$groups[ $key ]['revenue'] += (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 );
			$status = (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' );
			if ( 'completed' === $status ) {
				$groups[ $key ]['completed']++;
			} elseif ( 'cancelled' === $status ) {
				$groups[ $key ]['cancelled']++;
			}
			$groups[ $key ]['commission'] += $this->commission_for_order( $order );
		}

		ksort( $groups );

		return array(
			'period' => $period,
			'items'  => $groups,
		);
	}

	/**
	 * Build a chef stat payload.
	 *
	 * @param int                  $chef_user_id Chef user ID.
	 * @param array<string, mixed> $cached Cached aggregates.
	 * @param array<int, array<string, mixed>> $orders Orders.
	 * @return array<string, mixed>
	 */
	private function build_chef_stats( int $chef_user_id, array $cached, array $orders ) : array {
		$chef_orders = array_values(
			array_filter(
				$orders,
				static fn( array $order ) : bool => absint( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 ) === $chef_user_id
			)
		);

		$total_orders = count( $chef_orders );
		$completed = 0;
		$cancelled = 0;
		$revenue = 0.0;
		$commission = 0.0;
		$items = array();
		$monthly = array();

		foreach ( $chef_orders as $order ) {
			$amount = (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 );
			$revenue += $amount;
			$commission += $this->commission_for_order( $order );
			$status = (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' );
			if ( 'completed' === $status ) {
				$completed++;
			} elseif ( 'cancelled' === $status ) {
				$cancelled++;
			}

			foreach ( (array) ( $order[ OrderKeys::ITEMS ] ?? array() ) as $item ) {
				$name = sanitize_text_field( (string) ( $item['item_name'] ?? '' ) );
				if ( '' === $name ) {
					continue;
				}
				$items[ $name ] = ( $items[ $name ] ?? 0 ) + absint( $item['quantity'] ?? 1 );
			}

			$month = gmdate( 'Y-m', strtotime( (string) ( $order[ OrderKeys::CREATED_AT ] ?? 'now' ) ) );
			$monthly[ $month ]['orders'] = ( $monthly[ $month ]['orders'] ?? 0 ) + 1;
			$monthly[ $month ]['revenue'] = ( $monthly[ $month ]['revenue'] ?? 0.0 ) + $amount;
		}

		arsort( $items );

		return array(
			'chef_user_id'            => $chef_user_id,
			'total_orders'            => $total_orders,
			'completed_orders'        => $completed,
			'cancelled_orders'        => $cancelled,
			'total_revenue'           => round( $revenue, 2 ),
			'total_commission_generated' => round( $commission, 2 ),
			'average_order_value'     => 0 < $total_orders ? round( $revenue / $total_orders, 2 ) : 0.0,
			'most_popular_menu_items'  => array_slice( $items, 0, 5, true ),
			'monthly_performance'      => $monthly,
			'last_updated'             => $cached['last_updated'] ?? current_time( 'mysql' ),
		);
	}

	/**
	 * Build a platform stat payload.
	 *
	 * @param array<string, mixed> $cached Cached aggregates.
	 * @param array<int, array<string, mixed>> $orders Orders.
	 * @return array<string, mixed>
	 */
	private function build_platform_stats( array $cached, array $orders ) : array {
		$total_orders = count( $orders );
		$completed = 0;
		$cancelled = 0;
		$revenue = 0.0;
		$commission = 0.0;
		$chef_activity = array();
		$peak_periods = array();
		$active_chefs = array();

		foreach ( $orders as $order ) {
			$chef_id = absint( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 );
			if ( $chef_id > 0 ) {
				$active_chefs[ $chef_id ] = true;
				$chef_activity[ $chef_id ] = ( $chef_activity[ $chef_id ] ?? 0 ) + 1;
			}

			$amount = (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 );
			$revenue += $amount;
			$status = (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' );
			if ( 'completed' === $status ) {
				$completed++;
			} elseif ( 'cancelled' === $status ) {
				$cancelled++;
			}

			$commission += $this->commission_for_order( $order );
			$period = gmdate( 'Y-m', strtotime( (string) ( $order[ OrderKeys::CREATED_AT ] ?? 'now' ) ) );
			$peak_periods[ $period ] = ( $peak_periods[ $period ] ?? 0 ) + 1;
		}

		arsort( $chef_activity );
		arsort( $peak_periods );

		return array(
			'total_orders'            => $total_orders,
			'total_active_chefs'      => count( $active_chefs ),
			'total_revenue_volume'    => round( $revenue, 2 ),
			'total_commissions_generated' => round( $commission, 2 ),
			'order_completion_rate'   => 0 < $total_orders ? round( ( $completed / $total_orders ) * 100, 2 ) : 0.0,
			'most_active_chefs'       => array_slice( $chef_activity, 0, 5, true ),
			'peak_ordering_periods'   => array_slice( $peak_periods, 0, 5, true ),
			'cancelled_orders'        => $cancelled,
			'last_updated'            => $cached['last_updated'] ?? current_time( 'mysql' ),
		);
	}

	/**
	 * Compute commission for an order.
	 *
	 * @param array<string, mixed> $order Order record.
	 * @return float
	 */
	private function commission_for_order( array $order ) : float {
		$processed = ! empty( $order['maklaplace_commission_processed'] ) || ! empty( $order['_maklaplace_commission_processed'] );
		if ( ! $processed || 'completed' !== (string) ( $order[ OrderKeys::STATUS ] ?? '' ) ) {
			return 0.0;
		}

		return round( (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ) * 0.10, 2 );
	}

	/**
	 * Build grouping key for a timestamp.
	 *
	 * @param string $date Date string.
	 * @param string $period Period.
	 * @return string
	 */
	private function group_key( string $date, string $period ) : string {
		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			$timestamp = current_time( 'timestamp' );
		}

		return match ( $period ) {
			'daily' => gmdate( 'Y-m-d', $timestamp ),
			'weekly' => gmdate( 'o-\WW', $timestamp ),
			default => gmdate( 'Y-m', $timestamp ),
		};
	}

	/**
	 * Retrieve cached analytics.
	 *
	 * @return array<string, mixed>
	 */
	private function get_cache() : array {
		$cache = get_option( AnalyticsKeys::CACHE_OPTION, array() );
		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * Store cached analytics.
	 *
	 * @param array<string, mixed> $cache Cache data.
	 * @return void
	 */
	private function save_cache( array $cache ) : void {
		update_option( AnalyticsKeys::CACHE_OPTION, $cache, false );
	}

	/**
	 * Invalidate analytics cache.
	 *
	 * @return void
	 */
	private function invalidate_cache() : void {
		$cache = $this->get_cache();
		$cache['last_updated'] = current_time( 'mysql' );
		$cache['orders'] = $this->get_orders();
		$cache['platform'] = array();
		$cache['chefs'] = array();
		$this->save_cache( $cache );
	}

	/**
	 * Append a raw event record.
	 *
	 * @param array<string, mixed> $event Event record.
	 * @return void
	 */
	private function append_event( array $event ) : void {
		$events = get_option( AnalyticsKeys::EVENTS_OPTION, array() );
		$events = is_array( $events ) ? $events : array();
		$events[] = $event;
		update_option( AnalyticsKeys::EVENTS_OPTION, array_slice( $events, -500 ), false );
	}

	/**
	 * Get stored orders.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_orders() : array {
		$orders = get_option( 'maklaplace_orders', array() );
		return is_array( $orders ) ? array_values( $orders ) : array();
	}
}
