<?php
/**
 * Base repository.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Provides common database operations.
 */
abstract class BaseRepository {

	/**
	 * Database connection.
	 *
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Constructor.
	 *
	 * @param wpdb   $wpdb  Database connection.
	 * @param string $table Table name.
	 */
	public function __construct( wpdb $wpdb, string $table ) {
		$this->wpdb  = $wpdb;
		$this->table = $table;
	}

	/**
	 * Insert a record.
	 *
	 * @param array<string, mixed> $data Insert data.
	 * @param array<string, string> $format Column formats.
	 * @return int|false
	 */
	public function insert( array $data, array $format = array() ) : int|false {
		$result = $this->wpdb->insert( $this->table, $data, $format );

		return false === $result ? false : (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a record.
	 *
	 * @param array<string, mixed> $data   Update data.
	 * @param array<string, mixed> $where  Where clause.
	 * @param array<string, string> $format Data formats.
	 * @param array<string, string> $where_format Where formats.
	 * @return int|false
	 */
	public function update( array $data, array $where, array $format = array(), array $where_format = array() ) : int|false {
		return $this->wpdb->update( $this->table, $data, $where, $format, $where_format );
	}

	/**
	 * Delete a record.
	 *
	 * @param array<string, mixed> $where Where clause.
	 * @param array<string, string> $where_format Where formats.
	 * @return int|false
	 */
	public function delete( array $where, array $where_format = array() ) : int|false {
		return $this->wpdb->delete( $this->table, $where, $where_format );
	}

	/**
	 * Find a record by ID.
	 *
	 * @param int $id Record ID.
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $id ) : ?array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id );
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find multiple records.
	 *
	 * @param array<string, mixed> $where Optional where conditions.
	 * @param int                  $limit Limit.
	 * @param int                  $offset Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_many( array $where = array(), int $limit = 50, int $offset = 0 ) : array {
		$sql = "SELECT * FROM {$this->table}";
		$args = array();

		if ( ! empty( $where ) ) {
			$clauses = array();

			foreach ( $where as $column => $value ) {
				$clauses[] = "{$column} = %s";
				$args[] = $value;
			}

			$sql .= ' WHERE ' . implode( ' AND ', $clauses );
		}

		$sql   .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		$prepared = $this->wpdb->prepare( $sql, $args );
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}
}
