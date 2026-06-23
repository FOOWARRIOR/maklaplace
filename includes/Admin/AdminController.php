<?php
/**
 * Admin controller and list tables.
 *
 * @package MaklaPlace\Admin
 */

namespace MaklaPlace\Admin;

use MaklaPlace\Core\AnalyticsService;
use MaklaPlace\Core\ChefProfileService;
use MaklaPlace\Core\Container;
use MaklaPlace\Core\MenuService;
use MaklaPlace\Core\NotificationService;
use MaklaPlace\Core\OrderService;
use MaklaPlace\Core\UserService;
use MaklaPlace\Core\WalletService;
use MaklaPlace\Helpers\ChefProfileKeys;
use MaklaPlace\Helpers\MenuKeys;
use MaklaPlace\Helpers\NotificationKeys;
use MaklaPlace\Helpers\OrderKeys;
use MaklaPlace\Helpers\UserMeta;
use MaklaPlace\Helpers\WalletHelper;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class AdminController {

	private const SETTINGS_OPTION = 'maklaplace_settings';
	private const DB_SCHEMA_VERSION = '1.0.0';

	public function __construct(
		private Container $container
	) {
	}

	public function register_hooks() : void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_post_maklaplace_save_settings', array( $this, 'handle_settings_submit' ) );
	}

	public function register_settings() : void {
		register_setting(
			'maklaplace_settings_group',
			self::SETTINGS_OPTION,
			array( $this, 'sanitize_settings' )
		);
	}

	public function dashboard_page() : void {
		$analytics = $this->container->get( AnalyticsService::class );
		$stats     = $analytics->get_platform_stats();
		$summary   = $this->get_dashboard_summary( $stats );

		$this->render_header( __( 'Dashboard', 'maklaplace' ) );
		echo '<div class="maklaplace-admin-grid">';
		foreach ( $summary as $card ) {
			echo '<div class="maklaplace-card"><div class="maklaplace-card__label">' . esc_html( $card['label'] ) . '</div><div class="maklaplace-card__value">' . esc_html( $card['value'] ) . '</div></div>';
		}
		echo '</div>';
		$this->render_stats_table( $stats );
		$this->render_footer();
	}

	public function orders_page() : void {
		$table = new Orders_List_Table( $this->container );
		$table->prepare_items();
		$this->render_header( __( 'Orders', 'maklaplace' ) );
		$this->render_bulk_notice();
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="maklaplace-orders" />';
		$table->search_box( __( 'Search Orders', 'maklaplace' ), 'maklaplace-orders' );
		$table->display();
		echo '</form>';
		$this->render_footer();
	}

	public function chefs_page() : void {
		$table = new Chefs_List_Table( $this->container );
		$table->prepare_items();
		$this->render_header( __( 'Chefs', 'maklaplace' ) );
		$table->display();
		$this->render_footer();
	}

	public function customers_page() : void {
		$table = new Customers_List_Table( $this->container );
		$table->prepare_items();
		$this->render_header( __( 'Customers', 'maklaplace' ) );
		$table->display();
		$this->render_footer();
	}

	public function menus_page() : void {
		$table = new Menus_List_Table( $this->container );
		$table->prepare_items();
		$this->render_header( __( 'Menus', 'maklaplace' ) );
		$table->display();
		$this->render_footer();
	}

	public function wallets_page() : void {
		$table = new Wallets_List_Table( $this->container );
		$table->prepare_items();
		$this->render_header( __( 'Wallets', 'maklaplace' ) );
		$table->display();
		$this->render_footer();
	}

	public function notifications_page() : void {
		$table = new Notifications_List_Table( $this->container );
		$table->prepare_items();
		$this->render_header( __( 'Notifications', 'maklaplace' ) );
		$table->display();
		$this->render_footer();
	}

	public function analytics_page() : void {
		$analytics = $this->container->get( AnalyticsService::class );
		$this->render_header( __( 'Analytics', 'maklaplace' ) );
		$this->render_key_value_table( $analytics->get_platform_stats() );
		$this->render_key_value_table( array( 'time_based_metrics' => $analytics->calculate_time_based_metrics() ) );
		$this->render_footer();
	}

	public function settings_page() : void {
		$this->render_header( __( 'Settings', 'maklaplace' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'maklaplace_save_settings', 'maklaplace_settings_nonce' );
		echo '<input type="hidden" name="action" value="maklaplace_save_settings" />';
		$this->render_settings_sections();
		submit_button();
		echo '</form>';
		$this->render_footer();
	}

	public function tools_page() : void {
		$this->render_header( __( 'Tools', 'maklaplace' ) );
		echo '<div class="card"><p><strong>' . esc_html__( 'Plugin version', 'maklaplace' ) . ':</strong> ' . esc_html( MAKLAPLACE_VERSION ) . '</p><p><strong>' . esc_html__( 'Database schema version', 'maklaplace' ) . ':</strong> ' . esc_html( self::DB_SCHEMA_VERSION ) . '</p><p><strong>' . esc_html__( 'Clear plugin cache', 'maklaplace' ) . ':</strong> ' . esc_html__( 'No cache layer is currently configured.', 'maklaplace' ) . '</p><p><strong>' . esc_html__( 'Recalculate wallet balances', 'maklaplace' ) . ':</strong> ' . esc_html__( 'Wallet balances are stored on user meta and can be recalculated from order history.', 'maklaplace' ) . '</p><p><strong>' . esc_html__( 'Rebuild analytics', 'maklaplace' ) . ':</strong> ' . esc_html__( 'Analytics are derived from stored orders and event logs.', 'maklaplace' ) . '</p></div>';
		$this->render_system_info();
		$this->render_footer();
	}

	public function handle_actions() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( (string) ( $_POST['maklaplace_action'] ?? '' ) );
		if ( '' === $action ) {
			$action = sanitize_key( (string) ( $_GET['maklaplace_action'] ?? '' ) );
		}
		if ( '' === $action ) {
			return;
		}

		check_admin_referer( 'maklaplace_admin_action', 'maklaplace_nonce' );

		switch ( $action ) {
			case 'update_order_status':
				$this->container->get( OrderService::class )->update_status( get_current_user_id(), absint( $_POST['order_id'] ?? 0 ), sanitize_text_field( (string) ( $_POST['status'] ?? '' ) ) );
				break;
			case 'approve_chef':
				$this->container->get( ChefProfileService::class )->approve( absint( $_POST['user_id'] ?? 0 ) );
				break;
			case 'reject_chef':
				$this->container->get( ChefProfileService::class )->reject( absint( $_POST['user_id'] ?? 0 ), sanitize_text_field( (string) ( $_POST['reason'] ?? '' ) ) );
				break;
			case 'suspend_chef':
				$this->container->get( ChefProfileService::class )->suspend( absint( $_POST['user_id'] ?? 0 ) );
				break;
			case 'toggle_menu':
				$this->container->get( MenuService::class )->set_availability( absint( $_POST['menu_id'] ?? 0 ), ! empty( $_POST['enabled'] ) );
				break;
			case 'wallet_in_progress':
				$this->container->get( WalletService::class )->start_collection( absint( $_POST['chef_user_id'] ?? 0 ) );
				break;
			case 'wallet_deduct':
				$this->container->get( WalletService::class )->confirm_collection( absint( $_POST['chef_user_id'] ?? 0 ), (float) ( $_POST['amount'] ?? 0 ) );
				break;
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=maklaplace' ) );
		exit;
	}

	public function handle_settings_submit() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'maklaplace' ) );
		}

		check_admin_referer( 'maklaplace_save_settings', 'maklaplace_settings_nonce' );
		$sanitized = $this->sanitize_settings( (array) wp_unslash( $_POST[ self::SETTINGS_OPTION ] ?? array() ) );
		update_option( self::SETTINGS_OPTION, $sanitized, false );
		wp_safe_redirect( admin_url( 'admin.php?page=maklaplace-settings&updated=1' ) );
		exit;
	}

	public function sanitize_settings( array $settings ) : array {
		$defaults = $this->default_settings();
		$settings = array_merge( $defaults, $settings );
		$settings['general']['platform_name'] = sanitize_text_field( (string) ( $settings['general']['platform_name'] ?? $defaults['general']['platform_name'] ) );
		$settings['general']['currency'] = sanitize_text_field( (string) ( $settings['general']['currency'] ?? 'DA' ) );
		$settings['general']['commission_percentage'] = max( 0, (float) $settings['general']['commission_percentage'] );
		$settings['wallet']['collection_threshold'] = max( 0, (float) $settings['wallet']['collection_threshold'] );
		$settings['orders']['default_status'] = sanitize_key( (string) ( $settings['orders']['default_status'] ?? 'pending' ) );
		$settings['notifications']['email'] = ! empty( $settings['notifications']['email'] ) ? 1 : 0;
		$settings['notifications']['in_app'] = ! empty( $settings['notifications']['in_app'] ) ? 1 : 0;
		return $settings;
	}

	private function default_settings() : array {
		return array(
			'general' => array(
				'platform_name' => 'MaklaPlace',
				'currency' => 'DA',
				'commission_percentage' => 10,
			),
			'wallet' => array(
				'collection_threshold' => 2000,
			),
			'orders' => array(
				'default_status' => 'pending',
			),
			'notifications' => array(
				'email' => 1,
				'in_app' => 1,
			),
		);
	}

	private function get_dashboard_summary( array $stats ) : array {
		$chef_service = $this->container->get( ChefProfileService::class );
		$wallet_service = $this->container->get( WalletService::class );
		$pending = 0;
		$ready = 0;
		$active_customers = 0;
		foreach ( get_users( array( 'fields' => 'all' ) ) as $user ) {
			$roles = $user instanceof \WP_User ? (array) $user->roles : array();

			if ( in_array( 'maklaplace_customer', $roles, true ) ) {
				$active_customers++;
			}

			if ( in_array( 'maklaplace_chef', $roles, true ) && 'pending' === $chef_service->get_status( (int) $user->ID ) ) {
				$pending++;
			}

			if ( in_array( 'maklaplace_chef', $roles, true ) && 'ready_to_collect' === $wallet_service->get_status( (int) $user->ID ) ) {
				$ready++;
			}
		}
		return array(
			array( 'label' => __( 'Total Orders', 'maklaplace' ), 'value' => number_format_i18n( (int) ( $stats['total_orders'] ?? 0 ) ) ),
			array( 'label' => __( 'Active Chefs', 'maklaplace' ), 'value' => number_format_i18n( (int) ( $stats['total_active_chefs'] ?? 0 ) ) ),
			array( 'label' => __( 'Active Customers', 'maklaplace' ), 'value' => number_format_i18n( $active_customers ) ),
			array( 'label' => __( 'Pending Chef Approvals', 'maklaplace' ), 'value' => number_format_i18n( $pending ) ),
			array( 'label' => __( 'Platform Revenue', 'maklaplace' ), 'value' => number_format_i18n( (float) ( $stats['total_revenue_volume'] ?? 0 ) ) . ' DA' ),
			array( 'label' => __( 'Total Commission', 'maklaplace' ), 'value' => number_format_i18n( (float) ( $stats['total_commissions_generated'] ?? 0 ) ) . ' DA' ),
			array( 'label' => __( 'Wallets Ready for Collection', 'maklaplace' ), 'value' => number_format_i18n( $ready ) ),
		);
	}

	private function render_header( string $title ) : void {
		echo '<div class="wrap maklaplace-admin"><h1>' . esc_html( $title ) . '</h1>';
		echo '<style>.maklaplace-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin:20px 0}.maklaplace-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px}.maklaplace-card__label{font-size:12px;text-transform:uppercase;color:#646970;margin-bottom:8px}.maklaplace-card__value{font-size:24px;font-weight:700}.maklaplace-admin .widefat td,.maklaplace-admin .widefat th{vertical-align:top}</style>';
	}

	private function render_footer() : void {
		echo '</div>';
	}

	private function render_stats_table( array $stats ) : void {
		echo '<h2>' . esc_html__( 'Platform Statistics', 'maklaplace' ) . '</h2><table class="widefat striped"><tbody>';
		foreach ( $stats as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			echo '<tr><th>' . esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_key_value_table( array $data ) : void {
		echo '<table class="widefat striped"><tbody>';
		foreach ( $data as $key => $value ) {
			echo '<tr><th>' . esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ) . '</th><td>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_system_info() : void {
		echo '<div class="card"><h2>' . esc_html__( 'System Information', 'maklaplace' ) . '</h2><table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Plugin Version', 'maklaplace' ) . '</th><td>' . esc_html( MAKLAPLACE_VERSION ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Database Schema Version', 'maklaplace' ) . '</th><td>' . esc_html( self::DB_SCHEMA_VERSION ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'WordPress', 'maklaplace' ) . '</th><td>' . esc_html( get_bloginfo( 'version' ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'PHP', 'maklaplace' ) . '</th><td>' . esc_html( PHP_VERSION ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Wallet Threshold', 'maklaplace' ) . '</th><td>' . esc_html( (string) WalletHelper::threshold() ) . ' DA</td></tr>';
		echo '</tbody></table></div>';
	}

	private function render_settings_sections() : void {
		$settings = get_option( self::SETTINGS_OPTION, $this->default_settings() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $this->default_settings() );
		echo '<h2>' . esc_html__( 'General', 'maklaplace' ) . '</h2>';
		$this->input_row( 'general', 'platform_name', __( 'Platform Name', 'maklaplace' ), $settings['general']['platform_name'] );
		$this->input_row( 'general', 'currency', __( 'Currency', 'maklaplace' ), $settings['general']['currency'] );
		$this->input_row( 'general', 'commission_percentage', __( 'Commission Percentage', 'maklaplace' ), $settings['general']['commission_percentage'], 'number', '0' );
		echo '<h2>' . esc_html__( 'Wallet', 'maklaplace' ) . '</h2>';
		$this->input_row( 'wallet', 'collection_threshold', __( 'Collection Threshold', 'maklaplace' ), $settings['wallet']['collection_threshold'], 'number', '0' );
		echo '<h2>' . esc_html__( 'Orders', 'maklaplace' ) . '</h2>';
		$this->input_row( 'orders', 'default_status', __( 'Default Order Status', 'maklaplace' ), $settings['orders']['default_status'] );
		echo '<h2>' . esc_html__( 'Notifications', 'maklaplace' ) . '</h2>';
		$this->checkbox_row( 'email', __( 'Enable Email', 'maklaplace' ), ! empty( $settings['notifications']['email'] ) );
		$this->checkbox_row( 'in_app', __( 'Enable In-App Notifications', 'maklaplace' ), ! empty( $settings['notifications']['in_app'] ) );
	}

	private function input_row( string $section, string $field, string $label, mixed $value, string $type = 'text', string $min = '' ) : void {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br />';
		echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( self::SETTINGS_OPTION . '[' . $section . '][' . $field . ']' ) . '" value="' . esc_attr( (string) $value ) . '"';
		if ( '' !== $min ) {
			echo ' min="' . esc_attr( $min ) . '"';
		}
		if ( 'number' === $type ) {
			echo ' step="any"';
		}
		echo ' class="regular-text" /></label></p>';
	}

	private function checkbox_row( string $field, string $label, bool $checked ) : void {
		echo '<p><label><input type="checkbox" name="' . esc_attr( self::SETTINGS_OPTION . '[notifications][' . $field . ']' ) . '" value="1" ' . checked( $checked, true, false ) . ' /> ' . esc_html( $label ) . '</label></p>';
	}

	private function render_bulk_notice() : void {
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings updated.', 'maklaplace' ) . '</p></div>';
		}
	}
}

abstract class Base_List_Table extends WP_List_Table {
	protected Container $container;

	public function __construct( array $args, Container $container ) {
		$this->container = $container;
		parent::__construct( $args );
	}

	public function column_default( $item, $column_name ) {
		$value = $item[ $column_name ] ?? '';
		if ( is_array( $value ) ) {
			return esc_html( wp_json_encode( $value ) );
		}

		return esc_html( (string) $value );
	}

	protected function sort_array( array $items, string $orderby, string $order, array $allowed ) : array {
		if ( ! in_array( $orderby, $allowed, true ) ) {
			return $items;
		}

		usort(
			$items,
			static function ( array $a, array $b ) use ( $orderby, $order ) : int {
				$left  = $a[ $orderby ] ?? '';
				$right = $b[ $orderby ] ?? '';
				$compare = strcmp( (string) $left, (string) $right );
				return 'desc' === strtolower( $order ) ? -$compare : $compare;
			}
		);

		return $items;
	}

	protected function slice_page( array $items, int $per_page ) : array {
		$current_page = max( 1, (int) $this->get_pagenum() );
		$total_items = count( $items );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);

		return array_slice( $items, ( $current_page - 1 ) * $per_page, $per_page );
	}
}

final class Orders_List_Table extends Base_List_Table {
	private OrderService $orders;

	public function __construct( Container $container ) {
		parent::__construct( array( 'singular' => 'order', 'plural' => 'orders', 'ajax' => false ), $container );
		$this->orders = $container->get( OrderService::class );
	}

	public function get_columns() : array {
		return array(
			'id'       => '#',
			'customer' => __( 'Customer', 'maklaplace' ),
			'total'    => __( 'Total', 'maklaplace' ),
			'status'   => __( 'Status', 'maklaplace' ),
			'date'     => __( 'Date', 'maklaplace' ),
			'actions'  => __( 'Actions', 'maklaplace' ),
		);
	}

	public function get_sortable_columns() : array {
		return array(
			'id'     => array( 'id', false ),
			'customer' => array( 'customer', false ),
			'total'  => array( 'total', false ),
			'status' => array( 'status', false ),
			'date'   => array( 'date', false ),
		);
	}

	public function no_items() : void {
		esc_html_e( 'No orders found.', 'maklaplace' );
	}

	public function prepare_items() : void {
		$all = array_map( array( $this, 'map_item' ), array_values( $this->orders->get_orders() ) );
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		$status_filter = sanitize_key( (string) ( $_REQUEST['order_status'] ?? '' ) );
		if ( '' !== $search ) {
			$all = array_values(
				array_filter(
					$all,
					static fn( array $order ) : bool => false !== stripos( (string) $order['customer'], $search ) || false !== stripos( (string) $order['status'], $search )
				)
			);
		}
		if ( '' !== $status_filter ) {
			$all = array_values( array_filter( $all, static fn( array $order ) : bool => $order['status'] === $status_filter ) );
		}
		$orderby = sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'date' ) );
		$order = sanitize_key( (string) ( $_REQUEST['order'] ?? 'desc' ) );
		$all = $this->sort_array( $all, $orderby, $order, array( 'id', 'customer', 'total', 'status', 'date' ) );
		$this->items = $this->slice_page( $all, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'customer' );
	}

	public function extra_tablenav( $which ) : void {
		if ( 'top' !== $which ) {
			return;
		}
		$current = sanitize_key( (string) ( $_REQUEST['order_status'] ?? '' ) );
		$statuses = array( '' => __( 'All statuses', 'maklaplace' ), 'pending' => __( 'Pending', 'maklaplace' ), 'accepted' => __( 'Accepted', 'maklaplace' ), 'preparing' => __( 'Preparing', 'maklaplace' ), 'ready' => __( 'Ready', 'maklaplace' ), 'on_the_way' => __( 'On the Way', 'maklaplace' ), 'completed' => __( 'Completed', 'maklaplace' ), 'cancelled' => __( 'Cancelled', 'maklaplace' ) );
		echo '<div class="alignleft actions">';
		echo '<select name="order_status">';
		foreach ( $statuses as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'maklaplace' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	public function column_id( array $item ) : string {
		return '#' . esc_html( (string) $item['id'] );
	}

	public function column_customer( array $item ) : string {
		return esc_html( (string) $item['customer'] );
	}

	public function column_total( array $item ) : string {
		return esc_html( number_format_i18n( (float) $item['total'], 2 ) . ' DA' );
	}

	public function column_status( array $item ) : string {
		return esc_html( ucwords( str_replace( '_', ' ', (string) $item['status'] ) ) );
	}

	public function column_date( array $item ) : string {
		return esc_html( mysql2date( 'M j, Y g:i a', (string) $item['date'] ) );
	}

	public function column_actions( array $item ) : string {
		$base = wp_nonce_url( admin_url( 'admin.php?page=maklaplace-orders&maklaplace_nonce=' . wp_create_nonce( 'maklaplace_admin_action' ) ), 'maklaplace_admin_action', 'maklaplace_nonce' );
		$actions = array();
		foreach ( array( 'accepted', 'preparing', 'ready', 'on_the_way', 'completed', 'cancelled' ) as $status ) {
			$actions[ $status ] = sprintf(
				'<a href="%s&maklaplace_action=update_order_status&order_id=%d&status=%s">%s</a>',
				esc_url( $base ),
				(int) $item['id'],
				esc_attr( $status ),
				esc_html( ucwords( str_replace( '_', ' ', $status ) ) )
			);
		}
		return implode( ' | ', $actions );
	}

	public function get_views() : array {
		return array();
	}

	private function map_item( array $order ) : array {
		return array(
			'id'       => (int) ( $order['id'] ?? 0 ),
			'customer' => (string) ( $order[ OrderKeys::CUSTOMER_NAME ] ?? '' ),
			'total'    => (float) ( $order[ OrderKeys::TOTAL_AMOUNT ] ?? 0 ),
			'status'   => (string) ( $order[ OrderKeys::STATUS ] ?? '' ),
			'date'     => (string) ( $order[ OrderKeys::CREATED_AT ] ?? '' ),
		);
	}
}
final class Chefs_List_Table extends Base_List_Table {
	private ChefProfileService $chefs;
	private WalletService $wallets;
	private OrderService $orders;

	public function __construct( Container $container ) {
		parent::__construct( array( 'singular' => 'chef', 'plural' => 'chefs', 'ajax' => false ), $container );
		$this->chefs = $container->get( ChefProfileService::class );
		$this->wallets = $container->get( WalletService::class );
		$this->orders = $container->get( OrderService::class );
	}

	public function get_columns() : array {
		return array(
			'name'   => __( 'Name', 'maklaplace' ),
			'status' => __( 'Verification Status', 'maklaplace' ),
			'wallet' => __( 'Wallet Balance', 'maklaplace' ),
			'orders' => __( 'Total Orders', 'maklaplace' ),
			'date'   => __( 'Registration Date', 'maklaplace' ),
			'actions' => __( 'Actions', 'maklaplace' ),
		);
	}

	public function get_sortable_columns() : array {
		return array( 'name' => array( 'name', false ), 'status' => array( 'status', false ), 'wallet' => array( 'wallet', false ), 'orders' => array( 'orders', false ), 'date' => array( 'date', false ) );
	}

	public function prepare_items() : void {
		$items = array();
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		$status_filter = sanitize_key( (string) ( $_REQUEST['chef_status'] ?? '' ) );
		foreach ( get_users( array( 'role' => 'maklaplace_chef' ) ) as $user ) {
			$row = array(
				'user_id' => $user->ID,
				'name'    => $user->display_name,
				'status'  => $this->chefs->get_status( $user->ID ),
				'wallet'  => $this->wallets->get_balance( $user->ID ),
				'orders'  => count( $this->orders->get_orders_by_chef( $user->ID ) ),
				'date'    => $user->user_registered,
			);
			if ( '' !== $search && false === stripos( $row['name'], $search ) ) {
				continue;
			}
			if ( '' !== $status_filter && $status_filter !== $row['status'] ) {
				continue;
			}
			$items[] = $row;
		}
		$orderby = sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'date' ) );
		$order = sanitize_key( (string) ( $_REQUEST['order'] ?? 'desc' ) );
		$items = $this->sort_array( $items, $orderby, $order, array( 'name', 'status', 'wallet', 'orders', 'date' ) );
		$this->items = $this->slice_page( $items, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	public function extra_tablenav( $which ) : void {
		if ( 'top' !== $which ) {
			return;
		}
		$current = sanitize_key( (string) ( $_REQUEST['chef_status'] ?? '' ) );
		$options = array( '' => __( 'All statuses', 'maklaplace' ), 'pending' => __( 'Pending', 'maklaplace' ), 'approved' => __( 'Approved', 'maklaplace' ), 'rejected' => __( 'Rejected', 'maklaplace' ), 'suspended' => __( 'Suspended', 'maklaplace' ) );
		echo '<div class="alignleft actions">';
		echo '<select name="chef_status">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'maklaplace' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	public function column_actions( array $item ) : string {
		$base = wp_nonce_url( admin_url( 'admin.php?page=maklaplace-chefs' ), 'maklaplace_admin_action', 'maklaplace_nonce' );
		return sprintf(
			'<a href="%s&maklaplace_action=approve_chef&user_id=%d">%s</a> | <a href="%s&maklaplace_action=reject_chef&user_id=%d">%s</a> | <a href="%s&maklaplace_action=suspend_chef&user_id=%d">%s</a>',
			esc_url( $base ),
			(int) $item['user_id'],
			esc_html__( 'Approve', 'maklaplace' ),
			esc_url( $base ),
			(int) $item['user_id'],
			esc_html__( 'Reject', 'maklaplace' ),
			esc_url( $base ),
			(int) $item['user_id'],
			esc_html__( 'Suspend', 'maklaplace' )
		);
	}
}
final class Customers_List_Table extends Base_List_Table {
	private OrderService $orders;

	public function __construct( Container $container ) {
		parent::__construct( array( 'singular' => 'customer', 'plural' => 'customers', 'ajax' => false ), $container );
		$this->orders = $container->get( OrderService::class );
	}

	public function get_columns() : array {
		return array( 'name' => __( 'Name', 'maklaplace' ), 'email' => __( 'Email', 'maklaplace' ), 'orders' => __( 'Total Orders', 'maklaplace' ), 'date' => __( 'Registration Date', 'maklaplace' ) );
	}

	public function get_sortable_columns() : array {
		return array( 'name' => array( 'name', false ), 'email' => array( 'email', false ), 'orders' => array( 'orders', false ), 'date' => array( 'date', false ) );
	}

	public function prepare_items() : void {
		$items = array();
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		foreach ( get_users( array( 'role' => 'maklaplace_customer' ) ) as $user ) {
			$row = array(
				'name' => $user->display_name,
				'email' => $user->user_email,
				'orders' => count( $this->orders->get_orders_by_customer( $user->ID ) ),
				'date' => $user->user_registered,
			);
			if ( '' !== $search && false === stripos( $row['name'] . ' ' . $row['email'], $search ) ) {
				continue;
			}
			$items[] = $row;
		}
		$orderby = sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'date' ) );
		$order = sanitize_key( (string) ( $_REQUEST['order'] ?? 'desc' ) );
		$items = $this->sort_array( $items, $orderby, $order, array( 'name', 'email', 'orders', 'date' ) );
		$this->items = $this->slice_page( $items, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}
}
final class Menus_List_Table extends Base_List_Table {
	private MenuService $menus;

	public function __construct( Container $container ) {
		parent::__construct( array( 'singular' => 'menu', 'plural' => 'menus', 'ajax' => false ), $container );
		$this->menus = $container->get( MenuService::class );
	}

	public function get_columns() : array {
		return array( 'title' => __( 'Menu Item', 'maklaplace' ), 'chef' => __( 'Chef', 'maklaplace' ), 'availability' => __( 'Status', 'maklaplace' ), 'price' => __( 'Price', 'maklaplace' ), 'actions' => __( 'Actions', 'maklaplace' ) );
	}

	public function get_sortable_columns() : array {
		return array( 'title' => array( 'title', false ), 'chef' => array( 'chef', false ), 'availability' => array( 'availability', false ), 'price' => array( 'price', false ) );
	}

	public function prepare_items() : void {
		$items = array();
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		$chef_filter = absint( $_REQUEST['chef_id'] ?? 0 );
		foreach ( $this->menus->get_menu_items() as $menu ) {
			$chef = get_userdata( (int) ( $menu[ MenuKeys::CHEF_USER_ID ] ?? 0 ) );
			$row = array(
				'menu_id' => (int) ( $menu['id'] ?? 0 ),
				'title' => (string) ( $menu[ MenuKeys::TITLE ] ?? '' ),
				'chef' => $chef ? $chef->display_name : '',
				'chef_id' => (int) ( $menu[ MenuKeys::CHEF_USER_ID ] ?? 0 ),
				'availability' => (string) ( $menu[ MenuKeys::AVAILABILITY ] ?? '' ),
				'price' => (float) ( $menu[ MenuKeys::PRICE ] ?? 0 ),
			);
			if ( $chef_filter > 0 && $chef_filter !== $row['chef_id'] ) {
				continue;
			}
			if ( '' !== $search && false === stripos( $row['title'] . ' ' . $row['chef'], $search ) ) {
				continue;
			}
			$items[] = $row;
		}
		$orderby = sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'title' ) );
		$order = sanitize_key( (string) ( $_REQUEST['order'] ?? 'asc' ) );
		$items = $this->sort_array( $items, $orderby, $order, array( 'title', 'chef', 'availability', 'price' ) );
		$this->items = $this->slice_page( $items, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
	}

	public function extra_tablenav( $which ) : void {
		if ( 'top' !== $which ) {
			return;
		}
		echo '<div class="alignleft actions">';
		echo '<input type="number" name="chef_id" min="0" placeholder="' . esc_attr__( 'Chef ID', 'maklaplace' ) . '" value="' . esc_attr( (string) absint( $_REQUEST['chef_id'] ?? 0 ) ) . '" />';
		submit_button( __( 'Filter', 'maklaplace' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	public function column_actions( array $item ) : string {
		$enabled = 'available' === (string) $item['availability'];
		$base = wp_nonce_url( admin_url( 'admin.php?page=maklaplace-menus' ), 'maklaplace_admin_action', 'maklaplace_nonce' );
		return sprintf(
			'<a href="%s&maklaplace_action=toggle_menu&menu_id=%d&enabled=%d">%s</a>',
			esc_url( $base ),
			(int) $item['menu_id'],
			$enabled ? 0 : 1,
			$enabled ? esc_html__( 'Disable', 'maklaplace' ) : esc_html__( 'Enable', 'maklaplace' )
		);
	}
}
final class Wallets_List_Table extends Base_List_Table {
	private WalletService $wallets;

	public function __construct( Container $container ) {
		parent::__construct( array( 'singular' => 'wallet', 'plural' => 'wallets', 'ajax' => false ), $container );
		$this->wallets = $container->get( WalletService::class );
	}

	public function get_columns() : array {
		return array( 'chef' => __( 'Chef', 'maklaplace' ), 'balance' => __( 'Current Balance', 'maklaplace' ), 'status' => __( 'Wallet Status', 'maklaplace' ), 'updated' => __( 'Last Updated', 'maklaplace' ), 'actions' => __( 'Actions', 'maklaplace' ) );
	}

	public function get_sortable_columns() : array {
		return array( 'chef' => array( 'chef', false ), 'balance' => array( 'balance', false ), 'status' => array( 'status', false ), 'updated' => array( 'updated', false ) );
	}

	public function prepare_items() : void {
		$items = array();
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		$status_filter = sanitize_key( (string) ( $_REQUEST['wallet_status'] ?? '' ) );
		foreach ( get_users( array( 'role' => 'maklaplace_chef' ) ) as $user ) {
			$row = array(
				'chef_id' => $user->ID,
				'chef' => $user->display_name,
				'balance' => $this->wallets->get_balance( $user->ID ),
				'status' => $this->wallets->get_status( $user->ID ),
				'updated' => (string) get_user_meta( $user->ID, UserMeta::WALLET_LAST_UPDATED, true ),
			);
			if ( '' !== $search && false === stripos( $row['chef'], $search ) ) {
				continue;
			}
			if ( '' !== $status_filter && $status_filter !== $row['status'] ) {
				continue;
			}
			$items[] = $row;
		}
		$orderby = sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'updated' ) );
		$order = sanitize_key( (string) ( $_REQUEST['order'] ?? 'desc' ) );
		$items = $this->sort_array( $items, $orderby, $order, array( 'chef', 'balance', 'status', 'updated' ) );
		$this->items = $this->slice_page( $items, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'chef' );
	}

	public function extra_tablenav( $which ) : void {
		if ( 'top' !== $which ) {
			return;
		}
		$current = sanitize_key( (string) ( $_REQUEST['wallet_status'] ?? '' ) );
		$options = array( '' => __( 'All statuses', 'maklaplace' ), 'empty' => __( 'Empty', 'maklaplace' ), 'not_ready' => __( 'Not Ready', 'maklaplace' ), 'ready_to_collect' => __( 'Ready to Collect', 'maklaplace' ), 'in_progress' => __( 'In Progress', 'maklaplace' ) );
		echo '<div class="alignleft actions">';
		echo '<select name="wallet_status">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'maklaplace' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	public function column_balance( array $item ) : string {
		return esc_html( number_format_i18n( (float) $item['balance'], 2 ) . ' DA' );
	}

	public function column_updated( array $item ) : string {
		return esc_html( (string) $item['updated'] );
	}

	public function column_actions( array $item ) : string {
		$base = wp_nonce_url( admin_url( 'admin.php?page=maklaplace-wallets' ), 'maklaplace_admin_action', 'maklaplace_nonce' );
		return sprintf(
			'<a href="%s&maklaplace_action=wallet_in_progress&chef_user_id=%d">%s</a>',
			esc_url( $base ),
			(int) $item['chef_id'],
			esc_html__( 'Mark In Progress', 'maklaplace' )
		);
	}
}
final class Notifications_List_Table extends Base_List_Table {
	private NotificationService $notifications;

	public function __construct( Container $container ) {
		parent::__construct( array( 'singular' => 'notification', 'plural' => 'notifications', 'ajax' => false ), $container );
		$this->notifications = $container->get( NotificationService::class );
	}

	public function get_columns() : array {
		return array( 'recipient' => __( 'Recipient', 'maklaplace' ), 'event_type' => __( 'Event Type', 'maklaplace' ), 'status' => __( 'Status', 'maklaplace' ), 'date' => __( 'Date', 'maklaplace' ) );
	}

	public function get_sortable_columns() : array {
		return array( 'recipient' => array( 'recipient', false ), 'event_type' => array( 'event_type', false ), 'status' => array( 'status', false ), 'date' => array( 'date', false ) );
	}

	public function prepare_items() : void {
		$items = array();
		$search = sanitize_text_field( (string) ( $_REQUEST['s'] ?? '' ) );
		foreach ( $this->notifications->get_all() as $notification ) {
			$recipient = get_userdata( (int) ( $notification[ NotificationKeys::RECIPIENT_USER_ID ] ?? 0 ) );
			$row = array(
				'recipient' => $recipient ? $recipient->display_name : '',
				'event_type' => (string) ( $notification[ NotificationKeys::EVENT_TYPE ] ?? '' ),
				'status' => (string) ( $notification[ NotificationKeys::READ_STATUS ] ?? '' ),
				'date' => (string) ( $notification[ NotificationKeys::CREATED_AT ] ?? '' ),
			);
			if ( '' !== $search && false === stripos( $row['recipient'] . ' ' . $row['event_type'] . ' ' . $row['status'], $search ) ) {
				continue;
			}
			$items[] = $row;
		}
		$orderby = sanitize_key( (string) ( $_REQUEST['orderby'] ?? 'date' ) );
		$order = sanitize_key( (string) ( $_REQUEST['order'] ?? 'desc' ) );
		$items = $this->sort_array( $items, $orderby, $order, array( 'recipient', 'event_type', 'status', 'date' ) );
		$this->items = $this->slice_page( $items, 10 );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'date' );
	}
}
