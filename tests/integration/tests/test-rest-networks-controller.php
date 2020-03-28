<?php

class  WP_MS_Test_REST_Networks_Controller extends WP_Test_REST_Controller_Testcase {
	protected static $superadmin_id;
	protected static $total_networks = 30;
	protected static $per_page       = 50;
	protected static $network_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);
		grant_super_admin( self::$superadmin_id );

		// Set up networks for pagination tests.
		for ( $i = 0; $i < self::$total_networks - 1; $i++ ) {
			$network_ids[] = $factory->network->create();
		}

		self::$network_id = $factory->network->create();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$superadmin_id );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wpmn/v1/networks', $routes );
		$this->assertCount( 2, $routes['/wpmn/v1/networks'] );
		$this->assertArrayHasKey( '/wpmn/v1/networks/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wpmn/v1/networks/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wpmn/v1/networks' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$network  = $this->factory->network->create();
		$request  = new WP_REST_Request( 'OPTIONS', '/wpmn/v1/networks/' . $network );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wpmn/v1/networks' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertEquals(
			array(
				'context',
				'domain',
				'domain_exclude',
				'exclude',
				'include',
				'offset',
				'order',
				'orderby',
				'page',
				'path',
				'path_exclude',
				'per_page',
				'search',
			),
			$keys
		);
	}


	public function test_get_items() {
		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'GET', '/wpmn/v1/networks' );
		$request->set_param( 'per_page', self::$per_page );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$networks = $response->get_data();
		$this->assertCount( self::$total_networks + 1, $networks );
	}

	public function test_get_item() {
		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wpmn/v1/networks/%d', self::$network_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_network_data( $data );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wpmn/v1/networks/%d', self::$network_id ) );
		$request->set_query_params(
			array(
				'context' => 'edit',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_network_data( $data );
	}

	public function test_create_item() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wpmn/v1/networks' );
		$request->set_param( 'domain', 'www.example.net' );
		$request->set_param( 'path', '/network' );
		$request->set_param( 'site_name', 'main-network' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->check_network_data( $data );
		$network = get_network( $data['id'] );
		$this->assertEquals( $network->domain, 'www.example.net' );
		$this->assertEquals( $network->path, '/network/' );
		$this->assertEquals( $network->site_name, 'main-network' );
	}

	public function test_update_item() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'POST', '/wpmn/v1/networks/' . $network_id );
		$request->set_param( 'domain', 'www.example.co' );
		$request->set_param( 'path', '/update' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->check_network_data( $data );
		$network = get_network( $data['id'] );
		$this->assertEquals( $network->domain, 'www.example.co' );
		$this->assertEquals( $network->path, '/update/' );
	}

	public function test_delete_item() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( self::$superadmin_id );
		$request          = new WP_REST_Request( 'DELETE', '/wpmn/v1/networks/' . $network_id );
		$request['force'] = true;
		$response         = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_no_update_item() {
		$network_id = wp_rand();
		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'POST', '/wpmn/v1/networks/' . $network_id );
		$request->set_param( 'domain', 'www.example.co' );
		$request->set_param( 'path', '/update' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	public function test_no_delete_item() {
		$network_id = wp_rand();
		wp_set_current_user( self::$superadmin_id );
		$request          = new WP_REST_Request( 'DELETE', '/wpmn/v1/networks/' . $network_id );
		$request['force'] = true;
		$response         = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wpmn/v1/networks' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 5, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'path', $properties );
		$this->assertArrayHasKey( 'domain', $properties );
		$this->assertArrayHasKey( 'site_id', $properties );
		$this->assertArrayHasKey( 'link', $properties );

	}


	/**
	 *
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wpmn/v1/networks' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 *
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wpmn/v1/networks/' . self::$network_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 *
	 */
	public function test_create_item_no_permission() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', '/wpmn/v1/networks' );
		$request->set_param( 'domain', 'www.example.net' );
		$request->set_param( 'path', '/network' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 *
	 */
	public function test_update_item_no_permission() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', '/wpmn/v1/networks/' . $network_id );
		$request->set_param( 'domain', 'www.example.co' );
		$request->set_param( 'path', '/update' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );
	}

	public function test_delete_item_no_permission() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( 0 );
		$request          = new WP_REST_Request( 'DELETE', '/wpmn/v1/networks/' . $network_id );
		$request['force'] = true;
		$response         = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	protected function check_network_data( $data ) {
		$network = get_network( $data['id'] );

		$this->assertEquals( $network->id, $data['id'] );
		$this->assertEquals( $network->domain, $data['domain'] );
		$this->assertEquals( $network->path, $data['path'] );
		$this->assertEquals( $network->site_name, $data['site_name'] );
	}
}
