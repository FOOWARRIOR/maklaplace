<?php
/**
 * Chef review repository.
 *
 * @package MaklaPlace\Repositories
 */

namespace MaklaPlace\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and summarizes chef reviews in an option-backed repository.
 */
final class ChefReviewRepository {

	/**
	 * Review storage option.
	 */
	private const OPTION_KEY = 'maklaplace_chef_reviews';
	private const DEFAULT_EDIT_WINDOW_DAYS = 30;

	/**
	 * Fetch reviews for a chef.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_chef( int $chef_user_id ) : array {
		return array_values(
			array_filter(
				$this->get_all(),
				static fn( array $review ) : bool => absint( $review['chef_user_id'] ?? 0 ) === $chef_user_id
			)
		);
	}

	/**
	 * Get summary stats for a chef.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return array<string, mixed>
	 */
	public function get_stats( int $chef_user_id ) : array {
		$reviews = $this->get_for_chef( $chef_user_id );
		$total   = count( $reviews );
		$sum     = array_reduce(
			$reviews,
			static fn( float $carry, array $review ) : float => $carry + (float) ( $review['rating'] ?? 0 ),
			0.0
		);

		return array(
			'total_reviews' => $total,
			'average_rating' => 0 < $total ? round( $sum / $total, 1 ) : 0.0,
			'low_rated_reviews' => count(
				array_filter(
					$reviews,
					static fn( array $review ) : bool => (float) ( $review['rating'] ?? 0 ) <= 2
				)
			),
		);
	}

	/**
	 * Fetch a review by order.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_order( int $order_id ) : ?array {
		foreach ( $this->get_all() as $review ) {
			if ( absint( $review['order_id'] ?? 0 ) === $order_id ) {
				return $review;
			}
		}

		return null;
	}

	/**
	 * Check whether a customer may edit a review.
	 *
	 * @param array<string, mixed> $review Review record.
	 * @return bool
	 */
	public function can_edit_review( array $review ) : bool {
		$created_at = strtotime( (string) ( $review['created_at'] ?? '' ) );
		if ( false === $created_at ) {
			return false;
		}

		$days = (int) apply_filters( 'maklaplace_review_edit_window_days', self::DEFAULT_EDIT_WINDOW_DAYS, $review );
		if ( $days <= 0 ) {
			return true;
		}

		return ( current_time( 'timestamp' ) - $created_at ) <= ( $days * DAY_IN_SECONDS );
	}

	/**
	 * Create or update a review for an order.
	 *
	 * @param array<string, mixed> $data Review data.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function upsert_review( array $data ) : array|\WP_Error {
		$order_id = absint( $data['order_id'] ?? 0 );
		$customer_user_id = absint( $data['customer_user_id'] ?? 0 );
		$chef_user_id = absint( $data['chef_user_id'] ?? 0 );
		$rating = min( 5, max( 1, absint( $data['rating'] ?? 0 ) ) );
		$comment = sanitize_textarea_field( (string) ( $data['comment'] ?? '' ) );

		if ( $order_id <= 0 || $customer_user_id <= 0 || $chef_user_id <= 0 ) {
			return new \WP_Error( 'maklaplace_review_invalid', __( 'Invalid review data.', 'maklaplace' ) );
		}

		$reviews = $this->get_all();
		$existing_index = null;
		foreach ( $reviews as $index => $review ) {
			if ( absint( $review['order_id'] ?? 0 ) === $order_id ) {
				$existing_index = $index;
				break;
			}
		}

		$record = array(
			'order_id'         => $order_id,
			'customer_user_id'  => $customer_user_id,
			'chef_user_id'      => $chef_user_id,
			'rating'           => $rating,
			'comment'          => $comment,
			'reviewer_name'    => (string) ( $data['reviewer_name'] ?? '' ),
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		if ( null !== $existing_index ) {
			$existing = $reviews[ $existing_index ];
			$record['created_at'] = (string) ( $existing['created_at'] ?? $record['created_at'] );
			$reviews[ $existing_index ] = array_merge( $existing, $record );
		} else {
			$reviews[] = $record;
		}

		$this->save_all( $reviews );

		return $record;
	}

	/**
	 * Get all stored reviews.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all() : array {
		$reviews = get_option( self::OPTION_KEY, array() );

		return is_array( $reviews ) ? array_values( $reviews ) : array();
	}

	/**
	 * Save reviews.
	 *
	 * @param array<int, array<string, mixed>> $reviews Reviews.
	 * @return void
	 */
	private function save_all( array $reviews ) : void {
		update_option( self::OPTION_KEY, array_values( $reviews ), false );
	}
}
