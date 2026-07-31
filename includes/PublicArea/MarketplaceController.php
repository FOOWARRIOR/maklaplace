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
		add_shortcode( 'maklaplace_customer_dashboard', array( $this, 'render_customer_dashboard_shortcode' ) );
		add_shortcode( 'maklaplace_customer_profile', array( $this, 'render_customer_profile_shortcode' ) );
		add_shortcode( 'maklaplace_customer_addresses', array( $this, 'render_customer_addresses_shortcode' ) );
		add_shortcode( 'maklaplace_customer_notifications', array( $this, 'render_customer_notifications_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_head', array( $this, 'output_meta_description' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
		add_action( 'admin_post_maklaplace_checkout_submit', array( $this, 'handle_checkout_submit' ) );
		add_action( 'admin_post_nopriv_maklaplace_checkout_submit', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_confirm_order_received', array( $this, 'handle_confirm_order_received' ) );
		add_action( 'admin_post_nopriv_maklaplace_confirm_order_received', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_submit_order_review', array( $this, 'handle_submit_order_review' ) );
		add_action( 'admin_post_nopriv_maklaplace_submit_order_review', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_save_customer_address', array( $this, 'handle_save_customer_address' ) );
		add_action( 'admin_post_nopriv_maklaplace_save_customer_address', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_delete_customer_address', array( $this, 'handle_delete_customer_address' ) );
		add_action( 'admin_post_nopriv_maklaplace_delete_customer_address', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_set_default_customer_address', array( $this, 'handle_set_default_customer_address' ) );
		add_action( 'admin_post_nopriv_maklaplace_set_default_customer_address', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_save_customer_profile', array( $this, 'handle_save_customer_profile' ) );
		add_action( 'admin_post_nopriv_maklaplace_save_customer_profile', array( $this, 'handle_login_required' ) );
		add_action( 'admin_post_maklaplace_mark_notification_read', array( $this, 'handle_mark_notification_read' ) );
		add_action( 'admin_post_nopriv_maklaplace_mark_notification_read', array( $this, 'handle_login_required' ) );
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
		add_rewrite_rule( '^dashboard/?$', 'index.php?post_type=page&maklaplace_dashboard=1', 'top' );
		add_rewrite_rule( '^profile/?$', 'index.php?post_type=page&maklaplace_profile=1', 'top' );
		add_rewrite_rule( '^addresses/?$', 'index.php?post_type=page&maklaplace_addresses=1', 'top' );
		add_rewrite_rule( '^notifications/?$', 'index.php?post_type=page&maklaplace_notifications=1', 'top' );
		add_rewrite_rule( '^order-confirmation/?$', 'index.php?post_type=page&maklaplace_order_confirmation=1', 'top' );
		add_rewrite_rule( '^order/?$', 'index.php?post_type=page&maklaplace_order_entry=1', 'top' );
	}

	public function register_query_vars( array $vars ) : array {
		$vars[] = 'maklaplace_chefs';
		$vars[] = 'maklaplace_favorites';
		$vars[] = 'maklaplace_checkout';
		$vars[] = 'maklaplace_orders';
		$vars[] = 'maklaplace_dashboard';
		$vars[] = 'maklaplace_profile';
		$vars[] = 'maklaplace_addresses';
		$vars[] = 'maklaplace_notifications';
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

		if ( 'dashboard' === $path ) {
			$this->render_customer_dashboard_route();
			exit;
		}

		if ( 'profile' === $path ) {
			$this->render_customer_profile_route();
			exit;
		}

		if ( 'addresses' === $path ) {
			$this->render_customer_addresses_route();
			exit;
		}

		if ( 'notifications' === $path ) {
			$this->render_customer_notifications_route();
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

		if ( get_query_var( 'maklaplace_dashboard' ) ) {
			$parts['title'] = __( 'Customer Dashboard', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_profile' ) ) {
			$parts['title'] = __( 'Profile', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_addresses' ) ) {
			$parts['title'] = __( 'Addresses', 'maklaplace' );
		}

		if ( get_query_var( 'maklaplace_notifications' ) ) {
			$parts['title'] = __( 'Notifications', 'maklaplace' );
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
		} elseif ( (bool) get_query_var( 'maklaplace_dashboard' ) ) {
			$description = __( 'Manage your orders, favorites, reviews, and saved addresses on MaklaPlace.', 'maklaplace' );
		} elseif ( (bool) get_query_var( 'maklaplace_profile' ) ) {
			$description = __( 'Update your profile details, password, and default delivery address on MaklaPlace.', 'maklaplace' );
		} elseif ( (bool) get_query_var( 'maklaplace_addresses' ) ) {
			$description = __( 'Add, edit, delete, and manage your saved delivery addresses on MaklaPlace.', 'maklaplace' );
		} elseif ( (bool) get_query_var( 'maklaplace_notifications' ) ) {
			$description = __( 'View your latest order and platform notifications on MaklaPlace.', 'maklaplace' );
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
		$html = '<div class="maklaplace-panel"><div class="maklaplace-page-actions"><p>' . esc_html__( 'Manage your saved chefs here.', 'maklaplace' ) . '</p><a class="button" href="' . esc_url( home_url( '/chefs/' ) ) . '">' . esc_html__( 'Browse Chefs', 'maklaplace' ) . '</a></div></div>';
		$html .= '<div class="maklaplace-grid">';
		if ( empty( $chef_ids ) ) {
			return $html . '<div class="maklaplace-panel">' . esc_html__( 'No favorite chefs yet.', 'maklaplace' ) . '</div></div>';
		}

		foreach ( $chef_ids as $chef_id ) {
			$html .= $this->render_chef_card( $chef_id, true );
		}

		return $html . '</div>';
	}

	public function render_customer_dashboard_shortcode() : string {
		if ( ! is_user_logged_in() ) {
			return '<div class="maklaplace-panel">' . esc_html__( 'Log in to view your dashboard.', 'maklaplace' ) . '</div>';
		}

		return $this->render_customer_dashboard();
	}

	public function render_customer_profile_shortcode() : string {
		if ( ! is_user_logged_in() ) {
			return '<div class="maklaplace-panel">' . esc_html__( 'Log in to manage your profile.', 'maklaplace' ) . '</div>';
		}

		return $this->render_customer_profile();
	}

	public function render_customer_addresses_shortcode() : string {
		if ( ! is_user_logged_in() ) {
			return '<div class="maklaplace-panel">' . esc_html__( 'Log in to manage addresses.', 'maklaplace' ) . '</div>';
		}

		return $this->render_customer_addresses();
	}

	public function render_customer_notifications_shortcode() : string {
		if ( ! is_user_logged_in() ) {
			return '<div class="maklaplace-panel">' . esc_html__( 'Log in to view notifications.', 'maklaplace' ) . '</div>';
		}

		return $this->render_customer_notifications();
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
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\CustomerDashboardWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\CustomerProfileWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\CustomerAddressesWidget() );
		$widgets_manager->register( new \MaklaPlace\PublicArea\Widgets\CustomerNotificationsWidget() );
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

	private function render_customer_dashboard_route() : void {
		$this->render_document( $this->render_customer_dashboard(), __( 'Customer Dashboard', 'maklaplace' ) );
	}

	private function render_customer_profile_route() : void {
		$this->render_document( $this->render_customer_profile(), __( 'Profile', 'maklaplace' ) );
	}

	private function render_customer_addresses_route() : void {
		$this->render_document( $this->render_customer_addresses(), __( 'Addresses', 'maklaplace' ) );
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
			if ( is_array( $order ) && $order_service->can_view_order( $customer_id, $order ) ) {
				$this->render_document( $this->render_order_details_panel( $order ), __( 'Order Details', 'maklaplace' ) );
				return;
			}
		}

		$orders = $order_service->get_visible_orders_for_actor( $customer_id );

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
							(string) ( $this->get_chef_display_name( (int) ( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 ) ) ) . ' ' .
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

	private function render_customer_dashboard() : string {
		$this->require_customer_access();

		$user_id = get_current_user_id();
		$order_service = $this->container->get( OrderService::class );
		$orders = $order_service->get_orders_by_customer( $user_id );
		$favorites = $this->get_favorite_chefs( $user_id );
		$addresses = $this->get_saved_addresses( $user_id );
		$recent_orders = array_slice(
			$this->sort_orders_by_date( $orders, 'desc' ),
			0,
			5
		);
		$active_orders = count(
			array_filter(
				$orders,
				static fn( array $order ) : bool => in_array( (string) ( $order[ OrderKeys::STATUS ] ?? '' ), array( 'pending', 'accepted', 'preparing', 'ready', 'on_the_way' ), true )
			)
		);
		$completed_orders = count(
			array_filter(
				$orders,
				static fn( array $order ) : bool => 'completed' === (string) ( $order[ OrderKeys::STATUS ] ?? '' )
			)
		);
		$pending_reviews = $this->count_pending_reviews( $user_id, $orders );

		$content = '<div class="wrap maklaplace-public-marketplace"><div class="maklaplace-page-actions"><h1>' . esc_html__( 'Customer Dashboard', 'maklaplace' ) . '</h1><a class="button" href="' . esc_url( home_url( '/chefs/' ) ) . '">' . esc_html__( 'Browse Chefs', 'maklaplace' ) . '</a></div>';
		$content .= '<div class="maklaplace-grid maklaplace-dashboard-grid">';
		$content .= $this->render_dashboard_card( __( 'Active Orders', 'maklaplace' ), number_format_i18n( $active_orders ), __( 'Orders currently in progress.', 'maklaplace' ) );
		$content .= $this->render_dashboard_card( __( 'Recent Orders', 'maklaplace' ), number_format_i18n( count( $recent_orders ) ), __( 'Latest orders placed.', 'maklaplace' ) );
		$content .= $this->render_dashboard_card( __( 'Favorite Chefs', 'maklaplace' ), number_format_i18n( count( $favorites ) ), __( 'Saved chefs you can revisit.', 'maklaplace' ) );
		$content .= $this->render_dashboard_card( __( 'Completed Orders', 'maklaplace' ), number_format_i18n( $completed_orders ), __( 'Orders marked completed.', 'maklaplace' ) );
		$content .= $this->render_dashboard_card( __( 'Pending Reviews', 'maklaplace' ), number_format_i18n( $pending_reviews ), __( 'Completed orders without a review yet.', 'maklaplace' ) );
		$content .= $this->render_dashboard_card( __( 'Saved Addresses', 'maklaplace' ), number_format_i18n( count( $addresses ) ), __( 'Stored delivery addresses.', 'maklaplace' ) );
		$content .= '</div>';
		$content .= $this->render_dashboard_recent_orders( $recent_orders );
		$content .= $this->render_dashboard_favorites_preview( $favorites );
		$content .= $this->render_dashboard_addresses_preview( $addresses );
		$content .= '<div class="maklaplace-panel"><div class="maklaplace-page-actions"><h2>' . esc_html__( 'Profile', 'maklaplace' ) . '</h2><a class="button button-primary" href="' . esc_url( home_url( '/profile/' ) ) . '">' . esc_html__( 'Edit Profile', 'maklaplace' ) . '</a></div><p class="maklaplace-meta">' . esc_html__( 'Update your name, phone number, email, password, and default delivery address.', 'maklaplace' ) . '</p></div>';
		$content .= '</div>';

		return $content;
	}

	private function render_customer_profile() : string {
		$this->require_customer_access();

		$user = wp_get_current_user();
		$user_id = get_current_user_id();
		$addresses = $this->get_saved_addresses_data( $user_id );
		$default_address = (string) get_user_meta( $user_id, UserMeta::CUSTOMER_DEFAULT_ADDRESS, true );

		$content = '<div class="wrap maklaplace-public-marketplace"><div class="maklaplace-page-actions"><h1>' . esc_html__( 'Profile', 'maklaplace' ) . '</h1><a class="button" href="' . esc_url( home_url( '/dashboard/' ) ) . '">' . esc_html__( 'Back to Dashboard', 'maklaplace' ) . '</a></div>';
		$content .= '<div class="maklaplace-panel"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="maklaplace-order-form">';
		$content .= wp_nonce_field( 'maklaplace_save_customer_profile', 'maklaplace_nonce', true, false );
		$content .= '<input type="hidden" name="action" value="maklaplace_save_customer_profile">';
		$content .= '<div class="maklaplace-grid">';
		$content .= '<label><strong>' . esc_html__( 'Name', 'maklaplace' ) . '</strong><br><input type="text" name="display_name" value="' . esc_attr( $user->display_name ) . '" required></label>';
		$content .= '<label><strong>' . esc_html__( 'Phone Number', 'maklaplace' ) . '</strong><br><input type="text" name="customer_phone" value="' . esc_attr( (string) get_user_meta( $user_id, UserMeta::CUSTOMER_PHONE_NUMBER, true ) ) . '"></label>';
		$content .= '<label><strong>' . esc_html__( 'Email', 'maklaplace' ) . '</strong><br><input type="email" name="user_email" value="' . esc_attr( $user->user_email ) . '" required></label>';
		$content .= '<label><strong>' . esc_html__( 'New Password', 'maklaplace' ) . '</strong><br><input type="password" name="new_password" autocomplete="new-password" placeholder="' . esc_attr__( 'Leave blank to keep current password', 'maklaplace' ) . '"></label>';
		$content .= '<label><strong>' . esc_html__( 'Confirm Password', 'maklaplace' ) . '</strong><br><input type="password" name="confirm_password" autocomplete="new-password"></label>';
		$content .= '<label style="grid-column:1/-1"><strong>' . esc_html__( 'Default Delivery Address', 'maklaplace' ) . '</strong><br><select name="default_address_id">';
		$content .= '<option value="">' . esc_html__( 'Select a default address', 'maklaplace' ) . '</option>';
		foreach ( $addresses as $address ) {
			$content .= '<option value="' . esc_attr( (string) ( $address['id'] ?? '' ) ) . '"' . selected( $default_address, (string) ( $address['address'] ?? '' ), false ) . '>' . esc_html( $this->format_saved_address_label( $address ) ) . ' - ' . esc_html( (string) ( $address['address'] ?? '' ) ) . '</option>';
		}
		$content .= '</select></label>';
		$content .= '</div>';
		$content .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Profile', 'maklaplace' ) . '</button></p>';
		$content .= '</form></div></div>';

		return $content;
	}

	private function render_customer_addresses() : string {
		$this->require_customer_access();

		$user_id = get_current_user_id();
		$addresses = $this->get_saved_addresses_data( $user_id );
		$default_address = $this->get_default_saved_address( $addresses );
		$editing_address_id = sanitize_key( (string) ( $_GET['edit_address_id'] ?? '' ) );
		$editing_address = '' !== $editing_address_id && isset( $addresses[ $editing_address_id ] ) ? $addresses[ $editing_address_id ] : array();

		$content = '<div class="wrap maklaplace-public-marketplace"><div class="maklaplace-page-actions"><h1>' . esc_html__( 'Addresses', 'maklaplace' ) . '</h1><a class="button button-primary" href="#maklaplace-add-address">' . esc_html__( 'Add Address', 'maklaplace' ) . '</a></div>';
		$content .= '<div class="maklaplace-panel"><p>' . esc_html__( 'Saved addresses can be reused during checkout.', 'maklaplace' ) . '</p></div>';
		$content .= '<div class="maklaplace-panel" id="maklaplace-add-address"><h2>' . esc_html__( 'Add Address', 'maklaplace' ) . '</h2>';
		$content .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="maklaplace-order-form">';
		$content .= wp_nonce_field( 'maklaplace_save_customer_address', 'maklaplace_nonce', true, false );
		$content .= '<input type="hidden" name="action" value="maklaplace_save_customer_address">';
		$content .= '<input type="hidden" name="address_id" value="' . esc_attr( $editing_address_id ) . '">';
		$content .= '<p><label><strong>' . esc_html__( 'Label', 'maklaplace' ) . '</strong><br><input type="text" name="label" class="regular-text" placeholder="' . esc_attr__( 'Home', 'maklaplace' ) . '" value="' . esc_attr( (string) ( $editing_address['label'] ?? '' ) ) . '"></label></p>';
		$content .= '<p><label><strong>' . esc_html__( 'Address', 'maklaplace' ) . '</strong><br><textarea name="address" rows="4" class="large-text" required>' . esc_textarea( (string) ( $editing_address['address'] ?? '' ) ) . '</textarea></label></p>';
		$content .= '<p><label><input type="checkbox" name="is_default" value="1"' . checked( ! empty( $editing_address['is_default'] ), true, false ) . '> ' . esc_html__( 'Set as default address', 'maklaplace' ) . '</label></p>';
		$content .= '<p><button type="submit" class="button button-primary">' . esc_html__( '' !== $editing_address_id ? 'Update Address' : 'Save Address', 'maklaplace' ) . '</button></p>';
		if ( '' !== $editing_address_id ) {
			$content .= '<p><a class="button" href="' . esc_url( home_url( '/addresses/' ) ) . '">' . esc_html__( 'Cancel Edit', 'maklaplace' ) . '</a></p>';
		}
		$content .= '</form></div>';

		if ( empty( $addresses ) ) {
			return $content . '<div class="maklaplace-panel"><p>' . esc_html__( 'No saved addresses yet.', 'maklaplace' ) . '</p></div></div>';
		}

		$content .= '<div class="maklaplace-grid">';
		foreach ( $addresses as $address ) {
		$content .= $this->render_saved_address_card( $address, $default_address );
		}
		$content .= '</div></div>';

		return $content;
	}

	private function render_order_summary_card( array $order ) : string {
		$order_id = absint( $order['id'] ?? 0 );
		$status = (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' );
		$total = (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 );
		$items = (array) ( $order[ OrderKeys::ITEMS ] ?? array() );
		$chef_id = (int) ( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 );
		$chef_name = $chef_id > 0 ? $this->get_chef_display_name( $chef_id ) : '';
		$order_date = (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' );

		$html = '<article class="maklaplace-card">';
		$html .= '<h2>#' . esc_html( number_format_i18n( $order_id ) ) . '</h2>';
		$html .= '<p><strong>' . esc_html__( 'Chef:', 'maklaplace' ) . '</strong> ' . esc_html( $chef_name ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Status:', 'maklaplace' ) . '</strong> <span class="maklaplace-status-badge maklaplace-status-' . esc_attr( sanitize_key( $status ) ) . '">' . esc_html( ucfirst( $status ) ) . '</span></p>';
		$html .= '<p><strong>' . esc_html__( 'Total:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $total ) ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CURRENCY ] ?? 'DA' ) ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Items:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( count( $items ) ) ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Order Date:', 'maklaplace' ) . '</strong> ' . esc_html( $order_date ) . '</p>';
		$html .= '<p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'order_id' => $order_id ), home_url( '/orders/' ) ) ) . '">' . esc_html__( 'View Details', 'maklaplace' ) . '</a></p>';
		$html .= '</article>';

		return $html;
	}

	private function render_dashboard_card( string $title, string $value, string $description ) : string {
		return '<article class="maklaplace-card maklaplace-dashboard-card"><h2>' . esc_html( $title ) . '</h2><strong style="font-size:28px;line-height:1.1">' . esc_html( $value ) . '</strong><p class="maklaplace-meta">' . esc_html( $description ) . '</p></article>';
	}

	private function render_dashboard_recent_orders( array $orders ) : string {
		$html = '<div class="maklaplace-panel"><h2>' . esc_html__( 'Recent Orders', 'maklaplace' ) . '</h2>';
		if ( empty( $orders ) ) {
			return $html . '<p>' . esc_html__( 'No orders found.', 'maklaplace' ) . '</p></div>';
		}

		$html .= '<div class="maklaplace-grid maklaplace-order-card-grid">';
		foreach ( $orders as $order ) {
			$html .= $this->render_order_summary_card( $order );
		}
		$html .= '</div></div>';

		return $html;
	}

	private function render_dashboard_favorites_preview( array $chef_ids ) : string {
		$html = '<div class="maklaplace-panel"><h2>' . esc_html__( 'Favorite Chefs', 'maklaplace' ) . '</h2>';
		if ( empty( $chef_ids ) ) {
			return $html . '<p>' . esc_html__( 'No favorite chefs saved yet.', 'maklaplace' ) . '</p></div>';
		}

		$html .= '<div class="maklaplace-grid maklaplace-order-card-grid">';
		foreach ( array_slice( $chef_ids, 0, 4 ) as $chef_id ) {
			$html .= $this->render_chef_card( (int) $chef_id, true );
		}
		$html .= '</div><p><a class="button" href="' . esc_url( home_url( '/favorites/' ) ) . '">' . esc_html__( 'View Favorites', 'maklaplace' ) . '</a></p></div>';

		return $html;
	}

	private function render_dashboard_addresses_preview( array $addresses ) : string {
		$html = '<div class="maklaplace-panel"><h2>' . esc_html__( 'Saved Addresses', 'maklaplace' ) . '</h2>';
		if ( empty( $addresses ) ) {
			return $html . '<p>' . esc_html__( 'No saved addresses yet.', 'maklaplace' ) . '</p></div>';
		}

		$html .= '<ul style="margin:0;padding-left:18px">';
		foreach ( array_slice( $addresses, 0, 5 ) as $address ) {
			$html .= '<li>' . esc_html( (string) $address ) . '</li>';
		}
		$html .= '</ul></div>';

		return $html;
	}

	private function sort_orders_by_date( array $orders, string $direction = 'desc' ) : array {
		usort(
			$orders,
			static function ( array $left, array $right ) use ( $direction ) : int {
				$cmp = strcmp( (string) ( $left[ OrderKeys::CREATED_AT ] ?? '' ), (string) ( $right[ OrderKeys::CREATED_AT ] ?? '' ) );
				return 'asc' === $direction ? $cmp : -$cmp;
			}
		);

		return $orders;
	}

	private function get_saved_addresses( int $user_id ) : array {
		$addresses = $this->get_saved_addresses_data( $user_id );

		return array_values(
			array_filter(
				array_map(
					static fn( array $address ) : string => trim( (string) ( $address['address'] ?? '' ) ),
					$addresses
				)
			)
		);
	}

	private function get_saved_addresses_data( int $user_id ) : array {
		$addresses = get_user_meta( $user_id, UserMeta::CUSTOMER_SAVED_ADDRESSES, true );
		if ( ! is_array( $addresses ) || empty( $addresses ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $addresses as $key => $address ) {
			if ( is_string( $address ) ) {
				$address_id = is_string( $key ) && '' !== $key ? sanitize_key( $key ) : 'addr_' . substr( md5( $address ), 0, 8 );
				$normalized[ $address_id ] = array(
					'id'         => $address_id,
					'label'      => '',
					'address'    => trim( $address ),
					'is_default' => false,
					'updated_at' => '',
				);
				continue;
			}

			if ( ! is_array( $address ) ) {
				continue;
			}

			$address_id = sanitize_key( (string) ( $address['id'] ?? ( is_string( $key ) ? $key : '' ) ) );
			if ( '' === $address_id ) {
				$address_id = 'addr_' . wp_generate_password( 8, false, false );
			}

			$normalized[ $address_id ] = array(
				'id'         => $address_id,
				'label'      => sanitize_text_field( (string) ( $address['label'] ?? '' ) ),
				'address'    => sanitize_textarea_field( (string) ( $address['address'] ?? '' ) ),
				'is_default' => ! empty( $address['is_default'] ),
				'updated_at' => sanitize_text_field( (string) ( $address['updated_at'] ?? '' ) ),
			);
		}

		return $normalized;
	}

	private function save_customer_addresses( int $user_id, array $addresses ) : void {
		$normalized = array();
		$default_address = '';

		foreach ( $addresses as $address_id => $address ) {
			if ( ! is_array( $address ) ) {
				continue;
			}

			$address_id = sanitize_key( (string) $address_id );
			$address_text = sanitize_textarea_field( (string) ( $address['address'] ?? '' ) );
			if ( '' === $address_id || '' === trim( $address_text ) ) {
				continue;
			}

			$is_default = ! empty( $address['is_default'] );
			$normalized[ $address_id ] = array(
				'id'         => $address_id,
				'label'      => sanitize_text_field( (string) ( $address['label'] ?? '' ) ),
				'address'    => $address_text,
				'is_default' => $is_default,
				'updated_at' => sanitize_text_field( (string) ( $address['updated_at'] ?? current_time( 'mysql' ) ) ),
			);

			if ( $is_default ) {
				$default_address = $address_text;
			}
		}

		if ( '' === $default_address && ! empty( $normalized ) ) {
			$first_key = array_key_first( $normalized );
			if ( null !== $first_key ) {
				$normalized[ $first_key ]['is_default'] = true;
				$default_address = (string) $normalized[ $first_key ]['address'];
			}
		}

		update_user_meta( $user_id, UserMeta::CUSTOMER_SAVED_ADDRESSES, $normalized );
		update_user_meta( $user_id, UserMeta::CUSTOMER_DEFAULT_ADDRESS, $default_address );
	}

	private function get_default_saved_address( array $addresses ) : array {
		foreach ( $addresses as $address ) {
			if ( ! empty( $address['is_default'] ) ) {
				return $address;
			}
		}

		return array();
	}

	private function render_saved_address_card( array $address, array $default_address ) : string {
		$address_id = (string) ( $address['id'] ?? '' );
		$is_default = ! empty( $address['is_default'] ) || ( ! empty( $default_address ) && $address_id === (string) ( $default_address['id'] ?? '' ) );
		$label = $this->format_saved_address_label( $address );

		$html = '<article class="maklaplace-card">';
		$html .= '<h2>' . esc_html( $label ) . '</h2>';
		$html .= '<p>' . esc_html( (string) ( $address['address'] ?? '' ) ) . '</p>';
		if ( $is_default ) {
			$html .= '<p><span class="maklaplace-chip">' . esc_html__( 'Default', 'maklaplace' ) . '</span></p>';
		}
		$html .= '<div class="maklaplace-actions">';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= wp_nonce_field( 'maklaplace_set_default_customer_address', 'maklaplace_nonce', true, false );
		$html .= '<input type="hidden" name="action" value="maklaplace_set_default_customer_address">';
		$html .= '<input type="hidden" name="address_id" value="' . esc_attr( $address_id ) . '">';
		$html .= '<button type="submit" class="button">' . esc_html__( 'Set Default', 'maklaplace' ) . '</button>';
		$html .= '</form>';
		$html .= '<a class="button" href="' . esc_url( add_query_arg( array( 'edit_address_id' => $address_id ), home_url( '/addresses/' ) ) . '#maklaplace-add-address' ) . '">' . esc_html__( 'Edit', 'maklaplace' ) . '</a>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Delete this address?', 'maklaplace' ) ) . '\');">';
		$html .= wp_nonce_field( 'maklaplace_delete_customer_address', 'maklaplace_nonce', true, false );
		$html .= '<input type="hidden" name="action" value="maklaplace_delete_customer_address">';
		$html .= '<input type="hidden" name="address_id" value="' . esc_attr( $address_id ) . '">';
		$html .= '<button type="submit" class="button button-link-delete">' . esc_html__( 'Delete', 'maklaplace' ) . '</button>';
		$html .= '</form>';
		$html .= '</div></article>';

		return $html;
	}

	private function format_saved_address_label( array $address ) : string {
		$label = trim( (string) ( $address['label'] ?? '' ) );
		if ( '' !== $label ) {
			return $label;
		}

		return __( 'Saved Address', 'maklaplace' );
	}

	private function count_pending_reviews( int $customer_id, array $orders ) : int {
		$completed_orders = array_values(
			array_filter(
				$orders,
				static fn( array $order ) : bool => 'completed' === (string) ( $order[ OrderKeys::STATUS ] ?? '' )
			)
		);

		if ( empty( $completed_orders ) ) {
			return 0;
		}

		$reviews = $this->container->get( ChefReviewRepository::class )->get_all();
		$reviewed_order_ids = array();
		$reviewed_by_order = false;
		foreach ( $reviews as $review ) {
			if ( isset( $review['order_id'] ) ) {
				$reviewed_by_order = true;
				$reviewed_order_ids[ absint( $review['order_id'] ) ] = true;
			}
		}

		if ( $reviewed_by_order ) {
			return count(
				array_filter(
					$completed_orders,
					static fn( array $order ) : bool => ! isset( $reviewed_order_ids[ absint( $order['id'] ?? 0 ) ] )
				)
			);
		}

		return 0;
	}

	private function render_order_details_panel( array $order ) : string {
		$order_id = absint( $order['id'] ?? 0 );
		$chef_id = (int) ( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 );
		$chef_name = $chef_id > 0 ? $this->get_chef_display_name( $chef_id ) : __( 'Unknown', 'maklaplace' );
		$order_date = (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' );
		$review_eligible = $this->container->get( OrderService::class )->can_customer_review_order( $order );
		$existing_review = $this->container->get( ChefReviewRepository::class )->get_by_order( $order_id );
		$content = '<div class="wrap maklaplace-public-marketplace"><h1>' . esc_html__( 'Order Details', 'maklaplace' ) . '</h1><div class="maklaplace-panel">';
		$content .= '<p><strong>' . esc_html__( 'Order Number:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( $order_id ) ) . '</p>';
		$content .= '<p><strong>' . esc_html__( 'Status:', 'maklaplace' ) . '</strong> <span class="maklaplace-status-badge maklaplace-status-' . esc_attr( sanitize_key( (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' ) ) ) . '">' . esc_html( ucfirst( (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' ) ) ) . '</span></p>';
		$content .= '<p><strong>' . esc_html__( 'Chef:', 'maklaplace' ) . '</strong> ' . esc_html( $chef_name ) . '</p>';
		$content .= '<p><strong>' . esc_html__( 'Total:', 'maklaplace' ) . '</strong> ' . esc_html( number_format_i18n( (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ) ) ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CURRENCY ] ?? 'DA' ) ) . '</p>';
		$content .= '<p><strong>' . esc_html__( 'Order Date:', 'maklaplace' ) . '</strong> ' . esc_html( $order_date ) . '</p>';
		$content .= '<h2>' . esc_html__( 'Products', 'maklaplace' ) . '</h2><ul>';
		foreach ( (array) ( $order[ OrderKeys::ITEMS ] ?? array() ) as $item ) {
			$content .= '<li>' . esc_html( (string) ( $item['item_name'] ?? '' ) ) . ' x ' . esc_html( number_format_i18n( (int) ( $item['quantity'] ?? 0 ) ) ) . ' - ' . esc_html( number_format_i18n( (float) ( $item['total'] ?? 0 ) ) ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CURRENCY ] ?? 'DA' ) ) . '</li>';
		}
		$content .= '</ul><h2>' . esc_html__( 'Tracking', 'maklaplace' ) . '</h2>';
		$content .= $this->render_customer_order_timeline( (string) ( $order[ OrderKeys::STATUS ] ?? 'pending' ) );
		if ( 'completed' === (string) ( $order[ OrderKeys::STATUS ] ?? '' ) ) {
			$content .= '<h2>' . esc_html__( 'Order Confirmation', 'maklaplace' ) . '</h2>';
			if ( ! empty( $order[ OrderKeys::CUSTOMER_RECEIVED_CONFIRMED ] ) ) {
				$content .= '<p>' . esc_html__( 'You confirmed that this order was successfully received.', 'maklaplace' ) . '</p>';
			} else {
				$content .= '<p>' . esc_html__( 'Please confirm that you successfully received this order before leaving a review.', 'maklaplace' ) . '</p>';
				$content .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="maklaplace-order-form">';
				$content .= wp_nonce_field( 'maklaplace_confirm_order_received', 'maklaplace_nonce', true, false );
				$content .= '<input type="hidden" name="action" value="maklaplace_confirm_order_received">';
				$content .= '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order_id ) . '">';
				$content .= '<button type="submit" class="button button-primary">' . esc_html__( 'Confirm Received', 'maklaplace' ) . '</button>';
				$content .= '</form>';
			}
		}
		$content .= $review_eligible ? '<p class="maklaplace-meta">' . esc_html__( 'This order is now eligible for reviews.', 'maklaplace' ) . '</p>' : '<p class="maklaplace-meta">' . esc_html__( 'Reviews become available after you confirm receipt.', 'maklaplace' ) . '</p>';
		if ( $review_eligible && ! is_array( $existing_review ) ) {
			$content .= $this->render_order_review_form( $order );
		} elseif ( is_array( $existing_review ) ) {
			$content .= '<p>' . esc_html__( 'You already submitted a review for this order.', 'maklaplace' ) . '</p>';
			if ( $this->container->get( ChefReviewRepository::class )->can_edit_review( $existing_review ) ) {
				$content .= $this->render_order_review_form( $order, $existing_review );
			}
		}
		$content .= '<p><a class="button" href="' . esc_url( home_url( '/orders/' ) ) . '">' . esc_html__( 'Back to Orders', 'maklaplace' ) . '</a></p>';
		$content .= '</div></div>';

		return $content;
	}

	private function render_customer_order_timeline( string $current_status ) : string {
		$steps = array(
			'pending'    => __( 'Pending', 'maklaplace' ),
			'accepted'   => __( 'Accepted', 'maklaplace' ),
			'preparing'  => __( 'Preparing', 'maklaplace' ),
			'ready'      => __( 'Ready', 'maklaplace' ),
			'on_the_way' => __( 'On the way', 'maklaplace' ),
			'completed'  => __( 'Completed', 'maklaplace' ),
			'cancelled'  => __( 'Cancelled', 'maklaplace' ),
		);

		$normalized = sanitize_key( $current_status );
		$timeline = '<ol class="maklaplace-order-timeline" style="list-style:none;margin:0;padding:0;display:grid;gap:10px">';
		foreach ( $steps as $key => $label ) {
			$is_current = $key === $normalized;
			$is_done = ! $is_current && $this->is_order_timeline_step_done( $key, $normalized );
			$state = $is_current ? 'current' : ( $is_done ? 'done' : 'upcoming' );
			$timeline .= '<li class="maklaplace-order-timeline-item maklaplace-order-timeline-' . esc_attr( $state ) . '" style="display:flex;align-items:center;gap:10px">';
			$timeline .= '<span class="maklaplace-status-badge maklaplace-status-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</span>';
			$timeline .= '<span class="maklaplace-meta">' . esc_html( $is_current ? __( 'Current status', 'maklaplace' ) : ( $is_done ? __( 'Completed', 'maklaplace' ) : __( 'Pending next step', 'maklaplace' ) ) ) . '</span>';
			$timeline .= '</li>';
		}
		$timeline .= '</ol>';

		return $timeline;
	}

	private function render_order_review_form( array $order, array $existing_review = array() ) : string {
		$order_id = absint( $order['id'] ?? 0 );
		$rating = absint( $existing_review['rating'] ?? 5 );
		$comment = (string) ( $existing_review['comment'] ?? '' );
		$title = is_array( $existing_review ) && ! empty( $existing_review ) ? __( 'Edit Review', 'maklaplace' ) : __( 'Leave a Review', 'maklaplace' );

		$html = '<h2>' . esc_html( $title ) . '</h2>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="maklaplace-order-form">';
		$html .= wp_nonce_field( 'maklaplace_submit_order_review', 'maklaplace_nonce', true, false );
		$html .= '<input type="hidden" name="action" value="maklaplace_submit_order_review">';
		$html .= '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order_id ) . '">';
		$html .= '<p><label><strong>' . esc_html__( 'Rating', 'maklaplace' ) . '</strong><br><select name="rating" required>';
		for ( $i = 5; $i >= 1; $i-- ) {
			$html .= '<option value="' . esc_attr( (string) $i ) . '"' . selected( $rating, $i, false ) . '>' . esc_html( number_format_i18n( $i ) ) . '</option>';
		}
		$html .= '</select></label></p>';
		$html .= '<p><label><strong>' . esc_html__( 'Comment', 'maklaplace' ) . '</strong><br><textarea name="comment" rows="4" class="large-text">' . esc_textarea( $comment ) . '</textarea></label></p>';
		$html .= '<p><button type="submit" class="button button-primary">' . esc_html( $title ) . '</button></p>';
		$html .= '</form>';

		return $html;
	}

	private function is_order_timeline_step_done( string $step, string $current_status ) : bool {
		$sequence = array( 'pending', 'accepted', 'preparing', 'ready', 'on_the_way', 'completed' );
		$current_index = array_search( $current_status, $sequence, true );
		$step_index = array_search( $step, $sequence, true );

		if ( false === $current_index || false === $step_index ) {
			return false;
		}

		return $step_index < $current_index;
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
		$html .= '<div style="grid-column:1/-1">' . $this->render_checkout_address_select( get_current_user_id(), $details['delivery_address'] ) . '</div>';
		$html .= '<label style="grid-column:1/-1"><strong>' . esc_html__( 'Delivery Address', 'maklaplace' ) . '</strong><br><textarea name="delivery_address" rows="4" required>' . esc_textarea( $details['delivery_address'] ) . '</textarea></label>';
		$html .= '<label style="grid-column:1/-1"><strong>' . esc_html__( 'Notes', 'maklaplace' ) . '</strong><br><textarea name="customer_notes" rows="4">' . esc_textarea( $details['customer_notes'] ) . '</textarea></label>';
		$html .= '</div>';
		$html .= '<p><strong>' . esc_html__( 'Payment Method:', 'maklaplace' ) . '</strong> ' . esc_html__( 'Cash on Delivery (COD)', 'maklaplace' ) . '</p>';
		$html .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Place Order', 'maklaplace' ) . '</button></p>';
		$html .= '</form></div></div>';

		return $html;
	}

	private function render_checkout_address_select( int $user_id, string $current_address ) : string {
		$addresses = $this->get_saved_addresses_data( $user_id );
		if ( empty( $addresses ) ) {
			return '<p class="maklaplace-meta">' . esc_html__( 'No saved addresses yet. Add one on the Addresses page.', 'maklaplace' ) . '</p>';
		}

		$html = '<label><strong>' . esc_html__( 'Saved Addresses', 'maklaplace' ) . '</strong><br><select name="selected_saved_address" class="regular-text">';
		$html .= '<option value="">' . esc_html__( 'Use current delivery address', 'maklaplace' ) . '</option>';
		foreach ( $addresses as $address ) {
			$label = $this->format_saved_address_label( $address );
			$is_selected = '' !== $current_address && $current_address === (string) ( $address['address'] ?? '' );
			$html .= '<option value="' . esc_attr( (string) ( $address['id'] ?? '' ) ) . '"' . selected( $is_selected, true, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></label>';
		$html .= '<p class="maklaplace-meta">' . esc_html__( 'Select a saved address to reuse it during checkout. You can still edit the delivery address below.', 'maklaplace' ) . '</p>';

		return $html;
	}

	public function handle_checkout_submit() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_checkout_submit', 'maklaplace_nonce' );

		$user_id = get_current_user_id();
		$selected_address_id = sanitize_key( (string) ( $_POST['selected_saved_address'] ?? '' ) );
		$selected_address = '';
		if ( '' !== $selected_address_id ) {
			$addresses = $this->get_saved_addresses_data( $user_id );
			if ( isset( $addresses[ $selected_address_id ] ) ) {
				$selected_address = (string) ( $addresses[ $selected_address_id ]['address'] ?? '' );
			}
		}

		$delivery_address = sanitize_textarea_field( (string) ( $_POST['delivery_address'] ?? '' ) );
		if ( '' !== $selected_address ) {
			$delivery_address = $selected_address;
		}

		$cart_service = $this->container->get( CartService::class );
		$cart_service->set_customer_details(
			$user_id,
			array(
				'customer_name'    => sanitize_text_field( (string) ( $_POST['customer_name'] ?? '' ) ),
				'customer_phone'   => sanitize_text_field( (string) ( $_POST['customer_phone'] ?? '' ) ),
				'delivery_address' => $delivery_address,
				'customer_notes'   => wp_kses_post( (string) ( $_POST['customer_notes'] ?? '' ) ),
			)
		);

		$payload = $cart_service->build_order_payload( $user_id );
		if ( is_wp_error( $payload ) ) {
			wp_die( esc_html( $payload->get_error_message() ) );
		}

		$order_service = $this->container->get( OrderService::class );
		$order = $order_service->create_order( get_current_user_id(), $payload );
		if ( is_wp_error( $order ) ) {
			wp_die( esc_html( $order->get_error_message() ) );
		}

		do_action( 'maklaplace_order_confirmed', $order );

		$cart_service->clear_cart( get_current_user_id() );
		wp_safe_redirect( add_query_arg( array( 'order_id' => (int) ( $order['id'] ?? 0 ) ), home_url( '/order-confirmation/' ) ) );
		exit;
	}

	private function render_order_confirmation_route() : void {
		$order_id = absint( $_GET['order_id'] ?? 0 );
		$order_service = $this->container->get( OrderService::class );
		$order = $order_id > 0 ? $order_service->get_order( $order_id ) : null;

		if ( ! is_array( $order ) || ! $order_service->can_view_order( get_current_user_id(), $order ) ) {
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

	public function handle_confirm_order_received() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_confirm_order_received', 'maklaplace_nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order_service = $this->container->get( OrderService::class );
		$result = $order_service->confirm_receipt( get_current_user_id(), $order_id );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( array( 'order_id' => $order_id, 'confirmed' => 1 ), home_url( '/orders/' ) ) );
		exit;
	}

	public function handle_submit_order_review() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_submit_order_review', 'maklaplace_nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order_service = $this->container->get( OrderService::class );
		$order = $order_service->get_order( $order_id );
		if ( ! is_array( $order ) || ! $order_service->can_view_order( get_current_user_id(), $order ) ) {
			wp_die( esc_html__( 'Order not found.', 'maklaplace' ) );
		}

		if ( ! $order_service->can_customer_review_order( $order ) ) {
			wp_die( esc_html__( 'This order is not yet eligible for a review.', 'maklaplace' ) );
		}

		$repository = $this->container->get( ChefReviewRepository::class );
		$existing = $repository->get_by_order( $order_id );
		if ( is_array( $existing ) && ! $repository->can_edit_review( $existing ) ) {
			wp_die( esc_html__( 'Your review can no longer be edited.', 'maklaplace' ) );
		}

		$review = $repository->upsert_review(
			array(
				'order_id'        => $order_id,
				'customer_user_id' => get_current_user_id(),
				'chef_user_id'     => (int) ( $order[ OrderKeys::CHEF_USER_ID ] ?? 0 ),
				'rating'          => absint( $_POST['rating'] ?? 0 ),
				'comment'         => sanitize_textarea_field( (string) ( $_POST['comment'] ?? '' ) ),
				'reviewer_name'   => wp_get_current_user() instanceof WP_User ? wp_get_current_user()->display_name : '',
			)
		);

		if ( is_wp_error( $review ) ) {
			wp_die( esc_html( $review->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( array( 'order_id' => $order_id, 'reviewed' => 1 ), home_url( '/orders/' ) ) );
		exit;
	}

	public function handle_save_customer_address() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_save_customer_address', 'maklaplace_nonce' );

		$address_id = sanitize_key( (string) ( $_POST['address_id'] ?? '' ) );
		$label = sanitize_text_field( (string) ( $_POST['label'] ?? '' ) );
		$address = sanitize_textarea_field( (string) ( $_POST['address'] ?? '' ) );
		$default = ! empty( $_POST['is_default'] );

		if ( '' === trim( $address ) ) {
			wp_die( esc_html__( 'Address is required.', 'maklaplace' ) );
		}

		$addresses = $this->get_saved_addresses_data( get_current_user_id() );
		if ( '' === $address_id ) {
			$address_id = 'addr_' . wp_generate_password( 8, false, false );
		}
		$addresses[ $address_id ] = array(
			'id'          => $address_id,
			'label'       => $label,
			'address'     => $address,
			'is_default'  => $default,
			'updated_at'  => current_time( 'mysql' ),
		);
		if ( $default ) {
			foreach ( $addresses as $key => $item ) {
				if ( $key !== $address_id ) {
					$addresses[ $key ]['is_default'] = false;
				}
			}
		}
		$this->save_customer_addresses( get_current_user_id(), $addresses );
		wp_safe_redirect( wp_get_referer() ?: home_url( '/addresses/' ) );
		exit;
	}

	public function handle_delete_customer_address() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_delete_customer_address', 'maklaplace_nonce' );
		$address_id = sanitize_key( (string) ( $_POST['address_id'] ?? '' ) );
		$addresses = $this->get_saved_addresses_data( get_current_user_id() );
		unset( $addresses[ $address_id ] );
		$this->save_customer_addresses( get_current_user_id(), $addresses );
		wp_safe_redirect( wp_get_referer() ?: home_url( '/addresses/' ) );
		exit;
	}

	public function handle_set_default_customer_address() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_set_default_customer_address', 'maklaplace_nonce' );
		$address_id = sanitize_key( (string) ( $_POST['address_id'] ?? '' ) );
		$addresses = $this->get_saved_addresses_data( get_current_user_id() );
		if ( isset( $addresses[ $address_id ] ) ) {
			foreach ( $addresses as $key => $item ) {
				$addresses[ $key ]['is_default'] = $key === $address_id;
			}
			$this->save_customer_addresses( get_current_user_id(), $addresses );
		}
		wp_safe_redirect( wp_get_referer() ?: home_url( '/addresses/' ) );
		exit;
	}

	public function handle_save_customer_profile() : void {
		$this->require_customer_access();
		check_admin_referer( 'maklaplace_save_customer_profile', 'maklaplace_nonce' );

		$user_id = get_current_user_id();
		$display_name = sanitize_text_field( (string) ( $_POST['display_name'] ?? '' ) );
		$customer_phone = sanitize_text_field( (string) ( $_POST['customer_phone'] ?? '' ) );
		$user_email = sanitize_email( (string) ( $_POST['user_email'] ?? '' ) );
		$new_password = (string) ( $_POST['new_password'] ?? '' );
		$confirm_password = (string) ( $_POST['confirm_password'] ?? '' );
		$default_address_id = sanitize_key( (string) ( $_POST['default_address_id'] ?? '' ) );

		if ( '' === $display_name || '' === $user_email ) {
			wp_die( esc_html__( 'Name and email are required.', 'maklaplace' ) );
		}

		if ( ! is_email( $user_email ) ) {
			wp_die( esc_html__( 'Please enter a valid email address.', 'maklaplace' ) );
		}

		$current_user = get_userdata( $user_id );
		if ( $current_user instanceof WP_User && $user_email !== $current_user->user_email ) {
			$existing_user = get_user_by( 'email', $user_email );
			if ( $existing_user instanceof WP_User && (int) $existing_user->ID !== $user_id ) {
				wp_die( esc_html__( 'That email address is already in use.', 'maklaplace' ) );
			}
		}

		if ( '' !== $new_password || '' !== $confirm_password ) {
			if ( $new_password !== $confirm_password ) {
				wp_die( esc_html__( 'Passwords do not match.', 'maklaplace' ) );
			}

			if ( strlen( $new_password ) < 8 ) {
				wp_die( esc_html__( 'Password must be at least 8 characters long.', 'maklaplace' ) );
			}
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $display_name,
				'user_email'   => $user_email,
			)
		);

		update_user_meta( $user_id, UserMeta::CUSTOMER_PHONE_NUMBER, $customer_phone );

		if ( '' !== $new_password ) {
			wp_update_user(
				array(
					'ID'        => $user_id,
					'user_pass' => $new_password,
				)
			);
		}

		if ( '' !== $default_address_id ) {
			$addresses = $this->get_saved_addresses_data( $user_id );
			if ( isset( $addresses[ $default_address_id ] ) ) {
				foreach ( $addresses as $key => $item ) {
					$addresses[ $key ]['is_default'] = $key === $default_address_id;
				}
				$this->save_customer_addresses( $user_id, $addresses );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'updated' => 1 ), wp_get_referer() ?: home_url( '/profile/' ) ) );
		exit;
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
