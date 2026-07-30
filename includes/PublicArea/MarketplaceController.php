<?php
/**
 * Public marketplace controller.
 *
 * @package MaklaPlace\PublicArea
 */

namespace MaklaPlace\PublicArea;

use MaklaPlace\Core\AnalyticsService;
use MaklaPlace\Core\CartService;
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
	private array $approved_chefs_cache = array();
	private array $chef_review_stats_cache = array();
	private array $chef_reviews_cache = array();
	private array $chef_stats_cache = array();
	private array $chef_menu_cache = array();

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
		add_action( 'admin_post_maklaplace_checkout_submit', array( $this, 'handle_checkout_submit' ) );
		add_action( 'admin_post_nopriv_maklaplace_checkout_submit', array( $this, 'handle_login_required' ) );
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
		add_rewrite_rule( '^favorites/?$', 'index.php?post_type=page&maklaplace_favorites=1', 'top' );
		add_rewrite_rule( '^checkout/?$', 'index.php?post_type=page&maklaplace_checkout=1', 'top' );
		add_rewrite_rule( '^orders/?$', 'index.php?post_type=page&maklaplace_orders=1', 'top' );
		add_rewrite_rule( '^order-confirmation/?$', 'index.php?post_type=page&maklaplace_order_confirmation=1', 'top' );
		add_rewrite_rule( '^order/?$', 'index.php?post_type=page&maklaplace_order_entry=1', 'top' );
	}

	public function register_query_vars( array $vars ) : array {
		$vars[] = 'maklaplace_chefs';
		$vars[] = 'maklaplace_favorites';
		$vars[] = 'maklaplace_checkout';
		$vars[] = 'maklaplace_orders';
		$vars[] = 'maklaplace_order_confirmation';
		$vars[] = 'maklaplace_order_entry';
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_render_clean_routes() : void {
		$path = trim( (string) parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( 'chefs' === $path ) {
			$this->render_directory_route();
			exit;
		}

		if ( 'favorites' === $path ) {
			$this->render_favorites_route();
			exit;
		}

		if ( 'checkout' === $path ) {
			$this->render_checkout_route();
			exit;
		}

		if ( 'orders' === $path ) {
			$this->render_orders_route();
			exit;
		}

		if ( 'order-confirmation' === $path ) {
			$this->render_order_confirmation_route();
			exit;
		}

		if ( 'order' === $path ) {
			$this->render_order_entry_route();
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

		if ( get_query_var( 'maklaplace_favorites' ) ) {
			$parts['title'] = __( 'Favorites', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_checkout' ) ) {
			$parts['title'] = __( 'Checkout', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_orders' ) ) {
			$parts['title'] = __( 'My Orders', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_order_confirmation' ) ) {
			$parts['title'] = __( 'Order Confirmation', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_order_entry' ) ) {
			$parts['title'] = __( 'Start Order', 'maklaplace' );
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
			'.maklaplace-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.maklaplace-card,.maklaplace-panel{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.maklaplace-card{display:flex;flex-direction:column;gap:10px;min-height:100%}.maklaplace-card h2{margin:0;font-size:18px;line-height:1.3}.maklaplace-card p{margin:0}.maklaplace-meta{color:#646970;font-size:14px}.maklaplace-actions{display:flex;gap:8px;flex-wrap:wrap}.maklaplace-page-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.maklaplace-chip{display:inline-block;background:#f6f7f7;border:1px solid #dcdcde;border-radius:999px;padding:4px 10px;margin:0 6px 6px 0}.maklaplace-status-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:600;line-height:1;text-transform:capitalize}.maklaplace-status-pending{background:#f6f7f7;color:#1d2327;border:1px solid #dcdcde}.maklaplace-status-accepted,.maklaplace-status-preparing,.maklaplace-status-ready,.maklaplace-status-on_the_way{background:#e7f3ff;color:#0a4b78;border:1px solid #bee1ff}.maklaplace-status-completed{background:#edfaef;color:#0a5d20;border:1px solid #b6e3bf}.maklaplace-status-cancelled{background:#fcf0f1;color:#8a1538;border:1px solid #f1c0c6}.maklaplace-product{display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f0f0f1}.maklaplace-product img{width:88px;height:88px;object-fit:cover;border-radius:6px}.maklaplace-favorites form,.maklaplace-order-form{display:inline-block;margin:0 8px 8px 0}.maklaplace-order-card-grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}.maklaplace-order-summary strong{display:block;margin-bottom:4px}.maklaplace-order-summary .button{width:100%;text-align:center}.maklaplace-order-filters select,.maklaplace-order-filters input[type=search]{width:100%}@media (max-width:782px){.maklaplace-page-actions{align-items:flex-start}.maklaplace-grid{grid-template-columns:1fr}.maklaplace-product{flex-direction:column}.maklaplace-product img{width:100%;height:auto;max-width:240px}.maklaplace-order-summary .button{width:auto}}'
		);
	}

	public function output_meta_description() : void {
		$chef_slug = $this->get_requested_chef_slug();
		$is_directory = (bool) get_query_var( 'maklaplace_chefs' );
		$is_favorites = (bool) get_query_var( 'maklaplace_favorites' );
		$is_order_entry = (bool) get_query_var( 'maklaplace_order_entry' );

		if ( '' === $chef_slug && ! $is_directory && ! $is_favorites && ! $is_order_entry ) {
			return;
		}

		if ( $is_order_entry ) {
			$description = __( 'Start an order and continue to the upcoming checkout flow.', 'maklaplace' );
		} elseif ( $is_favorites ) {
			$description = __( 'View your saved favorite chefs on MaklaPlace.', 'maklaplace' );
		} elseif ( (bool) get_query_var( 'maklaplace_checkout' ) ) {
			$description = __( 'Review your cart and enter delivery details before placing your order.', 'maklaplace' );
		} elseif ( (bool) get_query_var( 'maklaplace_orders' ) ) {
			$description = __( 'View and search your past MaklaPlace orders.', 'maklaplace' );
		} elseif ( (bool) get_query_var( 'maklaplace_order_confirmation' ) ) {
			$description = __( 'Review your order confirmation details on MaklaPlace.', 'maklaplace' );
		} elseif ( '' !== $chef_slug ) {
			$description = __( 'View chef profiles, menus, reviews, and start your order.', 'maklaplace' );
		} else {
			$description = __( 'Discover approved chefs on MaklaPlace.', 'maklaplace' );
		}
		$canonical = $this->get_current_canonical_url();
		$og_title = $this->get_current_social_title( $chef_slug );
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
		$this->output_structured_data();
	}

	public function render_directory_shortcode( array $atts = array() ) : string {
		return $this->render_directory( shortcode_atts( array( 'per_page' => 12 ), $atts ) );
	}

	public function render_card_shortcode( array $atts = array() ) : string {
		$chef_id = absint( $atts['chef_id'] ?? 0 );
		return $chef_id > 0 && $this->is_approved_chef( $chef_id ) ? $this->render_chef_card( $chef_id ) : '';
	}

	public function render_menu_shortcode( array $atts = array() ) : string {
		$chef_id = absint( $atts['chef_id'] ?? 0 );
		return $chef_id > 0 && $this->is_approved_chef( $chef_id ) ? $this->render_menu( $chef_id, $atts ) : '';
	}

	public function render_reviews_shortcode( array $atts = array() ) : string {
		$chef_id = absint( $atts['chef_id'] ?? 0 );
		return $chef_id > 0 && $this->is_approved_chef( $chef_id ) ? $this->render_reviews( $chef_id ) : '';
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

		if ( method_exists( $widgets_manager, 'register_category' ) ) {
			$widgets_manager->register_category(
				'maklaplace',
				array(
					'title' => __( 'MaklaPlace', 'maklaplace' ),
					'icon'  => 'fa fa-plug',
				)
			);
		}

		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\ChefDirectoryWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\ChefCardWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\ChefMenuWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\ChefReviewsWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\ChefFavoritesWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\ChefReviewsListWidget() );
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
		if ( ! $this->is_approved_chef( $chef_id ) ) {
			wp_die( esc_html__( 'Selected chef is not available.', 'maklaplace' ) );
		}
		$item_id = absint( $_POST['item_id'] ?? 0 );
		$quantity = max( 1, absint( $_POST['quantity'] ?? 1 ) );
		$args = array(
			'chef_id' => $chef_id,
			'step'    => 'customer-details',
		);
		if ( $item_id > 0 ) {
			$seed_item = $this->container->get( MenuService::class )->get_menu_item( $item_id );
			if ( ! is_array( $seed_item ) || (int) ( $seed_item[ MenuKeys::CHEF_USER_ID ] ?? 0 ) !== $chef_id ) {
				$item_id = 0;
				$quantity = 1;
			}
		}
		if ( $item_id > 0 ) {
			$args['item_id'] = $item_id;
			$args['quantity'] = $quantity;
		}
		wp_safe_redirect( add_query_arg( $args, home_url( '/order/' ) ) );
		exit;
	}

	public function handle_login_required() : void {
		auth_redirect();
		exit;
	}

	private function render_directory_route() : void {
		$this->render_document( $this->render_directory( array() ), __( 'Chefs', 'maklaplace' ) );
	}

	private function render_favorites_route() : void {
		$content = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'Favorites', 'maklaplace' ) . '</h1>';
		$content .= $this->render_favorites_shortcode();
		$content .= '</div>';
		$this->render_document( $content, __( 'Favorites', 'maklaplace' ) );
	}

	private function render_checkout_route() : void {
		$this->render_document( $this->render_checkout_page(), __( 'Checkout', 'maklaplace' ) );
	}

	private function render_orders_route() : void {
		$this->require_customer_access();

		$order_service = $this->container->get( OrderService::class );
		$customer_id = get_current_user_id();
		$search = sanitize_text_field( (string) ( $_GET['s'] ?? '' ) );
		$status = sanitize_key( (string) ( $_GET['status'] ?? '' ) );
		$sort = sanitize_key( (string) ( $_GET['sort'] ?? 'newest' ) );
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = 6;
		$order_id = absint( $_GET['order_id'] ?? 0 );

		if ( $order_id > 0 ) {
			$order = $order_service->get_order( $order_id );
			if ( is_array( $order ) && (int) ( $order[ OrderKeys::CUSTOMER_USER_ID ] ?? 0 ) === $customer_id ) {
				$this->render_document( $this->render_order_details_panel( $order ), __( 'Order Details', 'maklaplace' ) );
				return;
			}
		}

		$orders = array_values(
			array_filter(
				$order_service->get_orders_by_customer( $customer_id ),
				static fn( array $order ) : bool => (int) ( $order[ OrderKeys::CUSTOMER_USER_ID ] ?? 0 ) === $customer_id
			)
		);

		$orders = array_values(
			array_filter(
				$orders,
				static function ( array $order ) use ( $search, $status ) : bool {
					if ( '' !== $status && sanitize_key( (string) ( $order[ OrderKeys::STATUS ] ?? '' ) ) !== $status ) {
						return false;
					}

					if ( '' !== $search ) {
						$haystack = strtolower(
							(string) ( $order[ OrderKeys::CUSTOMER_NAME ] ?? '' ) . ' ' .
							(string) ( $order[ OrderKeys::CHEF_USER_ID ] ?? '' ) . ' ' .
							(string) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? '' ) . ' ' .
							(string) ( $order[ OrderKeys::STATUS ] ?? '' ) . ' ' .
							(string) ( $order[ OrderKeys::SUBMISSION_HASH ] ?? '' )
						);

						if ( false === strpos( $haystack, strtolower( $search ) ) ) {
							return false;
						}
					}

					return true;
				}
			)
		);

		usort(
			$orders,
			static function ( array $a, array $b ) use ( $sort ) : int {
				if ( 'oldest' === $sort ) {
					return strcmp( (string) ( $a[ OrderKeys::CREATED_AT ] ?? '' ), (string) ( $b[ OrderKeys::CREATED_AT ] ?? '' ) );
				}

				if ( 'total_amount' === $sort ) {
					return (float) ( $b[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ) <=> (float) ( $a[ OrderKeys::TOTAL_AMOUNT ] ?? 0 );
				}

				return strcmp( (string) ( $b[ OrderKeys::CREATED_AT ] ?? '' ), (string) ( $a[ OrderKeys::CREATED_AT ] ?? '' ) );
			}
		);

		$total = count( $orders );
		$orders = array_slice( $orders, ( $page - 1 ) * $per_page, $per_page );

		$content = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'My Orders', 'maklaplace' ) . '</h1>';
		$content .= '<div class="maklaplace-panel"><form method="get" class="maklaplace-grid">';
		$content .= '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search orders', 'maklaplace' ) . '">';
		$content .= '<select name="status"><option value="">' . esc_html__( 'All statuses', 'maklaplace' ) . '</option>';
		foreach ( array( 'pending', 'accepted', 'preparing', 'ready', 'on_the_way', 'completed', 'cancelled' ) as $value ) {
			$content .= '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( ucfirst( $value ) ) . '</option>';
		}
		$content .= '</select>';
		$content .= '<select name="sort"><option value="newest"' . selected( $sort, 'newest', false ) . '>' . esc_html__( 'Newest', 'maklaplace' ) . '</option><option value="oldest"' . selected( $sort, 'oldest', false ) . '>' . esc_html__( 'Oldest', 'maklaplace' ) . '</option><option value="total_amount"' . selected( $sort, 'total_amount', false ) . '>' . esc_html__( 'Total Amount', 'maklaplace' ) . '</option></select>';
		$content .= '<button type="submit" class="button">' . esc_html__( 'Filter', 'maklaplace' ) . '</button></form></div>';

		if ( empty( $orders ) ) {
			$content .= '<div class="maklaplace-panel"><p>' . esc_html__( 'No orders found.', 'maklaplace' ) . '</p></div></div>';
			$this->render_document( $content, __( 'My Orders', 'maklaplace' ) );
			return;
		}

		$content .= '<div class="maklaplace-grid">';
		foreach ( $orders as $order ) {
			$content .= $this->render_order_summary_card( $order );
		}
		$content .= '</div>';
		$content .= $this->render_order_archive_pagination( $total, $per_page, $page );
		$content .= '</div>';

		$this->render_document( $content, __( 'My Orders', 'maklaplace' ) );
	}

	private function render_order_summary_card( array $order ) : string {
		$order_id = absint( $order['id'] ?? 0 );
		$status = (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' );
		$total = (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 );
		$items = (array) ( $order[ OrderKeys::ITEMS ] ?? array() );
		$chef_id = (int) ( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 );
		$chef_name = $chef_id > 0 ? $this->get_chef_display_name( $chef_id ) : '';

		$html = '<article class="maklaplace-card">';
		$html .= '<h2>#' . esc_html( number_format_i18n( $order_id ) ) . '</h2>';
		$html .= '<p><strong>' . esc_html__( 'Chef:', 'maklaplace' ) . '</strong> ' . esc_html( $chef_name ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Status:', 'maklaplace' ) . '</strong> <span class="maklaplace-status-badge maklaplace-status-' . esc_attr( sanitize_key( $status ) ) . '">' . esc_html( ucfirst( $status ) ) . '</span></p>';
		$html .= '<p><strong>' . esc_html__( 'Total:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $total ) ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CURRENCY ] ?? 'DA' ) ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Items:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( count( $items ) ) ) . '</p>';
		$html .= '<p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'order_id' => $order_id ), home_url( '/orders/' ) ) ) . '">' . esc_html__( 'View Details', 'maklaplace' ) . '</a></p>';
		$html .= '</article>';

		return $html;
	}

	private function render_order_details_panel( array $order ) : string {
		$order_id = absint( $order['id'] ?? 0 );
		$chef_id = (int) ( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 );
		$chef_name = $chef_id > 0 ? $this->get_chef_display_name( $chef_id ) : __( 'Unknown', 'maklaplace' );
		$content = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'Order Details', 'maklaplace' ) . '</h1><div class="maklaplace-panel">';
		$content .= '<p><strong>' . esc_html__( 'Order Number:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $order_id ) ) . '</p>';
		$content .= '<p><strong>' . esc_html__( 'Status:', 'maklaplace' ) . '</strong> <span class="maklaplace-status-badge maklaplace-status-' . esc_attr( sanitize_key( (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' ) ) ) . '">' . esc_html( ucfirst( (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' ) ) ) . '</span></p>';
		$content .= '<p><strong>' . esc_html__( 'Chef:', 'maklaplace' ) . '</strong> ' . esc_html( $chef_name ) . '</p>';
		$content .= '<p><strong>' . esc_html__( 'Total:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ) ) ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CURRENCY ] ?? 'DA' ) ) . '</p>';
		$content .= '<h2>' . esc_html__( 'Products', 'maklaplace' ) . '</h2><ul>';
		foreach ( (array) ( $order[ OrderKeys::ITEMS ] ?? array() ) as $item ) {
			$content .= '<li>' . esc_html( (string) ( $item['item_name'] ?? '' ) ) . ' x ' . esc_html( number_format_i18n( (int) ( $item['quantity'] ?? 0 ) ) ) . '</li>';
		}
		$content .= '</ul><p><a class="button" href="' . esc_url( home_url( '/orders/' ) ) . '">' . esc_html__( 'Back to Orders', 'maklaplace' ) . '</a></p>';
		$content .= '</div></div>';

		return $content;
	}

	private function render_order_archive_pagination( int $total, int $per_page, int $page ) : string {
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $total_pages <= 1 ) {
			return '';
		}

		return '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(
			array(
				'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', home_url( '/orders/' ) ) ),
				'format'    => '',
				'total'     => $total_pages,
				'current'   => $page,
				'add_args'  => $this->get_orders_query_args(),
				'prev_text' => __( '&laquo; Previous', 'maklaplace' ),
				'next_text' => __( 'Next &raquo;', 'maklaplace' ),
			)
		) . '</div></div>';
	}

	private function get_orders_query_args() : array {
		$args = array(
			's'      => sanitize_text_field( (string) ( $_GET['s'] ?? '' ) ),
			'status' => sanitize_key( (string) ( $_GET['status'] ?? '' ) ),
			'sort'   => sanitize_key( (string) ( $_GET['sort'] ?? 'newest' ) ),
		);

		return array_filter(
			$args,
			static fn( string $value ) : bool => '' !== $value
		);
	}

	private function render_checkout_page() : string {
		$this->require_customer_access();

		$cart_service = $this->container->get( CartService::class );
		$cart = $cart_service->get_cart( get_current_user_id() );
		$details = $cart_service->get_checkout_details( get_current_user_id() );

		$html = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'Checkout', 'maklaplace' ) . '</h1><div class="maklaplace-panel">';
		if ( empty( $cart['items'] ) ) {
			$html .= '<p>' . esc_html__( 'Your cart is empty. Add items before continuing to checkout.', 'maklaplace' ) . '</p>';
			$html .= '<p><a class="button button-primary" href="' . esc_url( home_url( '/chefs/' ) ) . '">' . esc_html__( 'Browse Chefs', 'maklaplace' ) . '</a></p>';
			return $html . '</div></div>';
		}

		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= wp_nonce_field( 'maklaplace_checkout_submit', 'maklaplace_nonce', true, false );
		$html .= '<input type="hidden" name="action" value="maklaplace_checkout_submit">';
		$html .= '<div class="maklaplace-grid">';
		$html .= '<label><strong>' . esc_html__( 'Customer Name', 'maklaplace' ) . '</strong><br><input type="text" name="customer_name" value="' . esc_attr( $details['customer_name'] ) . '" required></label>';
		$html .= '<label><strong>' . esc_html__( 'Phone Number', 'maklaplace' ) . '</strong><br><input type="text" name="customer_phone" value="' . esc_attr( $details['customer_phone'] ) . '" required></label>';
		$html .= '<label style="grid-column:1/-1"><strong>' . esc_html__( 'Delivery Address', 'maklaplace' ) . '</strong><br><textarea name="delivery_address" rows="4" required>' . esc_textarea( $details['delivery_address'] ) . '</textarea></label>';
		$html .= '<label style="grid-column:1/-1"><strong>' . esc_html__( 'Notes', 'maklaplace' ) . '</strong><br><textarea name="customer_notes" rows="4">' . esc_textarea( $details['customer_notes'] ) . '</textarea></label>';
		$html .= '</div>';
		$html .= '<p><strong>' . esc_html__( 'Payment Method:', 'maklaplace' ) . '</strong> ' . esc_html__( 'Cash on Delivery (COD)', 'maklaplace' ) . '</p>';
		$html .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Place Order', 'maklaplace' ) . '</button></p>';
		$html .= '</form></div></div>';

		return $html;
	}

	public function handle_checkout_submit() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_checkout_submit', 'maklaplace_nonce' );

		$cart_service = $this->container->get( CartService::class );
		$cart_service->set_customer_details(
			get_current_user_id(),
			array(
				'customer_name'    => sanitize_text_field( (string) ( $_POST['customer_name'] ?? '' ) ),
				'customer_phone'   => sanitize_text_field( (string) ( $_POST['customer_phone'] ?? '' ) ),
				'delivery_address' => sanitize_textarea_field( (string) ( $_POST['delivery_address'] ?? '' ) ),
				'customer_notes'   => wp_kses_post( (string) ( $_POST['customer_notes'] ?? '' ) ),
			)
		);

		$payload = $cart_service->build_order_payload( get_current_user_id() );
		if ( is_wp_error( $payload ) ) {
			wp_die( esc_html( $payload->get_error_message() ) );
		}

		$order_service = $this->container->get( OrderService::class );
		$order = $order_service->create_order( get_current_user_id(), $payload );
		if ( is_wp_error( $order ) ) {
			wp_die( esc_html( $order->get_error_message() ) );
		}

		$cart_service->clear_cart( get_current_user_id() );
		wp_safe_redirect( add_query_arg( array( 'order_id' => (int) ( $order['id'] ?? 0 ) ), home_url( '/order-confirmation/' ) ) );
		exit;
	}

	private function render_order_confirmation_route() : void {
		$order_id = absint( $_GET['order_id'] ?? 0 );
		$order = $order_id > 0 ? $this->container->get( OrderService::class )->get_order( $order_id ) : null;

		if ( ! is_array( $order ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			$this->render_document( '<div class="wrap"><p>' . esc_html__( 'Order not found.', 'maklaplace' ) . '</p></div>', __( 'Order not found', 'maklaplace' ) );
			return;
		}

		$chef_id = (int) ( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 );
		$chef = $chef_id > 0 ? get_userdata( $chef_id ) : false;

		$content = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'Order Confirmation', 'maklaplace' ) . '</h1>';
		$content .= '<div class="maklaplace-panel" style="max-width:720px">';
		$content .= '<div style="display:grid;gap:12px">';
		$content .= '<div><strong>' . esc_html__( 'Order Number:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $order_id ) ) . '</div>';
		$content .= '<div><strong>' . esc_html__( 'Status:', 'maklaplace' ) . '</strong> ' . esc_html( 'pending' === (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' ) ? __( 'Pending', 'maklaplace' ) : ucfirst( (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' ) ) ) . '</div>';
		$content .= '<div><strong>' . esc_html__( 'Total:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ) ) ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CURRENCY ] ?? 'DA' ) ) . '</div>';
		$content .= '<div><strong>' . esc_html__( 'Chef:', 'maklaplace' ) . '</strong> ';
		if ( $chef instanceof \WP_User ) {
			$chef_profile = $this->container->get( ChefProfileService::class )->get_profile( $chef_id ) ?? array();
			$content .= esc_html( $this->get_chef_display_name( $chef_id ) ) . ' - ' . esc_html( (string) ( $chef_profile[ ChefProfileKeys::CITY ] ?? '' ) ) . ', ' . esc_html( (string) ( $chef_profile[ ChefProfileKeys::WILAYA ] ?? '' ) );
		} else {
			$content .= esc_html__( 'Chef details are unavailable.', 'maklaplace' );
		}
		$content .= '</div>';
		$content .= '<div><strong>' . esc_html__( 'Products:', 'maklaplace' ) . '</strong><ul style="margin:8px 0 0 18px">';
		foreach ( (array) ( $order[ OrderKeys::ITEMS ] ?? array() ) as $item ) {
			$content .= '<li>' . esc_html( (string) ( $item['item_name'] ?? '' ) ) . ' x ' . esc_html( number_format_i18n( (int) ( $item['quantity'] ?? 0 ) ) ) . '</li>';
		}
		$content .= '</ul></div></div>';
		$content .= '<p style="margin-top:20px"><a class="button button-primary" href="' . esc_url( home_url( '/orders/' ) ) . '">' . esc_html__( 'View My Orders', 'maklaplace' ) . '</a></p>';
		$content .= '</div></div>';

		$this->render_document( $content, __( 'Order Confirmation', 'maklaplace' ) );
	}

	private function render_order_entry_route() : void {
		$chef_id = absint( $_GET['chef_id'] ?? 0 );
		$chef = $chef_id > 0 && $this->is_approved_chef( $chef_id ) ? get_userdata( $chef_id ) : false;
		$step = sanitize_key( (string) ( $_GET['step'] ?? 'customer-details' ) );
		$item_id = absint( $_GET['item_id'] ?? 0 );
		$quantity = max( 1, absint( $_GET['quantity'] ?? 1 ) );
		$seed_item = array();
		if ( $chef instanceof WP_User && $item_id > 0 ) {
			$seed_item = $this->container->get( MenuService::class )->get_menu_item( $item_id ) ?? array();
			if ( ! is_array( $seed_item ) || (int) ( $seed_item[ MenuKeys::CHEF_USER_ID ] ?? 0 ) !== $chef_id ) {
				$seed_item = array();
				$item_id = 0;
				$quantity = 1;
			}
		}
		$content = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'Start Order', 'maklaplace' ) . '</h1><div class="maklaplace-panel">';
		if ( $chef instanceof WP_User ) {
			$content .= '<p><strong>' . esc_html__( 'Chef:', 'maklaplace' ) . '</strong> ' . esc_html( $this->get_chef_display_name( $chef->ID ) ) . '</p>';
			$content .= '<p><strong>' . esc_html__( 'Chef ID:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $chef->ID ) ) . '</p>';
		} else {
			$content .= '<p>' . esc_html__( 'No chef selected yet.', 'maklaplace' ) . '</p>';
		}
		$content .= '<p><strong>' . esc_html__( 'Current Step:', 'maklaplace' ) . '</strong> ' . esc_html( $step ) . '</p>';
		if ( $item_id > 0 ) {
			$content .= '<p><strong>' . esc_html__( 'Seed Item ID:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $item_id ) ) . '</p>';
			$content .= '<p><strong>' . esc_html__( 'Quantity:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $quantity ) ) . '</p>';
			if ( ! empty( $seed_item ) ) {
				$content .= '<p><strong>' . esc_html__( 'Seed Item:', 'maklaplace' ) . '</strong> ' . esc_html( (string) ( $seed_item[ MenuKeys::TITLE ] ?? '' ) ) . '</p>';
			}
		}
		$content .= '<p>' . esc_html__( 'Checkout is not available yet. This page only prepares the upcoming order flow.', 'maklaplace' ) . '</p>';
		if ( $chef instanceof WP_User ) {
			$content .= '<p><a class="button button-primary" href="' . esc_url( home_url( '/chefs/' . $this->get_chef_slug( $chef->ID ) . '/' ) ) . '">' . esc_html__( 'Back to Chef', 'maklaplace' ) . '</a></p>';
		}
		$content .= '</div></div>';
		$this->render_document( $content, __( 'Start Order', 'maklaplace' ) );
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

	private function is_approved_chef( int $chef_id ) : bool {
		return $chef_id > 0 && 'approved' === (string) get_user_meta( $chef_id, ChefProfileKeys::VERIFICATION_STATUS, true );
	}

	private function render_directory( array $atts ) : string {
		$chef_service = $this->container->get( ChefProfileService::class );
		$menu_service = $this->container->get( MenuService::class );
		$per_page = max( 1, min( 24, absint( $atts['per_page'] ?? 12 ) ) );
		$page = $this->get_current_directory_page();
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
				$stats = $this->get_chef_review_stats( $chef->ID );
				$menu = $this->get_chef_menu_items( $chef->ID );
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

		$html = '<div class="wrap maklaplace-public-marketplace"><div class="maklaplace-page-actions"><h1>' . esc_html__( 'Chefs', 'maklaplace' ) . '</h1><a class="button" href="' . esc_url( home_url( '/favorites/' ) ) . '">' . esc_html__( 'Favorites', 'maklaplace' ) . '</a></div>';
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
		$reviews = $this->get_chef_review_stats( $chef_id );
		$orders = $this->get_chef_stats( $chef_id );
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
		$reviews = $this->get_chef_review_stats( $chef_id );
		$stats = $this->get_chef_stats( $chef_id );
		$menu = $this->get_chef_menu_items( $chef_id );
		$menu = array_values(
			array_filter(
				$menu,
				static fn( array $item ) : bool => 'available' === (string) ( $item[ MenuKeys::AVAILABILITY ] ?? '' )
			)
		);

		$business_name = trim( (string) ( $profile[ ChefProfileKeys::DISPLAY_NAME ] ?? '' ) );
		if ( '' === $business_name ) {
			$business_name = $this->get_chef_display_name( $chef_id );
		}

		$cover_image = esc_url( (string) ( $profile[ ChefProfileKeys::COVER_IMAGE ] ?? '' ) );
		$logo_image = esc_url( (string) ( $profile[ ChefProfileKeys::PROFILE_PHOTO ] ?? '' ) );
		$cuisine_types = array_filter(
			array_map(
				'trim',
				is_array( $profile[ ChefProfileKeys::CUISINE_TYPES ] ?? null ) ? (array) $profile[ ChefProfileKeys::CUISINE_TYPES ] : explode( ',', (string) ( $profile[ ChefProfileKeys::CUISINE_TYPES ] ?? '' ) )
			)
		);
		$working_hours = $profile[ ChefProfileKeys::WORKING_HOURS ] ?? '';
		$working_hours_text = is_array( $working_hours ) ? implode( ', ', array_filter( array_map( 'trim', $working_hours ) ) ) : trim( (string) $working_hours );

		$html = '<div class="wrap maklaplace-public-chef">';
		$html .= '<div class="maklaplace-hero">';
		$html .= '' !== $cover_image
			? '<img src="' . $cover_image . '" alt="' . esc_attr( $business_name ) . '" loading="lazy">'
			: '<div class="maklaplace-image-placeholder">' . esc_html__( 'Cover image not available', 'maklaplace' ) . '</div>';
		$html .= '</div>';
		$html .= '<div class="maklaplace-panel">';
		$html .= '<div class="maklaplace-chef-header">';
		$html .= '' !== $logo_image
			? '<img class="maklaplace-chef-logo" src="' . $logo_image . '" alt="' . esc_attr( $business_name ) . '" loading="lazy">'
			: '<div class="maklaplace-chef-logo maklaplace-image-placeholder">' . esc_html__( 'Logo not available', 'maklaplace' ) . '</div>';
		$html .= '<div><h1>' . esc_html( $business_name ) . '</h1>';
		$html .= '<p>' . esc_html( (string) ( $profile[ ChefProfileKeys::BIO ] ?? '' ) ) . '</p></div>';
		$html .= '</div>';
		$html .= '<p>' . esc_html__( 'City:', 'maklaplace' ) . ' ' . esc_html( (string) ( $profile[ ChefProfileKeys::CITY ] ?? '' ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Wilaya:', 'maklaplace' ) . ' ' . esc_html( (string) ( $profile[ ChefProfileKeys::WILAYA ] ?? '' ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Cuisine Types:', 'maklaplace' ) . ' ';
		if ( ! empty( $cuisine_types ) ) {
			foreach ( $cuisine_types as $cuisine ) {
				$html .= '<span class="maklaplace-chip">' . esc_html( $cuisine ) . '</span>';
			}
		} else {
			$html .= esc_html__( 'Not specified', 'maklaplace' );
		}
		$html .= '</p>';
		$html .= '<p>' . esc_html__( 'Working Hours:', 'maklaplace' ) . ' ' . esc_html( '' !== $working_hours_text ? $working_hours_text : __( 'Not specified', 'maklaplace' ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Rating Summary:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (float) ( $reviews['average_rating'] ?? 0 ), 1 ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Review Count:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (int) ( $reviews['total_reviews'] ?? 0 ) ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Completed Orders:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (int) ( $stats['completed_orders'] ?? 0 ) ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Average Rating:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (float) ( $stats['average_rating'] ?? $reviews['average_rating'] ?? 0 ), 1 ) ) . '</p>';
		$html .= '</div>';
		$html .= '<div class="maklaplace-actions">';
		$html .= $this->render_start_order_button( $chef_id );
		$html .= '<a class="button button-secondary" href="' . esc_url( home_url( '/order/?chef_id=' . $chef_id . '&step=customer-details' ) ) . '">' . esc_html__( 'Start Order', 'maklaplace' ) . '</a>';
		if ( is_user_logged_in() && $this->current_user_can_favorite() ) {
			$html .= '<a class="button" href="' . esc_url( home_url( '/favorites/' ) ) . '">' . esc_html__( 'View Favorites', 'maklaplace' ) . '</a>';
		}
		$html .= '</div>';
		$html .= $this->render_menu( $chef_id, array() );
		$html .= $this->render_reviews( $chef_id );
		$html .= '</div>';
		return $html;
	}

	private function render_menu( int $chef_id, array $atts ) : string {
		$menu_service = $this->container->get( MenuService::class );
		$all_items = $this->get_chef_menu_items( $chef_id );
		$items = array_values( $all_items );
		$category = sanitize_text_field( (string) ( $_GET['category'] ?? '' ) );
		$search = sanitize_text_field( (string) ( $_GET['menu_s'] ?? '' ) );
		$availability = sanitize_key( (string) ( $_GET['menu_availability'] ?? 'available' ) );

		if ( 'available' === $availability ) {
			$items = array_values(
				array_filter(
					$items,
					static fn( array $item ) : bool => 'available' === (string) ( $item[ MenuKeys::AVAILABILITY ] ?? '' )
				)
			);
		}

		if ( '' !== $category ) {
			$items = array_values( array_filter( $items, static fn( array $item ) : bool => strtolower( (string) ( $item[ MenuKeys::CATEGORY ] ?? '' ) ) === strtolower( $category ) ) );
		}

		if ( '' !== $search ) {
			$items = array_values( array_filter( $items, static fn( array $item ) : bool => false !== strpos( strtolower( (string) ( $item[ MenuKeys::TITLE ] ?? '' ) . ' ' . (string) ( $item[ MenuKeys::DESCRIPTION ] ?? '' ) ), strtolower( $search ) ) ) );
		}

		$categories = array();
		foreach ( $all_items as $item ) {
			$value = sanitize_text_field( (string) ( $item[ MenuKeys::CATEGORY ] ?? '' ) );
			if ( '' !== $value ) {
				$categories[ strtolower( $value ) ] = $value;
			}
		}
		ksort( $categories );

		$html = '<div class="maklaplace-panel"><h2>' . esc_html__( 'Menu', 'maklaplace' ) . '</h2>';
		$html .= '<form method="get"><input type="hidden" name="chef_id" value="' . esc_attr( (string) $chef_id ) . '">';
		$html .= '<input type="search" name="menu_s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search menu items', 'maklaplace' ) . '">';
		$html .= '<select name="category"><option value="">' . esc_html__( 'All categories', 'maklaplace' ) . '</option>';
		foreach ( $categories as $value ) {
			$html .= '<option value="' . esc_attr( strtolower( $value ) ) . '"' . selected( $category, strtolower( $value ), false ) . '>' . esc_html( ucfirst( $value ) ) . '</option>';
		}
		$html .= '</select>';
		$html .= '<select name="menu_availability"><option value="available"' . selected( $availability, 'available', false ) . '>' . esc_html__( 'Available only', 'maklaplace' ) . '</option><option value="all"' . selected( $availability, 'all', false ) . '>' . esc_html__( 'All products', 'maklaplace' ) . '</option></select>';
		$html .= '<button type="submit" class="button">' . esc_html__( 'Filter', 'maklaplace' ) . '</button></form>';
		foreach ( $items as $item ) {
			$html .= '<div class="maklaplace-product">';
			$image = esc_url( (string) ( $item[ MenuKeys::IMAGE ] ?? '' ) );
			$html .= '<div>' . ( '' !== $image ? '<img src="' . $image . '" alt="' . esc_attr( (string) ( $item[ MenuKeys::TITLE ] ?? '' ) ) . '" loading="lazy">' : '<div class="maklaplace-image-placeholder">' . esc_html__( 'Image not available', 'maklaplace' ) . '</div>' ) . '</div>';
			$html .= '<div><h3>' . esc_html( (string) ( $item[ MenuKeys::TITLE ] ?? '' ) ) . '</h3>';
			$html .= '<p>' . esc_html( wp_strip_all_tags( (string) ( $item[ MenuKeys::DESCRIPTION ] ?? '' ) ) ) . '</p>';
			$html .= '<p>' . esc_html( number_format_i18n( (float) ( $item[ MenuKeys::PRICE ] ?? 0 ) ) ) . ' DA &middot; ' . esc_html( number_format_i18n( (int) ( $item[ MenuKeys::PREPARATION_TIME ] ?? 0 ) ) ) . ' min</p></div>';
			$html .= '<div class="maklaplace-actions"><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'chef_id' => $chef_id, 'step' => 'customer-details', 'item_id' => absint( $item['id'] ?? 0 ), 'quantity' => 1 ), home_url( '/order/' ) ) ) . '">' . esc_html__( 'Start Order', 'maklaplace' ) . '</a></div>';
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
		$reviews = $this->get_chef_reviews( $chef_id );
		$stats = $this->get_chef_review_stats( $chef_id );
		$html = '<div class="maklaplace-panel"><h2>' . esc_html__( 'Reviews', 'maklaplace' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'Average rating:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (float) ( $stats['average_rating'] ?? 0 ), 1 ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Total reviews:', 'maklaplace' ) . ' ' . esc_html( number_format_i18n( (int) ( $stats['total_reviews'] ?? 0 ) ) ) . '</p>';
		foreach ( $reviews as $review ) {
			$reviewer = trim( (string) ( $review['reviewer_name'] ?? '' ) );
			$comment = trim( (string) ( $review['comment'] ?? '' ) );
			$created_at = trim( (string) ( $review['created_at'] ?? '' ) );
			$html .= '<div class="maklaplace-card">';
			$html .= '<strong>' . esc_html( number_format_i18n( (float) ( $review['rating'] ?? 0 ), 1 ) ) . '</strong>';
			$html .= '<div>' . esc_html( '' !== $reviewer ? $reviewer : __( 'Anonymous', 'maklaplace' ) ) . '</div>';
			if ( '' !== $created_at ) {
				$html .= '<div class="maklaplace-meta">' . esc_html( mysql2date( __( 'M j, Y g:i A', 'maklaplace' ), $created_at ) ) . '</div>';
			}
			if ( '' !== $comment ) {
				$html .= '<p>' . esc_html( $comment ) . '</p>';
			}
			$html .= '</div>';
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

		return '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(
			array(
				'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $this->get_current_directory_url() ) ),
				'format'    => '',
				'total'     => $total_pages,
				'current'   => $page,
				'add_args'  => $this->get_directory_query_args(),
				'prev_text' => __( '&laquo; Previous', 'maklaplace' ),
				'next_text' => __( 'Next &raquo;', 'maklaplace' ),
			)
		) . '</div></div>';
	}

	private function get_current_directory_page() : int {
		$page = absint( $_GET['paged'] ?? 0 );
		if ( $page > 0 ) {
			return $page;
		}

		$page = absint( $_GET['page'] ?? 0 );
		if ( $page > 0 ) {
			return $page;
		}

		$page = absint( get_query_var( 'paged' ) );
		if ( $page > 0 ) {
			return $page;
		}

		$page = absint( get_query_var( 'page' ) );
		if ( $page > 0 ) {
			return $page;
		}

		return 1;
	}

	private function get_current_directory_url() : string {
		$request_uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		return trailingslashit( home_url( '/' . ltrim( $path, '/' ) ) );
	}

	private function get_current_canonical_url() : string {
		$request_uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$path = trailingslashit( '/' . ltrim( $path, '/' ) );

		if ( get_query_var( 'maklaplace_chefs' ) ) {
			return home_url( '/chefs/' );
		}

		if ( get_query_var( 'maklaplace_favorites' ) ) {
			return home_url( '/favorites/' );
		}

		if ( get_query_var( 'maklaplace_checkout' ) ) {
			return home_url( '/checkout/' );
		}

		if ( get_query_var( 'maklaplace_orders' ) ) {
			return home_url( '/orders/' );
		}

		if ( get_query_var( 'maklaplace_order_confirmation' ) ) {
			return home_url( '/order-confirmation/' );
		}

		if ( get_query_var( 'maklaplace_order_entry' ) ) {
			$args = array(
				'chef_id' => absint( $_GET['chef_id'] ?? 0 ),
				'step'    => sanitize_key( (string) ( $_GET['step'] ?? 'customer-details' ) ),
			);

			$item_id = absint( $_GET['item_id'] ?? 0 );
			$quantity = absint( $_GET['quantity'] ?? 0 );
			if ( $item_id > 0 ) {
				$args['item_id'] = $item_id;
			}
			if ( $quantity > 0 ) {
				$args['quantity'] = $quantity;
			}
			return add_query_arg( $args, home_url( '/order/' ) );
		}

		$chef_slug = $this->get_requested_chef_slug();
		if ( '' !== $chef_slug ) {
			return home_url( '/chefs/' . $chef_slug . '/' );
		}

		return home_url( $path );
	}

	private function get_current_social_title( string $chef_slug ) : string {
		if ( get_query_var( 'maklaplace_chefs' ) ) {
			return __( 'Chefs', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_favorites' ) ) {
			return __( 'Favorites', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_checkout' ) ) {
			return __( 'Checkout', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_orders' ) ) {
			return __( 'My Orders', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_order_confirmation' ) ) {
			return __( 'Order Confirmation', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_order_entry' ) ) {
			return __( 'Start Order', 'maklaplace' );
		}

		if ( '' !== $chef_slug ) {
			$chef = $this->get_chef_by_slug( $chef_slug );
			if ( $chef instanceof WP_User ) {
				return $this->get_chef_display_name( $chef->ID );
			}
		}

		return get_bloginfo( 'name' );
	}

	private function output_structured_data() : void {
		$chef_slug = $this->get_requested_chef_slug();
		if ( '' === $chef_slug ) {
			return;
		}

		$chef = $this->get_chef_by_slug( $chef_slug );
		if ( ! $chef instanceof WP_User ) {
			return;
		}

		$profile = $this->container->get( ChefProfileService::class )->get_profile( $chef->ID ) ?? array();
		$reviews = $this->container->get( ChefReviewRepository::class )->get_stats( $chef->ID );
		$structured = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Restaurant',
			'name'     => $this->get_chef_display_name( $chef->ID ),
			'description' => (string) ( $profile[ ChefProfileKeys::BIO ] ?? '' ),
			'address'  => array(
				'@type'            => 'PostalAddress',
				'addressLocality'   => (string) ( $profile[ ChefProfileKeys::CITY ] ?? '' ),
				'addressRegion'     => (string) ( $profile[ ChefProfileKeys::WILAYA ] ?? '' ),
			),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue'  => (float) ( $reviews['average_rating'] ?? 0 ),
				'reviewCount'  => (int) ( $reviews['total_reviews'] ?? 0 ),
			),
			'url' => home_url( '/chefs/' . $this->get_chef_slug( $chef->ID ) . '/' ),
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	private function get_requested_chef_slug() : string {
		$chef_slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' !== $chef_slug ) {
			return $chef_slug;
		}

		$request_uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
		$path = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
		if ( 0 === strpos( $path, 'chefs/' ) ) {
			$chef_slug = trim( substr( $path, strlen( 'chefs/' ) ), '/' );
		}

		return sanitize_title( $chef_slug );
	}

	private function get_directory_query_args() : array {
		$args = array(
			's'           => sanitize_text_field( (string) ( $_GET['s'] ?? '' ) ),
			'cuisine'     => sanitize_text_field( (string) ( $_GET['cuisine'] ?? '' ) ),
			'city'        => sanitize_text_field( (string) ( $_GET['city'] ?? '' ) ),
			'wilaya'      => sanitize_text_field( (string) ( $_GET['wilaya'] ?? '' ) ),
			'availability' => sanitize_key( (string) ( $_GET['availability'] ?? '' ) ),
			'sort'        => sanitize_key( (string) ( $_GET['sort'] ?? 'newest' ) ),
		);

		return array_filter(
			$args,
			static fn( string $value ) : bool => '' !== $value
		);
	}

	private function render_document( string $content, string $title ) : void {
		status_header( is_404() ? 404 : 200 );
		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( $title ) . '</title>';
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );
		wp_head();
		echo '</head><body>';
		wp_body_open();
		echo $content;
		wp_footer();
		echo '</body></html>';
	}

	private function get_approved_chefs() : array {
		if ( array_key_exists( 'all', $this->approved_chefs_cache ) ) {
			return $this->approved_chefs_cache['all'];
		}

		$users = get_users(
			array(
				'role'   => 'maklaplace_chef',
				'fields' => 'all',
			)
		);

		$this->approved_chefs_cache['all'] = array_values(
			array_filter(
				$users,
				static fn( WP_User $user ) : bool => 'approved' === (string) get_user_meta( $user->ID, ChefProfileKeys::VERIFICATION_STATUS, true )
			)
		);

		return $this->approved_chefs_cache['all'];
	}

	private function get_chef_review_stats( int $chef_id ) : array {
		if ( ! array_key_exists( $chef_id, $this->chef_review_stats_cache ) ) {
			$this->chef_review_stats_cache[ $chef_id ] = $this->container->get( ChefReviewRepository::class )->get_stats( $chef_id );
		}

		return $this->chef_review_stats_cache[ $chef_id ];
	}

	private function get_chef_reviews( int $chef_id ) : array {
		$key = 'reviews_' . $chef_id;
		if ( ! array_key_exists( $key, $this->chef_reviews_cache ) ) {
			$this->chef_reviews_cache[ $key ] = $this->container->get( ChefReviewRepository::class )->get_for_chef( $chef_id );
		}

		return $this->chef_reviews_cache[ $key ];
	}

	private function get_chef_stats( int $chef_id ) : array {
		if ( ! array_key_exists( $chef_id, $this->chef_stats_cache ) ) {
			$this->chef_stats_cache[ $chef_id ] = $this->container->get( AnalyticsService::class )->get_chef_stats( $chef_id );
		}

		return $this->chef_stats_cache[ $chef_id ];
	}

	private function get_chef_menu_items( int $chef_id ) : array {
		if ( ! array_key_exists( $chef_id, $this->chef_menu_cache ) ) {
			$this->chef_menu_cache[ $chef_id ] = $this->container->get( MenuService::class )->get_menu_items_by_chef( $chef_id );
		}

		return $this->chef_menu_cache[ $chef_id ];
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
