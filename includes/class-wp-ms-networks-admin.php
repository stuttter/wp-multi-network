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

	public function __construct() {
		add_action( 'admin_menu',         array( $this, 'admin_menu'                   ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu'           ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu_separator' ) );

		add_filter( 'manage_sites_action_links', array( $this, 'add_move_blog_link' ), 10, 3 );
		if ( ! has_action( 'manage_sites_action_links' ) ) {
			add_action( 'wpmublogsaction',    array( $this, 'assign_blogs_link'  ) );
		}
	}

	/**
	 * Return the URL of the Networks page
	 * @return string Absolute URL to Networks page
	 */
	public function admin_url() {
		$result = add_query_arg( array( 'page' => 'networks' ), esc_url( network_admin_url( 'admin.php' ) ) );
		return $result;
	}

	/**
	 * Add JS code for Assign Sites public function to admin head
	 */
	public function admin_head() {
	?>

		<script type="text/javascript">
			jQuery(document).ready( function() {

				jQuery( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
				jQuery( '.postbox' ).children( 'h3' ).click( function() {
					if (jQuery( this.parentNode ).hasClass( 'closed' ) ) {
						jQuery( this.parentNode ).removeClass( 'closed' );
					} else {
						jQuery( this.parentNode ).addClass( 'closed' );
					}
				} );

				/** add field to signal javascript is enabled */
				jQuery(document.createElement('input'))
					.attr( 'type', 'hidden' )
					.attr( 'name', 'jsEnabled' )
					.attr( 'value', 'true' )
					.appendTo( '#site-assign-form' );

				/** Handle clicks to add/remove sites to/from selected list */
				jQuery( 'input[name=assign]' ).click( function() {		move( 'from', 'to' );	});
				jQuery( 'input[name=unassign]' ).click( function() {	move( 'to', 'from' );	});

				/** Select all sites in "selected" box when submitting */
				jQuery( '#site-assign-form' ).submit( function() {
					jQuery( '#to' ).children( 'option' ).attr( 'selected', true );
				});


			});

			public function move( from, to ) {
				jQuery( '#' + from ).children( 'option:selected' ).each( function() {
					jQuery( '#' + to ).append( jQuery( this ).clone() );
					jQuery( this ).remove();
				});
			}

		</script>

		<style type="text/css">
			th.column-sitename {
				width: 35%;
			}
			th.column-path {
				width: 15%;
			}
			th.column-blogs {
				width: 10%;
			}
			td.column-domain {
				white-space: nowrap;
				overflow: hidden;
			}
		</style>

	<?php
	}

	/**
	 * Add the Move action to Sites page on WP >= 3.1
	 */
	public function add_move_blog_link( $actions, $cur_blog_id, $blog_name ) {
		$url = add_query_arg( array(
			'action'  => 'move',
			'blog_id' => (int) $cur_blog_id ),
			$this->admin_url()
		);
		$actions['move'] = '<a href="' . esc_url( $url ) . '" class="edit">' . esc_html__( 'Move', 'wp-multi-network' ) . '</a>';
		return $actions;
	}

	/**
	 * Legacy - add a Move link on Sites page on WP < 3.1
	 */
	public function assign_blogs_link( $cur_blog_id ) {
		$url = add_query_arg( array(
			'action'  => 'move',
			'blog_id' => (int) $cur_blog_id ),
			$this->admin_url()
		);
		echo '<a href="' . esc_url( $url ) . '" class="edit">' . esc_html__( 'Move', 'wp-multi-network' ) . '</a>';
	}

	/**
	 * Add the My Networks page to the site-level dashboard
	 */
	public function admin_menu() {

		// If the user is super admin on another Network, don't require elevated permissions on the current Site
		if ( user_has_networks() ) {
			add_dashboard_page( esc_html__( 'My Networks', 'wp-multi-network' ), esc_html__( 'My Networks', 'wp-multi-network' ), 'read', 'my-networks', array( $this, 'my_networks_page' ) );
		}
	}

	/**
	 * Add Networks menu and entries to the Network-level dashboard
	 */
	public function network_admin_menu() {
		$page = add_menu_page( esc_html__( 'Networks', 'wp-multi-network' ), esc_html__( 'Networks', 'wp-multi-network' ), 'manage_options', 'networks', array( $this, 'networks_page' ), 'dashicons-networking', -1 );
		add_submenu_page( 'networks', esc_html__( 'All Networks', 'wp-multi-network' ), esc_html__( 'All Networks', 'wp-multi-network' ), 'manage_options', 'networks',        array( $this, 'networks_page' ) );
		add_submenu_page( 'networks', esc_html__( 'Add New', 'wp-multi-network'      ), esc_html__( 'Add New', 'wp-multi-network'      ), 'manage_options', 'add-new-network', array( $this, 'add_network_page' ) );

		require dirname( __FILE__ ) . '/class-wp-ms-networks-list-table.php' ;

		add_filter( "manage_{$page}-network_columns", array( new WP_MS_Networks_List_Table(), 'get_columns' ), 0 );
		add_action( "load-{$page}",                   array( $this, 'enqueue_js' ) );
	}

	/**
	 * Add a seperator between the 'Networks' and 'Dashboard' menus to the Network-level dashboard
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
	 * @since 1.5.2
	 */
	public function enqueue_js() {
		add_action( 'admin_head', array( $this, 'admin_head' ) );
	}

	/* Config Page */
	public function feedback() {

		if ( isset( $_GET['updated'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Options saved.', 'wp-multi-network' ); ?></p></div>

		<?php elseif ( isset( $_GET['added'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Network created.', 'wp-multi-network' ); ?></p></div>

		<?php elseif ( isset( $_GET['deleted'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Network(s) deleted.', 'wp-multi-network' ); ?></p></div>

		<?php elseif ( isset( $_GET['moved'] ) ) : ?>

			<div id="message" class="updated fade"><p><?php esc_html_e( 'Site(s) moved.', 'wp-multi-network' ); ?></p></div>

		<?php endif;

	}

	/**
	 * Main Network-level dashboard page
	 * 	Network listing and editing functions are routed through this function
	 */
	public function networks_page() {

		if ( ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-multi-network' ) );
		}

		if ( isset( $_POST['update'] ) && isset( $_GET['id'] ) ) {
			$this->update_network_page();
		}

		if ( isset( $_POST['delete'] ) && isset( $_GET['id'] ) ) {
			$this->delete_network_page();
		}

		if ( isset( $_POST['delete_multiple'] ) && isset( $_POST['deleted_networks'] ) ) {
			$this->delete_multiple_network_page();
		}

		if ( isset( $_POST['add'] ) && isset( $_POST['domain'] ) && isset( $_POST['path'] ) ) {
			$this->add_network_handler();
		}

		if ( isset( $_POST['move'] ) && isset( $_GET['blog_id'] ) ) {
			$this->move_site_page();
		}

		if ( isset( $_POST['reassign'] ) && isset( $_GET['id'] ) ) {
			$this->reassign_site_page();
		}

		$this->feedback(); ?>

		<div class="wrap" style="position: relative">

		<?php

			$action = isset( $_GET['action'] ) ? $_GET['action'] : '';

			switch ( $action ) {
				case 'move':
					$this->move_site_page();
					break;

				case 'assignblogs':
					$this->reassign_site_page();
					break;

				case 'deletenetwork':
					$this->delete_network_page();
					break;

				case 'editnetwork':
					$this->update_network_page();
					break;

				case 'allnetworks':

					$doaction = isset( $_POST['action'] ) && $_POST['action'] != -1 ? $_POST['action'] : $_POST['action2'];

					switch ( $doaction ) {
						case 'delete':
							$this->delete_multiple_network_page();
							break;

						default:
							$this->all_networks();
							break;

						// handle other bulk network actions here
					}
					break;

				default:
					$this->all_networks();
					break;
			}

		?>

		</div>

		<?php
	}

	/**
	 * Network listing dashboard page
	 * @uses WP_MS_Networks_List_Table List_Table iterator for networks
	 */
	public function all_networks() {
		$wp_list_table = new WP_MS_Networks_List_Table();
		$wp_list_table->prepare_items(); ?>

		<div class="wrap">
			<?php screen_icon( 'ms-admin' ); ?>
			<h2><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?>

			<?php if ( current_user_can( 'manage_network_options' ) ) : ?>

				<a href="<?php echo add_query_arg( array( 'page' => 'add-new-network' ), $this->admin_url() ); ?>" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'network', 'wp-multi-network' ); ?></a>

			<?php endif;

			if ( isset( $_REQUEST['s'] ) && $_REQUEST['s'] ) {
				printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;', 'wp-multi-network' ) . '</span>', esc_html( $_REQUEST['s'] ) );
			} ?>
			</h2>

			<form action="<?php echo add_query_arg( array( 'action' => 'domains' ), $this->admin_url() ); ?>" method="post" id="domain-search">
				<?php $wp_list_table->search_box( esc_html__( 'Search Networks', 'wp-multi-network' ), 'networks' ); ?>
				<input type="hidden" name="action" value="domains" />
			</form>

			<form id="form-domain-list" action="<?php echo add_query_arg( array( 'action' => 'allnetworks' ), $this->admin_url() ); ?>" method="post">
				<?php $wp_list_table->display(); ?>
			</form>
		</div>

		<?php
	}

	/**
	 * New network creation dashboard page
	 */
	public function add_network_page() {

		// Strip off URL parameters
		$query_str = remove_query_arg( array(
			'action', 'id', 'updated', 'deleted'
		) ); ?>

		<div class="wrap">
			<?php screen_icon( 'ms-admin' ); ?>
			<h2><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h2>

			<div id="col-container">
				<p><?php esc_html_e( 'A site will be created at the root of the new network', 'wp-multi-network' ); ?>.</p>
				<form method="POST" action="<?php echo $this->admin_url(); ?>">
					<table class="form-table">
						<tr><th scope="row"><label for="newName"><?php esc_html_e( 'Network Name', 'wp-multi-network' ); ?>:</label></th><td><input type="text" name="name" id="newName" title="<?php esc_html_e( 'A friendly name for your new network', 'wp-multi-network' ); ?>" /></td></tr>
						<tr><th scope="row"><label for="newDom"><?php  esc_html_e( 'Domain',       'wp-multi-network' ); ?>:</label></th><td> http://<input type="text" name="domain" id="newDom" title="<?php esc_html_e( 'The domain for your new network', 'wp-multi-network' ); ?>" /></td></tr>
						<tr><th scope="row"><label for="newPath"><?php esc_html_e( 'Path',         'wp-multi-network' ); ?>:</label></th><td><input type="text" name="path" id="newPath" value="/" title="<?php esc_html_e( 'If you are unsure, put in /', 'wp-multi-network' ); ?>" /></td></tr>
						<tr><th scope="row"><label for="newSite"><?php esc_html_e( 'Site Name',    'wp-multi-network' ); ?>:</label></th><td><input type="text" name="newSite" id="newSite" title="<?php esc_html_e( 'The name for the new site for this network.', 'wp-multi-network' ); ?>" /></td></tr>
					</table>

					<?php submit_button( esc_html__( 'Create Network', 'wp-multi-network' ), 'primary', 'add' ); ?>

				</form>
			</div>
		</div>

		<?php
	}

	/**
	 * Dashbaord screen for moving sites -- accessed from the "Sites" screen
	 */
	public function move_site_page() {
		global $wpdb;

		if ( isset( $_POST['move'] ) && isset( $_GET['blog_id'] ) ) {
			if ( isset( $_POST['from'] ) && isset( $_POST['to'] ) ) {
				move_site( $_GET['blog_id'], $_POST['to'] );
				$_GET['moved']  = 'yes';
				$_GET['action'] = 'saved';
			}
		} else {
			if ( !isset( $_GET['blog_id'] ) ) {
				die( esc_html__( 'You must select a blog to move.', 'wp-multi-network' ) );
			}

			$site = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d", (int)$_GET['blog_id'] ) );

			if ( empty( $site ) ) {
				die( esc_html__( 'Invalid blog id.', 'wp-multi-network' ) );
			}

			$table_name = $wpdb->get_blog_prefix( $site->blog_id ) . "options";
			$details    = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE option_name = 'blogname'" );

			if ( empty( $details ) ) {
				die( esc_html__( 'Invalid blog id.', 'wp-multi-network' ) );
			}

			$sites = $wpdb->get_results( "SELECT * FROM {$wpdb->site}" );

			foreach ( $sites as $key => $network ) {
				if ( $network->id == $site->site_id ) {
					$myNetwork = $sites[$key];
				}
			} ?>

			<div class="wrap">
				<?php screen_icon( 'ms-admin' ); ?>
				<h2><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h2>
				<h3><?php printf( esc_html__( 'Moving %s', 'wp-multi-network' ), stripslashes( $details->option_value ) ); ?></h3>
				<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<table class="widefat">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'From', 'wp-multi-network' ); ?>:</th>
								<th scope="col"><label for="to"><?php esc_html_e( 'To', 'wp-multi-network' ); ?>:</label></th>
							</tr>
						</thead>
						<tr>
							<td><?php echo esc_html( $myNetwork->domain ); ?></td>
							<td>
								<select name="to" id="to">
									<option value="0"><?php esc_html_e( 'Select a Network', 'wp-multi-network' ); ?></option>
									<?php foreach ( $sites as $network ) : ?>
										<?php if ( $network->id != $myNetwork->id ) : ?>
										<option value="<?php echo esc_attr( $network->id ); ?>"><?php echo esc_html( $network->domain ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
					<br />
					<?php if ( has_action( 'add_move_blog_option' ) ) : ?>
						<table class="widefat">
							<thead>
								<tr scope="col"><th colspan="2"><?php esc_html_e( 'Options', 'wp-multi-network' ); ?>:</th></tr>
							</thead>
							<?php do_action( 'add_move_blog_option', $site->blog_id ); ?>
						</table>
						<br />
					<?php endif; ?>
					<div>
						<input type="hidden" name="from" value="<?php echo esc_attr( $site->site_id ); ?>" />
						<input class="button" type="submit" name="move" value="<?php esc_attr_e( 'Move Site', 'wp-multi-network' ); ?>" />
						<a class="button" href="./sites.php"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
					</div>
				</form>
			</div>
			<?php
		}
	}

	public function reassign_site_page() {
		global $wpdb;

		if ( isset( $_POST['reassign'] ) && isset( $_GET['id'] ) ) {
			/** Javascript enabled for client - check the 'to' box */
			if ( isset( $_POST['jsEnabled'] ) ) {
				if ( !isset( $_POST['to'] ) ) {
					die( esc_html__( 'No blogs selected.', 'wp-multi-network' ) );
				}

				$sites = $_POST['to'];

				/** Javascript disabled for client - check the 'from' box */
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

			/* true sync - move any unlisted blogs to 'zero' network */
			if ( ENABLE_NETWORK_ZERO ) {
				foreach ( $current_blogs as $current_blog ) {
					if ( !in_array( $current_blog->blog_id, $sites ) ) {
						move_site( $current_blog->blog_id, 0 );
					}
				}
			}

			$_GET['moved']  = 'yes';
			$_GET['action'] = 'saved';
		} else {

			// get network by id
			$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) );

			if ( empty( $network ) ) {
				die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
			}

			$sites = $wpdb->get_results( "SELECT * FROM {$wpdb->blogs}" );
			if ( empty( $sites ) ) {
				die( esc_html__( 'Site table inaccessible.', 'wp-multi-network' ) );
			}

			foreach ( $sites as $key => $site ) {
				$table_name = $wpdb->get_blog_prefix( $site->blog_id ) . "options";
				$site_name  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE option_name = %s", 'blogname' ) );

				if ( empty( $site_name ) ) {
					die( esc_html__( 'Invalid blog.', 'wp-multi-network' ) );
				}

				$sites[$key]->name = stripslashes( $site_name->option_value );
			}

			?>
			<div class="wrap">
				<form method="post" id="site-assign-form" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<?php screen_icon( 'ms-admin' ); ?>
					<h2><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h2>
					<h3><?php esc_html_e( 'Assign Sites to', 'wp-multi-network' ); ?>: http://<?php echo esc_html( $network->domain . $network->path ); ?></h3>
					<noscript>
						<div id="message" class="updated"><p><?php esc_html_e( 'Select the blogs you want to assign to this network from the column at left, and click "Update Assignments."', 'wp-multi-network' ); ?></p></div>
					</noscript>
					<table class="widefat">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Available', 'wp-multi-network' ); ?></th>
								<th style="width: 2em;"></th>
								<th><?php esc_html_e( 'Assigned', 'wp-multi-network' ); ?></th>
							</tr>
						</thead>
						<tr>
							<td>
								<select name="from[]" id="from" multiple style="height: auto; width: 98%;">
								<?php
								foreach ( $sites as $site ) {
									if ( $site->site_id != $network->id ) {
										echo '<option value="' . esc_attr( $site->blog_id ) . '">' . esc_html( sprintf( '%1$s (%2$s%3$s)', $site->name, $site->domain, $site->path ) ) . '</option>';
									}
								}
								?>
								</select>
							</td>
							<td>
								<input type="button" name="unassign" id="unassign" value="<<" /><br />
								<input type="button" name="assign" id="assign" value=">>" />
							</td>
							<td valign="top">
							<?php if ( ! ENABLE_NETWORK_ZERO ) { ?>
								<ul style="margin: 0; padding: 0; list-style-type: none;">
								<?php foreach ( $sites as $site ) : ?>
									<?php if ( $site->site_id === $network->id ) : ?>
									<li><?php echo esc_html( sprintf( '%1$s (%2$s%3$s)', $site->name, $site->domain, $site->path ) ); ?></li>
									<?php endif; ?>
								<?php endforeach; ?>
								</ul>
							<?php } ?>
								<select name="to[]" id="to" multiple style="height: auto; width: 98%">
									<?php
									if ( ENABLE_NETWORK_ZERO ) :
										foreach ( $sites as $site ) :
											if ( $site->site_id === $network->id ) :
												echo '<option value="' . esc_attr( $site->blog_id ) . '">' . esc_html( sprintf( '%1$s (%2$s%3$s)', $site->name, $site->domain, $site->path ) ) . '</option>';
											endif;
										endforeach;
									endif;
									?>
								</select>
							</td>
						</tr>
					</table>
					<br class="clear" />
						<?php if ( has_action( 'add_move_blog_option' ) ) : ?>
						<table class="widefat">
							<thead>
								<tr scope="col"><th colspan="2"><?php esc_html_e( 'Options', 'wp-multi-network' ); ?>:</th></tr>
							</thead>
							<?php do_action( 'add_move_blog_option', $site->blog_id ); ?>
						</table>
						<br />
						<?php endif; ?>
					<?php submit_button( esc_attr__( 'Update Assignments', 'wp-multi-network' ), 'primary', 'reassign', false ); ?>
					<a class="button" href="<?php echo $this->admin_url(); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
				</form>
			</div>
			<?php
		}
	}

	/**
	 *
	 */
	public function add_network_handler() {
		global $current_site;

		if ( isset( $_POST['add'] ) && isset( $_POST['domain'] ) && isset( $_POST['path'] ) ) {

			/** grab custom options to clone if set */
			if ( isset( $_POST['options_to_clone'] ) && is_array( $_POST['options_to_clone'] ) ) {
				$options_to_clone = array_keys( $_POST['options_to_clone'] );
			} else {
				$options_to_clone = array_keys( network_options_to_copy() );
			}

			$result = add_network(
				$_POST['domain'],
				$_POST['path'],
				( isset( $_POST['newSite']      ) ? $_POST['newSite']      : esc_attr__( 'New Network Created', 'wp-multi-network' ) ),
				( isset( $_POST['cloneNetwork'] ) ? $_POST['cloneNetwork'] : $current_site->id ),
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
					echo( "<h2>Error: " . $error[0] . "</h2>" );
				}
			}
		}
	}

	public function update_network_page() {
		global $wpdb;

		if ( isset( $_POST['update'] ) && isset( $_GET['id'] ) ) {

			$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) );
			if ( empty( $network ) ) {
				die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
			}

			update_network( (int) $_GET['id'], $_POST['domain'], $_POST['path'] );

			$_GET['updated'] = 'true';
			$_GET['action']  = 'saved';
		} else {

			// get network by id
			$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) );

			if ( empty( $network ) ) {
				wp_die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
			}

			?>
			<div class="wrap">
				<?php screen_icon( 'ms-admin' ); ?>
				<h2><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h2>
				<h3><?php esc_html_e( 'Edit Network', 'wp-multi-network' ); ?>: http://<?php echo esc_html( $network->domain . $network->path ); ?></h3>
				<form method="post" action="<?php echo remove_query_arg( 'action' ); ?>">
					<table class="form-table">
						<tr class="form-field"><th scope="row"><label for="domain"><?php esc_html_e( 'Domain', 'wp-multi-network' ); ?></label></th><td> http://<input type="text" id="domain" name="domain" value="<?php echo esc_attr( $network->domain ); ?>"></td></tr>
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
						<a class="button" href="<?php echo $this->admin_url(); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
					</p>
				</form>
			</div>
			<?php
		}
	}

	public function delete_network_page() {
		global $wpdb;

		if ( isset( $_POST['delete'] ) && isset( $_GET['id'] ) ) {
			$result = delete_network( (int) $_GET['id'], ( isset( $_POST['override'] ) ) );

			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}

			$_GET['deleted'] = 'yes';
			$_GET['action']  = 'saved';
		} else {

			// get network by id
			$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", (int) $_GET['id'] ) );

			if ( empty( $network ) ) {
				die( esc_html__( 'Invalid network id.', 'wp-multi-network' ) );
			}

			$sites = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d", (int) $_GET['id'] ) ); ?>

			<form method="POST" action="<?php echo remove_query_arg( 'action' ); ?>">
				<?php screen_icon( 'ms-admin' ); ?>
				<h2><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h2>
				<h3><?php esc_html_e( 'Delete Network', 'wp-multi-network' ); ?>: <?php echo esc_html( $network->domain . $network->path ); ?></h3>
				<div>
					<?php
					if ( !empty( $sites ) ) {
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
					<a class="button" href="<?php echo $this->admin_url(); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
				</div>
			</form>
			<?php
		}
	}


	public function delete_multiple_network_page() {
		global $wpdb;

		if ( isset( $_POST['delete_multiple'] ) && isset( $_POST['deleted_networks'] ) ) {
			foreach ( $_POST['deleted_networks'] as $deleted_network ) {
				$result = delete_network( (int) $deleted_network, (isset( $_POST['override'] ) ) );
				if ( is_a( $result, 'WP_Error' ) ) {
					wp_die( $result->get_error_message() );
				}
			}
			$_GET['deleted'] = 'yes';
			$_GET['action']  = 'saved';
		} else {

			// ensure a list of networks was sent
			if ( !isset( $_POST['allnetworks'] ) ) {
				wp_die( esc_html__( 'You have not selected any networks to delete.', 'wp-multi-network' ) );
			}
			$allnetworks = array_map( create_function( '$val', 'return (int)$val;' ), $_POST['allnetworks'] );

			// ensure each network is valid
			foreach ( $allnetworks as $network ) {
				if ( !network_exists( (int) $network ) ) {
					wp_die( esc_html__( 'You have selected an invalid network for deletion.', 'wp-multi-network' ) );
				}
			}

			// remove primary network from list
			if ( in_array( 1, $allnetworks ) ) {
				$sites = array( );
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
				<form method="POST" action="<?php echo $this->admin_url(); ?>">
					<h2><?php esc_html_e( 'Networks', 'wp-multi-network' ); ?></h2>
					<h3><?php esc_html_e( 'Delete Multiple Networks', 'wp-multi-network' ); ?></h3>
					<?php if ( $sites ) {
						if ( RESCUE_ORPHANED_BLOGS && ENABLE_NETWORK_ZERO ) { ?>
							<div id="message" class="error">
								<h3><?php esc_html_e( 'You have selected the following networks for deletion', 'wp-multi-network' ); ?>:</h3>
								<ul>
									<?php foreach ( $network as $deleted_network ) { ?>
										<li><input type="hidden" name="deleted_networks[]" value="<?php echo esc_attr( $deleted_network->id ); ?>" /><?php echo esc_html( $deleted_network->domain . $deleted_network->path ); ?></li>
									<?php } ?>
								</ul>
								<p><?php esc_html_e( 'There are blogs associated with one or more of these networks.  Deleting them will move these blogs to the holding network.', 'wp-multi-network' ); ?></p>
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
								<p><?php esc_html_e( 'There are blogs associated with one or more of these networks.  Deleting them will delete those blogs as well.', 'wp-multi-network' ); ?></p>
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
	}

	/**
	 * Admin page for users who are network admins on another network, but possibly not the current one
	 */
	public function my_networks_page() {
		global $wpdb; ?>

		<div class="wrap">
			<h2><?php esc_html_e( 'My Networks', 'wp-multi-network' ); ?></h2>

			<?php
			$my_networks = user_has_networks();
			foreach( $my_networks as $key => $network_id ) {
				$my_networks[$key] = $wpdb->get_row( $wpdb->prepare(
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
	 * Admin page for Networks settings -
	 */
	public function networks_settings_page() {

	}
}
