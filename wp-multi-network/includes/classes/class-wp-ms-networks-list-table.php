<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Networks List Table class.
 *
 * @package WPMN
 * @since 1.3
 */
class WP_MS_Networks_List_Table extends WP_List_Table {

	/**
	 * Main constructor
	 */
	public function __construct() {
		parent::__construct( array(
			'ajax'     => false,
			'plural'   => 'networks',
			'singular' => 'network',
			'screen'   => 'wpmn'
		) );
	}

	/**
	 * Return capability used to determine if user can manage networks during
	 * an ajax request
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_networks' );
	}

	/**
	 * Prepare items for querying
	 */
	public function prepare_items() {

		// Pagination
		$per_page = $this->get_items_per_page( 'networks_per_page' );
		$pagenum  = $this->get_pagenum();

		// User vars
		$order_by = ! empty( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby']     ) : '';
		$order    = ! empty( $_REQUEST['order']   ) ? strtoupper( $_REQUEST['order']         ) : 'ASC';
		$search   = ! empty( $_REQUEST['s']       ) ? stripslashes( trim( $_REQUEST[ 's' ] ) ) : '';

		// Searching?
		if ( false !== strpos( $search, '*' ) ) {
			$search = trim( $search, '*' );
		}

		// Fallback to ASC
		if ( ! in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
			$order = 'ASC';
		}

		// Query arguments
		$args = array(
			'number'  => intval( $per_page ),
			'offset'  => intval( ( $pagenum - 1 ) * $per_page ),
			'orderby' => $order_by,
			'order'   => $order,
			'search'  => $search,
		);

		// Get networks
		$this->items = get_networks( $args );

		// Get total network count
		$count = get_networks( array_merge( $args, array(
			'count'  => true,
			'offset' => 0,
			'number' => 0,
		) ) );

		// Setup pagination
		$this->set_pagination_args( array(
			'total_items' => $count,
			'per_page'    => $per_page,
		) );
	}

	/**
	 * Output message when no networks are found
	 */
	public function no_items() {
		esc_html_e( 'No networks found.', 'wp-multi-network' );
	}

	/**
	 * Return array of bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array();

		if ( current_user_can( 'delete_networks' ) ) {
			$actions['delete'] = __( 'Delete', 'wp-multi-network' );
		}

		return $actions;
	}

	/**
	 * Output pagination
	 *
	 * @param type $which
	 */
	public function pagination( $which ) {
		parent::pagination( $which );
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @return string Name of the default primary column, in this case, 'title'.
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * Return array of columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return apply_filters( 'wpmn_networks_columns', array(
			'cb'     => '<input type="checkbox">',
			'title'  => __( 'Network Title',  'wp-multi-network' ),
			'domain' => __( 'Domain',         'wp-multi-network' ),
			'path'   => __( 'Path',           'wp-multi-network' ),
			'blogs'  => __( 'Sites',          'wp-multi-network' ),
			'admins' => __( 'Network Admins', 'wp-multi-network' )
		) );
	}

	/**
	 * Return array of columns that are sortable
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title'  => 'id',
			'domain' => 'domain',
			'path'   => 'path'
		);
	}

	/**
	 * Return all classes for list-table
	 *
	 * @return type
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', $this->_args['plural'] );
	}

	/**
	 * Display table rows
	 *
	 * @since 2.0.0
	 */
	public function display_rows() {

		// Start buffer
		ob_start();

		// Loop through rows
		foreach ( $this->items as $network ) {
			$class = ( (int) get_current_site()->id === (int) $network->id )
				? 'current'
				: 'not-current';

			?><tr class="<?php echo esc_attr( $class ); ?>"><?php

				$this->single_row_columns( $network );

			?></tr><?php
		}

		// End buffer
		echo ob_get_clean();
	}

	/**
	 * Can the current user delete this network?
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network
	 *
	 * @return boolean
	 */
	private function can_delete( $network ) {

		// Bail if main network
		if ( is_main_network( $network->id ) ) {
			return false;
		}

		// Can't delete current network
		if ( get_current_network_id() === $network->id ) {
			return false;
		}

		// Bail if user cannot delete network
		if ( ! current_user_can( 'delete_network', $network->id ) ) {
			return false;
		}

		// Assume true (if you're already on this screen)
		return true;
	}

	/**
	 * Get network states
	 *
	 * @since 2.2.0
	 *
	 * @param WP_Network $network
	 */
	private function get_states( $network ) {

		// Defaults
		$network_states = array();
		$network_state  = '';

		// Primary
		if ( is_main_network( $network->id ) ) {
			$network_states['primary'] = esc_html__( 'Primary', 'wp-multi-network' );
		}

		/**
		 * Filters the default network display states used in the network list table.
		 *
		 * @since 2.2.0
		 *
		 * @param array   $network_states An array of network display states.
		 * @param WP_Post $network        The current network object.
		 */
		$network_states = apply_filters( 'display_network_states', $network_states, $network );

		// Setup states
		if ( ! empty( $network_states ) ) {
			$state_count = count( $network_states );
			$i = 0;
			$network_state = ' &mdash; ';

			// Concatenate states
			foreach ( $network_states as $state ) {
				++$i;
				( $i === $state_count )
					? $sep = ''
					: $sep = ', ';

				$network_state .= "<span class='network-state'>{$state}{$sep}</span>";
			}
		}

		return $network_state;
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @param WP_Network $network
	 */
	public function column_cb( $network ) {

		// Bail if user cannot delete
		if ( ! $this->can_delete( $network ) ) {
			return;
		}

		?><label class="screen-reader-text" for="network_<?php echo esc_attr( $network->id ); ?>"><?php
			printf( __( 'Select %s' ), get_network_option( $network->id, 'site_name' ) );
		?></label>
		<input type="checkbox" id="network_<?php echo esc_attr( $network->id ); ?>" name="all_networks[]" value="<?php echo esc_attr( $network->id ) ?>">
		<?php
	}

	/**
	 * Handles the network name column output.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @global string $mode
	 *
	 * @param array $network Current network.
	 */
	public function column_title( $network ) {

		// Get states
		$network_states = $this->get_states( $network );

		// Title, with edit link if available.
		$link = esc_html( $network->site_name );
		if ( current_user_can( 'edit_network', $network->id ) ) {
			$link = sprintf(
				'<a href="%1$s" class="edit" aria-label="%2$s">%3$s</a>',
				esc_url( add_query_arg( array(
					'page'   => 'networks',
					'action' => 'edit_network',
					'id'     => $network->id
				) ) ),
				/* translators: %s: network title */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)', 'wp-multi-network' ), $link ) ),
				$link
			);
		}

		?>

		<strong>
			<?php
			echo $link;
			echo $network_states;
			?>
		</strong>

		<?php
	}

	/**
	 * Output network domain
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network
	 */
	public function column_domain( $network ) {
		echo esc_html( $network->domain );
	}

	/**
	 * Output network path
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network
	 */
	public function column_path( $network ) {
		echo esc_html( $network->path );
	}

	/**
	 * Output network sites
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network
	 */
	public function column_blogs( $network ) {

		// Get site count for each network
		$sites = get_network_option( $network->id, 'blog_count' );

		// Switch to get href
		switch_to_network( $network->id );
		$url = network_admin_url( 'sites.php' );
		restore_current_network();

		echo '<a href="' . esc_url( $url ) . '">' . esc_html( $sites ) . '</a>';
	}

	/**
	 * Output administrators for a network
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network
	 */
	public function column_admins( $network ) {

		// Get network administrators
		$network_admins = (array) get_network_option( $network->id, 'site_admins', array() );
		$network_admins = ! empty( $network_admins )
			? array_filter( $network_admins )
			: array();

		// Admins or nothing
		echo empty( $network_admins )
			? join( ', ', $network_admins )
			: '&mdash;';
	}

	/**
	 * Default column
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network
	 * @param string     $column_name
	 */
	public function column_default( $network, $column_name ) {
		parent::column_default( $network, $column_name );
	}

	/**
	 * Handles the ID column output.
	 *
	 * @access public
	 *
	 * @param array $network Current network.
	 */
	public function column_id( $network ) {
		echo $network->id;
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @since 2.0.0
	 * @access protected
	 *
	 * @param WP_Network $network     Site being acted upon.
	 * @param string     $column_name Current column name.
	 * @param string     $primary     Primary column name.
	 *
	 * @return string Row actions output.
	 */
	protected function handle_row_actions( $network, $column_name, $primary ) {

		// Bail if not primary column
		if ( $primary !== $column_name ) {
			return;
		}

		switch_to_network( $network->id );
		$network_admin_url = network_admin_url();
		$network_home_url  = network_home_url();
		restore_current_network();

		$myurl = add_query_arg( array(
			'page' => 'networks',
			'id'   => $network->id
		) );

		$edit_network_url = add_query_arg( array(
			'action' => 'edit_network'
		), $myurl );

		$delete_network_url = wp_nonce_url( add_query_arg( array(
			'action' => 'delete_network'
		), $myurl ) );

		// Empty actions array
		$actions = array();

		// Edit
		if ( current_user_can( 'edit_network', $network->id ) ) {
			$actions['edit'] = '<span class="edit"><a href="' . esc_url( $edit_network_url  ) . '">' . esc_html__( 'Edit', 'wp-multi-network' ) . '</a></span>';
		}

		// Dashboard
		if ( current_user_can( 'manage_networks' ) ) {
			$actions['network_admin'] = '<span><a href="' . esc_url( $network_admin_url ) . '">' . esc_html__( 'Dashboard', 'wp-multi-network' ) . '</a></span>';
		}

		// Visit
		$actions['visit'] = '<span><a href="' . esc_url( $network_home_url ) . '">' . esc_html__( 'Visit', 'wp-multi-network' ) . '</a></span>';

		// Delete
		if ( $this->can_delete( $network ) ) {
			$actions['delete']	= '<span class="delete"><a href="' . esc_url( $delete_network_url ) . '">' . esc_html__( 'Delete', 'wp-multi-network' ) . '</a></span>';
		}

		$actions = apply_filters( 'manage_networks_action_links', array_filter( $actions ), $network->id, $network->sitename );

		return $this->row_actions( $actions );
	}
}
