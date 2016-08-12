<?php

/**
 * WP Multi Network Admin
 *
 * @package WPMN
 * @subpackage Admin
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Main admin interface
 *
 * @since 1.3
 */
class WP_MS_Networks_Admin {

	/**
	 * Hook methods in
	 */
	public function __construct() {

		// Menus
		add_action( 'admin_menu',         array( $this, 'admin_menu'                   ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu'           ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu_separator' ) );

		// Page save handers
		add_action( 'admin_init',         array( $this, 'page_save_handlers' ) );

		// Add feedback to notices
		add_action( 'network_admin_notices', array( $this, 'network_admin_notices' ) );

		// Row links
		add_filter( 'manage_sites_action_links', array( $this, 'add_move_blog_link' ), 10, 2 );

		// Styling & Scripting
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Return the URL of the Networks page
	 *
	 * @return string Absolute URL to Networks page
	 */
	public function admin_url( $args = array() ) {
		$network_admin = network_admin_url( 'admin.php' );

		// Parse args
		$r = wp_parse_args( $args, array(
			'page' => 'networks'
		) );

		$result = add_query_arg( $r, $network_admin );

		return apply_filters( 'edit_networks_screen_url', $result, $args );
	}

	/**
	 * Add the Move action to Sites page on WP >= 3.1
	 */
	public function add_move_blog_link( $actions = array(), $cur_blog_id = 0 ) {

		// Bail if main site for network
		if ( (int) get_current_site()->blog_id === (int) $cur_blog_id ) {
			return $actions;
		}

		// Assemble URL
		$url = $this->admin_url( array(
			'action'  => 'move',
			'blog_id' => (int) $cur_blog_id,
		) );

		// Add URL to actions links
		if ( current_user_can( 'manage_networks' ) ) {
			$actions['move'] = '<a href="' . esc_url( $url ) . '" class="move">' . esc_html__( 'Move', 'wp-multi-network' ) . '</a>';
		}

		// Return modified actions links
		return $actions;
	}

	/**
	 * Add the My Networks page to the site-level dashboard
	 *
	 * If the user is super admin on another Network, don't require elevated
	 * permissions on the current Site
	 */
	public function admin_menu() {

		// Bail if user has no networks
		if ( ! user_has_networks() ) {
			return;
		}

		// Add the dashboard page
		add_dashboard_page( esc_html__( 'My Networks', 'wp-multi-network' ), esc_html__( 'My Networks', 'wp-multi-network' ), 'read', 'my-networks', array( $this, 'my_networks_page' ) );
	}

	/**
	 * Add Networks menu and entries to the Network-level dashboard
	 *
	 * This method puts the cart before the horse, and could maybe live in the
	 * WP_MS_Networks_List_Table class also.
	 */
	public function network_admin_menu() {
		add_menu_page( esc_html__( 'Networks', 'wp-multi-network' ), esc_html__( 'Networks', 'wp-multi-network' ), 'manage_networks', 'networks', array( $this, 'networks_page_router' ), 'dashicons-networking', -1 );

		add_submenu_page( 'networks', esc_html__( 'All Networks', 'wp-multi-network' ), esc_html__( 'All Networks', 'wp-multi-network' ), 'list_networks',   'networks',        array( $this, 'networks_page_router' ) );
		add_submenu_page( 'networks', esc_html__( 'Add New',      'wp-multi-network' ), esc_html__( 'Add New',      'wp-multi-network' ), 'create_networks', 'add-new-network', array( $this, 'edit_network_page'    ) );

		require_once wpmn()->plugin_dir . '/includes/classes/class-wp-ms-networks-list-table.php' ;
	}

	/**
	 * Add a seperator between the 'Networks' and 'Dashboard' menu items on the
	 * Network dashboard
	 *
	 * @since 1.5.2
	 */
	public function network_admin_menu_separator() {
		global $menu;

		$menu['-2'] = array( '', 'read', 'separator', '', 'wp-menu-separator' );
	}

	/**
	 * Add javascript on networks admin pages only
	 *
	 * @since 1.7.0
	 */
	public function enqueue_scripts( $page = '' ) {

		// Bail if not a network page
		if ( ! in_array( $page, array( 'toplevel_page_networks', 'networks_page_add-new-network' ) ) ) {
			return;
		}

		// Enqueue assets
		wp_enqueue_style( 'wp-multi-network',  wpmn()->plugin_url . 'assets/css/wp-multi-network.css', array(),           wpmn()->asset_version, false );
		wp_enqueue_script( 'wp-multi-network', wpmn()->plugin_url . 'assets/js/wp-multi-network.js',   array( 'jquery', 'post' ), wpmn()->asset_version, true  );
	}

	/**
	 * Action feedback
	 */
	public function network_admin_notices() {

		// Possible feedbacks
		$feedbacks = array(
			'network_updated' => array(
				'1' => esc_html__( 'Network updated.',     'wp-multi-network' ),
				'0' => esc_html__( 'Network not updated.', 'wp-multi-network' )
			),
			'network_created' => array(
				'1' => esc_html__( 'Network created.',     'wp-multi-network' ),
				'0' => esc_html__( 'Network not created.', 'wp-multi-network' )
			),
			'network_deleted' => array(
				'1' => esc_html__( 'Network deleted.',     'wp-multi-network' ),
				'0' => esc_html__( 'Network not deleted.', 'wp-multi-network' )
			),
			'site_moved' => array(
				'1' => esc_html__( 'Site moved.',     'wp-multi-network' ),
				'0' => esc_html__( 'Site not moved.', 'wp-multi-network' )
			)
		);

		// Look for possible notice
		foreach ( $feedbacks as $type => $success ) {
			if ( isset( $_GET[ $type ] ) && in_array( $_GET[ $type ], array_keys( $success ) ) ) :
				$updated = ( '1' === $_GET[ $type ] )
					? 'updated'
					: 'error'; ?>

				<div id="message" class="<?php echo esc_attr( $updated ); ?> notice is-dismissible">
					<p>
						<?php echo esc_html( $feedbacks[ $type ][ $_GET[ $type ] ] ); ?>
						<a href="<?php echo esc_url( $this->admin_url() ); ?>"><?php esc_html_e( 'Back to Networks.', 'wp-multi-network' ); ?></a>
					</p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'wp-multi-network' ); ?></span>
					</button>
				</div>

			<?php
				break;
			endif;
		}
	}

	/**
	 * Save any form submissions
	 *
	 * @since 1.7.0
	 */
	public function page_save_handlers() {

		// Create network
		if ( isset( $_POST['action'] ) && isset( $_POST['domain'] ) && isset( $_POST['path'] ) && ( 'create' === $_POST['action'] ) ) {
			$this->add_network_handler();
		}

		// Update network
		if ( isset( $_POST['action'] ) && isset( $_POST['network_id'] ) && ( 'update' === $_POST['action'] ) ) {
			$this->reassign_sites_handler();
			$this->update_network_handler();
		}

		// Delete network
		if ( isset( $_POST['delete'] ) && isset( $_GET['id'] ) ) {
			$this->delete_network_handler();
		}

		// Delete many networks
		if ( isset( $_POST['delete_multiple'] ) && isset( $_POST['deleted_networks'] ) ) {
			$this->delete_multiple_networks_handler();
		}

		// Move site to different network
		if ( isset( $_POST['move'] ) && isset( $_GET['blog_id'] ) ) {
			$this->move_site_handler();
		}
	}

	/**
	 * Network listing and editing functions are routed through this function
	 *
	 * @since 1.7.0
	 */
	public function networks_page_router() {

		// Bail if not Super Admin
		if ( ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-multi-network' ) );
		}

		// Handle form saving
		$this->page_save_handlers();

		// What action is taking place?
		$action = isset( $_GET['action'] )
			? sanitize_key( $_GET['action'] )
			: '';

		switch ( $action ) {

			// Move a site
			case 'move':
				$this->move_site_page();
				break;

			// Delete an entire network
			case 'delete_network':
				$this->delete_network_page();
				break;

			// Edit a single network
			case 'edit_network':
				$this->edit_network_page();
				break;

			// View all networks
			case 'all_networks':

				// Doing action?
				$doaction = isset( $_POST['action'] ) && ( $_POST['action'] != -1 )
					? sanitize_key( $_POST['action']  )
					: sanitize_key( $_POST['action2'] );

				switch ( $doaction ) {
					case 'delete':
						$this->delete_multiple_network_page();
						break;

					default:
						$this->all_networks_page();
						break;
				}
				break;

			// All networks
			default:
				$this->all_networks_page();
				break;
		}
	}

	/** Pages *****************************************************************/

	/**
	 * Network listing dashboard page
	 *
	 * @since 1.7.0
	 *
	 * @uses WP_MS_Networks_List_Table List_Table iterator for networks
	 */
	public function all_networks_page() {
		$wp_list_table = new WP_MS_Networks_List_Table();
		$wp_list_table->prepare_items(); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' );

				// Add New link
				if ( current_user_can( 'create_networks' ) ) : ?>

					<a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

				<?php endif;

				// Search results
				if ( isset( $_REQUEST['s'] ) && $_REQUEST['s'] ) :
					printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;', 'wp-multi-network' ) . '</span>', esc_html( $_REQUEST['s'] ) );
				endif; ?></h1>

			<form method="post" action="<?php echo esc_url( $this->admin_url( array( 'action' => 'domains' ) ) ); ?>" id="domain-search">
				<?php $wp_list_table->search_box( esc_html__( 'Search Networks', 'wp-multi-network' ), 'networks' ); ?>
				<input type="hidden" name="action" value="domains">
			</form>

			<form method="post" id="form-domain-list" action="<?php echo esc_url( $this->admin_url( array( 'action' => 'all_networks' ) ) ); ?>">
				<?php $wp_list_table->display(); ?>
			</form>
		</div>

		<?php
	}

	/**
	 * New network creation dashboard page
	 *
	 * @since 1.7.0
	 */
	public function edit_network_page() {

		// Get the network
		$network = isset( $_GET['id'] )
			? wp_get_network( $_GET['id'] )
			: null;

		// Metaboxes
		add_meta_box( 'wpmn-edit-network-details', esc_html__( 'Details', 'wp-multi-network' ), 'wpmn_edit_network_details_metabox', get_current_screen()->id, 'normal', 'high', array( $network ) );
		add_meta_box( 'wpmn-edit-network-publish', esc_html__( 'Network', 'wp-multi-network' ), 'wpmn_edit_network_publish_metabox', get_current_screen()->id, 'side',   'high', array( $network ) );

		// New Site
		if ( empty( $network ) ) {
			$network_title = '';

			add_meta_box( 'wpmn-edit-network-new-site', esc_html__( 'Root Site', 'wp-multi-network' ), 'wpmn_edit_network_new_site_metabox', get_current_screen()->id, 'advanced', 'high', array( $network ) );
		} else {
			switch_to_network( $network->id );

			// Network title?
			$network_title = ! empty( $network )
				? get_site_option( 'site_name' )
				: '';

			restore_current_network();

			add_meta_box( 'wpmn-edit-network-assign-sites', esc_html__( 'Site Assignment', 'wp-multi-network' ), 'wpmn_edit_network_assign_sites_metabox', get_current_screen()->id, 'advanced', 'high', array( $network ) );
		} ?>

		<div class="wrap">
			<h1><?php

				// Edit
				if ( ! empty( $network )  ) :
					esc_html_e( 'Edit Network', 'wp-multi-network' ); ?>

					<?php if ( current_user_can( 'create_networks' ) ) : ?>

						<a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

					<?php endif;

				// Add New
				else :
					esc_html_e( 'Add New Network', 'wp-multi-network' );
				endif; ?>
			</h1>

			<form method="post" id="edit-network-form" action="">
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content" style="position: relative;">
							<div id="titlediv">
								<div id="titlewrap">
									<label class="screen-reader-text" id="title-prompt-text" for="title"><?php echo esc_html_e( 'Enter network title here', 'wp-multi-network' ); ?></label>
									<input type="text" name="title" size="30" id="title" spellcheck="true" autocomplete="off" value="<?php echo esc_attr( $network_title ); ?>">
								</div>
							</div>
						</div>

						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes( get_current_screen()->id, 'side', $network ); ?>
						</div>

						<div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes( get_current_screen()->id, 'normal',   $network ); ?>
							<?php do_meta_boxes( get_current_screen()->id, 'advanced', $network ); ?>
						</div>
					</div>
				</div>
			</form>
		</div>

		<?php
	}

	/**
	 * Dashboard screen for moving sites -- accessed from the "Sites" screen
	 */
	public function move_site_page() {

		// Get network by id
		$site = get_blog_details( (int) $_GET['blog_id'] );

		add_meta_box( 'wpmn-move-site-list',    esc_html__( 'Assign Network', 'wp-multi-network' ), 'wpmn_move_site_list_metabox',   get_current_screen()->id, 'normal', 'high', array( $site ) );
		add_meta_box( 'wpmn-move-site-publish', esc_html__( 'Site',           'wp-multi-network' ), 'wpmn_move_site_assign_metabox', get_current_screen()->id, 'side',   'high', array( $site ) ); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' );

				if ( current_user_can( 'create_networks' ) ) : ?>

					<a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

				<?php endif; ?></h1>

			<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes( get_current_screen()->id, 'side', $site ); ?>
						</div>

						<div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes( get_current_screen()->id, 'normal',   $site ); ?>
							<?php do_meta_boxes( get_current_screen()->id, 'advanced', $site ); ?>
						</div>
					</div>
				</div>
			</form>
		</div>

		<?php
	}

	/**
	 * Output the delete network page
	 *
	 * @global object $wpdb
	 */
	public function delete_network_page() {
		global $wpdb;

		// get network by id
		$network = wp_get_network( $_GET['id'] );

		if ( empty( $network ) ) {
			wp_die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
		}

		// Get sites to delete
		$sites = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d", (int) $_GET['id'] ) ); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Delete Network', 'wp-multi-network' );

				if ( current_user_can( 'create_networks' ) ) : ?>

					<a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

				<?php endif; ?></h1>

			<form method="post" action="<?php echo remove_query_arg( 'action' ); ?>">
				<?php

				// Network has sites
				if ( ! empty( $sites ) ) :

					// Orphaned sites are rescued
					if ( RESCUE_ORPHANED_BLOGS ) : ?>

						<div id="message" class="network-delete">
							<p><?php esc_html_e( 'The following sites are associated with this network:', 'wp-multi-network' ); ?></p>
							<ul class="delete-sites">

								<?php foreach ( $sites as $site ) : ?>

									<li><?php echo esc_html( $site->domain . $site->path ); ?></li>

								<?php endforeach; ?>

							</ul>
							<p>
								<input type="checkbox" name="override" id="override">
								<label for="override">
									<?php esc_html_e( 'Rescue these sites', 'wp-multi-network' ); ?>
								</label>
							</p>
						</div>
						<p><?php printf( esc_html__( 'Are you sure you want to delete the entire "%s" network?', 'wp-multi-network' ), esc_html( $network->domain . $network->path ) ); ?></p>

					<?php else : ?>

						<div id="message" class="network-delete">
							<p><?php esc_html_e( 'The following sites are associated with this network:', 'wp-multi-network' ); ?></p>
							<ul class="delete-sites">

								<?php foreach ( $sites as $site ) : ?>

									<li><?php echo esc_html( $site->domain . $site->path ); ?></li>

								<?php endforeach; ?>

							</ul>
							<p>
								<input type="checkbox" name="override" id="override">
								<label for="override">
									<?php esc_html_e( 'Delete these sites', 'wp-multi-network' ); ?>
								</label>
							</p>
						</div>
						<p><?php printf( esc_html__( 'Are you sure you want to delete the entire "%s" network?', 'wp-multi-network' ), esc_html( wp_get_scheme() . $network->domain . $network->path ) ); ?></p>

					<?php

					endif;
				endif;

				submit_button( esc_html__( 'Delete Network', 'wp-multi-network' ), 'primary', 'delete', false ); ?>
				<a class="button" href="<?php echo esc_url( $this->admin_url() ); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
			</form>
		</div>

		<?php
	}

	/**
	 * Output the delete multiple networks page
	 *
	 * @global object $wpdb
	 */
	public function delete_multiple_network_page() {
		global $wpdb;

		$all_networks = array_map( 'absint', $_POST['all_networks'] );

		// Ensure each network is valid
		foreach ( $all_networks as $network ) {
			if ( ! wp_get_network( $network ) ) {
				wp_die( esc_html__( 'You have selected an invalid network for deletion.', 'wp-multi-network' ) );
			}
		}

		// remove primary network from list
		if ( in_array( 1, $all_networks ) ) {
			$sites = array();
			foreach ( $all_networks as $network ) {
				if ( $network != 1 ) {
					$sites[] = $network;
				}
			}
			$all_networks = $sites;
		}

		$network = $wpdb->get_results( "SELECT * FROM {$wpdb->site} WHERE id IN (" . implode( ',', $all_networks ) . ')' );
		if ( empty( $network ) ) {
			wp_die( esc_html__( 'You have selected an invalid network or networks for deletion', 'wp-multi-network' ) );
		}

		$sites = $wpdb->get_results( "SELECT * FROM {$wpdb->blogs} WHERE site_id IN (" . implode( ',', $all_networks ) . ')' ); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h1>
			<h3><?php esc_html_e( 'Delete Multiple Networks', 'wp-multi-network' ); ?></h3>
			<form method="post" action="<?php echo esc_url( $this->admin_url() ); ?>">
				<?php if ( ! empty( $sites ) ) {

					if ( RESCUE_ORPHANED_BLOGS ) { ?>

						<div class="error inline">
							<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
							<ul>
								<?php foreach ( $network as $deleted_network ) : ?>
									<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>"><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p><?php esc_html_e( 'There are blogs associated with one or more of these networks. Deleting them will move these blogs to the holding network.', 'wp-multi-network' ); ?></p>
							<p><label for="override"><?php esc_html_e( 'If you still want to delete these networks, check the following box', 'wp-multi-network' ); ?>:</label> <input type="checkbox" name="override" id="override"></p>
						</div>

					<?php } else { ?>

						<div class="error inline">
							<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
							<ul>
								<?php foreach ( $network as $deleted_network ) : ?>
									<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>"><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p><?php esc_html_e( 'There are blogs associated with one or more of these networks. Deleting them will delete those blogs as well.', 'wp-multi-network' ); ?></p>
							<p><label for="override"><?php esc_html_e( 'If you still want to delete these networks, check the following box', 'wp-multi-network' ); ?>:</label> <input type="checkbox" name="override" id="override"></p>
						</div>

					<?php
					}

				} else { ?>

					<div id="message inline">
						<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
						<ul>
							<?php foreach ( $network as $deleted_network ) : ?>
								<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>"><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php } ?>

				<p><?php esc_html_e( 'Are you sure you want to delete these networks?', 'wp-multi-network' ); ?></p>
				<input type="submit" name="delete_multiple" value="<?php esc_html_e( 'Delete Networks', 'wp-multi-network' ); ?>" class="button">
				<input type="submit" name="cancel" value="<?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?>" class="button">
			</form>
		</div>

		<?php
	}

	/**
	 * Output the my networks page
	 */
	public function my_networks_page() {
		global $wpdb; ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'My Networks', 'wp-multi-network' ); ?></h1>

			<?php

			$my_networks = user_has_networks();
			foreach( $my_networks as $key => $network_id ) {
				$my_networks[ $key ] = $wpdb->get_row( $wpdb->prepare(
					'SELECT s.*, sm.meta_value as site_name, b.blog_id FROM ' . $wpdb->site . ' s LEFT JOIN ' . $wpdb->sitemeta . ' as sm ON sm.site_id = s.id AND sm.meta_key = %s LEFT JOIN ' . $wpdb->blogs . ' b ON s.id = b.site_id AND b.path = s.path WHERE s.id = %d',
					'site_name',
					$network_id
				) );
			}

			// Shameless copy of My Sites
			?>
			<table class="widefat fixed">
			<?php
			$num = count( $my_networks );
			$cols = 1;
			if ( $num >= 20 ) {
				$cols = 4;
			} elseif ( $num >= 10 ) {
				$cols = 2;
			}
			$num_rows = ceil( $num / $cols );
			$split = 0;
			for ( $i = 1; $i <= $num_rows; $i++ ) {
				$rows[] = array_slice( $my_networks, $split, $cols );
				$split = $split + $cols;
			}

			$c = '';
			foreach ( $rows as $row ) {
				$c = $c == 'alternate' ? '' : 'alternate';
				echo "<tr class='$c'>";
				$i = 0;
				foreach ( $row as $network ) {
					$s = $i == 3 ? '' : 'border-right: 1px solid #ccc;';
					switch_to_network( $network->id ); ?>

					<td valign='top' style= <?php echo $s ?>>
						<h3><?php echo esc_html( $network->site_name ); ?></h3>
						<p><?php echo apply_filters( 'mynetworks_network_actions', "<a href='" . network_home_url(). "'>" . esc_html__( 'Visit', 'wp-multi-network' ) . "</a> | <a href='" . network_admin_url() . "'>" . esc_html__( 'Dashboard', 'wp-multi-network' ) . "</a>", $network ); ?></p>
					</td>

					<?php
					restore_current_network();
					$i++;
				}
				echo "</tr>";
			}?>
			</table>
		</div>

		<?php
	}

	/** Handlers **************************************************************/

	/**
	 * Handle the request to add a new network
	 *
	 * @since 1.7.0
	 */
	private function add_network_handler() {

		// Options to copy
		if ( isset( $_POST['options_to_clone'] ) && is_array( $_POST['options_to_clone'] ) ) {
			$options_to_clone = array_keys( $_POST['options_to_clone'] );
		} else {
			$options_to_clone = array_keys( network_options_to_copy() );
		}

		// Clone from
		$clone = isset( $_POST['clone_network'] )
			? (int) $_POST['clone_network']
			: get_current_site()->id;

		// Title
		$network_title = isset( $_POST['title'] )
			? $_POST['title']
			: '';

		// Domain
		$network_domain = isset( $_POST['domain'] )
			? $_POST['domain']
			: '';

		// Path
		$network_path = isset( $_POST['path'] )
			? $_POST['path']
			: '';

		// Path
		$site_name = ! empty( $_POST['new_site'] )
			? $_POST['new_site']
			: $network_title;

		// Bail if missing fields
		if ( empty( $network_title ) || empty( $network_domain ) || empty( $network_path ) ) {
			$this->handler_redirect( array(
				'page'            => 'add-new-network',
				'network_created' => '0'
			) );
		}

		// Add the network
		$result = add_network( array(
			'domain'           => $network_domain,
			'path'             => $network_path,
			'site_name'        => $site_name,
			'user_id'          => get_current_user_id(),
			'clone_network'    => $clone,
			'options_to_clone' => $options_to_clone
		) );

		// Update title
		if ( ! empty( $result ) && ! is_wp_error( $result ) ) {
			switch_to_network( $result );

			if ( ! empty( $_POST['title'] ) ) {
				update_site_option( 'site_name', $_POST['title'] );
			}

			// Activate WPMN on this new network
			update_site_option( 'active_sitewide_plugins', array( 'wp-multi-network/wpmn-loader.php' => time() ) );
			restore_current_network();

			// Redirect args
			$r = array( 'network_created' => '1' );

		// Failure
		} else {
			$r = array( 'network_created' => '0' );
		}

		// Handle redirect
		$this->handler_redirect( $r );
	}

	/**
	 * Handle the request to update a network
	 *
	 * @since 1.7.0
	 */
	private function update_network_handler() {

		// Cast
		$network_id = (int) $_POST['network_id'];

		// Bail if invalid network
		if ( ! wp_get_network( $network_id ) ) {
			wp_die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
		}

		// Title
		$network_title = isset( $_POST['title'] )
			? $_POST['title']
			: '';

		// Domain
		$network_domain = isset( $_POST['domain'] )
			? $_POST['domain']
			: '';

		// Path
		$network_path = isset( $_POST['path'] )
			? $_POST['path']
			: '';

		// Bail if missing fields
		if ( empty( $network_title ) || empty( $network_domain ) || empty( $network_path ) ) {
			$this->handler_redirect( array(
				'page'            => 'networks',
				'id'              => $network_id,
				'action'          => 'edit_network',
				'network_updated' => '0'
			) );
		}

		// Update domain & path
		$updated = update_network( $network_id, $_POST['domain'], $_POST['path'] );
		$success = 0;

		// Maybe update network title
		if ( ! is_wp_error( $updated ) ) {
			update_network_option( $network_id, 'site_name', $network_title );
			$success = '1';
		}

		// Handle redirect
		$this->handler_redirect( array(
			'id'              => $network_id,
			'action'          => 'edit_network',
			'network_updated' => $success,
		) );
	}

	/**
	 * Handle the request to move a site
	 *
	 * @since 1.7.0
	 */
	private function move_site_handler() {
		$moved   = move_site( $_GET['blog_id'], $_POST['to'] );
		$success = is_wp_error( $moved )
			? '0'
			: '1';

		// Handle redirect
		wp_safe_redirect( add_query_arg( array(
			'site_moved' => $success,
		), network_admin_url( 'sites.php' ) ) );
		exit;
	}

	/**
	 * Handle the request to reassign sites
	 *
	 * @since 1.7.0
	 *
	 * @global object $wpdb
	 */
	private function reassign_sites_handler() {
		global $wpdb;

		// Coming in
		$to = isset( $_POST['to'] )
			? $_POST['to']
			: array();

		// Orphaning out
		$from = isset( $_POST['from'] )
			? $_POST['from']
			: array();

		// Bail early if no movement
		if ( empty( $to ) && empty( $from ) ) {
			return;
		}

		// Cast the network ID
		$network_id = (int) $_GET['id'];

		// Query for sites in this network
		$sql    = "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = %d";
		$prep   = $wpdb->prepare( $sql, $network_id );
		$sites  = $wpdb->get_results( $prep );
		$moving = array_filter( array_merge( $to, $from ) );

		// Loop through and move sites
		foreach ( $moving as $site_id ) {

			// Skip the main site of this network
			if ( is_main_site_for_network( $site_id ) ) {
				continue;
			}

			// Coming in
			if ( in_array( $site_id, $to ) && ! in_array( $site_id, $sites ) ) {
				move_site( $site_id, $network_id );

			// Orphaning out
			} elseif ( in_array( $site_id, $from ) ) {
				move_site( $site_id, 0 );
			}
		}
	}

	/**
	 * Handle the request to delete a network
	 *
	 * @since 1.7.0
	 */
	private function delete_network_handler() {

		// Delete network
		$result = delete_network( (int) $_GET['id'], isset( $_POST['override'] ) );
		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		// Handle redirect
		$this->handler_redirect( array(
			'network_deleted' => '1',
		) );
	}

	/**
	 * Handle the request to helete many networks
	 *
	 * @since 1.7.0
	 */
	private function delete_multiple_networks_handler() {

		// Delete networks
		foreach ( $_POST['deleted_networks'] as $deleted_network ) {
			$result = delete_network( (int) $deleted_network, ( isset( $_POST['override'] ) ) );
			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}
		}

		// Handle redirect
		$this->handler_redirect( array(
			'networks_deleted' => '1',
		) );
	}

	/**
	 * Handle redirect after page submit
	 *
	 * @since 1.7.0
	 *
	 * @param array $args
	 */
	private function handler_redirect( $args = array() ) {

		// Set feedback flags
		wp_safe_redirect( $this->admin_url( $args ) );
		exit;
	}
}
