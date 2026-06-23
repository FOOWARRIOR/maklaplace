<?php
/**
 * Wallet service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\UserMeta;
use MaklaPlace\Helpers\WalletHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Handles commission wallet accounting.
 */
final class WalletService {

	/**
	 * Wallet transaction history option key.
	 */
	private const HISTORY_OPTION = 'maklaplace_wallet_history';

	/**
	 * Add commission to a chef wallet.
	 *
	 * @param int   $chef_user_id Chef user ID.
	 * @param int   $order_id Order ID.
	 * @param float $order_total Order total.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function add_commission( int $chef_user_id, int $order_id, float $order_total ) : array|\WP_Error {
		if ( $this->is_commission_processed( $order_id ) ) {
			return new \WP_Error( 'maklaplace_commission_duplicate', __( 'Commission already processed for this order.', 'maklaplace' ) );
		}

		$commission = round( $order_total * 0.10, 2 );
		$balance = $this->get_balance( $chef_user_id ) + $commission;

		UserMeta::set( $chef_user_id, UserMeta::WALLET_BALANCE, $balance );
		UserMeta::set( $chef_user_id, UserMeta::WALLET_STATUS, $this->determine_status( $balance ) );
		UserMeta::set( $chef_user_id, UserMeta::WALLET_LAST_UPDATED, current_time( 'mysql' ) );
		$this->mark_commission_processed( $order_id, true );
		$this->append_history(
			array(
				'chef_user_id' => $chef_user_id,
				'order_id'     => $order_id,
				'type'         => 'commission_added',
				'amount'       => $commission,
				'balance'      => $balance,
				'created_at'   => current_time( 'mysql' ),
			)
		);

		$payload = array(
			'chef_user_id' => $chef_user_id,
			'order_id'     => $order_id,
			'commission'   => $commission,
			'balance'      => $balance,
		);

		do_action( 'maklaplace_commission_added', $payload );
		do_action( 'maklaplace_wallet_updated', $payload );

		if ( $balance >= WalletHelper::threshold() ) {
			do_action( 'maklaplace_wallet_threshold_reached', $payload );
		}

		return $payload;
	}

	/**
	 * Get a wallet balance.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return float
	 */
	public function get_balance( int $chef_user_id ) : float {
		return (float) get_user_meta( $chef_user_id, UserMeta::WALLET_BALANCE, true );
	}

	/**
	 * Get wallet status.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return string
	 */
	public function get_status( int $chef_user_id ) : string {
		$status = get_user_meta( $chef_user_id, UserMeta::WALLET_STATUS, true );

		return is_string( $status ) && '' !== $status ? $status : $this->determine_status( $this->get_balance( $chef_user_id ) );
	}

	/**
	 * Update wallet status manually.
	 *
	 * @param int    $chef_user_id Chef user ID.
	 * @param string $status Wallet status.
	 * @return bool
	 */
	public function update_status( int $chef_user_id, string $status ) : bool {
		$allowed = array( 'empty', 'not_ready', 'ready_to_collect', 'in_progress' );
		$status = in_array( $status, $allowed, true ) ? $status : $this->determine_status( $this->get_balance( $chef_user_id ) );

		UserMeta::set( $chef_user_id, UserMeta::WALLET_STATUS, $status );
		UserMeta::set( $chef_user_id, UserMeta::WALLET_LAST_UPDATED, current_time( 'mysql' ) );

		do_action(
			'maklaplace_wallet_updated',
			array(
				'chef_user_id' => $chef_user_id,
				'balance'      => $this->get_balance( $chef_user_id ),
				'status'       => $status,
			)
		);
		$this->append_history(
			array(
				'chef_user_id' => $chef_user_id,
				'type'         => 'status_changed',
				'amount'       => 0.0,
				'balance'      => $this->get_balance( $chef_user_id ),
				'status'       => $status,
				'created_at'   => current_time( 'mysql' ),
			)
		);

		return true;
	}

	/**
	 * Start a collection flow.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return bool
	 */
	public function start_collection( int $chef_user_id ) : bool {
		return $this->update_status( $chef_user_id, 'in_progress' );
	}

	/**
	 * Confirm a manual deduction.
	 *
	 * @param int   $chef_user_id Chef user ID.
	 * @param float $amount Deduction amount.
	 * @return bool|\WP_Error
	 */
	public function confirm_collection( int $chef_user_id, float $amount ) : bool|\WP_Error {
		$balance = $this->get_balance( $chef_user_id );

		if ( $amount <= 0 || $amount > $balance ) {
			return new \WP_Error( 'maklaplace_wallet_invalid_deduction', __( 'Invalid deduction amount.', 'maklaplace' ) );
		}

		$new_balance = round( $balance - $amount, 2 );
		UserMeta::set( $chef_user_id, UserMeta::WALLET_BALANCE, $new_balance );
		UserMeta::set( $chef_user_id, UserMeta::WALLET_STATUS, $this->determine_status( $new_balance ) );
		UserMeta::set( $chef_user_id, UserMeta::WALLET_LAST_UPDATED, current_time( 'mysql' ) );

		do_action(
			'maklaplace_wallet_updated',
			array(
				'chef_user_id' => $chef_user_id,
				'balance'      => $new_balance,
				'status'       => $this->determine_status( $new_balance ),
				'deduction'    => $amount,
			)
		);
		$this->append_history(
			array(
				'chef_user_id' => $chef_user_id,
				'type'         => 'deduction',
				'amount'       => $amount,
				'balance'      => $new_balance,
				'created_at'   => current_time( 'mysql' ),
			)
		);

		return true;
	}

	/**
	 * Get wallet transaction history.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_history( int $chef_user_id ) : array {
		$history = get_option( self::HISTORY_OPTION, array() );
		$history = is_array( $history ) ? $history : array();

		return array_values(
			array_filter(
				$history,
				static fn( array $entry ) : bool => absint( $entry['chef_user_id'] ?? 0 ) === $chef_user_id
			)
		);
	}

	/**
	 * Determine wallet status from balance.
	 *
	 * @param float $balance Balance amount.
	 * @return string
	 */
	public function determine_status( float $balance ) : string {
		if ( $balance <= 0 ) {
			return 'empty';
		}

		if ( $balance < WalletHelper::threshold() ) {
			return 'not_ready';
		}

		return 'ready_to_collect';
	}

	/**
	 * Check whether commission has already been processed.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function is_commission_processed( int $order_id ) : bool {
		$order = $this->get_order_record( $order_id );

		return is_array( $order ) && ! empty( $order[ UserMeta::COMMISSION_PROCESSED ] );
	}

	/**
	 * Mark commission as processed on the stored order record.
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $processed Processed state.
	 * @return void
	 */
	private function mark_commission_processed( int $order_id, bool $processed ) : void {
		$orders = $this->get_order_store();

		if ( ! isset( $orders[ $order_id ] ) || ! is_array( $orders[ $order_id ] ) ) {
			return;
		}

		$orders[ $order_id ][ UserMeta::COMMISSION_PROCESSED ] = $processed;
		$this->save_order_store( $orders );
	}

	/**
	 * Get a stored order record.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string, mixed>|null
	 */
	private function get_order_record( int $order_id ) : ?array {
		$orders = $this->get_order_store();
		return isset( $orders[ $order_id ] ) && is_array( $orders[ $order_id ] ) ? $orders[ $order_id ] : null;
	}

	/**
	 * Get stored orders.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_order_store() : array {
		$items = get_option( 'maklaplace_orders', array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Save stored orders.
	 *
	 * @param array<int, array<string, mixed>> $items Orders.
	 * @return void
	 */
	private function save_order_store( array $items ) : void {
		update_option( 'maklaplace_orders', $items, false );
	}

	/**
	 * Append a wallet history record.
	 *
	 * @param array<string, mixed> $entry Entry data.
	 * @return void
	 */
	private function append_history( array $entry ) : void {
		$history   = get_option( self::HISTORY_OPTION, array() );
		$history   = is_array( $history ) ? $history : array();
		$history[] = $entry;
		update_option( self::HISTORY_OPTION, array_slice( $history, -500 ), false );
	}
}
