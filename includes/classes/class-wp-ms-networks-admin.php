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
class WPMN_Admin {

	/**
	 * Hook methods in
	 */
	public function __construct() {

		// Menus
		add_action( 'admin_menu',         array( $this, 'admin_menu'                   ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu'           ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu_separator' ) );

		// Row links
		add_filter( 'manage_sites_action_links', array( $this, 'add_move_blog_link' ), 10, 2 );
	}

	/**
	 * Return the URL of the Networks page
	 *
	 * @return string Absolute URL to Networks page
	 */
	public function admin_url() {
		$network_admin = network_admin_url( 'admin.php' );
		$args          = array( 'page' => 'networks' );
		$result        = add_query_arg( $args, $network_admin );

		return $result;
	}

	/**
	 * Add the Move action to Sites page on WP >= 3.1
	 */
	public function add_move_blog_link( $actions, $cur_blog_id ) {
		
		// Assemble URL
		$url = add_query_arg( array(
			'action'  => 'move',
			'blog_id' => (int) $cur_blog_id ),
			$this->admin_url()
		);
		
		// Add URL to actions links
		$actions['move'] = '<a href="' . esc_url( $url ) . '" class="move">' . esc_html__( 'Move', 'wp-multi-network' ) . '</a>';

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
	 */
	public function network_admin_menu() {
		$page = add_menu_page( esc_html__( 'Networks', 'wp-multi-network' ), esc_html__( 'Networks', 'wp-multi-network' ), 'manage_options', 'networks', array( $this, 'networks_page_router' ), 'dashicons-networking', -1 );

		add_submenu_page( 'networks', esc_html__( 'All Networks', 'wp-multi-network' ), esc_html__( 'All Networks', 'wp-multi-network' ), 'manage_options', 'networks',        array( $this, 'networks_page_router' ) );
		add_submenu_page( 'networks', esc_html__( 'Add New',      'wp-multi-network' ), esc_html__( 'Add New',      'wp-multi-network' ), 'manage_options', 'add-new-network', array( $this, 'add_network_page'     ) );

		require_once wpmn()->plugin_dir . '/includes/classes/class-wp-ms-networks-list-table.php' ;

		add_filter( "manage_{$page}-network_columns", array( new WP_MS_Networks_List_Table(), 'get_columns' ), 0 );
		add_action( "load-{$page}",                   array( $this, 'enqueue_scripts' ) );
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
	public function enqueue_scripts() {
		wp_enqueue_style( 'wp-multi-network',  wpmn()->plugin_url . 'assets/css/wp-multi-network.css', array(),           wpmn()->asset_version, false );
		wp_enqueue_script( 'wp-multi-network', wpmn()->plugin_url . 'assets/js/wp-multi-network.js',   array( 'jquery' ), wpmn()->asset_version, true  );
	}

	/**
	 * Action feedback
	 */
	private function feedback() {

		// Updated
		if ( isset( $_GET['updated'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Network updated.', 'wp-multi-network' ); ?></p></div>

		<?php

		// Created
		elseif ( isset( $_GET['added'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Network created.', 'wp-multi-network' ); ?></p></div>

		<?php

		// Delete
		elseif ( isset( $_GET['deleted'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Network deleted.', 'wp-multi-network' ); ?></p></div>

		<?php

		// Moved
		elseif ( isset( $_GET['moved'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Sites moved.', 'wp-multi-network' ); ?></p></div>

		<?php endif;
	}

	/**
	 * Save any form submissions
	 *
	 * @since 1.7.0
	 */
	private function page_save_handlers() {

		// Update network
		if ( isset( $_POST['update'] ) && isset( $_GET['id'] ) ) {
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

		// Create network
		if ( isset( $_POST['add'] ) && isset( $_POST['domain'] ) && isset( $_POST['path'] ) ) {
			$this->add_network_handler();
		}

		// Move site to different network
		if ( isset( $_POST['move'] ) && isset( $_GET['blog_id'] ) ) {
			$this->move_site_handler();
		}

		// Move many sites to this network
		if ( isset( $_POST['reassign'] ) && isset( $_GET['id'] ) ) {
			$this->reassign_site_handler();
		}		
	}

	/**
	 * Main Network-level dashboard page
	 *
	 * Network listing and editing functions are routed through this function
	 */
	public function networks_page_router() {

		// Bail if not Super Admin
		if ( ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-multi-network' ) );
		}

		$this->page_save_handlers();

		// Messages
		$this->feedback();

		// What action is taking place?
		$action = isset( $_GET['action'] )
			? sanitize_key( $_GET['action'] )
			: '';

		switch ( $action ) {

			// Move a site
			case 'move':
				$this->move_site_page();
				break;

			// Move many sites
			case 'assignblogs':
				$this->reassign_site_page();
				break;

			// Delete an entire network
			case 'deletenetwork':
				$this->delete_network_page();
				break;

			// Edit a single network
			case 'editnetwork':
				$this->update_network_page();
				break;

			// View all networks
			case 'allnetworks':

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
	 * @uses WP_MS_Networks_List_Table List_Table iterator for networks
	 */
	public function all_networks_page() {
		$wp_list_table = new WP_MS_Networks_List_Table();
		$wp_list_table->prepare_items(); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' );
			
				// Add New link
				if ( current_user_can( 'manage_network_options' ) ) : ?>

					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'add-new-network' ), $this->admin_url() ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

				<?php endif;

				// Search results
				if ( isset( $_REQUEST['s'] ) && $_REQUEST['s'] ) :
					printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;', 'wp-multi-network' ) . '</span>', esc_html( $_REQUEST['s'] ) );
				endif; ?></h1>

			<form action="<?php echo esc_url( add_query_arg( array( 'action' => 'domains' ), $this->admin_url() ) ); ?>" method="post" id="domain-search">
				<?php $wp_list_table->search_box( esc_html__( 'Search Networks', 'wp-multi-network' ), 'networks' ); ?>
				<input type="hidden" name="action" value="domains" />
			</form>

			<form id="form-domain-list" action="<?php echo esc_url( add_query_arg( array( 'action' => 'allnetworks' ), $this->admin_url() ) ); ?>" method="post">
				<?php $wp_list_table->display(); ?>
			</form>
		</div>

		<?php
	}

	/**
	 * New network creation dashboard page
	 */
	public function add_network_page() {
		?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h1>
			<div id="col-container">
				<p><?php esc_html_e( 'A site will be created at the root of the new network.', 'wp-multi-network' ); ?></p>
				<form method="POST" action="<?php echo esc_url( $this->admin_url() ); ?>">
					<table class="form-table">
						<tr class="form-field form-required">
							<th scope="row"><label for="newName"><?php esc_html_e( 'Network Name', 'wp-multi-network' ); ?>:</label></th>
							<td><input type="text" name="name" id="newName" class="regular-text" title="<?php esc_html_e( 'A friendly name for your new network', 'wp-multi-network' ); ?>" /></td>
						</tr>
						<tr class="form-field form-required">
							<th scope="row"><label for="newDom"><?php esc_html_e( 'Domain', 'wp-multi-network' ); ?>:</label></th>
							<td><?php echo wp_get_scheme(); ?><input type="text" name="domain" id="newDom" class="regular-text" title="<?php esc_html_e( 'The domain for your new network', 'wp-multi-network' ); ?>" /></td>
						</tr>
						<tr class="form-field form-required">
							<th scope="row"><label for="newPath"><?php esc_html_e( 'Path', 'wp-multi-network' ); ?>:</label></th>
							<td><input type="text" name="path" id="newPath" value="/" class="regular-text" title="<?php esc_html_e( 'If you are unsure, put in /', 'wp-multi-network' ); ?>" /></td>
						</tr>
						<tr class="form-field form-required">
							<th scope="row"><label for="newSite"><?php esc_html_e( 'Site Name', 'wp-multi-network' ); ?>:</label></th>
							<td><input type="text" name="newSite" id="newSite" class="regular-text" title="<?php esc_html_e( 'The name for the new site for this network.', 'wp-multi-network' ); ?>" /></td>
						</tr>
					</table>

					<?php submit_button( esc_html__( 'Create Network', 'wp-multi-network' ), 'primary', 'add' ); ?>

				</form>
			</div>
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

				if ( current_user_can( 'manage_network_options' ) ) : ?>

					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'add-new-network' ), $this->admin_url() ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

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
	 * Output site reassignment page
	 *
	 * @global object $wpdb
	 */
	public function reassign_site_page() {
		global $wpdb;

		// Get network by id
		$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) );

		add_meta_box( 'wpmn-assign-sites-list',    esc_html__( 'Site Assignment', 'wp-multi-network' ), 'wpmn_assign_sites_list_metabox',    get_current_screen()->id, 'normal', 'high', array( $network ) );
		add_meta_box( 'wpmn-assign-sites-publish', esc_html__( 'Assign',          'wp-multi-network' ), 'wpmn_assign_sites_publish_metabox', get_current_screen()->id, 'side',   'high', array( $network ) ); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' );

				if ( current_user_can( 'manage_network_options' ) ) : ?>

					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'add-new-network' ), $this->admin_url() ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

				<?php endif; ?></h1>

			<form method="post" id="site-assign-form" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
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
	 * Output the network update page
	 *
	 * @global object $wpdb
	 */
	public function update_network_page() {
		global $wpdb;

		// Get network by id
		$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) ); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks',      'wp-multi-network' ); ?></h1>
			<h3><?php esc_html_e( 'Edit Network:', 'wp-multi-network' ); ?> <?php echo wp_get_scheme(); echo esc_html( $network->domain . $network->path ); ?></h3>
			<form method="post" action="<?php echo remove_query_arg( 'action' ); ?>">
				<table class="form-table">
					<tr class="form-field"><th scope="row"><label for="domain"><?php esc_html_e( 'Domain', 'wp-multi-network' ); ?></label></th><td> <?php echo wp_get_scheme(); ?><input type="text" id="domain" name="domain" value="<?php echo esc_attr( $network->domain ); ?>"></td></tr>
					<tr class="form-field"><th scope="row"><label for="path"><?php esc_html_e( 'Path', 'wp-multi-network' ); ?></label></th><td><input type="text" id="path" name="path" value="<?php echo esc_attr( $network->path ); ?>" /></td></tr>
				</table>
				<?php if ( has_action( 'add_edit_network_option' ) ) : ?>
					<h3><?php esc_html_e( 'Options:', 'wp-multi-network' ); ?></h3>
					<table class="form-table">
						<?php do_action( 'add_edit_network_option' ); ?>
					</table>
				<?php endif; ?>
				<p>
					<input type="hidden" name="networkId" value="<?php echo esc_attr( $network->id ); ?>" />
					<?php submit_button( esc_html__( 'Update Network', 'wp-multi-network' ), 'primary', 'update', false ); ?>
					<a class="button" href="<?php echo esc_url( $this->admin_url() ); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
				</p>
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
		$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) );

		if ( empty( $network ) ) {
			die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
		}

		$sites = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d", (int) $_GET['id'] ) ); ?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h1>
			<h3><?php esc_html_e( 'Delete Network', 'wp-multi-network' ); ?>: <?php echo esc_html( $network->domain . $network->path ); ?></h3>
			<form method="POST" action="<?php echo remove_query_arg( 'action' ); ?>">
				<?php
				if ( ! empty( $sites ) ) {
					if ( RESCUE_ORPHANED_BLOGS && ENABLE_NETWORK_ZERO ) { ?>

						<div id="message" class="error">
							<p><?php esc_html_e( 'There are blogs associated with this network. Deleting it will move them to the holding network.', 'wp-multi-network' ); ?></p>
							<p><label for="override"><?php esc_html_e( 'If you still want to delete this network, check the following box', 'wp-multi-network' ); ?>:</label> <input type="checkbox" name="override" id="override" /></p>
						</div>

					<?php } else { ?>

						<div id="message" class="error">
							<p><?php esc_html_e( 'There are blogs associated with this network. Deleting it will delete those blogs as well.', 'wp-multi-network' ); ?></p>
							<p><label for="override"><?php esc_html_e( 'If you still want to delete this network, check the following box', 'wp-multi-network' ); ?>:</label> <input type="checkbox" name="override" id="override" /></p>
						</div>

					<?php
					}
				}
				?>
				<p><?php esc_html_e( 'Are you sure you want to delete this network?', 'wp-multi-network' ); ?></p>
				<?php submit_button( esc_html__( 'Delete Network', 'wp-multi-network' ), 'primary', 'delete', false ); ?>
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

		// Ensure a list of networks was sent
		if ( !isset( $_POST['allnetworks'] ) ) {
			wp_die( esc_html__( 'You have not selected any networks to delete.', 'wp-multi-network' ) );
		}
		$allnetworks = array_map( create_function( '$val', 'return (int)$val;' ), $_POST['allnetworks'] );

		// Ensure each network is valid
		foreach ( $allnetworks as $network ) {
			if ( !network_exists( (int) $network ) ) {
				wp_die( esc_html__( 'You have selected an invalid network for deletion.', 'wp-multi-network' ) );
			}
		}

		// remove primary network from list
		if ( in_array( 1, $allnetworks ) ) {
			$sites = array();
			foreach ( $allnetworks as $network ) {
				if ( $network != 1 ) {
					$sites[] = $network;
				}
			}
			$allnetworks = $sites;
		}

		$network = $wpdb->get_results( "SELECT * FROM {$wpdb->site} WHERE id IN (" . implode( ',', $allnetworks ) . ')' );
		if ( empty( $network ) ) {
			wp_die( esc_html__( 'You have selected an invalid network or networks for deletion', 'wp-multi-network' ) );
		}

		$sites = $wpdb->get_results( "SELECT * FROM {$wpdb->blogs} WHERE site_id IN (" . implode( ',', $allnetworks ) . ')' ); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h1>
			<h3><?php esc_html_e( 'Delete Multiple Networks', 'wp-multi-network' ); ?></h3>
			<form method="POST" action="<?php echo esc_url( $this->admin_url() ); ?>">
				<?php if ( ! empty( $sites ) ) {

					if ( RESCUE_ORPHANED_BLOGS && ENABLE_NETWORK_ZERO ) { ?>

						<div id="message" class="error">
							<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
							<ul>
								<?php foreach ( $network as $deleted_network ) : ?>
									<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>" /><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p><?php esc_html_e( 'There are blogs associated with one or more of these networks. Deleting them will move these blogs to the holding network.', 'wp-multi-network' ); ?></p>
							<p><label for="override"><?php esc_html_e( 'If you still want to delete these networks, check the following box', 'wp-multi-network' ); ?>:</label> <input type="checkbox" name="override" id="override" /></p>
						</div>

					<?php } else { ?>

						<div id="message" class="error">
							<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
							<ul>
								<?php foreach ( $network as $deleted_network ) : ?>
									<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>" /><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p><?php esc_html_e( 'There are blogs associated with one or more of these networks. Deleting them will delete those blogs as well.', 'wp-multi-network' ); ?></p>
							<p><label for="override"><?php esc_html_e( 'If you still want to delete these networks, check the following box', 'wp-multi-network' ); ?>:</label> <input type="checkbox" name="override" id="override" /></p>
						</div>

					<?php
					}
				} else { ?>

					<div id="message">
						<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
						<ul>
							<?php foreach ( $network as $deleted_network ) : ?>
								<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>" /><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php } ?>

				<p><?php esc_html_e( 'Are you sure you want to delete these networks?', 'wp-multi-network' ); ?></p>
				<input type="submit" name="delete_multiple" value="<?php esc_html_e( 'Delete Networks', 'wp-multi-network' ); ?>" class="button" />
				<input type="submit" name="cancel" value="<?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?>" class="button" />
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

	/**
	 * Admin page for Networks settings
	 *
	 * @todo
	 */
	public function networks_settings_page() {

	}

	/** Handlers **************************************************************/

	/**
	 * Handle new network requests
	 */
	private function add_network_handler() {

		if ( isset( $_POST['options_to_clone'] ) && is_array( $_POST['options_to_clone'] ) ) {
			$options_to_clone = array_keys( $_POST['options_to_clone'] );
		} else {
			$options_to_clone = array_keys( network_options_to_copy() );
		}

		$result = add_network(
			$_POST['domain'],
			$_POST['path'],
			( isset( $_POST['newSite']      ) ? $_POST['newSite']      : esc_attr__( 'New Network Created', 'wp-multi-network' ) ),
			( isset( $_POST['cloneNetwork'] ) ? $_POST['cloneNetwork'] : get_current_site()->id ),
			$options_to_clone
		);

		if ( $result && !is_wp_error( $result ) ) {
			if ( ! empty( $_POST['name'] ) ) {
				switch_to_network( $result );
				add_site_option( 'site_name', $_POST['name'] );
				add_site_option( 'active_sitewide_plugins', array( 'wp-multi-network/wpmn-loader.php' => time() ) );
				restore_current_network();
			}

			$_GET['added']  = 'yes';
			$_GET['action'] = 'saved';
		} else {
			foreach ( $result->errors as $i => $error ) {
				echo( "<h1>Error: " . $error[0] . "</h1>" );
			}
		}
	}

	private function move_site_handler() {
		move_site( $_GET['blog_id'], $_POST['to'] );
		$_GET['moved']  = 'yes';
		$_GET['action'] = 'saved';
	}

	private function reassign_site_handler() {
		global $wpdb;

		if ( isset( $_POST['jsEnabled'] ) ) {
			if ( !isset( $_POST['to'] ) ) {
				die( esc_html__( 'No blogs selected.', 'wp-multi-network' ) );
			}

			$sites = $_POST['to'];
		} else {
			if ( !isset( $_POST['from'] ) ) {
				die( esc_html_e( 'No blogs selected.', 'wp-multi-network' ) );
			}

			$sites = $_POST['from'];
		}

		$current_blogs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d", (int) $_GET['id'] ) );

		foreach ( $sites as $site ) {
			move_site( $site, (int) $_GET['id'] );
		}

		// Move any unlisted blogs to 'zero' network
		if ( ENABLE_NETWORK_ZERO ) {
			foreach ( $current_blogs as $current_blog ) {
				if ( ! in_array( $current_blog->blog_id, $sites ) ) {
					move_site( $current_blog->blog_id, 0 );
				}
			}
		}

		$_GET['moved']  = 'yes';
		$_GET['action'] = 'saved';		
	}
	
	private function delete_network_handler() {
		$result = delete_network( (int) $_GET['id'], ( isset( $_POST['override'] ) ) );

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		$_GET['deleted'] = 'yes';
		$_GET['action']  = 'saved';
	}

	private function delete_multiple_networks_handler() {
		foreach ( $_POST['deleted_networks'] as $deleted_network ) {
			$result = delete_network( (int) $deleted_network, (isset( $_POST['override'] ) ) );
			if ( is_a( $result, 'WP_Error' ) ) {
				wp_die( $result->get_error_message() );
			}
		}
		$_GET['deleted'] = 'yes';
		$_GET['action']  = 'saved';
	}

	private function update_network_handler() {
		global $wpdb;

		$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) );
		if ( empty( $network ) ) {
			die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
		}

		update_network( (int) $_GET['id'], $_POST['domain'], $_POST['path'] );

		$_GET['updated'] = 'true';
		$_GET['action']  = 'saved';		
	}
}
