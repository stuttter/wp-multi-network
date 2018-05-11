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
 * @since 1.3.0
 */
class WP_MS_Networks_Admin {

	/**
	 * Stash the feedback in a private array to avoid running it multiple times.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private $feedback_strings = array();

	/**
	 * Hook methods in
	 *
	 * @since 1.3.0
	 */
	public function __construct() {

		$this->set_feedback_strings();

		// Menus
		add_action( 'admin_menu',         array( $this, 'admin_menu'                   ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu'           ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu_separator' ) );

		// Page save handers
		add_action( 'admin_init',         array( $this, 'route_save_handlers' ) );

		// Add feedback to notices
		add_action( 'network_admin_notices', array( $this, 'network_admin_notices' ) );

		// Row links
		add_filter( 'manage_sites_action_links', array( $this, 'add_move_blog_link' ), 10, 2 );

		// Styling & Scripting
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

    /**
     * Add the Move action to Sites page on WP >= 3.1
     *
     * @since 1.3.0
     *
     * @param array $actions Array of action links
     * @param int   $blog_id Blog ID in list table
     * @return array
     */
	public function add_move_blog_link( $actions = array(), $blog_id = 0 ) {

		// Bail if main site for network
		if ( (int) get_current_site()->blog_id === (int) $blog_id ) {
			return $actions;
		}

		// Assemble URL
		$url = $this->admin_url( array(
			'action'  => 'move',
			'blog_id' => (int) $blog_id,
		) );

		// Add URL to actions links
		if ( current_user_can( 'manage_networks' ) ) {
			$actions['move'] = '<a href="' . esc_url( $url ) . '" class="move">' . esc_html__( 'Move', 'wp-multi-network' ) . '</a>';
		}

		// Return modified actions links
		return $actions;
	}

	/** Menus *****************************************************************/

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
		add_dashboard_page( esc_html__( 'My Networks', 'wp-multi-network' ), esc_html__( 'My Networks', 'wp-multi-network' ), 'read', 'my-networks', array( $this, 'page_my_networks' ) );
	}

    /**
	 * Add Networks menu and entries to the Network-level dashboard
	 *
	 * This method puts the cart before the horse, and could maybe live in the
	 * WP_MS_Networks_List_Table class also.
	 */
	public function network_admin_menu() {
		$page = add_menu_page( esc_html__( 'Networks', 'wp-multi-network' ), esc_html__( 'Networks', 'wp-multi-network' ), 'manage_networks', 'networks', array( $this, 'route_pages' ), 'dashicons-networking', -1 );

		add_submenu_page( 'networks', esc_html__( 'All Networks', 'wp-multi-network' ), esc_html__( 'All Networks', 'wp-multi-network' ), 'list_networks',   'networks',        array( $this, 'route_pages'       ) );
		add_submenu_page( 'networks', esc_html__( 'Add New',      'wp-multi-network' ), esc_html__( 'Add New',      'wp-multi-network' ), 'create_networks', 'add-new-network', array( $this, 'page_edit_network' ) );

		add_action( "admin_head-{$page}", array( $this, 'fix_menu_highlight_for_move_page' ) );

		require_once wpmn()->plugin_dir . '/includes/classes/class-wp-ms-networks-list-table.php' ;
	}

	/**
	 * Add a separator between the 'Networks' and 'Dashboard' menu items on the
	 * Network dashboard
	 *
	 * @since 1.5.2
	 */
	public function network_admin_menu_separator() {
		$GLOBALS['menu']['-2'] = array( '', 'read', 'separator', '', 'wp-menu-separator' );
	}

	/**
	 * Maybe fix the menu highlight for the "Move" page, which is technically
	 * under the "Sites" menu.
	 *
	 * @since 2.2.0
	 *
	 * @global string $plugin_page
	 * @global string $submenu_file
	 */
	public function fix_menu_highlight_for_move_page() {
		global $plugin_page, $submenu_file;

		// This tweaks the Tools subnav menu to only show one bbPress menu item
		if ( 'networks' === $plugin_page ) {
			if ( ! empty( $_GET['action'] ) && ( 'move' === $_GET['action'] ) ) {
				$submenu_file = 'sites.php';
			}
		}
	}

	/** Assets ****************************************************************/

	/**
	 * Add JavaScript on networks admin pages only
	 *
	 * @since 2.0.0
	 */
	public function enqueue_scripts( $page = '' ) {

		// Bail if not a network page
		if ( ! in_array( $page, array( 'toplevel_page_networks', 'networks_page_add-new-network' ) ) ) {
			return;
		}

		// Register assets
		wp_register_style( 'wp-multi-network',  wpmn()->plugin_url . 'assets/css/wp-multi-network.css', array(),                   wpmn()->asset_version, false );
		wp_register_script( 'wp-multi-network', wpmn()->plugin_url . 'assets/js/wp-multi-network.js',   array( 'jquery', 'post' ), wpmn()->asset_version, true  );

		// Enqueue them
		wp_enqueue_style( 'wp-multi-network' );
		wp_enqueue_script( 'wp-multi-network' );
	}

	/** Notices ***************************************************************/

	/**
	 * Feedback strings
	 *
	 * @since 2.1.0
	 */
	private function set_feedback_strings() {
		$this->feedback_strings = array(
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
				'1' => esc_html__( 'Site moved.',          'wp-multi-network' ),
				'0' => esc_html__( 'Site not moved.',      'wp-multi-network' )
			)
		);
	}

	/**
	 * Action feedback
	 *
	 * @since 1.3.0
	 */
	public function network_admin_notices() {

		// Bail if no query vars to get
		if ( empty( $_GET ) ) {
			return;
		}

		// Possible feedback
		$feedback = array_intersect_key( $_GET, $this->feedback_strings );

		// Bail if no feedback
		if ( empty( $feedback ) ) {
			return;
		}

		// Look for possible notice
		$type    = key( $feedback );
		$updated = ( '1' === $_GET[ $type ] )
			? 'updated'
			: 'error'; ?>

		<div id="message" class="<?php echo esc_attr( $updated ); ?> notice is-dismissible">
			<p>
				<?php echo esc_html( $this->feedback_strings[ $type ][ $_GET[ $type ] ] ); ?>
				<a href="<?php echo esc_url( $this->admin_url() ); ?>"><?php esc_html_e( 'Back to Networks.', 'wp-multi-network' ); ?></a>
			</p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'wp-multi-network' ); ?></span>
			</button>
		</div>

		<?php
	}

	/** Routers ***************************************************************/

	/**
	 * Network listing and editing functions are routed through this function
	 *
	 * @since 2.0.0
	 */
	public function route_pages() {

		// Bail if not Super Admin
		if ( ! is_global_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-multi-network' ) );
		}

		// What action is taking place?
		$action = isset( $_GET['action'] )
			? sanitize_key( $_GET['action'] )
			: '';

		switch ( $action ) {

			// Move a site
			case 'move':
				$this->page_move_site();
				break;

			// Delete an entire network
			case 'delete_network':
				$this->page_delete_network();
				break;

			// Edit a single network
			case 'edit_network':
				$this->page_edit_network();
				break;

			// View all networks
			case 'all_networks':

				// Doing action?
				$doaction = isset( $_POST['action'] ) && ( $_POST['action'] != -1 )
					? sanitize_key( $_POST['action']  )
					: sanitize_key( $_POST['action2'] );

				switch ( $doaction ) {
					case 'delete':
						$this->page_delete_networks();
						break;

					default:
						$this->page_all_networks();
						break;
				}
				break;

			// All networks
			default:
				$this->page_all_networks();
				break;
		}
	}

	/**
	 * Save any form submissions
	 *
	 * @since 2.0.0
	 */
	public function route_save_handlers() {

		// Create network
		if ( isset( $_POST['action'] ) && isset( $_POST['domain'] ) && isset( $_POST['path'] ) && ( 'create' === $_POST['action'] ) ) {
			$this->check_nonce();
			$this->handle_add_network();

		// Update network
		} elseif ( isset( $_POST['action'] ) && isset( $_POST['network_id'] ) && ( 'update' === $_POST['action'] ) ) {
			$this->check_nonce();
			$this->handle_reassign_sites();
			$this->handle_update_network();

		// Delete network
		} elseif ( isset( $_POST['delete'] ) && isset( $_GET['id'] ) ) {
			$this->check_nonce();
			$this->handle_delete_network();

		// Delete many networks
		} elseif ( isset( $_POST['delete_multiple'] ) && isset( $_POST['deleted_networks'] ) ) {
			$this->check_nonce();
			$this->handle_delete_networks();

		// Move site to different network
		} elseif ( isset( $_POST['move'] ) && isset( $_GET['blog_id'] ) ) {
			$this->check_nonce();
			$this->handle_move_site();
		}
	}

	/** Pages *****************************************************************/

	/**
	 * New network creation dashboard page
	 *
	 * @since 2.0.0
	 */
	public function page_edit_network() {

		// Get the network
		$network = isset( $_GET['id'] )
			? get_network( $_GET['id'] )
			: null;

		// Metaboxes
		add_meta_box( 'wpmn-edit-network-details', esc_html__( 'Details', 'wp-multi-network' ), 'wpmn_edit_network_details_metabox', get_current_screen()->id, 'normal', 'high', array( $network ) );
		add_meta_box( 'wpmn-edit-network-publish', esc_html__( 'Network', 'wp-multi-network' ), 'wpmn_edit_network_publish_metabox', get_current_screen()->id, 'side',   'high', array( $network ) );

		// New Site
		if ( empty( $network ) ) {
			$network_title = '';

			add_meta_box( 'wpmn-edit-network-new-site', esc_html__( 'Root Site', 'wp-multi-network' ), 'wpmn_edit_network_new_site_metabox', get_current_screen()->id, 'advanced', 'high', array( $network ) );
		} else {
			$network_title = get_network_option( $network->id, 'site_name', '' );

			add_meta_box( 'wpmn-edit-network-assign-sites', esc_html__( 'Site Assignment', 'wp-multi-network' ), 'wpmn_edit_network_assign_sites_metabox', get_current_screen()->id, 'advanced', 'high', array( $network ) );
		} ?>

		<div class="wrap">
			<h1><?php

				// Edit
				if ( ! empty( $network )  ) :
					esc_html_e( 'Edit Network', 'wp-multi-network' );

					// Add new network link
					if ( current_user_can( 'create_networks' ) ) :

						?><a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a><?php

					endif;

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
	 * Network listing dashboard page
	 *
	 * @since 2.0.0
	 *
	 * @uses WP_MS_Networks_List_Table List_Table iterator for networks
	 */
	private function page_all_networks() {
		$wp_list_table = new WP_MS_Networks_List_Table();
		$wp_list_table->prepare_items(); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' );

				// Add New link
				if ( current_user_can( 'create_networks' ) ) :

					?><a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a><?php

				endif;

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
	 * Dashboard screen for moving sites -- accessed from the "Sites" screen
	 *
	 * @since 2.0.0
	 */
	private function page_move_site() {

		// Get network by id
		$site = get_site( (int) $_GET['blog_id'] );

		add_meta_box( 'wpmn-move-site-list',    esc_html__( 'Assign Network', 'wp-multi-network' ), 'wpmn_move_site_list_metabox',   get_current_screen()->id, 'normal', 'high', array( $site ) );
		add_meta_box( 'wpmn-move-site-publish', esc_html__( 'Site',           'wp-multi-network' ), 'wpmn_move_site_assign_metabox', get_current_screen()->id, 'side',   'high', array( $site ) ); ?>

		<div class="wrap">
			<h1><?php

				// Title
				esc_html_e( 'Networks', 'wp-multi-network' );

				// "Add New" link
				if ( current_user_can( 'create_networks' ) ) :

					?><a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a><?php

				endif;
			?></h1>

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
	 * @since 2.0.0
	 */
	private function page_delete_network() {

		// Get the network
		$network = isset( $_GET['id'] )
			? get_network( $_GET['id'] )
			: null;

		// Bail if invalid network ID
		if ( empty( $network ) ) {
			wp_die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
		}

		// Get sites to delete
		$sites = get_sites( array(
			'network_id' => $network->id
		) ); ?>

		<div class="wrap">
			<h1><?php

				esc_html_e( 'Delete Network', 'wp-multi-network' );

				// Add link to create new network
				if ( current_user_can( 'create_networks' ) ) :

					?><a href="<?php echo esc_url( $this->admin_url( array( 'page' => 'add-new-network' ) ) ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a><?php

				endif;

			?></h1>

			<form method="post" action="<?php echo remove_query_arg( 'action' ); ?>">
				<?php

				// Network has sites
				if ( ! empty( $sites ) ) : ?>

					<div id="message" class="network-delete">
						<p><?php esc_html_e( 'The following sites are associated with this network:', 'wp-multi-network' ); ?></p>
						<ul class="delete-sites"><?php

							foreach ( $sites as $site ) :

								?><li><?php echo esc_html( $site->domain . $site->path ); ?></li><?php

							endforeach;

						?></ul>
						<p>
							<input type="checkbox" name="override" id="override">
							<label for="override"><?php

								// Verbiage change
								wp_should_rescue_orphaned_sites()
									? esc_html_e( 'Rescue these sites', 'wp-multi-network' )
									: esc_html_e( 'Delete these sites', 'wp-multi-network' );

							?></label>
						</p>
					</div>
					<p><?php printf( esc_html__( 'Are you sure you want to delete the entire "%s" network?', 'wp-multi-network' ), esc_html( $network->domain . $network->path ) ); ?></p><?php

				endif;

				wp_nonce_field( 'edit_network', 'network_edit' );

				submit_button( esc_html__( 'Delete Network', 'wp-multi-network' ), 'primary', 'delete', false ); ?>

				<a class="button" href="<?php echo esc_url( $this->admin_url() ); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
			</form>
		</div>

		<?php
	}

	/**
	 * Output the delete multiple networks page
	 *
	 * @since 2.0.0
	 */
	private function page_delete_networks() {

		// Get posted networks
		$network_id   = get_main_network_id();
		$all_networks = isset( $_POST['all_networks'] )
			? array_map( 'absint', $_POST['all_networks'] )
			: array();

		// Prevent primary network from being deleted
		$all_networks = array_diff( $all_networks, array( $network_id ) );

		// Query for networks
		$networks = get_networks( array(
			'network__in' => $all_networks
		) );

		// Bail if no networks
		if ( empty( $networks ) ) {
			wp_die( esc_html__( 'You have selected an invalid network or networks for deletion', 'wp-multi-network' ) );
		}

		// Ensure each network is valid
		foreach ( $networks as $network ) {
			if ( ! get_network( $network ) ) {
				wp_die( esc_html__( 'You have selected an invalid network for deletion.', 'wp-multi-network' ) );
			}
		}

		// Query for sites in selected networks
		$sites = get_sites( array(
			'network__in' => $all_networks
		) ); ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h1>
			<h3><?php esc_html_e( 'Delete Multiple Networks', 'wp-multi-network' ); ?></h3>
			<form method="post" action="<?php echo esc_url( $this->admin_url() ); ?>">
				<?php if ( ! empty( $sites ) ) : ?>

					<div class="error inline">
						<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
						<ul>
							<?php foreach ( $networks as $deleted_network ) : ?>
								<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>"><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p><?php

						// Messaging
						wp_should_rescue_orphaned_sites()
							? esc_html_e( 'One or more of these networks have sites. Deleting these networks will orphan their sites.',             'wp-multi-network' )
							: esc_html_e( 'One or more of these networks have sites. Deleting these networks will permanently delete their sites.', 'wp-multi-network' );

						?></p>
						<p>
							<label for="override"><?php esc_html_e( 'Please confirm that you still want to delete these networks', 'wp-multi-network' ); ?>:</label>
							<input type="checkbox" name="override" id="override">
						</p>
					</div>

				<?php else : ?>

					<div id="message inline">
						<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
						<ul><?php

							foreach ( $networks as $deleted_network ) :

								?><li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>"><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li><?php

							endforeach;

						?></ul>
					</div>

				<?php endif; ?>

				<p><?php esc_html_e( 'Are you sure you want to delete these networks?', 'wp-multi-network' ); ?></p>
				<?php

				wp_nonce_field( 'edit_network', 'network_edit' );

				submit_button( esc_html__( 'Delete Networks', 'wp-multi-network' ), 'primary', 'delete_multiple', false ); ?>

				<a class="button" href="<?php echo esc_url( $this->admin_url() ); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
			</form>
		</div>

		<?php
	}

	/**
	 * Output the my networks page
	 *
	 * @since 2.0.0
	 */
	public function page_my_networks() {
		global $wpdb; ?>

		<div class="wrap">
			<h1><?php esc_html_e( 'My Networks', 'wp-multi-network' ); ?></h1>

			<?php

			$my_networks = user_has_networks();
			foreach ( $my_networks as $key => $network_id ) {
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
	 * @since 2.0.0
	 */
	private function handle_add_network() {

		// Unslash posted data for sanitization
		$posted = wp_unslash( $_POST );

		// Options to copy
		if ( isset( $posted['options_to_clone'] ) && is_array( $posted['options_to_clone'] ) ) {
			$options_to_clone = array_keys( $posted['options_to_clone'] );
		} else {
			$options_to_clone = array_keys( network_options_to_copy() );
		}

		// Clone from
		$clone = isset( $posted['clone_network'] )
			? (int) $posted['clone_network']
			: get_current_site()->id;

		// Title
		$network_title = isset( $posted['title'] )
			? strip_tags( $posted['title'] )
			: '';

		// Domain
		$network_domain = isset( $posted['domain'] )
			? str_replace( ' ', '', strtolower( $posted['domain'] ) )
			: '';

		// Path
		$network_path = isset( $posted['path'] )
			? str_replace( ' ', '', strtolower( $posted['path'] ) )
			: '';

		// Site name
		$site_name = ! empty( $posted['new_site'] )
			? strip_tags( $posted['new_site'] )
			: $network_title;

		// Bail if missing fields
		if ( empty( $network_title ) || empty( $network_domain ) || empty( $network_path ) ) {
			$this->handle_redirect( array(
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

			// Maybe update the site name
			if ( ! empty( $posted['title'] ) ) {
				update_network_option( $result, 'site_name', $posted['title'] );
			}

			// Activate WPMN on this new network
			update_network_option( $result, 'active_sitewide_plugins', array( 'wp-multi-network/wpmn-loader.php' => time() ) );

			// Redirect args
			$r = array( 'network_created' => '1' );

		// Failure
		} else {
			$r = array( 'network_created' => '0' );
		}

		// Handle redirect
		$this->handle_redirect( $r );
	}

	/**
	 * Handle the request to update a network
	 *
	 * @since 2.0.0
	 */
	private function handle_update_network() {

		// Unslash posted data for sanitization
		$posted = wp_unslash( $_POST );

		// Cast
		$network_id = ! empty( $posted['network_id'] )
			? (int) $posted['network_id']
			: 0;

		// Bail if invalid network
		if ( ! get_network( $network_id ) ) {
			wp_die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
		}

		// Title
		$network_title = isset( $posted['title'] )
			? sanitize_text_field( $posted['title'] )
			: '';

		// Domain
		$network_domain = isset( $posted['domain'] )
			? str_replace( ' ', '', strtolower( sanitize_text_field( $posted['domain'] ) ) )
			: '';

		// Punycode support
		$network_domain = Requests_IDNAEncoder::encode( $network_domain );

		// Path
		$network_path = isset( $posted['path'] )
			? str_replace( ' ', '', strtolower( sanitize_text_field( $posted['path'] ) ) )
			: '';

		// Bail if missing fields
		if ( empty( $network_title ) || empty( $network_domain ) || empty( $network_path ) ) {
			$this->handle_redirect( array(
				'id'              => $network_id,
				'action'          => 'edit_network',
				'network_updated' => '0'
			) );
		}

		// Update domain & path
		$updated = update_network( $network_id, $network_domain, $network_path );
		$success = '0';

		// Maybe update network title
		if ( ! is_wp_error( $updated ) ) {
			update_network_option( $network_id, 'site_name', $network_title );
			$success = '1';
		}

		// Handle redirect
		$this->handle_redirect( array(
			'id'              => $network_id,
			'action'          => 'edit_network',
			'network_updated' => $success,
		) );
	}

	/**
	 * Handle the request to move a site
	 *
	 * @since 2.0.0
	 */
	private function handle_move_site() {

		// Get blog ID
		$site_id = ! empty( $_GET['blog_id'] )
			? absint( $_GET['blog_id'] )
			: 0;

		// New network ID
		$new_network = ! empty( $_POST['to'] )
			? absint( $_POST['to'] )
			: 0;

		// Bail if site or network are empty
		if ( empty( $site_id ) ) {
			wp_safe_redirect( add_query_arg( array(
				'site_moved' => 0,
			), network_admin_url( 'sites.php' ) ) );
			exit;
		}

		// Try to get site
		$site = get_site( $site_id );

		// Bail if site cannot be found or new network is the same as existing
		if ( empty( $site ) || ( $site->network_id === $new_network ) ) {
			wp_safe_redirect( add_query_arg( array(
				'site_moved' => 0,
			), network_admin_url( 'sites.php' ) ) );
			exit;
		}

		// Try to move site
		$moved   = move_site( $site_id, $new_network );
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
	 * @since 2.0.0
	 */
	private function handle_reassign_sites() {

		// Coming in
		$to = isset( $_POST['to'] )
			? array_map( 'absint', (array) $_POST['to'] )
			: array();

		// Orphaning out
		$from = isset( $_POST['from'] )
			? array_map( 'absint', (array) $_POST['from'] )
			: array();

		// Bail early if no movement
		if ( empty( $to ) && empty( $from ) ) {
			return;
		}

		// Cast the network ID
		$network_id = (int) $_GET['id'];

		// Setup sites arrays
		$moving_to = $moving_from = array();

		// Query for sites in this network
		$sites_list = get_sites( array(
			'network_id' => $network_id,
			'fields'     => 'ids'
		) );

		// Moving out from current network
		foreach ( $from as $site_id ) {
			if ( in_array( $site_id, $sites_list, true ) ) {
				$moving_from[] = $site_id;
			}
		}

		// Moving into current network
		foreach ( $to as $site_id ) {
			if ( ! in_array( $site_id, $sites_list, true ) ) {
				$moving_to[] = $site_id;
			}
		}

		// Merge into one array
		$moving = array_filter( array_merge( $moving_to, $moving_from ) );

		// Loop through and move sites
		foreach ( $moving as $site_id ) {

			// Get the site to move
			$site = get_site( $site_id );

			// Skip missing or main sites networks
			if ( empty( $site ) || is_main_site( $site->id, $site->network_id ) ) {
				continue;
			}

			// Coming in
			if ( in_array( $site_id, $to ) && ! in_array( $site_id, $sites_list, true ) ) {
				move_site( $site_id, $network_id );

			// Orphaning out
			} elseif ( in_array( $site_id, $from, true ) ) {
				move_site( $site_id, 0 );
			}
		}
	}

	/**
	 * Handle the request to delete a network
	 *
	 * @since 2.0.0
	 */
	private function handle_delete_network() {

		// Delete network
		$result = delete_network( (int) $_GET['id'], isset( $_POST['override'] ) );
		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		// Handle redirect
		$this->handle_redirect( array(
			'network_deleted' => '1',
		) );
	}

	/**
	 * Handle the request to helete many networks
	 *
	 * @since 2.0.0
	 */
	private function handle_delete_networks() {

		// Delete networks
		foreach ( $_POST['deleted_networks'] as $deleted_network ) {
			$result = delete_network( (int) $deleted_network, ( isset( $_POST['override'] ) ) );
			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}
		}

		// Handle redirect
		$this->handle_redirect( array(
			'networks_deleted' => '1',
		) );
	}

	/**
	 * Handle redirect after page submit
	 *
	 * @since 2.0.0
	 *
	 * @param array $args
	 */
	private function handle_redirect( $args = array() ) {
		wp_safe_redirect( $this->admin_url( $args ) );
		exit;
	}

	/** Helpers ***************************************************************/

	/**
	 * Return the URL of the Networks page
	 *
	 * @since 1.3.0
	 *
	 * @return string Absolute URL to Networks page
	 */
	private function admin_url( $args = array() ) {

		// Parse args
		$r = wp_parse_args( $args, array(
			'page' => 'networks'
		) );

		// Which admin page?
		$page = ! empty( $r['action'] ) && ( 'move' === $r['action'] )
			? 'sites.php'
			: 'admin.php';

		// Combine
		$result = add_query_arg( $r, network_admin_url( $page ) );

		// Filter & return
		return apply_filters( 'edit_networks_screen_url', $result, $r, $args );
	}

	/**
	 * Check the nonce
	 *
	 * @since 2.1.0
	 */
	private function check_nonce() {
		check_admin_referer( 'edit_network', 'network_edit' );
	}
}
