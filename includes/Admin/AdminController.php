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
		settings_fields( 'maklaplace_settings_group' );
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
		$this->sanitize_settings( (array) wp_unslash( $_POST[ self::SETTINGS_OPTION ] ?? array() ) );
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
		update_option( self::SETTINGS_OPTION, $settings, false );
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
		foreach ( get_users( array( 'fields' => array( 'ID', 'roles', 'user_registered' ) ) ) as $user ) {
			if ( in_array( 'maklaplace_customer', (array) $user->roles, true ) ) {
				$active_customers++;
			}
			if ( in_array( 'maklaplace_chef', (array) $user->roles, true ) && 'pending' === $chef_service->get_status( (int) $user->ID ) ) {
				$pending++;
			}
			if ( in_array( 'maklaplace_chef', (array) $user->roles, true ) && 'ready_to_collect' === $wallet_service->get_status( (int) $user->ID ) ) {
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
		echo '<input type="hidden" name="' . esc_attr( self::SETTINGS_OPTION ) . '[general][platform_name]" value="' . esc_attr( $settings['general']['platform_name'] ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::SETTINGS_OPTION ) . '[general][currency]" value="' . esc_attr( $settings['general']['currency'] ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::SETTINGS_OPTION ) . '[general][commission_percentage]" value="' . esc_attr( $settings['general']['commission_percentage'] ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::SETTINGS_OPTION ) . '[wallet][collection_threshold]" value="' . esc_attr( $settings['wallet']['collection_threshold'] ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::SETTINGS_OPTION ) . '[orders][default_status]" value="' . esc_attr( $settings['orders']['default_status'] ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::SETTINGS_OPTION ) . '[notifications][email]" value="0" />';
		echo '<input type="hidden" name="' . esc_attr( self::SETTINGS_OPTION ) . '[notifications][in_app]" value="0" />';
	}

	private function input_row( string $section, string $field, string $label, mixed $value, string $type = 'text', string $min = '' ) : void {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br />';
		echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( self::SETTINGS_OPTION . '[' . $section . '][' . $field . ']' ) . '" value="' . esc_attr( (string) $value ) . '"';
		if ( '' !== $min ) {
			echo ' min="' . esc_attr( $min ) . '"';
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
	public function column_default( $item, $column_name ) {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}
}

final class Orders_List_Table extends Base_List_Table {
	public function __construct( private Container $container ) { parent::__construct( array( 'singular' => 'order', 'plural' => 'orders', 'ajax' => false ) ); }
	public function get_columns() : array { return array( 'id' => '#', 'customer' => __( 'Customer', 'maklaplace' ), 'total' => __( 'Total', 'maklaplace' ), 'status' => __( 'Status', 'maklaplace' ), 'date' => __( 'Date', 'maklaplace' ), 'actions' => __( 'Actions', 'maklaplace' ) ); }
	public function prepare_items() : void { $orders = $this->container->get( OrderService::class )->get_orders(); $this->items = array_map( array( $this, 'map_item' ), array_values( $orders ) ); $this->_column_headers = array( $this->get_columns(), array(), array() ); }
	private function map_item( array $order ) : array { return array( 'id' => $order['id'] ?? 0, 'customer' => $order[OrderKeys::CUSTOMER_NAME] ?? '', 'total' => (string) ( $order[OrderKeys::TOTAL_AMOUNT] ?? 0 ), 'status' => $order[OrderKeys::STATUS] ?? '', 'date' => $order[OrderKeys::CREATED_AT] ?? '', 'actions' => '' ); }
}
final class Chefs_List_Table extends Base_List_Table {
	public function __construct( private Container $container ) { parent::__construct( array( 'singular' => 'chef', 'plural' => 'chefs', 'ajax' => false ) ); }
	public function get_columns() : array { return array( 'name' => __( 'Name', 'maklaplace' ), 'status' => __( 'Verification Status', 'maklaplace' ), 'wallet' => __( 'Wallet Balance', 'maklaplace' ), 'orders' => __( 'Total Orders', 'maklaplace' ), 'date' => __( 'Registration Date', 'maklaplace' ) ); }
	public function prepare_items() : void { $items = array(); foreach ( get_users( array( 'role' => 'maklaplace_chef' ) ) as $user ) { $items[] = array( 'name' => $user->display_name, 'status' => $this->container->get( ChefProfileService::class )->get_status( $user->ID ), 'wallet' => $this->container->get( WalletService::class )->get_balance( $user->ID ), 'orders' => count( $this->container->get( OrderService::class )->get_orders_by_chef( $user->ID ) ), 'date' => $user->user_registered ); } $this->items = $items; $this->_column_headers = array( $this->get_columns(), array(), array() ); }
}
final class Customers_List_Table extends Base_List_Table {
	public function __construct( private Container $container ) { parent::__construct( array( 'singular' => 'customer', 'plural' => 'customers', 'ajax' => false ) ); }
	public function get_columns() : array { return array( 'name' => __( 'Name', 'maklaplace' ), 'email' => __( 'Email', 'maklaplace' ), 'orders' => __( 'Total Orders', 'maklaplace' ), 'date' => __( 'Registration Date', 'maklaplace' ) ); }
	public function prepare_items() : void { $items = array(); foreach ( get_users( array( 'role' => 'maklaplace_customer' ) ) as $user ) { $items[] = array( 'name' => $user->display_name, 'email' => $user->user_email, 'orders' => count( $this->container->get( OrderService::class )->get_orders_by_customer( $user->ID ) ), 'date' => $user->user_registered ); } $this->items = $items; $this->_column_headers = array( $this->get_columns(), array(), array() ); }
}
final class Menus_List_Table extends Base_List_Table {
	public function __construct( private Container $container ) { parent::__construct( array( 'singular' => 'menu', 'plural' => 'menus', 'ajax' => false ) ); }
	public function get_columns() : array { return array( 'title' => __( 'Menu Item', 'maklaplace' ), 'chef' => __( 'Chef', 'maklaplace' ), 'availability' => __( 'Status', 'maklaplace' ), 'price' => __( 'Price', 'maklaplace' ) ); }
	public function prepare_items() : void { $items = array(); foreach ( $this->container->get( MenuService::class )->get_menu_items() as $menu ) { $chef = get_userdata( (int) ( $menu[MenuKeys::CHEF_USER_ID] ?? 0 ) ); $items[] = array( 'title' => $menu[MenuKeys::TITLE] ?? '', 'chef' => $chef ? $chef->display_name : '', 'availability' => $menu[MenuKeys::AVAILABILITY] ?? '', 'price' => $menu[MenuKeys::PRICE] ?? 0 ); } $this->items = $items; $this->_column_headers = array( $this->get_columns(), array(), array() ); }
}
final class Wallets_List_Table extends Base_List_Table {
	public function __construct( private Container $container ) { parent::__construct( array( 'singular' => 'wallet', 'plural' => 'wallets', 'ajax' => false ) ); }
	public function get_columns() : array { return array( 'chef' => __( 'Chef', 'maklaplace' ), 'balance' => __( 'Current Balance', 'maklaplace' ), 'status' => __( 'Wallet Status', 'maklaplace' ), 'updated' => __( 'Last Updated', 'maklaplace' ) ); }
	public function prepare_items() : void { $items = array(); foreach ( get_users( array( 'role' => 'maklaplace_chef' ) ) as $user ) { $items[] = array( 'chef' => $user->display_name, 'balance' => $this->container->get( WalletService::class )->get_balance( $user->ID ), 'status' => $this->container->get( WalletService::class )->get_status( $user->ID ), 'updated' => get_user_meta( $user->ID, UserMeta::WALLET_LAST_UPDATED, true ) ); } $this->items = $items; $this->_column_headers = array( $this->get_columns(), array(), array() ); }
}
final class Notifications_List_Table extends Base_List_Table {
	public function __construct( private Container $container ) { parent::__construct( array( 'singular' => 'notification', 'plural' => 'notifications', 'ajax' => false ) ); }
	public function get_columns() : array { return array( 'recipient' => __( 'Recipient', 'maklaplace' ), 'event_type' => __( 'Event Type', 'maklaplace' ), 'status' => __( 'Status', 'maklaplace' ), 'date' => __( 'Date', 'maklaplace' ) ); }
	public function prepare_items() : void { $items = array(); foreach ( $this->container->get( NotificationService::class )->get_all() as $notification ) { $recipient = get_userdata( (int) ( $notification[NotificationKeys::RECIPIENT_USER_ID] ?? 0 ) ); $items[] = array( 'recipient' => $recipient ? $recipient->display_name : '', 'event_type' => $notification[NotificationKeys::EVENT_TYPE] ?? '', 'status' => $notification[NotificationKeys::READ_STATUS] ?? '', 'date' => $notification[NotificationKeys::CREATED_AT] ?? '' ); } $this->items = $items; $this->_column_headers = array( $this->get_columns(), array(), array() ); }
}
