<?php
/**
 * WP_MS_Networks_List_Table class
 *
 * @package WPMN
 * @since 1.3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class used to implement displaying networks in a list table.
 *
 * @since 1.3.0
 */
class WP_MS_Networks_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'ajax'     => false,
				'plural'   => 'networks',
				'singular' => 'network',
				'screen'   => 'wpmn',
			)
		);
	}

	/**
	 * Checks whether the current user can manage list table items during an AJAX request.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True if the user can manage list table items, false otherwise.
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_networks' );
	}

	/**
	 * Prepares the list table items.
	 *
	 * @since 1.3.0
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'networks_per_page' );
		$pagenum  = $this->get_pagenum();

		$order_by = ! empty( $_GET['orderby'] )
			? sanitize_key( $_GET['orderby'] )
			: '';

		$order = ! empty( $_GET['order'] )
			? strtoupper( sanitize_key( $_GET['order'] ) )
			: 'ASC';

		$search = ! empty( $_REQUEST['s'] )
			? stripslashes( sanitize_text_field( $_REQUEST['s'] ) )
			: '';

		if ( false !== strpos( $search, '*' ) ) {
			$search = trim( $search, '*' );
		}

		if ( ! in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
			$order = 'ASC';
		}

		$args = array(
			'number'        => intval( $per_page ),
			'offset'        => intval( ( $pagenum - 1 ) * $per_page ),
			'orderby'       => $order_by,
			'order'         => $order,
			'search'        => $search,
			'no_found_rows' => false,
		);

		$query = new WP_Network_Query();

		$this->items = $query->query( $args );
		$count       = $query->found_networks;

		$this->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Outputs the message to show when no list items are found.
	 *
	 * @since 1.3.0
	 */
	public function no_items() {
		esc_html_e( 'No networks found.', 'wp-multi-network' );
	}

	/**
	 * Gets the array of supported bulk actions.
	 *
	 * @since 1.3.0
	 *
	 * @return array Bulk actions as $slug => $label pairs.
	 */
	public function get_bulk_actions() {
		$actions = array();

		if ( current_user_can( 'delete_networks' ) ) {
			$actions['delete'] = __( 'Delete', 'wp-multi-network' );
		}

		return $actions;
	}

	/**
	 * Outputs list table pagination.
	 *
	 * @since 1.3.0
	 *
	 * @param type $which Where to display the pagination. Either 'top' or 'bottom'.
	 */
	public function pagination( $which ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		parent::pagination( $which );
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 1.3.0
	 *
	 * @return string Name of the default primary column, in this case, 'title'.
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * Gets the list table columns.
	 *
	 * @since 1.3.0
	 *
	 * @return array Columns as $slug => $label pairs.
	 */
	public function get_columns() {
		$columns = array(
			'cb'     => '<input type="checkbox">',
			'title'  => __( 'Network Title', 'wp-multi-network' ),
			'domain' => __( 'Domain', 'wp-multi-network' ),
			'path'   => __( 'Path', 'wp-multi-network' ),
			'blogs'  => __( 'Sites', 'wp-multi-network' ),
			'admins' => __( 'Network Admins', 'wp-multi-network' ),
		);

		/**
		 * Filters the networks list table column.
		 *
		 * @since 1.3.0
		 *
		 * @param array $columns Columns as $slug => $label pairs.
		 */
		return apply_filters( 'wpmn_networks_columns', $columns );
	}

	/**
	 * Gets the list table columns that are sortable.
	 *
	 * @since 1.3.0
	 *
	 * @return array Columns as $slug => $orderby_field pairs.
	 */
	public function get_sortable_columns() {
		return array(
			'title'  => 'id',
			'domain' => 'domain',
			'path'   => 'path',
		);
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @since 2.3.0
	 *
	 * @param object $network The current network item.
	 */
	public function single_row( $network ) {
		$class = (int) get_current_site()->id === (int) $network->id ? 'current' : 'not-current';

		echo '<tr class="' . esc_attr( $class ) . '">';
		$this->single_row_columns( $network );
		echo '</tr>';
	}

	/**
	 * Checks whether the current user can delete a given network.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network Network to check delete capabilities for.
	 *
	 * @return bool True if the user can delete the network, false otherwise.
	 */
	private function can_delete( $network ) {

		// Bail if main network.
		if ( is_main_network( $network->id ) ) {
			return false;
		}

		// Bail if current network.
		if ( get_current_network_id() === $network->id ) {
			return false;
		}

		return current_user_can( 'delete_network', $network->id );
	}

	/**
	 * Gets the network states for a given network.
	 *
	 * @since 2.2.0
	 *
	 * @param WP_Network $network Network to get states for.
	 * @return string HTML output containing states for the network.
	 */
	private function get_states( $network ) {
		$network_states = array();
		$network_state  = '';

		if ( is_main_network( $network->id ) ) {
			$network_states['primary'] = esc_html__( 'Primary', 'wp-multi-network' );
		}

		/**
		 * Filters the default network display states used in the network list table.
		 *
		 * @since 2.2.0
		 *
		 * @param array      $network_states An array of network display states.
		 * @param WP_Network $network        The current network object.
		 */
		$network_states = apply_filters( 'display_network_states', $network_states, $network );

		// Setup states markup.
		if ( ! empty( $network_states ) ) {
			$state_count   = count( $network_states );
			$i             = 0;
			$network_state = ' &mdash; ';

			foreach ( $network_states as $state ) {
				++$i;

				$sep            = $i === $state_count ? '' : ', ';
				$network_state .= "<span class='network-state'>{$state}{$sep}</span>";
			}
		}

		return $network_state;
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network The current network object.
	 */
	public function column_cb( $network ) {

		// Bail if user cannot delete the network.
		if ( ! $this->can_delete( $network ) ) {
			return;
		}

		?>
		<label class="screen-reader-text" for="network_<?php echo esc_attr( $network->id ); ?>">
			<?php
			printf(
				/* translators: %s: network name */
				esc_html( _x( 'Select %s', 'network checkbox', 'wp-multi-network' ) ),
				esc_html( get_network_option( $network->id, 'site_name' ) )
			);
			?>
		</label>
		<input type="checkbox" id="network_<?php echo esc_attr( $network->id ); ?>" name="all_networks[]" value="<?php echo esc_attr( $network->id ); ?>">
		<?php
	}

	/**
	 * Handles the network name column output.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network The current network object.
	 */
	public function column_title( $network ) {
		$network_states = $this->get_states( $network );

		// Setup the title, with edit link if available.
		$link = esc_html( $network->site_name );
		if ( current_user_can( 'edit_network', $network->id ) ) {
			$link = sprintf(
				'<a href="%1$s" class="edit" aria-label="%2$s">%3$s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'   => 'networks',
							'action' => 'edit_network',
							'id'     => $network->id,
						)
					)
				),
				/* translators: %s: network title */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)', 'wp-multi-network' ), $link ) ),
				$link
			);
		}

		?>

		<strong>
			<?php
			echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $network_states; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</strong>

		<?php
	}

	/**
	 * Handles the network domain column output.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network The current network object.
	 */
	public function column_domain( $network ) {
		echo esc_html( $network->domain );
	}

	/**
	 * Handles the network path column output.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network The current network object.
	 */
	public function column_path( $network ) {
		echo esc_html( $network->path );
	}

	/**
	 * Handles the network sites column output.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network The current network object.
	 */
	public function column_blogs( $network ) {
		$sites = get_network_option( $network->id, 'blog_count' );

		switch_to_network( $network->id );
		$url = network_admin_url( 'sites.php' );
		restore_current_network();

		echo '<a href="' . esc_url( $url ) . '">' . esc_html( $sites ) . '</a>';
	}

	/**
	 * Handles the network administrators column output.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network The current network object.
	 */
	public function column_admins( $network ) {
		$network_admins = (array) get_network_option( $network->id, 'site_admins', array() );
		$network_admins = ! empty( $network_admins ) ? array_filter( $network_admins ) : array();

		// Concatenate for markup.
		echo ! empty( $network_admins ) ? esc_html( join( ', ', $network_admins ) ) : esc_html( '&mdash;' );
	}

	/**
	 * Handles the ID column output.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network The current network object.
	 */
	public function column_id( $network ) {
		echo esc_html( $network->id );
	}

	/**
	 * Generates row action links markup.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Network $network     Site being acted upon.
	 * @param string     $column_name Current column name.
	 * @param string     $primary     Primary column name.
	 *
	 * @return string Row actions output.
	 */
	protected function handle_row_actions( $network, $column_name, $primary ) {

		// Bail if not primary column.
		if ( $primary !== $column_name ) {
			return;
		}

		switch_to_network( $network->id );
		$network_admin_url = network_admin_url();
		$network_home_url  = network_home_url();
		restore_current_network();

		// Setup the base URL.
		$base_url = add_query_arg(
			array(
				'page' => 'networks',
				'id'   => $network->id,
			),
			remove_query_arg(
				array(
					'action',
					'network_created',
					'page',
					'site_moved',
					'success',
				)
			)
		);

		$actions = array();

		// Edit the network.
		if ( current_user_can( 'edit_network', $network->id ) ) {
			$edit_network_url = add_query_arg(
				array(
					'action' => 'edit_network',
				),
				$base_url
			);

			$actions['edit'] = '<span class="edit"><a href="' . esc_url( $edit_network_url ) . '">' . esc_html__( 'Edit', 'wp-multi-network' ) . '</a></span>';
		}

		// Visit the network dashboard.
		if ( current_user_can( 'manage_networks' ) ) {
			$actions['network_admin'] = '<span><a href="' . esc_url( $network_admin_url ) . '">' . esc_html__( 'Dashboard', 'wp-multi-network' ) . '</a></span>';
		}

		// Visit the network main site.
		$actions['visit'] = '<span><a href="' . esc_url( $network_home_url ) . '">' . esc_html__( 'Visit', 'wp-multi-network' ) . '</a></span>';

		// Delete the network.
		if ( $this->can_delete( $network ) ) {
			$delete_network_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'delete_network',
					), $base_url
				)
			);

			$actions['delete'] = '<span class="delete"><a href="' . esc_url( $delete_network_url ) . '">' . esc_html__( 'Delete', 'wp-multi-network' ) . '</a></span>';
		}

		/**
		 * Filters the networks list table row action links.
		 *
		 * @since 2.0.0
		 *
		 * @param array  Action links as $slug => $link_markup pairs.
		 * @param int    The current network ID.
		 * @param string The current network name.
		 */
		$actions = apply_filters( 'manage_networks_action_links', array_filter( $actions ), $network->id, $network->sitename );

		// Return all row actions.
		return $this->row_actions( $actions );
	}
}
