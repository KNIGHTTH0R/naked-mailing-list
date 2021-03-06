<?php

/**
 * NML DB Base Class
 *
 * @package   naked-mailing-list
 * @copyright Copyright (c) 2017, Ashley Gibson
 * @license   GPL2+
 * @since     1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NML_DB
 *
 * @since 1.0
 */
abstract class NML_DB {

	/**
	 * The name of our database table
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $table_name;

	/**
	 * The version of our database table
	 *
	 * @var int
	 * @access public
	 * @since  1.0
	 */
	public $version;

	/**
	 * The name of the primary column
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $primary_key;

	/**
	 * NML_DB constructor.
	 *
	 * @access public
	 * @since  1.0
	 * @return void
	 */
	public function __construct() {

	}

	/**
	 * Default column values
	 *
	 * @access public
	 * @since  1.0
	 * @return array
	 */
	public function get_columns() {
		return array();
	}

	/**
	 * Default column values.
	 *
	 * @access public
	 * @since  1.0
	 * @return array
	 */
	public function get_column_defaults() {
		return array();
	}

	/**
	 * Retrieve a row by the primary key
	 *
	 * @param int $row_id ID of the row.
	 *
	 * @access public
	 * @since  1.0
	 * @return object
	 */
	public function get( $row_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $row_id ) );
	}

	/**
	 * Retrieve a row by a specific column / value
	 *
	 * @param string       $column Column name.
	 * @param string|mixed $value  Value to check.
	 *
	 * @access public
	 * @since  1.0
	 * @return object
	 */
	public function get_by( $column, $value ) {
		global $wpdb;
		$column = esc_sql( $column );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $column = %s LIMIT 1;", $value ) );
	}

	/**
	 * Retrieve a specific column's value by the primary key
	 *
	 * @param string    $column Name of the column to get the value of.
	 * @param int|mixed $row_id Primary key value.
	 *
	 * @access public
	 * @since  1.0
	 * @return string
	 */
	public function get_column( $column, $row_id ) {
		global $wpdb;
		$column = esc_sql( $column );

		return $wpdb->get_var( $wpdb->prepare( "SELECT $column FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $row_id ) );
	}

	/**
	 * Retrieve a specific column's value by the the specified column / value
	 *
	 * @param string       $column       Name of the column to get the value of.
	 * @param string       $column_value Name of the column to check the value of.
	 * @param string|mixed $column_value Value of the column.
	 *
	 * @access public
	 * @since  1.0
	 * @return string
	 */
	public function get_column_by( $column, $column_where, $column_value ) {
		global $wpdb;
		$column_where = esc_sql( $column_where );
		$column       = esc_sql( $column );

		return $wpdb->get_var( $wpdb->prepare( "SELECT $column FROM $this->table_name WHERE $column_where = %s LIMIT 1;", $column_value ) );
	}

	/**
	 * Delete multiple entries by IDs
	 *
	 * @param array $ids Array of IDs.
	 *
	 * @access public
	 * @since  1.0
	 * @return int|false Number of rows deleted or false if none.
	 */
	public function delete_by_ids( $ids ) {

		global $wpdb;

		if ( is_array( $ids ) ) {
			$ids = implode( ',', array_map( 'intval', $ids ) );
		} else {
			$ids = intval( $ids );
		}

		$results = $wpdb->query( "DELETE FROM  $this->table_name WHERE `ID` IN( {$ids} )" );

		return $results;

	}

	/**
	 * Insert a new row
	 *
	 * @param array  $data Row data.
	 * @param string $type Type of table.
	 *
	 * @access public
	 * @since  1.0
	 * @return int|false Newly created ID or false on error.
	 */
	public function insert( $data, $type = '' ) {
		global $wpdb;

		// Set default values
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		do_action( 'nml_pre_insert_' . $type, $data );

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys      = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		$added = $wpdb->insert( $this->table_name, $data, $column_formats );

		$new_id = false;

		if ( $added ) {
			$new_id = $wpdb->insert_id;

			do_action( 'nml_post_insert_' . $type, $new_id, $data );
		}

		return $new_id;
	}

	/**
	 * Update a row
	 *
	 * @param int    $row_id ID of the row to update.
	 * @param array  $data   New data to insert in the row.
	 * @param string $where  Column to match the ID against.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function update( $row_id, $data = array(), $where = '' ) {

		global $wpdb;

		// Row ID must be positive integer
		$row_id = absint( $row_id );

		if ( empty( $row_id ) ) {
			return false;
		}

		if ( empty( $where ) ) {
			$where = $this->primary_key;
		}

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys      = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( false === $wpdb->update( $this->table_name, $data, array( $where => $row_id ), $column_formats ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Delete a row identified by the primary key.
	 *
	 * @param int $row_id ID of the row to delete.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function delete( $row_id = 0 ) {

		global $wpdb;

		// Row ID must be positive integer
		$row_id = absint( $row_id );

		if ( empty( $row_id ) ) {
			return false;
		}

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM $this->table_name WHERE $this->primary_key = %d", $row_id ) ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Check if the given table exists.
	 *
	 * @param string $table Name of the table to check.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function table_exists( $table ) {

		global $wpdb;
		$table = sanitize_text_field( $table );

		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE '%s'", $table ) ) === $table;

	}

	/**
	 * Check if the table was ever installed.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function installed() {
		return $this->table_exists( $this->table_name );
	}

}