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
