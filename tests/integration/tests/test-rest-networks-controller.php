<?php

class  WP_MS_Test_REST_Networks_Controller extends WP_Test_REST_Controller_Testcase{
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

	public function test_get_items(){

  }

	public function test_get_item(){

  }

	public function test_create_item(){

  }

	public function test_update_item(){

  }

	public function test_delete_item(){

  }

	public function test_prepare_item(){

  }

	public function test_get_item_schema(){

  }
}
