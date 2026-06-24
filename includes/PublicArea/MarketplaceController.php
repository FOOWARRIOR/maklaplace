<?php
/**
 * Public marketplace controller.
 *
 * @package MaklaPlace\PublicArea
 */

namespace MaklaPlace\PublicArea;

use MaklaPlace\Core\AnalyticsService;
use MaklaPlace\Core\Container;
use MaklaPlace\Core\MenuService;
use MaklaPlace\Core\NotificationService;
use MaklaPlace\Core\OrderService;
use MaklaPlace\Core\RoleService;
use MaklaPlace\Core\ChefProfileService;
use MaklaPlace\Helpers\ChefProfileKeys;
use MaklaPlace\Helpers\MenuKeys;
use MaklaPlace\Helpers\OrderKeys;
use MaklaPlace\Helpers\UserMeta;
use MaklaPlace\Repositories\ChefReviewRepository;
use WP_Query;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Renders public-facing marketplace views and shortcodes.
 */
final class MarketplaceController {

	private const QUERY_VAR = 'maklaplace_chef';

	public function __construct( private Container $container ) {
	}

	public function register_hooks() : void {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_clean_routes' ) );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ) );
		add_shortcode( 'maklaplace_chef_directory', array( $this, 'render_directory_shortcode' ) );
		add_shortcode( 'maklaplace_chef_card', array( $this, 'render_card_shortcode' ) );
		add_shortcode( 'maklaplace_chef_menu', array( $this, 'render_menu_shortcode' ) );
		add_shortcode( 'maklaplace_chef_reviews', array( $this, 'render_reviews_shortcode' ) );
		add_shortcode( 'maklaplace_chef_favorites', array( $this, 'render_favorites_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_head', array( $this, 'output_meta_description' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
		add_action( 'admin_post_maklaplace_add_favorite_chef', array( $this, 'handle_add_favorite' ) );
		add_action( 'admin_post_nopriv_maklaplace_add_favorite_chef', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_remove_favorite_chef', array( $this, 'handle_remove_favorite' ) );
		add_action( 'admin_post_nopriv_maklaplace_remove_favorite_chef', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_start_order', array( $this, 'handle_start_order' ) );
		add_action( 'admin_post_nopriv_maklaplace_start_order', array( $this, 'handle_login_required' ) );
	}

	public function register_rewrite_rules() : void {
		add_rewrite_rule( '^chefs/?$', 'index.php?post_type=page&maklaplace_chefs=1', 'top' );
		add_rewrite_rule( '^chefs/([^/]+)/?$', 'index.php?post_type=page&maklaplace_chef=$matches[1]', 'top' );
	}

	public function register_query_vars( array $vars ) : array {
		$vars[] = 'maklaplace_chefs';
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_render_clean_routes() : void {
		$path = trim( (string) parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( 'chefs' === $path ) {
			$this->render_directory_route();
			exit;
		}

		if ( 0 === strpos( $path, 'chefs/' ) ) {
			$chef_slug = trim( substr( $path, strlen( 'chefs/' ) ), '/' );
			if ( '' !== $chef_slug ) {
				$this->render_single_route( $chef_slug );
				exit;
			}
		}

		$chef_slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' !== $chef_slug ) {
			$this->render_single_route( $chef_slug );
			exit;
		}
	}

	public function filter_document_title( array $parts ) : array {
		if ( get_query_var( 'maklaplace_chefs' ) ) {
			$parts['title'] = __( 'Chefs', 'maklaplace' );
		}

		$chef_slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' !== $chef_slug ) {
			$chef = $this->get_chef_by_slug( $chef_slug );
			if ( $chef instanceof WP_User ) {
				$parts['title'] = $this->get_chef_display_name( $chef->ID );
			}
		}

		return $parts;
	}

	public function register_assets() : void {
		wp_register_style( 'maklaplace-public', false, array(), MAKLAPLACE_VERSION );
		wp_enqueue_style( 'maklaplace-public' );
		wp_add_inline_style(
			'maklaplace-public',
			'.maklaplace-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.maklaplace-card,.maklaplace-panel{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.maklaplace-meta{color:#646970;font-size:14px}.maklaplace-actions{display:flex;gap:8px;flex-wrap:wrap}.maklaplace-chip{display:inline-block;background:#f6f7f7;border:1px solid #dcdcde;border-radius:999px;padding:4px 10px;margin:0 6px 6px 0}.maklaplace-product{display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f0f0f1}.maklaplace-product img{width:88px;height:88px;object-fit:cover;border-radius:6px}.maklaplace-favorites form,.maklaplace-order-form{display:inline-block;margin:0 8px 8px 0}'
		);
	}

	public function output_meta_description() : void {
		$chef_slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $chef_slug && ! get_query_var( 'maklaplace_chefs' ) ) {
			return;
		}

		$description = get_query_var( self::QUERY_VAR ) ? __( 'View chef profiles, menus, reviews, and start your order.', 'maklaplace' ) : __( 'Discover approved chefs on MaklaPlace.', 'maklaplace' );
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
	}

	public function render_directory_shortcode( array $atts = array() ) : string {
		return $this->render_directory( shortcode_atts( array( 'per_page' => 12 ), $atts ) );
	}

	public function render_card_shortcode( array $atts = array() ) : string {
		$chef_id = absint( $atts['chef_id'] ?? 0 );
		return $chef_id > 0 ? $this->render_chef_card( $chef_id ) : '';
	}

	public function render_menu_shortcode( array $atts = array() ) : string {
		$chef_id = absint( $atts['chef_id'] ?? 0 );
		return $chef_id > 0 ? $this->render_menu( $chef_id, $atts ) : '';
	}

	public function render_reviews_shortcode( array $atts = array() ) : string {
		$chef_id = absint( $atts['chef_id'] ?? 0 );
		return $chef_id > 0 ? $this->render_reviews( $chef_id ) : '';
	}

	public function render_favorites_shortcode() : string {
		if ( ! is_user_logged_in() ) {
			return '<div class="maklaplace-panel">' . esc_html__( 'Log in to manage favorites.', 'maklaplace' ) . '</div>';
		}

		$chef_ids = $this->get_favorite_chefs( get_current_user_id() );
		$html = '<div class="maklaplace-grid">';
		if ( empty( $chef_ids ) ) {
			return $html . '<div class="maklaplace-panel">' . esc_html__( 'No favorite chefs yet.', 'maklaplace' ) . '</div></div>';
		}

		foreach ( $chef_ids as $chef_id ) {
			$html .= $this->render_chef_card( $chef_id, true );
		}

		return $html . '</div>';
	}

	public function register_elementor_widgets( $widgets_manager ) : void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\ChefDirectoryWidget() );
	}

	public function handle_add_favorite() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_favorite_chef', 'maklaplace_nonce' );
		$chef_id = absint( $_POST['chef_id'] ?? 0 );
		$this->set_favorite( get_current_user_id(), $chef_id, true );
		wp_safe_redirect( wp_get_referer() ?: home_url( '/chefs/' ) );
		exit;
	}

	public function handle_remove_favorite() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_favorite_chef', 'maklaplace_nonce' );
		$chef_id = absint( $_POST['chef_id'] ?? 0 );
		$this->set_favorite( get_current_user_id(), $chef_id, false );
		wp_safe_redirect( wp_get_referer() ?: home_url( '/chefs/' ) );
		exit;
	}

	public function handle_start_order() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_start_order', 'maklaplace_nonce' );
		$chef_id = absint( $_POST['chef_id'] ?? 0 );
		wp_safe_redirect( add_query_arg( array( 'chef_id' => $chef_id, 'step' => 'customer-details' ), home_url( '/order/' ) ) );
		exit;
	}

	public function handle_login_required() : void {
		auth_redirect();
		exit;
	}

	private function render_directory_route() : void {
		$this->render_document( $this->render_directory( array() ), __( 'Chefs', 'maklaplace' ) );
	}

	private function render_single_route( string $chef_slug ) : void {
		$chef = $this->get_chef_by_slug( $chef_slug );
		if ( ! $chef instanceof WP_User ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			$this->render_document( '<div class="wrap"><p>' . esc_html__( 'Chef not found.', 'maklaplace' ) . '</p></div>', __( 'Chef not found', 'maklaplace' ) );
			return;
		}

		$this->render_document( $this->render_chef_page( $chef->ID ), $this->get_chef_display_name( $chef->ID ) );
	}

	private function render_directory( array $atts ) : string {
		$chef_service = $this->container->get( ChefProfileService::class );
		$menu_service = $this->container->get( MenuService::class );
		$per_page = max( 1, min( 24, absint( $atts['per_page'] ?? 12 ) ) );
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$search = sanitize_text_field( (string) ( $_GET['s'] ?? '' ) );
		$cuisine = sanitize_text_field( (string) ( $_GET['cuisine'] ?? '' ) );
		$city = sanitize_text_field( (string) ( $_GET['city'] ?? '' ) );
		$wilaya = sanitize_text_field( (string) ( $_GET['wilaya'] ?? '' ) );
		$availability = sanitize_key( (string) ( $_GET['availability'] ?? '' ) );
		$sort = sanitize_key( (string) ( $_GET['sort'] ?? 'newest' ) );
		$chefs = $this->get_approved_chefs();
		$chefs = array_filter(
			$chefs,
			function ( WP_User $chef ) use ( $chef_service, $menu_service, $search, $cuisine, $city, $wilaya, $availability ) : bool {
				$profile = $chef_service->get_profile( $chef->ID ) ?? array();
				$stats = $this->container->get( ChefReviewRepository::class )->get_stats( $chef->ID );
				$menu = $menu_service->get_menu_items_by_chef( $chef->ID );
				$menu = array_filter( $menu, static fn( array $item ) : bool => 'available' === (string) ( $item[ MenuKeys::AVAILABILITY ] ?? '' ) );

				if ( '' !== $search ) {
					$haystack = strtolower( $this->get_chef_display_name( $chef->ID ) . ' ' . (string) ( $profile[ ChefProfileKeys::BIO ] ?? '' ) );
					if ( false === strpos( $haystack, strtolower( $search ) ) ) {
						return false;
					}
				}

				if ( '' !== $cuisine ) {
					$cuisine_types = array_map( 'strtolower', (array) ( $profile[ ChefProfileKeys::CUISINE_TYPES ] ?? array() ) );
					if ( ! in_array( strtolower( $cuisine ), $cuisine_types, true ) ) {
						return false;
					}
				}

				if ( '' !== $city && strtolower( (string) ( $profile[ ChefProfileKeys::CITY ] ?? '' ) ) !== strtolower( $city ) ) {
					return false;
				}

				if ( '' !== $wilaya && strtolower( (string) ( $profile[ ChefProfileKeys::WILAYA ] ?? '' ) ) !== strtolower( $wilaya ) ) {
					return false;
				}

				if ( 'available' === $availability && empty( $menu ) ) {
					return false;
				}

				if ( 'unavailable' === $availability && ! empty( $menu ) ) {
					return false;
				}

				return true;
			}
		);

		$chefs = $this->sort_chefs( $chefs, $sort );
		$total = count( $chefs );
		$chefs = array_slice( $chefs, ( $page - 1 ) * $per_page, $per_page );

		$html = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'Chefs', 'maklaplace' ) . '</h1>';
		$html .= $this->render_filters( $search, $cuisine, $city, $wilaya, $availability, $sort );
		$html .= '<div class="maklaplace-grid">';
		foreach ( $chefs as $chef ) {
			$html .= $this->render_chef_card( $chef->ID );
		}
		$html .= '</div>';
		$html .= $this->render_pagination( $total, $per_page, $page );
		$html .= '</div>';
		return $html;
	}

	private function render_chef_card( int $chef_id, bool $favorite_context = false ) : string {
		$profile = $this->container->get( ChefProfileService::class )->get_profile( $chef_id ) ?? array();
		$reviews = $this->container->get( ChefReviewRepository::class )->get_stats( $chef_id );
		$orders = $this->container->get( AnalyticsService::class )->get_chef_stats( $chef_id );
		$slug = $this->get_chef_slug( $chef_id );
		$is_favorite = is_user_logged_in() ? in_array( $chef_id, $this->get_favorite_chefs( get_current_user_id() ), true ) : false;

		$html = '<article class="maklaplace-card">';
		$html .= '<h2><a href="' . esc_url( home_url( '/chefs/' . $slug . '/' ) ) . '">' . esc_html( $this->get_chef_display_name( $chef_id ) ) . '</a></h2>';
		$html .= '<div class="maklaplace-meta">' . esc_html( (string) ( $profile[ ChefProfileKeys::CITY ] ?? '' ) ) . ' ' . esc_html( (string) ( $profile[ ChefProfileKeys::WILAYA ] ?? '' ) ) . '</div>';
		$html .= '<div>' . esc_html( sprintf( '%s %s', __( 'Rating:', 'maklaplace' ), number_format_i18n( (float) ( $reviews['average_rating'] ?? 0 ), 1 ) ) ) . '</div>';
		$html .= '<div>' . esc_html( sprintf( '%s %s', __( 'Orders:', 'maklaplace' ), number_format_i18n( (int) ( $orders['total_orders'] ?? 0 ) ) ) ) . '</div>';
		$html .= '<div class="maklaplace-actions">';
		$html .= '<a class="button" href="' . esc_url( home_url( '/chefs/' . $slug . '/' ) ) . '">' . esc_html__( 'View Chef', 'maklaplace' ) . '</a>';
		if ( is_user_logged_in() && $this->current_user_can_favorite() ) {
			$form_action = $is_favorite ? 'maklaplace_remove_favorite_chef' : 'maklaplace_add_favorite_chef';
			$label = $is_favorite ? __( 'Remove Favorite', 'maklaplace' ) : __( 'Save Favorite', 'maklaplace' );
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$html .= wp_nonce_field( 'maklaplace_favorite_chef', 'maklaplace_nonce', true, false );
			$html .= '<input type="hidden" name="action" value="' . esc_attr( $form_action ) . '">';
			$html .= '<input type="hidden" name="chef_id" value="' . esc_attr( (string) $chef_id ) . '">';
			$html .= '<button type="submit" class="button">' . esc_html( $label ) . '</button>';
			$html .= '</form>';
		}
		$html .= '</div></article>';

		return $html;
	}

	private function render_chef_page( int $chef_id ) : string {
		$profile = $this->container->get( ChefProfileService::class )->get_profile( $chef_id ) ?? array();
		$reviews = $this->container->get( ChefReviewRepository::class )->get_stats( $chef_id );
		$stats = $this->container->get( AnalyticsService::class )->get_chef_stats( $chef_id );
		$menu = $this->container->get( MenuService::class )->get_menu_items_by_chef( $chef_id );
		$menu = array_values(
			array_filter(
				$menu,
				static fn( array $item ) : bool => 'available' === (string) ( $item[ MenuKeys::AVAILABILITY ] ?? '' )
			)
		);

		$html = '<div class="wrap maklaplace-public-chef">';
		$html .= '<h1>' . esc_html( $this->get_chef_display_name( $chef_id ) ) . '</h1>';
		$html .= '<div class="maklaplace-panel">';
		$html .= '<p>' . esc_html( (string) ( $profile[ ChefProfileKeys::BIO ] ?? '' ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'City:', 'maklaplace' ) . ' ' . esc_html( (string) ( $profile[ ChefProfileKeys::CITY ] ?? '' ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Wilaya:', 'maklaplace' ) . ' ' . esc_html( (string) ( $profile[ ChefProfileKeys::WILAYA ] ?? '' ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Working Hours:', 'maklaplace' ) . ' ' . esc_html( is_array( $profile[ ChefProfileKeys::WORKING_HOURS ] ?? null ) ? implode( ', ', (array) $profile[ ChefProfileKeys::WORKING_HOURS ] ) : (string) ( $profile[ ChefProfileKeys::WORKING_HOURS ] ?? '' ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Average Rating:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (float) ( $reviews['average_rating'] ?? 0 ), 1 ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Review Count:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (int) ( $reviews['total_reviews'] ?? 0 ) ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Completed Orders:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (int) ( $stats['completed_orders'] ?? 0 ) ) ) . '</p>';
		$html .= '</div>';
		$html .= '<div class="maklaplace-actions">';
		$html .= $this->render_start_order_button( $chef_id );
		$html .= '</div>';
		$html .= $this->render_menu( $chef_id, array() );
		$html .= $this->render_reviews( $chef_id );
		$html .= '</div>';
		return $html;
	}

	private function render_menu( int $chef_id, array $atts ) : string {
		$menu_service = $this->container->get( MenuService::class );
		$items = array_values(
			array_filter(
				$menu_service->get_menu_items_by_chef( $chef_id ),
				static fn( array $item ) : bool => 'available' === (string) ( $item[ MenuKeys::AVAILABILITY ] ?? '' )
			)
		);
		$category = sanitize_text_field( (string) ( $_GET['category'] ?? '' ) );
		$search = sanitize_text_field( (string) ( $_GET['menu_s'] ?? '' ) );

		if ( '' !== $category ) {
			$items = array_values( array_filter( $items, static fn( array $item ) : bool => strtolower( (string) ( $item[ MenuKeys::CATEGORY ] ?? '' ) ) === strtolower( $category ) ) );
		}

		if ( '' !== $search ) {
			$items = array_values( array_filter( $items, static fn( array $item ) : bool => false !== strpos( strtolower( (string) ( $item[ MenuKeys::TITLE ] ?? '' ) . ' ' . (string) ( $item[ MenuKeys::DESCRIPTION ] ?? '' ) ), strtolower( $search ) ) ) );
		}

		$html = '<div class="maklaplace-panel"><h2>' . esc_html__( 'Menu', 'maklaplace' ) . '</h2>';
		$html .= '<form method="get"><input type="hidden" name="chef_id" value="' . esc_attr( (string) $chef_id ) . '">';
		$html .= '<input type="search" name="menu_s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search menu items', 'maklaplace' ) . '">';
		$html .= '<select name="category"><option value="">' . esc_html__( 'All categories', 'maklaplace' ) . '</option><option value="starter">' . esc_html__( 'Starter', 'maklaplace' ) . '</option><option value="main">' . esc_html__( 'Main', 'maklaplace' ) . '</option><option value="dessert">' . esc_html__( 'Dessert', 'maklaplace' ) . '</option><option value="drink">' . esc_html__( 'Drink', 'maklaplace' ) . '</option><option value="side">' . esc_html__( 'Side', 'maklaplace' ) . '</option><option value="special">' . esc_html__( 'Special', 'maklaplace' ) . '</option></select>';
		$html .= '<button type="submit" class="button">' . esc_html__( 'Filter', 'maklaplace' ) . '</button></form>';
		foreach ( $items as $item ) {
			$html .= '<div class="maklaplace-product">';
			$html .= '<div><img src="' . esc_url( (string) ( $item[ MenuKeys::IMAGE ] ?? '' ) ) . '" alt="' . esc_attr( (string) ( $item[ MenuKeys::TITLE ] ?? '' ) ) . '"></div>';
			$html .= '<div><h3>' . esc_html( (string) ( $item[ MenuKeys::TITLE ] ?? '' ) ) . '</h3>';
			$html .= '<p>' . esc_html( wp_strip_all_tags( (string) ( $item[ MenuKeys::DESCRIPTION ] ?? '' ) ) ) . '</p>';
			$html .= '<p>' . esc_html( number_format_i18n( (float) ( $item[ MenuKeys::PRICE ] ?? 0 ) ) ) . ' DA · ' . esc_html( number_format_i18n( (int) ( $item[ MenuKeys::PREPARATION_TIME ] ?? 0 ) ) ) . ' min</p></div>';
			$html .= '</div>';
		}
		if ( empty( $items ) ) {
			$html .= '<p>' . esc_html__( 'No menu items found.', 'maklaplace' ) . '</p>';
		}
		$html .= '</div>';

		return $html;
	}

	private function render_reviews( int $chef_id ) : string {
		$repository = $this->container->get( ChefReviewRepository::class );
		$reviews = $repository->get_for_chef( $chef_id );
		$stats = $repository->get_stats( $chef_id );
		$html = '<div class="maklaplace-panel"><h2>' . esc_html__( 'Reviews', 'maklaplace' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'Average rating:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (float) ( $stats['average_rating'] ?? 0 ), 1 ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Total reviews:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (int) ( $stats['total_reviews'] ?? 0 ) ) ) . '</p>';
		foreach ( $reviews as $review ) {
			$html .= '<div class="maklaplace-card"><strong>' . esc_html( number_format_i18n( (float) ( $review['rating'] ?? 0 ), 1 ) ) . '</strong><div>' . esc_html( (string) ( $review['reviewer_name'] ?? '' ) ) . '</div><p>' . esc_html( (string) ( $review['comment'] ?? '' ) ) . '</p></div>';
		}
		if ( empty( $reviews ) ) {
			$html .= '<p>' . esc_html__( 'No reviews yet.', 'maklaplace' ) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	private function render_start_order_button( int $chef_id ) : string {
		if ( ! is_user_logged_in() || ! $this->current_user_can_order() ) {
			return '';
		}

		return '<form class="maklaplace-order-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' .
			wp_nonce_field( 'maklaplace_start_order', 'maklaplace_nonce', true, false ) .
			'<input type="hidden" name="action" value="maklaplace_start_order">' .
			'<input type="hidden" name="chef_id" value="' . esc_attr( (string) $chef_id ) . '">' .
			'<button type="submit" class="button button-primary">' . esc_html__( 'Start Order', 'maklaplace' ) . '</button>' .
			'</form>';
	}

	private function render_filters( string $search, string $cuisine, string $city, string $wilaya, string $availability, string $sort ) : string {
		return '<form class="maklaplace-panel" method="get"><div class="maklaplace-grid">' .
			'<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search chefs', 'maklaplace' ) . '">' .
			'<input type="search" name="cuisine" value="' . esc_attr( $cuisine ) . '" placeholder="' . esc_attr__( 'Cuisine', 'maklaplace' ) . '">' .
			'<input type="search" name="city" value="' . esc_attr( $city ) . '" placeholder="' . esc_attr__( 'City', 'maklaplace' ) . '">' .
			'<input type="search" name="wilaya" value="' . esc_attr( $wilaya ) . '" placeholder="' . esc_attr__( 'Wilaya', 'maklaplace' ) . '">' .
			'<select name="availability"><option value="">' . esc_html__( 'All availability', 'maklaplace' ) . '</option><option value="available"' . selected( $availability, 'available', false ) . '>' . esc_html__( 'Available', 'maklaplace' ) . '</option><option value="unavailable"' . selected( $availability, 'unavailable', false ) . '>' . esc_html__( 'Unavailable', 'maklaplace' ) . '</option></select>' .
			'<select name="sort"><option value="newest"' . selected( $sort, 'newest', false ) . '>' . esc_html__( 'Newest', 'maklaplace' ) . '</option><option value="highest_rated"' . selected( $sort, 'highest_rated', false ) . '>' . esc_html__( 'Highest Rated', 'maklaplace' ) . '</option><option value="most_orders"' . selected( $sort, 'most_orders', false ) . '>' . esc_html__( 'Most Orders', 'maklaplace' ) . '</option></select>' .
			'</div><button type="submit" class="button">' . esc_html__( 'Search', 'maklaplace' ) . '</button></form>';
	}

	private function render_pagination( int $total, int $per_page, int $page ) : string {
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $total_pages <= 1 ) {
			return '';
		}

		return '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( array(
			'total'   => $total_pages,
			'current' => $page,
			'format'  => '?paged=%#%',
		) ) . '</div></div>';
	}

	private function render_document( string $content, string $title ) : void {
		status_header( is_404() ? 404 : 200 );
		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( $title ) . '</title>';
		wp_head();
		echo '</head><body>';
		wp_body_open();
		echo $content;
		wp_footer();
		echo '</body></html>';
	}

	private function get_approved_chefs() : array {
		$users = get_users(
			array(
				'role'   => 'maklaplace_chef',
				'fields' => 'all',
			)
		);

		return array_values(
			array_filter(
				$users,
				static fn( WP_User $user ) : bool => 'approved' === (string) get_user_meta( $user->ID, ChefProfileKeys::VERIFICATION_STATUS, true )
			)
		);
	}

	private function sort_chefs( array $chefs, string $sort ) : array {
		usort(
			$chefs,
			function ( WP_User $a, WP_User $b ) use ( $sort ) : int {
				$chef_service = $this->container->get( ChefProfileService::class );
				$reviews = $this->container->get( ChefReviewRepository::class );
				$analytics = $this->container->get( AnalyticsService::class );

				if ( 'highest_rated' === $sort ) {
					return $reviews->get_stats( $b->ID )['average_rating'] <=> $reviews->get_stats( $a->ID )['average_rating'];
				}

				if ( 'most_orders' === $sort ) {
					return $analytics->get_chef_stats( $b->ID )['total_orders'] <=> $analytics->get_chef_stats( $a->ID )['total_orders'];
				}

				$a_date = strtotime( (string) get_user_meta( $a->ID, ChefProfileKeys::APPROVAL_DATE, true ) ?: $a->user_registered );
				$b_date = strtotime( (string) get_user_meta( $b->ID, ChefProfileKeys::APPROVAL_DATE, true ) ?: $b->user_registered );
				return $b_date <=> $a_date;
			}
		);

		return $chefs;
	}

	private function get_chef_by_slug( string $slug ) : ?WP_User {
		foreach ( $this->get_approved_chefs() as $user ) {
			$display_slug = sanitize_title( $this->get_chef_display_name( $user->ID ) );
			$login_slug   = sanitize_title( (string) $user->user_login );

			if ( $slug === $display_slug || $slug === $login_slug ) {
				return $user;
			}
		}

		return null;
	}

	private function get_chef_display_name( int $chef_id ) : string {
		$profile = $this->container->get( ChefProfileService::class )->get_profile( $chef_id ) ?? array();
		$name = (string) ( $profile[ ChefProfileKeys::DISPLAY_NAME ] ?? '' );
		if ( '' !== $name ) {
			return $name;
		}

		$user = get_userdata( $chef_id );
		return $user instanceof WP_User ? $user->display_name : '';
	}

	private function get_chef_slug( int $chef_id ) : string {
		return sanitize_title( $this->get_chef_display_name( $chef_id ) ?: (string) $chef_id );
	}

	private function get_favorite_chefs( int $user_id ) : array {
		$favorites = get_user_meta( $user_id, 'maklaplace_favorite_chefs', true );
		$favorites = is_array( $favorites ) ? array_map( 'absint', $favorites ) : array();
		return array_values( array_filter( $favorites ) );
	}

	private function set_favorite( int $user_id, int $chef_id, bool $add ) : void {
		$favorites = $this->get_favorite_chefs( $user_id );
		if ( $add && ! in_array( $chef_id, $favorites, true ) ) {
			$favorites[] = $chef_id;
		} elseif ( ! $add ) {
			$favorites = array_values( array_diff( $favorites, array( $chef_id ) ) );
		}
		update_user_meta( $user_id, 'maklaplace_favorite_chefs', $favorites );
	}

	private function current_user_can_favorite() : bool {
		$user = wp_get_current_user();
		return $user instanceof WP_User && $this->container->get( RoleService::class )->has_role( $user->ID, 'maklaplace_customer' );
	}

	private function current_user_can_order() : bool {
		return $this->current_user_can_favorite();
	}

	private function require_customer_access() : void {
		if ( ! $this->current_user_can_favorite() ) {
			wp_die( esc_html__( 'You must be a logged-in customer to continue.', 'maklaplace' ) );
		}
	}
}
