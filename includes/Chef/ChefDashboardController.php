<?php
/**
 * Chef dashboard controller.
 *
 * @package MaklaPlace\Chef
 */

namespace MaklaPlace\Chef;

use MaklaPlace\Core\AnalyticsService;
use MaklaPlace\Core\ChefProfileService;
use MaklaPlace\Core\Container;
use MaklaPlace\Core\MenuService;
use MaklaPlace\Core\NotificationService;
use MaklaPlace\Core\OrderService;
use MaklaPlace\Core\RoleService;
use MaklaPlace\Core\WalletService;
use MaklaPlace\Helpers\ChefProfileKeys;
use MaklaPlace\Helpers\MenuKeys;
use MaklaPlace\Helpers\NotificationKeys;
use MaklaPlace\Helpers\OrderKeys;
use MaklaPlace\Helpers\UserMeta;
use MaklaPlace\Repositories\ChefReviewRepository;
use WP_List_Table;
use WP_User;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ChefDashboardController {

	private const PRODUCT_STORE = 'maklaplace_menu_items';
	private const PAGE_SLUG = 'maklaplace-chef-dashboard';

	public function __construct( private Container $container ) {
	}

	public function register_hooks() : void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_maklaplace_chef_save_profile', array( $this, 'handle_profile_submit' ) );
		add_action( 'admin_post_maklaplace_chef_save_product', array( $this, 'handle_product_submit' ) );
		add_action( 'admin_post_maklaplace_chef_delete_product', array( $this, 'handle_product_delete' ) );
		add_action( 'admin_post_maklaplace_chef_toggle_product', array( $this, 'handle_product_toggle' ) );
		add_action( 'admin_post_maklaplace_chef_toggle_featured', array( $this, 'handle_featured_toggle' ) );
		add_action( 'admin_post_maklaplace_chef_update_order_status', array( $this, 'handle_order_status' ) );
	}

	public function register_menu() : void {
		$capability = 'maklaplace_manage_own_profile';

		add_menu_page(
			__( 'Chef Dashboard', 'maklaplace' ),
			__( 'Chef Dashboard', 'maklaplace' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'dashboard_page' ),
			'dashicons-store',
			25
		);

		add_submenu_page( self::PAGE_SLUG, __( 'Dashboard', 'maklaplace' ), __( 'Dashboard', 'maklaplace' ), $capability, self::PAGE_SLUG, array( $this, 'dashboard_page' ) );
		add_submenu_page( self::PAGE_SLUG, __( 'Orders', 'maklaplace' ), __( 'Orders', 'maklaplace' ), $capability, 'maklaplace-chef-orders', array( $this, 'orders_page' ) );
		add_submenu_page( self::PAGE_SLUG, __( 'Products', 'maklaplace' ), __( 'Products', 'maklaplace' ), $capability, 'maklaplace-chef-products', array( $this, 'products_page' ) );
		add_submenu_page( self::PAGE_SLUG, __( 'Profile', 'maklaplace' ), __( 'Profile', 'maklaplace' ), $capability, 'maklaplace-chef-profile', array( $this, 'profile_page' ) );
		add_submenu_page( self::PAGE_SLUG, __( 'Wallet', 'maklaplace' ), __( 'Wallet', 'maklaplace' ), $capability, 'maklaplace-chef-wallet', array( $this, 'wallet_page' ) );
		add_submenu_page( self::PAGE_SLUG, __( 'Analytics', 'maklaplace' ), __( 'Analytics', 'maklaplace' ), $capability, 'maklaplace-chef-analytics', array( $this, 'analytics_page' ) );
		add_submenu_page( self::PAGE_SLUG, __( 'Notifications', 'maklaplace' ), __( 'Notifications', 'maklaplace' ), $capability, 'maklaplace-chef-notifications', array( $this, 'notifications_page' ) );
	}

	public function dashboard_page() : void {
		$chef_id = $this->get_current_chef_id();
		$analytics = $this->container->get( AnalyticsService::class );
		$wallet = $this->container->get( WalletService::class );
		$reviews = $this->container->get( ChefReviewRepository::class );
		$orders = $this->container->get( OrderService::class )->get_orders_by_chef( $chef_id );
		$stats = $analytics->get_chef_stats( $chef_id );
		$review_stats = $reviews->get_stats( $chef_id );
		$latest_orders = array_slice( array_reverse( $orders ), 0, 5 );
		$top_products = array_slice( (array) ( $stats['most_popular_menu_items'] ?? array() ), 0, 5, true );
		$monthly_commissions = $this->get_monthly_commissions( $wallet->get_history( $chef_id ) );

		$this->render_header( __( 'Chef Dashboard', 'maklaplace' ) );
		$this->render_widget_grid(
			array(
				array( __( 'Total Revenue', 'maklaplace' ), number_format_i18n( (float) ( $stats['total_revenue'] ?? 0 ) ) . ' DA' ),
				array( __( 'Orders This Month', 'maklaplace' ), number_format_i18n( $this->count_orders_in_month( $orders ) ) ),
				array( __( 'Orders Today', 'maklaplace' ), number_format_i18n( $this->count_orders_today( $orders ) ) ),
				array( __( 'Pending Orders', 'maklaplace' ), number_format_i18n( $this->count_orders_by_status( $orders, 'pending' ) ) ),
				array( __( 'Completed Orders', 'maklaplace' ), number_format_i18n( $this->count_orders_by_status( $orders, 'completed' ) ) ),
				array( __( 'Average Rating', 'maklaplace' ), number_format_i18n( (float) ( $review_stats['average_rating'] ?? 0 ), 1 ) ),
				array( __( 'Total Reviews', 'maklaplace' ), number_format_i18n( (int) ( $review_stats['total_reviews'] ?? 0 ) ) ),
				array( __( 'Wallet Balance', 'maklaplace' ), number_format_i18n( $wallet->get_balance( $chef_id ) ) . ' DA' ),
				array( __( 'Wallet Status', 'maklaplace' ), esc_html( ucwords( str_replace( '_', ' ', $wallet->get_status( $chef_id ) ) ) ) ),
			)
		);
		echo '<div class="wrap">';
		$this->render_section_table( __( 'Top Selling Products', 'maklaplace' ), array( __( 'Product', 'maklaplace' ), __( 'Quantity', 'maklaplace' ) ), $top_products, static function ( mixed $name, mixed $qty = null ) : array {
			return array( esc_html( $name ), esc_html( number_format_i18n( (int) $qty ) ) );
		} );
		$this->render_section_table( __( 'Latest Orders', 'maklaplace' ), array( __( 'Order', 'maklaplace' ), __( 'Customer', 'maklaplace' ), __( 'Status', 'maklaplace' ), __( 'Total', 'maklaplace' ) ), $latest_orders, function ( mixed $order, mixed $qty = null ) : array {
			$order = is_array( $order ) ? $order : array();
			return array(
				'#' . (int) ( $order['id'] ?? 0 ),
				esc_html( (string) ( $order[ OrderKeys::CUSTOMER_NAME ] ?? '' ) ),
				esc_html( ucwords( str_replace( '_', ' ', (string) ( $order[ OrderKeys::STATUS ] ?? '' ) ) ) ),
				esc_html( number_format_i18n( (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ) ) . ' DA' ),
			);
		} );
		$this->render_section_table( __( 'Monthly Commissions', 'maklaplace' ), array( __( 'Month', 'maklaplace' ), __( 'Amount', 'maklaplace' ) ), $monthly_commissions, static function ( mixed $amount, mixed $month = null ) : array {
			return array( esc_html( (string) $month ), esc_html( number_format_i18n( (float) $amount ) . ' DA' ) );
		} );
		echo '</div>';
		$this->render_footer();
	}

	public function orders_page() : void {
		$table = new ChefOrdersListTable( $this->container, $this->get_current_chef_id() );
		$table->prepare_items();
		$this->render_header( __( 'Orders', 'maklaplace' ) );
		$this->maybe_render_order_details();
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="maklaplace-chef-orders" />';
		$table->search_box( __( 'Search Orders', 'maklaplace' ), 'maklaplace-chef-orders' );
		$table->display();
		echo '</form>';
		$this->render_footer();
	}

	public function products_page() : void {
		$menu = $this->container->get( MenuService::class );
		$chef_id = $this->get_current_chef_id();
		$editing = absint( $_GET['product_id'] ?? 0 );
		$product = $editing ? $menu->get_menu_item( $editing ) : null;
		$table = new ChefProductsListTable( $this->container, $chef_id );
		$table->prepare_items();

		$this->render_header( __( 'Products', 'maklaplace' ) );
		$this->render_product_form( $product );
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="maklaplace-chef-products" />';
		$table->search_box( __( 'Search Products', 'maklaplace' ), 'maklaplace-chef-products' );
		$table->display();
		echo '</form>';
		$this->render_footer();
	}

	public function profile_page() : void {
		$chef_id = $this->get_current_chef_id();
		$profile = $this->container->get( ChefProfileService::class )->get_profile( $chef_id ) ?? array();

		$this->render_header( __( 'Profile', 'maklaplace' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'maklaplace_chef_save_profile', 'maklaplace_nonce' );
		echo '<input type="hidden" name="action" value="maklaplace_chef_save_profile" />';
		$this->render_profile_fields( $profile );
		submit_button( __( 'Save Profile', 'maklaplace' ) );
		echo '</form>';
		$this->render_footer();
	}

	public function wallet_page() : void {
		$chef_id = $this->get_current_chef_id();
		$wallet = $this->container->get( WalletService::class );
		$history = $wallet->get_history( $chef_id );
		$this->render_header( __( 'Wallet', 'maklaplace' ) );
		$this->render_widget_grid(
			array(
				array( __( 'Current Balance', 'maklaplace' ), number_format_i18n( $wallet->get_balance( $chef_id ) ) . ' DA' ),
				array( __( 'Wallet Status', 'maklaplace' ), esc_html( ucwords( str_replace( '_', ' ', $wallet->get_status( $chef_id ) ) ) ) ),
				array( __( 'Monthly Commissions', 'maklaplace' ), number_format_i18n( $this->get_monthly_commission_total( $history ) ) . ' DA' ),
			)
		);
		$this->render_history_table( $history );
		$this->render_footer();
	}

	public function analytics_page() : void {
		$chef_id = $this->get_current_chef_id();
		$analytics = $this->container->get( AnalyticsService::class );
		$stats = $analytics->get_chef_stats( $chef_id );
		$series = $analytics->calculate_time_based_metrics( 'monthly', $chef_id );
		$customer_growth = $analytics->get_customer_growth_series( $chef_id );
		$this->enqueue_chart_assets();
		$this->render_header( __( 'Analytics', 'maklaplace' ) );
		echo '<div class="wrap"><div class="chef-chart-grid"><canvas id="chef-revenue-chart" height="120"></canvas><canvas id="chef-orders-chart" height="120"></canvas><canvas id="chef-products-chart" height="120"></canvas><canvas id="chef-customers-chart" height="120"></canvas></div></div>';
		$this->render_section_table( __( 'Statistics', 'maklaplace' ), array( __( 'Metric', 'maklaplace' ), __( 'Value', 'maklaplace' ) ), array(
			array( 'metric' => __( 'Total Orders', 'maklaplace' ), 'value' => number_format_i18n( (int) ( $stats['total_orders'] ?? 0 ) ) ),
			array( 'metric' => __( 'Total Revenue', 'maklaplace' ), 'value' => number_format_i18n( (float) ( $stats['total_revenue'] ?? 0 ) ) . ' DA' ),
		), static function ( mixed $row, mixed $unused = null ) : array {
			$row = is_array( $row ) ? $row : array();
			return array( esc_html( (string) $row['metric'] ), esc_html( (string) $row['value'] ) );
		} );
		$this->render_chart_script( $chef_id, $series, $stats, $customer_growth );
		$this->render_footer();
	}

	public function notifications_page() : void {
		$table = new ChefNotificationsListTable( $this->container, $this->get_current_chef_id() );
		$table->prepare_items();
		$this->render_header( __( 'Notifications', 'maklaplace' ) );
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="maklaplace-chef-notifications" />';
		$table->search_box( __( 'Search Notifications', 'maklaplace' ), 'maklaplace-chef-notifications' );
		$table->display();
		echo '</form>';
		$this->render_footer();
	}

	public function handle_profile_submit() : void {
		$this->require_post_access( 'maklaplace_chef_save_profile', 'maklaplace_nonce' );
		$chef_id = $this->get_current_chef_id();
		$data = wp_unslash( $_POST['profile'] ?? array() );
		$this->container->get( ChefProfileService::class )->update_profile(
			$chef_id,
			array(
				ChefProfileKeys::DISPLAY_NAME => sanitize_text_field( (string) ( $data['business_name'] ?? '' ) ),
				ChefProfileKeys::BIO => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
				ChefProfileKeys::PHONE_NUMBER => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
				'whatsapp' => sanitize_text_field( (string) ( $data['whatsapp'] ?? '' ) ),
				ChefProfileKeys::ADDRESS => sanitize_text_field( (string) ( $data['address'] ?? '' ) ),
				ChefProfileKeys::CITY => sanitize_text_field( (string) ( $data['city'] ?? '' ) ),
				'wilaya' => sanitize_text_field( (string) ( $data['wilaya'] ?? '' ) ),
				ChefProfileKeys::COVER_IMAGE => esc_url_raw( (string) ( $data['cover_image'] ?? '' ) ),
				ChefProfileKeys::PROFILE_PHOTO => esc_url_raw( (string) ( $data['logo'] ?? '' ) ),
				ChefProfileKeys::WORKING_HOURS => sanitize_text_field( (string) ( $data['working_hours'] ?? '' ) ),
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=maklaplace-chef-profile&updated=1' ) );
		exit;
	}

	public function handle_product_submit() : void {
		$this->require_post_access( 'maklaplace_chef_save_product', 'maklaplace_nonce' );
		$chef_id = $this->get_current_chef_id();
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$data = wp_unslash( $_POST['profile'] ?? array() );
		$data = is_array( $data ) ? $data : array();
		$data = array(
			'title' => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'description' => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'price' => (float) ( $data['price'] ?? 0 ),
			'preparation_time' => absint( $data['preparation_time'] ?? 0 ),
			'category' => sanitize_text_field( (string) ( $data['category'] ?? '' ) ),
			'cuisine_type' => sanitize_text_field( (string) ( $data['cuisine_type'] ?? '' ) ),
			'image' => esc_url_raw( (string) ( $data['image'] ?? '' ) ),
			'availability' => ! empty( $_POST['availability'] ) ? 'available' : 'unavailable',
			'featured' => ! empty( $_POST['featured'] ),
		);
		if ( $product_id > 0 ) {
			$this->container->get( MenuService::class )->update_menu_item( $chef_id, $product_id, $data );
		} else {
			$this->container->get( MenuService::class )->create_menu_item( $chef_id, $chef_id, $data );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=maklaplace-chef-products&updated=1' ) );
		exit;
	}

	public function handle_product_delete() : void {
		$this->require_post_access( 'maklaplace_chef_delete_product', 'maklaplace_nonce' );
		$chef_id = $this->get_current_chef_id();
		$product_id = absint( $_REQUEST['product_id'] ?? 0 );
		$this->container->get( MenuService::class )->delete_menu_item( $chef_id, $product_id );
		wp_safe_redirect( admin_url( 'admin.php?page=maklaplace-chef-products&deleted=1' ) );
		exit;
	}

	public function handle_product_toggle() : void {
		$this->require_post_access( 'maklaplace_chef_toggle_product', 'maklaplace_nonce' );
		$chef_id = $this->get_current_chef_id();
		$product_id = absint( $_REQUEST['product_id'] ?? 0 );
		$enabled = ! empty( $_REQUEST['enabled'] );
		$this->container->get( MenuService::class )->set_availability( $product_id, $enabled );
		wp_safe_redirect( admin_url( 'admin.php?page=maklaplace-chef-products' ) );
		exit;
	}

	public function handle_featured_toggle() : void {
		$this->require_post_access( 'maklaplace_chef_toggle_featured', 'maklaplace_nonce' );
		$product_id = absint( $_REQUEST['product_id'] ?? 0 );
		$featured = ! empty( $_REQUEST['featured'] );
		$this->container->get( MenuService::class )->set_featured( $product_id, $featured );
		wp_safe_redirect( admin_url( 'admin.php?page=maklaplace-chef-products' ) );
		exit;
	}

	public function handle_order_status() : void {
		$this->require_post_access( 'maklaplace_chef_update_order_status', 'maklaplace_nonce' );
		$this->container->get( OrderService::class )->update_status(
			$this->get_current_chef_id(),
			absint( $_REQUEST['order_id'] ?? 0 ),
			sanitize_key( (string) ( $_REQUEST['status'] ?? '' ) )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=maklaplace-chef-orders' ) );
		exit;
	}

	private function render_product_form( ?array $product ) : void {
		$is_edit = is_array( $product ) && ! empty( $product );
		echo '<div class="card"><h2>' . esc_html( $is_edit ? __( 'Edit Product', 'maklaplace' ) : __( 'Create Product', 'maklaplace' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'maklaplace_chef_save_product', 'maklaplace_nonce' );
		echo '<input type="hidden" name="action" value="maklaplace_chef_save_product" />';
		echo '<input type="hidden" name="product_id" value="' . esc_attr( (string) ( $product['id'] ?? 0 ) ) . '" />';
		$this->input_row( 'title', __( 'Title', 'maklaplace' ), (string) ( $product[ MenuKeys::TITLE ] ?? '' ) );
		$this->textarea_row( 'description', __( 'Description', 'maklaplace' ), (string) ( $product[ MenuKeys::DESCRIPTION ] ?? '' ) );
		$this->input_row( 'price', __( 'Price', 'maklaplace' ), (string) ( $product[ MenuKeys::PRICE ] ?? '' ), 'number', '0' );
		$this->input_row( 'preparation_time', __( 'Preparation Time', 'maklaplace' ), (string) ( $product[ MenuKeys::PREPARATION_TIME ] ?? '' ), 'number', '0' );
		$this->input_row( 'category', __( 'Category', 'maklaplace' ), (string) ( $product[ MenuKeys::CATEGORY ] ?? '' ) );
		$this->input_row( 'cuisine_type', __( 'Cuisine Type', 'maklaplace' ), (string) ( $product[ MenuKeys::CUISINE_TYPE ] ?? '' ) );
		$this->input_row( 'image', __( 'Image URL', 'maklaplace' ), (string) ( $product[ MenuKeys::IMAGE ] ?? '' ) );
		$this->checkbox_row( 'availability', __( 'Available', 'maklaplace' ), 'available' === (string) ( $product[ MenuKeys::AVAILABILITY ] ?? '' ) );
		$this->checkbox_row( 'featured', __( 'Featured', 'maklaplace' ), ! empty( $product[ MenuKeys::FEATURED ] ) );
		submit_button( $is_edit ? __( 'Update Product', 'maklaplace' ) : __( 'Create Product', 'maklaplace' ) );
		echo '</form></div>';
	}

	private function render_profile_fields( array $profile ) : void {
		$fields = array(
			'business_name' => array( __( 'Business Name', 'maklaplace' ), (string) ( $profile[ ChefProfileKeys::DISPLAY_NAME ] ?? '' ) ),
			'description' => array( __( 'Description', 'maklaplace' ), (string) ( $profile[ ChefProfileKeys::BIO ] ?? '' ) ),
			'phone' => array( __( 'Phone', 'maklaplace' ), (string) ( $profile[ ChefProfileKeys::PHONE_NUMBER ] ?? '' ) ),
			'whatsapp' => array( __( 'WhatsApp', 'maklaplace' ), '' ),
			'address' => array( __( 'Address', 'maklaplace' ), (string) ( $profile[ ChefProfileKeys::ADDRESS ] ?? '' ) ),
			'wilaya' => array( __( 'Wilaya', 'maklaplace' ), '' ),
			'city' => array( __( 'City', 'maklaplace' ), (string) ( $profile[ ChefProfileKeys::CITY ] ?? '' ) ),
			'cover_image' => array( __( 'Cover Image', 'maklaplace' ), (string) ( $profile[ ChefProfileKeys::COVER_IMAGE ] ?? '' ) ),
			'logo' => array( __( 'Logo', 'maklaplace' ), (string) ( $profile[ ChefProfileKeys::PROFILE_PHOTO ] ?? '' ) ),
			'working_hours' => array( __( 'Working Hours', 'maklaplace' ), is_array( $profile[ ChefProfileKeys::WORKING_HOURS ] ?? null ) ? implode( ', ', (array) $profile[ ChefProfileKeys::WORKING_HOURS ] ) : (string) ( $profile[ ChefProfileKeys::WORKING_HOURS ] ?? '' ) ),
		);

		echo '<table class="form-table"><tbody>';
		foreach ( $fields as $key => $field ) {
			echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label></th><td><input class="regular-text" type="text" id="' . esc_attr( $key ) . '" name="profile[' . esc_attr( $key ) . ']" value="' . esc_attr( $field[1] ) . '" /></td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_history_table( array $history ) : void {
		echo '<h2>' . esc_html__( 'Collection History', 'maklaplace' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'maklaplace' ) . '</th><th>' . esc_html__( 'Amount', 'maklaplace' ) . '</th><th>' . esc_html__( 'Balance', 'maklaplace' ) . '</th><th>' . esc_html__( 'Date', 'maklaplace' ) . '</th></tr></thead><tbody>';
		if ( empty( $history ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No collection history found.', 'maklaplace' ) . '</td></tr>';
		}
		foreach ( array_reverse( $history ) as $row ) {
			echo '<tr><td>' . esc_html( ucwords( str_replace( '_', ' ', (string) ( $row['type'] ?? '' ) ) ) ) . '</td><td>' . esc_html( number_format_i18n( (float) ( $row['amount'] ?? 0 ) ) . ' DA' ) . '</td><td>' . esc_html( number_format_i18n( (float) ( $row['balance'] ?? 0 ) ) . ' DA' ) . '</td><td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function maybe_render_order_details() : void {
		$order_id = absint( $_GET['view_order'] ?? 0 );
		if ( $order_id <= 0 ) {
			return;
		}
		$order = $this->container->get( OrderService::class )->get_order( $order_id );
		if ( ! is_array( $order ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Order Details', 'maklaplace' ) . '</strong></p><p>' . esc_html__( 'Customer:', 'maklaplace' ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CUSTOMER_NAME ] ?? '' ) ) . '</p><p>' . esc_html__( 'Delivery Address:', 'maklaplace' ) . ' ' . esc_html( (string) ( $order[ OrderKeys::DELIVERY_ADDRESS ] ?? '' ) ) . '</p><p>' . esc_html__( 'Payment Method:', 'maklaplace' ) . ' ' . esc_html( (string) ( $order[ OrderKeys::PAYMENT_METHOD ] ?? __( 'Not stored', 'maklaplace' ) ) ) . '</p><p>' . esc_html__( 'Notes:', 'maklaplace' ) . ' ' . esc_html( (string) ( $order[ OrderKeys::CUSTOMER_NOTES ] ?? '' ) ) . '</p><p>' . esc_html__( 'Timeline:', 'maklaplace' ) . ' ' . esc_html( sprintf( '%s → %s', (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' ), (string) ( $order[ OrderKeys::UPDATED_AT ] ?? '' ) ) ) . '</p></div>';
	}

	private function count_orders_today( array $orders ) : int {
		$today = current_time( 'Y-m-d' );
		return count(
			array_filter(
				$orders,
				static fn( array $order ) : bool => str_starts_with( (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' ), $today )
			)
		);
	}

	private function count_orders_in_month( array $orders ) : int {
		$month = current_time( 'Y-m' );
		return count(
			array_filter(
				$orders,
				static fn( array $order ) : bool => str_starts_with( (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' ), $month )
			)
		);
	}

	private function count_orders_by_status( array $orders, string $status ) : int {
		return count(
			array_filter(
				$orders,
				static fn( array $order ) : bool => (string) ( $order[ OrderKeys::STATUS ] ?? '' ) === $status
			)
		);
	}

	private function get_monthly_commissions( array $history ) : array {
		$grouped = array();
		foreach ( $history as $entry ) {
			if ( 'commission_added' !== (string) ( $entry['type'] ?? '' ) ) {
				continue;
			}
			$month = substr( (string) ( $entry['created_at'] ?? '' ), 0, 7 );
			$grouped[ $month ] = ( $grouped[ $month ] ?? 0 ) + (float) ( $entry['amount'] ?? 0 );
		}
		krsort( $grouped );
		return $grouped;
	}

	private function get_monthly_commission_total( array $history ) : float {
		return array_reduce(
			$history,
			static fn( float $carry, array $entry ) : float => $carry + ( 'commission_added' === (string) ( $entry['type'] ?? '' ) ? (float) ( $entry['amount'] ?? 0 ) : 0.0 ),
			0.0
		);
	}

	private function enqueue_chart_assets() : void {
		wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
	}

	private function render_chart_script( int $chef_id, array $series, array $stats, array $customer_growth ) : void {
		$labels = array_keys( (array) ( $series['items'] ?? array() ) );
		$orders = array_map( static fn( array $bucket ) : int => (int) ( $bucket['orders'] ?? 0 ), (array) ( $series['items'] ?? array() ) );
		$revenue = array_map( static fn( array $bucket ) : float => (float) ( $bucket['revenue'] ?? 0 ), (array) ( $series['items'] ?? array() ) );
		$products = array_slice( array_keys( (array) ( $stats['most_popular_menu_items'] ?? array() ) ), 0, 5 );
		$product_counts = array_slice( array_values( (array) ( $stats['most_popular_menu_items'] ?? array() ) ), 0, 5 );
		$customer_labels = array_keys( (array) ( $customer_growth['items'] ?? array() ) );
		$customer_series = array_values( (array) ( $customer_growth['items'] ?? array() ) );
		$json = wp_json_encode(
			array(
				'labels' => array_values( $labels ),
				'orders' => array_values( $orders ),
				'revenue' => array_values( $revenue ),
				'products' => $products,
				'productCounts' => $product_counts,
				'customerLabels' => array_values( $customer_labels ),
				'customers' => array_values( $customer_series ),
			)
		);
		wp_add_inline_script(
			'chartjs',
			"const maklaplaceChefAnalytics = {$json};\n" .
			"if (window.Chart) { const base = {responsive:true, plugins:{legend:{display:true}}};\n" .
			"new Chart(document.getElementById('chef-revenue-chart'), {type:'line', data:{labels:maklaplaceChefAnalytics.labels, datasets:[{label:'Revenue', data:maklaplaceChefAnalytics.revenue, borderColor:'#2271b1', backgroundColor:'rgba(34,113,177,.15)'}]}, options:base});\n" .
			"new Chart(document.getElementById('chef-orders-chart'), {type:'bar', data:{labels:maklaplaceChefAnalytics.labels, datasets:[{label:'Orders', data:maklaplaceChefAnalytics.orders, backgroundColor:'#00a32a'}]}, options:base});\n" .
			"new Chart(document.getElementById('chef-products-chart'), {type:'bar', data:{labels:maklaplaceChefAnalytics.products, datasets:[{label:'Top Products', data:maklaplaceChefAnalytics.productCounts, backgroundColor:'#dba617'}]}, options:base});\n" .
			"new Chart(document.getElementById('chef-customers-chart'), {type:'line', data:{labels:maklaplaceChefAnalytics.customerLabels, datasets:[{label:'Customer Growth', data:maklaplaceChefAnalytics.customers, borderColor:'#8c8f94', backgroundColor:'rgba(140,143,148,.12)'}]}, options:base}); }"
		);
	}

	private function render_widget_grid( array $cards ) : void {
		echo '<div class="maklaplace-chef-grid">';
		foreach ( $cards as $card ) {
			echo '<div class="maklaplace-chef-card"><span>' . esc_html( $card[0] ) . '</span><strong>' . esc_html( $card[1] ) . '</strong></div>';
		}
		echo '</div>';
	}

	private function render_section_table( string $title, array $headers, array $rows, callable $mapper ) : void {
		echo '<div class="card"><h2>' . esc_html( $title ) . '</h2><table class="widefat striped"><thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="' . esc_attr( (string) count( $headers ) ) . '">' . esc_html__( 'No records found.', 'maklaplace' ) . '</td></tr>';
		}
		foreach ( $rows as $key => $row ) {
			$cols = $mapper( $row, $key );
			echo '<tr>';
			foreach ( (array) $cols as $col ) {
				echo '<td>' . wp_kses_post( (string) $col ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private function render_header( string $title ) : void {
		if ( ! $this->chef_has_access() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'maklaplace' ) );
		}
		echo '<div class="wrap maklaplace-chef"><h1>' . esc_html( $title ) . '</h1><style>.maklaplace-chef-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin:20px 0}.maklaplace-chef-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;display:flex;flex-direction:column;gap:8px}.maklaplace-chef-card span{text-transform:uppercase;font-size:12px;color:#646970}.maklaplace-chef-card strong{font-size:24px}.chef-chart-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.chef-chart-grid canvas{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px}.maklaplace-chef .widefat td,.maklaplace-chef .widefat th{vertical-align:top}</style>';
	}

	private function render_footer() : void {
		echo '</div>';
	}

	private function input_row( string $name, string $label, string $value, string $type = 'text', string $min = '' ) : void {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br /><input class="regular-text" type="' . esc_attr( $type ) . '" name="profile[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '"';
		if ( '' !== $min ) {
			echo ' min="' . esc_attr( $min ) . '"';
		}
		if ( 'number' === $type ) {
			echo ' step="any"';
		}
		echo ' /></label></p>';
	}

	private function textarea_row( string $name, string $label, string $value ) : void {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br /><textarea class="large-text" rows="4" name="profile[' . esc_attr( $name ) . ']">' . esc_textarea( $value ) . '</textarea></label></p>';
	}

	private function checkbox_row( string $name, string $label, bool $checked ) : void {
		echo '<p><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, true, false ) . ' /> ' . esc_html( $label ) . '</label></p>';
	}

	private function require_post_access( string $nonce_action, string $nonce_key ) : void {
		if ( ! $this->chef_has_access() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'maklaplace' ) );
		}
		check_admin_referer( $nonce_action, $nonce_key );
	}

	private function chef_has_access() : bool {
		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		return $this->container->get( RoleService::class )->has_role( $user->ID, 'maklaplace_chef' );
	}

	private function get_current_chef_id() : int {
		if ( ! $this->chef_has_access() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'maklaplace' ) );
		}

		return get_current_user_id();
	}
}

abstract class BaseChefListTable extends WP_List_Table {

	protected int $chef_id;
	protected Container $container;

	public function __construct( Container $container, int $chef_id, array $args ) {
		$this->container = $container;
		$this->chef_id    = $chef_id;
		parent::__construct( $args );
	}

	protected function slice_page( array $items, int $per_page ) : array {
		$current = max( 1, (int) $this->get_pagenum() );
		$total = count( $items );
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page, 'total_pages' => (int) ceil( $total / $per_page ) ) );
		return array_slice( $items, ( $current - 1 ) * $per_page, $per_page );
	}

	protected function sort_rows( array $rows, string $orderby, string $order, array $allowed ) : array {
		if ( ! in_array( $orderby, $allowed, true ) ) {
			return $rows;
		}
		usort(
			$rows,
			static function ( array $left, array $right ) use ( $orderby, $order ) : int {
				$comparison = strcmp( (string) ( $left[ $orderby ] ?? '' ), (string) ( $right[ $orderby ] ?? '' ) );
				return 'desc' === strtolower( $order ) ? -$comparison : $comparison;
			}
		);
		return $rows;
	}
}

final class ChefOrdersListTable extends BaseChefListTable {

	public function __construct( Container $container, int $chef_id ) {
		parent::__construct( $container, $chef_id, array( 'singular' => 'order', 'plural' => 'orders', 'ajax' => false ) );
	}

	public function get_columns() : array {
		return array( 'id' => '#', 'customer' => __( 'Customer', 'maklaplace' ), 'total' => __( 'Total', 'maklaplace' ), 'status' => __( 'Status', 'maklaplace' ), 'date' => __( 'Date', 'maklaplace' ), 'actions' => __( 'Actions', 'maklaplace' ) );
	}

	public function get_sortable_columns() : array {
		return array( 'id' => array( 'id', false ), 'customer' => array( 'customer', false ), 'total' => array( 'total', false ), 'status' => array( 'status', false ), 'date' => array( 'date', false ) );
	}

	public function prepare_items() : void {
		$orders = $this->container->get( OrderService::class )->get_orders_by_chef( $this->chef_id );
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		$status = sanitize_key( (string) ( $_REQUEST['status'] ?? '' ) );
		$rows = array();
		foreach ( $orders as $order ) {
			$row = array(
				'id' => (int) ( $order['id'] ?? 0 ),
				'customer' => (string) ( $order[ OrderKeys::CUSTOMER_NAME ] ?? '' ),
				'total' => (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ),
				'status' => (string) ( $order[ OrderKeys::STATUS ] ?? '' ),
				'date' => (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' ),
			);
			if ( '' !== $search && false === stripos( $row['customer'] . ' ' . $row['status'] . ' ' . (string) $row['id'], $search ) ) {
				continue;
			}
			if ( '' !== $status && $status !== $row['status'] ) {
				continue;
			}
			$rows[] = $row;
		}
		$rows = $this->sort_rows( $rows, sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'date' ) ), sanitize_key( (string) ( $_REQUEST['order'] ?? 'desc' ) ), array( 'id', 'customer', 'total', 'status', 'date' ) );
		$this->items = $this->slice_page( $rows, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'date' );
	}

	public function extra_tablenav( $which ) : void {
		if ( 'top' !== $which ) {
			return;
		}
		echo '<div class="alignleft actions"><select name="status">';
		$options = array( '' => __( 'All statuses', 'maklaplace' ), 'pending' => __( 'Pending', 'maklaplace' ), 'accepted' => __( 'Accepted', 'maklaplace' ), 'preparing' => __( 'Preparing', 'maklaplace' ), 'ready' => __( 'Ready', 'maklaplace' ), 'on_the_way' => __( 'Delivered', 'maklaplace' ), 'cancelled' => __( 'Cancelled', 'maklaplace' ) );
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( sanitize_key( (string) ( $_REQUEST['status'] ?? '' ) ), $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'maklaplace' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	public function column_actions( array $item ) : string {
		$nonce = wp_nonce_url( admin_url( 'admin-post.php?action=maklaplace_chef_update_order_status&order_id=' . (int) $item['id'] ), 'maklaplace_chef_update_order_status', 'maklaplace_nonce' );
		$statuses = array( 'accepted', 'preparing', 'ready', 'on_the_way', 'completed', 'cancelled' );
		$links = array();
		foreach ( $statuses as $status ) {
			$links[] = sprintf( '<a href="%s&status=%s">%s</a>', esc_url( $nonce ), esc_attr( $status ), esc_html( ucwords( str_replace( '_', ' ', $status ) ) ) );
		}
		return implode( ' | ', $links ) . ' | <a href="' . esc_url( admin_url( 'admin.php?page=maklaplace-chef-orders&view_order=' . (int) $item['id'] ) ) . '">' . esc_html__( 'View', 'maklaplace' ) . '</a>';
	}
}

final class ChefProductsListTable extends BaseChefListTable {

	public function __construct( Container $container, int $chef_id ) {
		parent::__construct( $container, $chef_id, array( 'singular' => 'product', 'plural' => 'products', 'ajax' => false ) );
	}

	public function get_columns() : array {
		return array( 'title' => __( 'Name', 'maklaplace' ), 'price' => __( 'Price', 'maklaplace' ), 'availability' => __( 'Availability', 'maklaplace' ), 'featured' => __( 'Featured', 'maklaplace' ), 'actions' => __( 'Actions', 'maklaplace' ) );
	}

	public function get_sortable_columns() : array {
		return array( 'title' => array( 'title', false ), 'price' => array( 'price', false ), 'availability' => array( 'availability', false ), 'featured' => array( 'featured', false ) );
	}

	public function prepare_items() : void {
		$menus = $this->container->get( MenuService::class )->get_menu_items_by_chef( $this->chef_id );
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		$availability = sanitize_key( (string) ( $_REQUEST['availability'] ?? '' ) );
		$rows = array();
		foreach ( $menus as $menu ) {
			$row = array(
				'id' => (int) ( $menu['id'] ?? 0 ),
				'title' => (string) ( $menu[ MenuKeys::TITLE ] ?? '' ),
				'price' => (float) ( $menu[ MenuKeys::PRICE ] ?? 0 ),
				'availability' => (string) ( $menu[ MenuKeys::AVAILABILITY ] ?? '' ),
				'featured' => ! empty( $menu[ MenuKeys::FEATURED ] ) ? 1 : 0,
			);
			if ( '' !== $search && false === stripos( $row['title'], $search ) ) {
				continue;
			}
			if ( '' !== $availability && $availability !== $row['availability'] ) {
				continue;
			}
			$rows[] = $row;
		}
		$rows = $this->sort_rows( $rows, sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'title' ) ), sanitize_key( (string) ( $_REQUEST['order'] ?? 'asc' ) ), array( 'title', 'price', 'availability', 'featured' ) );
		$this->items = $this->slice_page( $rows, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
	}

	public function extra_tablenav( $which ) : void {
		if ( 'top' !== $which ) {
			return;
		}
		echo '<div class="alignleft actions"><select name="availability">';
		$options = array( '' => __( 'All statuses', 'maklaplace' ), 'available' => __( 'Available', 'maklaplace' ), 'unavailable' => __( 'Unavailable', 'maklaplace' ) );
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( sanitize_key( (string) ( $_REQUEST['availability'] ?? '' ) ), $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'maklaplace' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	public function column_actions( array $item ) : string {
		$delete = wp_nonce_url( admin_url( 'admin-post.php?action=maklaplace_chef_delete_product&product_id=' . (int) $item['id'] ), 'maklaplace_chef_delete_product', 'maklaplace_nonce' );
		$toggle = wp_nonce_url( admin_url( 'admin-post.php?action=maklaplace_chef_toggle_product&product_id=' . (int) $item['id'] ), 'maklaplace_chef_toggle_product', 'maklaplace_nonce' );
		$featured = wp_nonce_url( admin_url( 'admin-post.php?action=maklaplace_chef_toggle_featured&product_id=' . (int) $item['id'] ), 'maklaplace_chef_toggle_featured', 'maklaplace_nonce' );
		return '<a href="' . esc_url( admin_url( 'admin.php?page=maklaplace-chef-products&product_id=' . (int) $item['id'] ) ) . '">' . esc_html__( 'Edit', 'maklaplace' ) . '</a> | <a href="' . esc_url( $delete ) . '">' . esc_html__( 'Delete', 'maklaplace' ) . '</a> | <a href="' . esc_url( $toggle . '&enabled=' . ( 'available' === (string) $item['availability'] ? 0 : 1 ) ) . '">' . esc_html__( 'Toggle Availability', 'maklaplace' ) . '</a> | <a href="' . esc_url( $featured . '&featured=' . ( empty( $item['featured'] ) ? 1 : 0 ) ) . '">' . esc_html__( 'Toggle Featured', 'maklaplace' ) . '</a>';
	}
}

final class ChefNotificationsListTable extends BaseChefListTable {

	public function __construct( Container $container, int $chef_id ) {
		parent::__construct( $container, $chef_id, array( 'singular' => 'notification', 'plural' => 'notifications', 'ajax' => false ) );
	}

	public function get_columns() : array {
		return array( 'recipient' => __( 'Recipient', 'maklaplace' ), 'event_type' => __( 'Event Type', 'maklaplace' ), 'status' => __( 'Status', 'maklaplace' ), 'date' => __( 'Date', 'maklaplace' ) );
	}

	public function prepare_items() : void {
		$rows = array();
		$user = wp_get_current_user();
		$recipient = $user instanceof WP_User ? $user->display_name : '';
		foreach ( $this->container->get( NotificationService::class )->get_for_user( $this->chef_id ) as $notification ) {
			$rows[] = array(
				'recipient' => $recipient,
				'event_type' => (string) ( $notification[ NotificationKeys::EVENT_TYPE ] ?? '' ),
				'status' => (string) ( $notification[ NotificationKeys::READ_STATUS ] ?? '' ),
				'date' => (string) ( $notification[ NotificationKeys::CREATED_AT ] ?? '' ),
			);
		}
		$this->items = $this->slice_page( $rows, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'date' );
	}
}
