<?php

/**
 * Subscriber Object
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
 * Class NML_Subscriber
 *
 * @since 1.0
 */
class NML_Subscriber {

	/**
	 * The subscriber ID
	 *
	 * @var int
	 * @access public
	 * @since  1.0
	 */
	public $ID = 0;

	/**
	 * The subscriber's email address
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $email;

	/**
	 * The subscriber's first name
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $first_name;

	/**
	 * The subscriber's last name
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $last_name;

	/**
	 * The subscriber's status
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $status;

	/**
	 * The subscriber's signup date
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $signup_date;

	/**
	 * The subscriber's confirmation date
	 *
	 * @var string|null
	 * @access public
	 * @since  1.0
	 */
	public $confirm_date = null;

	/**
	 * The subscriber's IP address used during signup
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $ip;

	/**
	 * Number of emails the subscriber has received
	 *
	 * @var int
	 * @access public
	 * @since  1.0
	 */
	public $email_count = 0;

	/**
	 * Subscriber notes
	 *
	 * @var string
	 * @access public
	 * @since  1.0
	 */
	public $notes = '';

	/**
	 * The database abstraction
	 *
	 * @var NML_DB_Subscribers
	 * @access protected
	 * @since  1.0
	 */
	protected $db;

	/**
	 * NML_Subscriber constructor.
	 *
	 * @param string|int $id_or_email Subscriber ID or email address.
	 *
	 * @access public
	 * @since  1.0
	 * @return void
	 */
	public function __construct( $id_or_email ) {

		$this->db = new NML_DB_Subscribers();

		if ( is_numeric( $id_or_email ) ) {
			$field = 'ID';
		} else {
			$field = 'email';
		}

		$subscriber = $this->db->get_subscriber_by( $field, $id_or_email );

		if ( empty( $subscriber ) || ! is_object( $subscriber ) ) {
			return;
		}

		$this->setup_subscriber( $subscriber );

	}

	/**
	 * Given the subscriber data, set up all the environment variables.
	 *
	 * @param object $subscriber Subscriber object from the database.
	 *
	 * @access private
	 * @since  1.0
	 * @return bool
	 */
	private function setup_subscriber( $subscriber ) {

		if ( ! is_object( $subscriber ) ) {
			return false;
		}

		foreach ( $subscriber as $key => $value ) {
			$this->$key = $value;
		}

		if ( ! empty( $this->ID ) && ! empty( $this->email ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Magic __get function to dispatch a call to retrieve a private property
	 *
	 * @param $key
	 *
	 * @access public
	 * @since  1.0
	 * @return mixed
	 */
	public function __get( $key ) {

		if ( method_exists( $this, 'get_' . $key ) ) {
			return call_user_func( array( $this, 'get_' . $key ) );
		} else {
			return new WP_Error( 'nml-subscriber-invalid-property', sprintf( __( 'Can\'t get property %s', 'naked-mailing-list' ), $key ) );
		}

	}

	/**
	 * Creates a subscriber
	 *
	 * @param array $data Array of attributes for the subscriber
	 *
	 * @access public
	 * @since  1.0
	 * @return int|false Subscriber ID if successfully added/updated, or false on failure.
	 */
	public function create( $data = array() ) {

		if ( $this->ID != 0 || empty( $data ) ) {
			return false;
		}

		$defaults = array();

		$args = wp_parse_args( $data, $defaults );
		$args = $this->sanitize_columns( $args );

		if ( empty( $args['email'] ) || ! is_email( $args['email'] ) ) {
			return false;
		}

		/**
		 * Fires before a customer is created
		 *
		 * @param array $args Contains customer information such as name and email.
		 */
		do_action( 'nml_subscriber_pre_create', $args );

		$created = false;

		// The DB class 'add' implies an update if the subscriber being asked to be created already exists.
		if ( $this->db->add( $data ) ) {

			// We've successfully added/updated the customer, reset the class vars with the new data
			$subscriber = $this->db->get_subscriber_by( 'email', $args['email'] );

			// Setup the subscriber data with the values from the DB.
			$this->setup_subscriber( $subscriber );

			$created = $this->ID;

		}

		/**
		 * Fires after a subscriber is created
		 *
		 * @param int   $created If created successfully, the subscriber ID.  Defaults to false.
		 * @param array $args    Contains subscriber information such as name and email.
		 */
		do_action( 'edd_subscriber_post_create', $created, $args );

		return $created;

	}

	/**
	 * Update a subscriber record
	 *
	 * @param array $data Array of data attributes for a subscriber (checked via whitelist).
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function update( $data = array() ) {

		if ( empty( $data ) ) {
			return false;
		}

		$data = $this->sanitize_columns( $data );

		do_action( 'nml_subscriber_pre_update', $this->ID, $data );

		$updated = false;

		if ( $this->db->update( $this->ID, $data ) ) {

			$subscriber = $this->db->get_subscriber_by( 'ID', $this->ID );
			$this->setup_subscriber( $subscriber );

			$updated = true;

		}

		do_action( 'nml_subscriber_post_update', $updated, $this->ID, $data );

		return $updated;

	}

	/**
	 * Get an array of lists the subscriber is added to.
	 *
	 * @access public
	 * @since  1.0
	 * @return array
	 */
	public function get_lists() {
		// @todo
	}

	/**
	 * Checks whether or not the subscriber is on a list.
	 *
	 * @param int $list_id ID of the list to check.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function is_on_list( $list_id ) {
		// @todo
	}

	/**
	 * Add the subscriber to a list.
	 *
	 * @param int $list_id ID of the list to add the subscriber to.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool Whether or not the subscriber was successfully added.
	 */
	public function add_to_list( $list_id ) {
		// @todo
	}

	/**
	 * Get an array of tags the subscriber has.
	 *
	 * @access public
	 * @since  1.0
	 * @return array
	 */
	public function get_tags() {
		// @todo
	}

	/**
	 * Checks whether or not the subscriber has a given tag.
	 *
	 * @param int $tag_id ID of the tag to check.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function has_tag( $tag_id ) {
		// @todo
	}

	/**
	 * Tag the subscriber
	 *
	 * @param int|string $tag_id_or_name ID or name of the tag to add to the subscriber.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool
	 */
	public function tag( $tag_id_or_name ) {

	}

	/**
	 * Increase the subscriber's email count.
	 *
	 * @param int $count The number to increment by.
	 *
	 * @access public
	 * @since  1.0
	 * @return int The new email count.
	 */
	public function increase_email_count( $count = 1 ) {

		// Make sure it's numeric and not negative
		if ( ! is_numeric( $count ) || $count != absint( $count ) ) {
			return false;
		}

		$new_total = (int) $this->email_count + (int) $count;

		do_action( 'nml_subscriber_pre_increase_email_count', $count, $this->ID );

		if ( $this->update( array( 'email_count' => $new_total ) ) ) {
			$this->email_count = $new_total;
		}

		do_action( 'nml_subscriber_post_increase_email_count', $this->email_count, $count, $this->ID );

		return $this->email_count;

	}

	/**
	 * Retrieve meta field for a subscriber.
	 *
	 * @param string $meta_key The meta key to retrieve.
	 * @param bool   $single   Whether to return a single value.
	 *
	 * @access public
	 * @since  1.0
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
	 */
	public function get_meta( $meta_key = '', $single = true ) {
		return naked_mailing_list()->subscriber_meta->get_meta( $this->ID, $meta_key, $single );
	}

	/**
	 * Add meta data for a subscriber.
	 *
	 * @param string $meta_key   The name of the meta field.
	 * @param mixed  $meta_value The value of the meta field.
	 * @param bool   $unique     Whether the same key should not be added.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool False for failure, true for success.
	 */
	public function add_meta( $meta_key = '', $meta_value, $unique = false ) {
		return naked_mailing_list()->subscriber_meta->add_meta( $this->ID, $meta_key, $meta_value, $unique );
	}

	/**
	 * Update meta data for a subscriber.
	 *
	 * @param string $meta_key   The name of the meta field.
	 * @param mixed  $meta_value The value of the meta field.
	 * @param mixed  $prev_value Optional. Previous value to check before updating.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool False for failure, true for success.
	 */
	public function update_meta( $meta_key = '', $meta_value, $prev_value = '' ) {
		return naked_mailing_list()->subscriber_meta->update_meta( $this->ID, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Delete meta data field for a subscriber.
	 *
	 * @param string $meta_key   The name of the meta field.
	 * @param mixed  $meta_value The value of the meta field.
	 *
	 * @access public
	 * @since  1.0
	 * @return bool False for failure, true for success.
	 */
	public function delete_meta( $meta_key = '', $meta_value ) {
		return naked_mailing_list()->subscriber_meta->delete_meta( $this->ID, $meta_key, $meta_value );
	}

	/**
	 * Sanitize the data for update/create
	 *
	 * @param array $data The data to sanitize.
	 *
	 * @access private
	 * @since  1.0
	 * @return array The sanitized data, based off column defaults.
	 */
	private function sanitize_columns( $data ) {

		$columns        = $this->db->get_columns();
		$default_values = $this->db->get_column_defaults();

		foreach ( $columns as $key => $type ) {

			// Only sanitize data that we were provided.
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			switch ( $type ) {

				case '%s' :
					if ( 'email' == $key ) {
						$data[ $key ] = sanitize_email( $data[ $key ] );
					} else {
						$data[ $key ] = sanitize_text_field( $data[ $key ] );
					}
					break;

				case '%d' :
					if ( ! is_numeric( $data[ $key ] ) || (int) $data[ $key ] !== absint( $data[ $key ] ) ) {
						$data[ $key ] = $default_values[ $key ];
					} else {
						$data[ $key ] = absint( $data[ $key ] );
					}
					break;

				case '%f' :
					// Convert what was given to a float.
					$value = floatval( $data[ $key ] );

					if ( ! is_float( $value ) ) {
						$data[ $key ] = $default_values[ $key ];
					} else {
						$data[ $key ] = $value;
					}
					break;

				default :
					$data[ $key ] = sanitize_text_field( $data[ $key ] );
					break;


			}

		}

		return $data;

	}

}