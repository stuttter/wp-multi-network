<?php
/**
 * REST API: WP_REST_Networks_Controller class
 *
 * @package WPMN
 * @subpackage REST_API
 * @since 2.3.0
 */

/**
 * Core controller used to access networks via the REST API.
 *
 * @since 2.3.0
 *
 * @see WP_REST_Controller
 */
class WP_MS_REST_Networks_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		$this->namespace = 'wpmn/v1';
		$this->rest_base = 'networks';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 2.3.0
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace, '/' . $this->rest_base, array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the object.', 'wp-multi-network' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( ' Flag to permit blog deletion - default setting of false will prevent deletion of occupied networks', 'wp-multi-network' ),
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks if a given request has access to read networks.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|bool True if the request has read access, error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'list_networks' );
	}

	/**
	 * Retrieves a list of network items.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function get_items( $request ) {

		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_collection_params();

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'domain'         => 'domain__in',
			'domain_exclude' => 'domain__not_in',
			'exclude'        => 'network__not_in',
			'include'        => 'network__in',
			'offset'         => 'offset',
			'order'          => 'order',
			'path'           => 'path__in',
			'path_exclude'   => 'path__not_in',
			'per_page'       => 'number',
			'search'         => 'search',
		);

		$prepared_args = array();

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $prepared_args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$prepared_args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Ensure certain parameter values default to empty strings.
		foreach ( array( 'search' ) as $param ) {
			if ( ! isset( $prepared_args[ $param ] ) ) {
				$prepared_args[ $param ] = '';
			}
		}

		if ( isset( $registered['orderby'] ) ) {
			$prepared_args['orderby'] = $this->normalize_query_param( $request['orderby'] );
		}

		$prepared_args['no_found_rows'] = false;

		if ( isset( $registered['page'] ) && empty( $request['offset'] ) ) {
			$prepared_args['offset'] = $prepared_args['number'] * ( absint( $request['page'] ) - 1 );
		}

		/**
		 * Filters arguments, before passing to WP_Network_Query, when querying networks via the REST API.
		 *
		 * @since 2.3.0
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_network_query/
		 *
		 * @param array $prepared_args Array of arguments for WP_Network_Query.
		 * @param WP_REST_Request $request The current request.
		 */
		$prepared_args = apply_filters( 'rest_network_query', $prepared_args, $request );

		$query        = new WP_Network_Query();
		$query_result = $query->query( $prepared_args );
		$networks     = array();
		foreach ( $query_result as $network ) {
			if ( ! $this->check_read_permission( $network, $request ) ) {
				continue;
			}

			$data       = $this->prepare_item_for_response( $network, $request );
			$networks[] = $this->prepare_response_for_collection( $data );
		}

		$total_networks = (int) $query->found_networks;
		$max_pages      = (int) $query->max_num_pages;

		if ( $total_networks < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $prepared_args['number'], $prepared_args['offset'] );

			$query                  = new WP_Network_Query();
			$prepared_args['count'] = true;

			$total_networks = $query->query( $prepared_args );
			$max_pages      = ceil( $total_networks / $request['per_page'] );
		}

		$response = rest_ensure_response( $networks );
		$response->header( 'X-WP-Total', $total_networks );
		$response->header( 'X-WP-TotalPages', $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( $request['page'] > 1 ) {
			$prev_page = $request['page'] - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}

		if ( $max_pages > $request['page'] ) {
			$next_page = $request['page'] + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Get the network, if the ID is valid.
	 *
	 * @since 2.3.0
	 *
	 * @param int $id Supplied ID.
	 *
	 * @return WP_Network|WP_Error Network object if ID is valid, WP_Error otherwise.
	 */
	protected function get_network( $id ) {
		$error = new WP_Error(
			'rest_network_invalid_id', __( 'Invalid network ID.', 'wp-multi-network' ), array(
				'status' => 404,
			)
		);
		if ( (int) $id <= 0 ) {
			return $error;
		}

		$id      = (int) $id;
		$network = get_network( $id );
		if ( empty( $network ) ) {
			return $error;
		}

		return $network;
	}

	/**
	 * Checks if a given request has access to read the network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|bool True if the request has read access for the item, error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		return $this->check_read_permission( $network, $request );
	}

	/**
	 * Retrieves a network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function get_item( $request ) {
		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		$data     = $this->prepare_item_for_response( $network, $request );
		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Checks if a given request has access to create a network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|bool True if the request has access to create items, error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'create_networks' );
	}

	/**
	 * Creates a network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error(
				'rest_network_exists', __( 'Cannot create existing network.', 'wp-multi-network' ), array(
					'status' => 400,
				)
			);
		}

		$prepared_network = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $prepared_network ) ) {
			return $prepared_network;
		}

		/**
		 * Filters a network before it is inserted via the REST API.
		 *
		 * Allows modification of the network right before it is inserted via insert_network().
		 * Returning a WP_Error value from the filter will shortcircuit insertion and allow
		 * skipping further processing.
		 *
		 * @since 2.3.0
		 *
		 * @param array|WP_Error $prepared_network The prepared network data for insert_network().
		 * @param WP_REST_Request $request Request used to insert the network.
		 */
		$prepared_network = apply_filters( 'rest_pre_insert_network', $prepared_network, $request );
		if ( is_wp_error( $prepared_network ) ) {
			return $prepared_network;
		}

		$network_id = add_network( wp_slash( (array) $prepared_network ) );

		if ( is_wp_error( $network_id ) ) {
			return $this->get_wp_error( $network_id );
		} elseif ( ! $network_id ) {
			return new WP_Error(
				'rest_network_failed_create', __( 'Creating network failed.', 'wp-multi-network' ), array(
					'status' => 500,
				)
			);
		}

		$network = $this->get_network( $network_id );

		/**
		 * Fires after a network is created or updated via the REST API.
		 *
		 * @since 2.3.0
		 *
		 * @param WP_Network $network Inserted or updated network object.
		 * @param WP_REST_Request $request Request object.
		 * @param bool $creating True when creating a network, false
		 *                                  when updating.
		 */
		do_action( 'rest_insert_network', $network, $request, true );

		$fields_update = $this->update_additional_fields_for_object( $network, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$context = current_user_can( 'manage_network' ) ? 'edit' : 'view';

		$request->set_param( 'context', $context );

		$response = $this->prepare_item_for_response( $network, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $network_id ) ) );

		return $response;
	}

	/**
	 * Checks if a given REST request has access to update a network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|bool True if the request has access to update the item, error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		if ( ! $this->check_edit_permission( $network ) ) {
			return new WP_Error(
				'rest_cannot_edit', __( 'Sorry, you are not allowed to edit this network.', 'wp-multi-network' ), array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Updates a network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function update_item( $request ) {
		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		$id = $network->id;

		$prepared_args = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_args ) ) {
			return $prepared_args;
		}

		if ( ! empty( $prepared_args ) ) {
			if ( is_wp_error( $prepared_args ) ) {
				return $prepared_args;
			}

			$domain = $prepared_args['domain'];
			$path   = $prepared_args['path'];

			$updated = update_network( $id, $domain, $path );

			if ( is_wp_error( $updated ) ) {
				return $this->get_wp_error( $updated );
			} elseif ( false === $updated ) {
				return new WP_Error(
					'rest_network_failed_edit', __( 'Updating network failed.', 'wp-multi-network' ), array(
						'status' => 500,
					)
				);
			}
		}

		$network = $this->get_network( $id );

		/** This action is documented in class-wp-rest-networks-api.php */
		do_action( 'rest_insert_network', $network, $request, false );

		$fields_update = $this->update_additional_fields_for_object( $network, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $network, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Checks if a given request has access to delete a network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|bool True if the request has access to delete the item, error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		return current_user_can( 'delete_network', $network->id );
	}

	/**
	 * Deletes a network.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function delete_item( $request ) {
		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		$request->set_param( 'context', 'edit' );

		$previous = $this->prepare_item_for_response( $network, $request );
		$result   = delete_network( $network->id, $force );
		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $this->get_wp_error( $result );
		} elseif ( ! $result ) {
			return new WP_Error(
				'rest_cannot_delete', __( 'The network cannot be deleted.', 'wp-multi-network' ), array(
					'status' => 500,
				)
			);
		}

		/**
		 * Fires after a network is deleted via the REST API.
		 *
		 * @since 2.3.0
		 *
		 * @param WP_Network $network The deleted network data.
		 * @param WP_REST_Response $response The response returned from the API.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( 'rest_delete_network', $network, $response, $request );

		return $response;
	}

	/**
	 * Prepares a single network output for response.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Network      $network Network object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $network, $request ) {
		$data = array(
			'id'            => (int) $network->id,
			'path'          => $network->path,
			'domain'        => $network->domain,
			'cookie_domain' => $network->cookie_domain,
			'site_name'     => $network->site_name,
			'site_id'       => (int) $network->site_id,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $network ) );

		/**
		 * Filters a network returned from the API.
		 *
		 * Allows modification of the network right before it is returned.
		 *
		 * @since 2.3.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Network $network The original network object.
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_network', $response, $network, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Network $network Network object.
	 *
	 * @return array Links for the given network.
	 */
	protected function prepare_links( $network ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $network->id ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		return $links;
	}

	/**
	 * Helper function to normalisze query params.
	 *
	 * @since 2.3.0
	 *
	 * @param string $query_param Query parameter.
	 *
	 * @return string The normalized query parameter.
	 */
	protected function normalize_query_param( $query_param ) {
		switch ( $query_param ) {
			case 'include':
				$normalized = 'network__in';
				break;
			default:
				$normalized = $query_param;
				break;
		}

		return $normalized;
	}

	/**
	 * Prepares a single network to be inserted into the database.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array|WP_Error Prepared network, otherwise WP_Error object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_network = array();

		if ( isset( $request['path'] ) ) {
			$prepared_network['path'] = $request['path'];
		}

		if ( isset( $request['domain'] ) ) {
			$prepared_network['domain'] = $request['domain'];
		}

		/**
		 * Filters a network after it is prepared for the database.
		 *
		 * Allows modification of the network right after it is prepared for the database.
		 *
		 * @since 2.3.0
		 *
		 * @param array $prepared_network The prepared network data for `insert_network`.
		 * @param WP_REST_Request $request The current request.
		 */
		return apply_filters( 'rest_preprocess_network', $prepared_network, $request );
	}

	/**
	 * Retrieves the network's schema, conforming to JSON Schema.
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'network',
			'type'       => 'object',
			'properties' => array(
				'id'      => array(
					'description' => __( 'Unique identifier for the object.', 'wp-multi-network' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'domain'  => array(
					'description' => __( 'The domain of the network object.', 'wp-multi-network' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'path'    => array(
					'description' => __( 'The path of the network object', 'wp-multi-network' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'site_id' => array(
					'description' => __( 'The id of the main site on the network object.', 'wp-multi-network' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'link'    => array(
					'description' => __( 'URL to the object.', 'wp-multi-network' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @since 2.3.0
	 *
	 * @return array Networks collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['domain'] = array(
			'description' => __( 'Limit result set to networks assigned to specific domains.', 'wp-multi-network' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['domain_exclude'] = array(
			'description' => __( 'Ensure result set excludes networks assigned to specific domain.', 'wp-multi-network' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['exclude'] = array(
			'description' => __( 'Ensure result set excludes specific IDs.', 'wp-multi-network' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['include'] = array(
			'description' => __( 'Limit result set to specific IDs.', 'wp-multi-network' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['offset'] = array(
			'description' => __( 'Offset the result set by a specific number of items.', 'wp-multi-network' ),
			'type'        => 'integer',
		);

		$query_params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'wp-multi-network' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array(
				'asc',
				'desc',
			),
		);

		$query_params['orderby'] = array(
			'description' => __( 'Sort collection by object attribute.', 'wp-multi-network' ),
			'type'        => 'string',
			'default'     => 'id',
			'enum'        => array(
				'id',
				'domain',
				'path',
				'domain_length',
				'path_length',
				'include',
			),
		);

		$query_params['path'] = array(
			'description' => __( 'Limit result set to networks of specific path.', 'wp-multi-network' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['path_exclude'] = array(
			'description' => __( 'Ensure result set excludes specific path.', 'wp-multi-network' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		/**
		 * Filter collection parameters for the networks controller.
		 *
		 * This filter registers the collection parameter, but does not map the
		 * collection parameter to an internal WP_Network_Query parameter. Use the
		 * `rest_network_query` filter to set WP_Network_Query parameters.
		 *
		 * @since 2.3.0
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_network_collection_params', $query_params );
	}


	/**
	 * Checks if the network can be read.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Network      $network Network object.
	 * @param WP_REST_Request $request Request data to check.
	 *
	 * @return bool Whether the network can be read.
	 */
	protected function check_read_permission( $network, $request ) {
		switch_to_network( $network->id );
		$allowed = current_user_can( 'manage_network' );
		restore_current_network();

		return $allowed;
	}

	/**
	 * Checks if a network can be edited or deleted.
	 *
	 * @since 2.3.0
	 *
	 * @param object $network Network object.
	 *
	 * @return bool Whether the network can be edited or deleted.
	 */
	protected function check_edit_permission( $network ) {
		return current_user_can( 'edit_network', $network->id );
	}

	/**
	 * Helper method to add status code to returned WP_error objects.
	 *
	 * @param WP_Error $error Input WP_Error object.
	 *
	 * @return WP_Error $error
	 */
	protected function get_wp_error( $error ) {
		$code = $error->get_error_code();
		switch ( $code ) {
			case 'network_not_exist':
				$status = 404;
				break;
			case 'network_exists':
				$status = 500;
				break;
			case 'network_not_empty':
			case 'network_empty_domain':
			case 'network_not_exist':
			case 'network_not_updated':
			case 'blog_bad':
				$status = 400;
				break;
			case 'network_super_admin':
			case 'network_user':
			default:
				$status = 403;
				break;
		}
		$error->add_data( array( 'status' => $status ), $code );

		return $error;
	}
}
