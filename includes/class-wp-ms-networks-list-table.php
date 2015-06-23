<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Networks List Table class.
 *
 * @package WPMN
 * @since 1.3
 * @access private
 */
class WP_MS_Networks_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'plural'   => 'networks',
			'singular' => 'network',
			'ajax'     => false,
			'screen'   => 'wpmn',
		) );
	}

	public function ajax_user_can() {
		return current_user_can( 'manage_network_options' );
	}

	public function prepare_items() {
		global $mode, $wpdb, $current_site;

		// TODO: include network zero when there are unassigned sites

		$wild              = '';
		$mode              = ( empty( $_REQUEST['mode'] ) ) ? 'list' : $_REQUEST['mode'];
		$per_page          = $this->get_items_per_page( 'networks_per_page' );
		$pagenum           = $this->get_pagenum();
		$search_conditions = isset( $_REQUEST['s'] ) ? stripslashes( trim( $_REQUEST[ 's' ] ) ) : '';
		$admin_user        = isset( $_REQUEST['network_admin'] ) ? stripslashes( trim( $_REQUEST[ 'network_admin' ] ) ) : '' ;

		if ( false !== strpos( $search_conditions, '*' ) ) {
			$wild              = '%';
			$search_conditions = trim( $search_conditions, '*' );
		}

		$like_s = esc_sql( $this->esc_like( $search_conditions ) );

		$total_query = 'SELECT COUNT( id ) FROM ' . $wpdb->site . ' WHERE 1=1 ';

		$query =	"SELECT {$wpdb->site}.*, meta1.meta_value as sitename, meta2.meta_value as network_admins, COUNT({$wpdb->blogs}.blog_id) as blogs, {$wpdb->blogs}.path as blog_path, {$wpdb->blogs}.site_id as site_id
						FROM {$wpdb->site}
					LEFT JOIN {$wpdb->blogs}
						ON {$wpdb->blogs}.site_id = {$wpdb->site}.id {$search_conditions}
					LEFT JOIN {$wpdb->sitemeta} meta1
						ON
							meta1.meta_key = 'site_name' AND
							meta1.site_id = {$wpdb->site}.id
					LEFT JOIN {$wpdb->sitemeta} meta2
						ON
							meta2.meta_key = 'site_admins' AND
							meta2.site_id = {$wpdb->site}.id
					WHERE 1=1 ";


		if ( ! empty( $search_conditions ) ) {

			if ( is_numeric($search_conditions) && empty( $wild ) ) {
				$query .= " AND ( {$wpdb->site}.site_id = '{$like_s}' )";
				$total_query .= " AND ( {$wpdb->site}.id = {$like_s} )";

			} elseif ( is_subdomain_install() ) {
				$blog_s = str_replace( '.' . $current_site->domain, '', $like_s );
				$blog_s .= $wild . '.' . $current_site->domain;
				$query .= " AND ( {$wpdb->site}.domain LIKE '$blog_s' ) ";
				$total_query .= " AND ( {$wpdb->site}.domain LIKE '$blog_s' ) ";

			} else {

				if ( $like_s != trim('/', $current_site->path) ) {
					$blog_s = $current_site->path . $like_s . $wild . '/';
				} else {
					$blog_s = $like_s;
				}

				$query .= " WHERE  ( {$wpdb->site}.path LIKE '$blog_s' )";
				$total_query .= " WHERE  ( {$wpdb->site}.path LIKE '$blog_s' )";
			}
		}

		if ( ! empty( $admin_user ) ) {
			$query .= ' AND meta2.meta_value LIKE "%' . $admin_user . '%"';
			// TODO: Fix total query
		}

		$total = $wpdb->get_var( $total_query );

		$query   .= " GROUP BY {$wpdb->site}.id";
		$order_by = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : '';

		switch ( $order_by ) {
			case 'domain':
				$query .= ' ORDER BY ' . $wpdb->site . '.domain ';
				break;
			case 'sitename':
				$query .= ' ORDER BY sitename ';
				break;
			case 'sites':
				$query .= ' ORDER BY blogs ';
				break;
			default :

		}

		if ( ! empty( $order_by ) ) {
			$order  = ( isset( $_REQUEST['order'] ) && 'DESC' == strtoupper( $_REQUEST['order'] ) ) ? "DESC" : "ASC";
			$query .= $order;
		}

		$query .= " LIMIT " . intval( ( $pagenum - 1 ) * $per_page ) . ", " . intval( $per_page );

		$this->items = $wpdb->get_results( $query, ARRAY_A );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
		) );
	}

	/**
	 * SQL escape helper function
	 *
	 * Provide a fallback for the new SQL escape function
	 *
	 * @since 1.5.2
	 *
	 * @global object $wpdb
	 * @param string $text
	 * @return string Escaped input
	 */
	public function esc_like( $text ) {
		global $wpdb;

		if ( method_exists( $wpdb, 'esc_like' ) ) {
			$escaped_text = $wpdb->esc_like( $text );
		} else {
			$escaped_text = like_escape( $text );
		}

		return $escaped_text;
	}

	public function no_items() {
		esc_html_e( 'No networks found.', 'wp-multi-network' );
	}

	public function get_bulk_actions() {
		$actions = array();

		if ( current_user_can( 'delete_sites' ) ) {
			$actions['delete'] = __( 'Delete', 'wp-multi-network' );
		}

		return $actions;
	}

	public function pagination( $which ) {
		parent::pagination( $which );
	}

	public function get_columns() {
		return apply_filters( 'wpmn_networks_columns', array(
			'cb'       => '<input type="checkbox" />',
			'sitename' => __( 'Site Name',      'wp-multi-network' ),
			'domain'   => __( 'Domain',         'wp-multi-network' ),
			'path'     => __( 'Path',           'wp-multi-network' ),
			'blogs'    => __( 'Sites',          'wp-multi-network' ),
			'admins'   => __( 'Network Admins', 'wp-multi-network' )
		) );
	}

	public function get_sortable_columns() {
		return array(
			'sitename' => 'sitename',
			'domain'   => 'domain',
			'blogs'    => 'sites'
		);
	}

	public function display_rows() {
		global $current_site;

		$class = '';

		foreach ( $this->items as $network ) {

			$class = ( 'alternate' == $class ) ? '' : 'alternate';

			echo "<tr class='$class'>";

			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( array_keys( $columns ) as $column_name ) {

				$style = in_array( $column_name, $hidden ) ? ' style="display:none;"' : '';

				switch ( $column_name ) {
					case 'cb': ?>
						<th scope="row" class="check-column">
							<input type="checkbox" id="network_<?php echo $network['site_id'] ?>" name="allnetworks[]" value="<?php echo esc_attr( $network['site_id'] ) ?>" />
						</th>
					<?php
					break;

					case 'domain':
						echo "<td class='column-$column_name $column_name'$style>"; ?>
							<?php echo esc_html( $network['domain'] ); ?>
						</td>
					<?php
					break;

					case 'path':
						echo "<td class='column-$column_name $column_name'$style>"; ?>
							<?php echo esc_html( $network['path'] ); ?>
						</td>
					<?php
					break;

					case 'sitename':
						echo "<td class='column-$column_name $column_name'$style>";

						switch_to_network( $network['id'] );
						$network_admin_url = network_admin_url();
						$network_home_url  = network_home_url();
						restore_current_network();

						$myurl = add_query_arg( array(
							'page' => 'networks',
							'id'   => $network['id']
						) ); ?>

						<a href="<?php echo add_query_arg( array( 'action' => 'editnetwork' ), $myurl ); ?>" class="edit"><?php echo $network['sitename']; ?></a>

						<?php

						$actions = array(
							'edit'          => '<span class="edit"><a href="' . add_query_arg( array( 'action' => 'editnetwork' ), $myurl ) . '">' . esc_html__( 'Edit',         'wp-multi-network' ) . '</a></span>',
							'network_admin' => '<span class="edit"><a href="' . esc_url( $network_admin_url ) . '">' . esc_html__( 'Dashboard', 'wp-multi-network' ) . '</a></span>',
							'visit'         => '<span class="edit"><a href="' . esc_url( $network_home_url  ) . '">' . esc_html__( 'Visit',     'wp-multi-network' ) . '</a></span>',
							'assign_sites'  => '<span class="edit"><a href="' . add_query_arg( array( 'action' => 'assignblogs' ), $myurl ) . '">' . esc_html__( 'Assign Sites', 'wp-multi-network' ) . '</a></span>',
						);

						if ( $current_site->id != $network['id'] && $network['id'] != 1 ) {
							if ( current_user_can( 'manage_network_options', $network['id'] ) ) {
								$actions['delete']	= '<span class="delete"><a href="' . esc_url( wp_nonce_url( add_query_arg(array( 'action'	=> 'deletenetwork' ), $myurl ) ) ) . '">' . esc_html__( 'Delete', 'wp-multi-network' ) . '</a></span>';
							}
						}

						$actions = apply_filters( 'manage_networks_action_links', array_filter( $actions ), $network['id'], $network['sitename'] );

						echo $this->row_actions( $actions ); ?>

						</td>

					<?php
					break;

					case 'blogs':
						echo "<td valign='top' class='$column_name column-$column_name'$style>";
							?>
							<a href="http://<?php echo $network['domain'] . $network['blog_path']; ?>wp-admin/network/sites.php" title="<?php esc_attr_e( 'Sites on this network', 'wp-multi-network' ); ?>"><?php echo $network['blogs'] ?></a>
						</td>
					<?php
					break;


					case 'admins':
						echo "<td valign='top' class='$column_name column-$column_name'$style>";
							$network_admins = array_filter( maybe_unserialize( $network['network_admins'] ) );
							if ( ! empty( $network_admins ) ) {
								echo join( ', ', $network_admins );
							}
							?>
						</td>
					<?php
					break;

				case 'plugins': ?>
					<?php if ( has_filter( 'wpmublogsaction' ) ) {
					echo "<td valign='top' class='$column_name column-$column_name'$style>";
						do_action( 'wpmublogsaction', $network['id'] ); ?>
					</td>
					<?php }
					break;

				default:
					echo "<td class='$column_name column-$column_name'$style>";
					do_action( 'manage_sites_custom_column', $column_name, $network['id'] );
					echo "</td>";
					break;
				}
			}
			?>
			</tr>
			<?php
		}
	}
}
