<?php
/**
 * Database manager.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin schema creation and upgrades.
 */
final class DatabaseManager {

	/**
	 * Option key for the database schema version.
	 */
	public const SCHEMA_VERSION_OPTION = 'maklaplace_db_schema_version';

	/**
	 * Current database schema version.
	 */
	public const SCHEMA_VERSION = '1.0.0';

	/**
	 * Database connection.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database instance.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Install or upgrade the schema.
	 *
	 * @return void
	 */
	public function install() : void {
		$stored_version = $this->get_schema_version();

		if ( '' === $stored_version ) {
			$this->create_schema();
			$this->update_schema_version( self::SCHEMA_VERSION );
			return;
		}

		if ( version_compare( $stored_version, self::SCHEMA_VERSION, '<' ) ) {
			$this->run_migrations( $stored_version );
			$this->update_schema_version( self::SCHEMA_VERSION );
		}
	}

	/**
	 * Create all plugin tables.
	 *
	 * @return void
	 */
	public function create_schema() : void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $this->get_table_definitions() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Run pending migrations.
	 *
	 * @param string $from_version Previous schema version.
	 * @return void
	 */
	public function run_migrations( string $from_version ) : void {
		$migrations = $this->get_migrations();

		foreach ( $migrations as $version => $migration ) {
			if ( version_compare( $from_version, $version, '<' ) ) {
				$migration();
			}
		}
	}

	/**
	 * Get the stored schema version.
	 *
	 * @return string
	 */
	public function get_schema_version() : string {
		$value = get_option( self::SCHEMA_VERSION_OPTION, '' );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Update the stored schema version.
	 *
	 * @param string $version Schema version.
	 * @return void
	 */
	public function update_schema_version( string $version ) : void {
		update_option( self::SCHEMA_VERSION_OPTION, $version, false );
	}

	/**
	 * Get the wallet transaction table name.
	 *
	 * @return string
	 */
	public function get_wallet_transactions_table() : string {
		return $this->table_name( 'wallet_transactions' );
	}

	/**
	 * Build a plugin table name.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public function table_name( string $suffix ) : string {
		return $this->wpdb->prefix . 'maklaplace_' . ltrim( $suffix, '_' );
	}

	/**
	 * Get the charset/collation string.
	 *
	 * @return string
	 */
	public function get_charset_collate() : string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Execute a safe query.
	 *
	 * @param string $query SQL query.
	 * @return int|false
	 */
	public function execute( string $query ) : int|false {
		return $this->wpdb->query( $query );
	}

	/**
	 * Start a database transaction when supported.
	 *
	 * @return void
	 */
	public function begin_transaction() : void {
		$this->wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit the current transaction.
	 *
	 * @return void
	 */
	public function commit() : void {
		$this->wpdb->query( 'COMMIT' );
	}

	/**
	 * Roll back the current transaction.
	 *
	 * @return void
	 */
	public function rollback() : void {
		$this->wpdb->query( 'ROLLBACK' );
	}

	/**
	 * Get table definitions.
	 *
	 * @return array<string, string>
	 */
	private function get_table_definitions() : array {
		$table = $this->get_wallet_transactions_table();
		$charset_collate = $this->get_charset_collate();

		return array(
			'wallet_transactions' => "CREATE TABLE {$table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				chef_id BIGINT(20) UNSIGNED NOT NULL,
				order_id BIGINT(20) UNSIGNED NULL,
				transaction_type VARCHAR(50) NOT NULL,
				amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				balance_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				notes TEXT NULL,
				created_by BIGINT(20) UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY chef_id (chef_id),
				KEY order_id (order_id),
				KEY transaction_type (transaction_type),
				KEY created_by (created_by)
			) {$charset_collate};",
		);
	}

	/**
	 * Get migration callbacks indexed by target version.
	 *
	 * @return array<string, callable():void>
	 */
	private function get_migrations() : array {
		return array(
			'1.0.1' => static function () : void {
				// Reserved for future schema migrations.
			},
		);
	}
}
