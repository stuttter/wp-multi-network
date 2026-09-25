<?php
/**
 * Metaboxes related to editing a network.
 *
 * @package WPMN
 * @since 1.7.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the metabox for assigning properties of a network.
 *
 * @since 1.7.0
 *
 * @param WP_Network $network Optional. Network object. Default null.
 * @return void
 */
function wpmn_edit_network_details_metabox( $network = null ) {

	$domain = ! empty( $network->domain )
		? $network->domain
		: '';

	$path = ! empty( $network->path )
		? $network->path
		: '/';

	?>

	<table class="edit-network form-table">
		<?php do_action( 'wpmn_edit_network_details_metabox_before_group', $network ); ?>

		<tr class="form-field form-required">
			<th scope="row">
				<label for="domain"><?php esc_html_e( 'Domain', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<label for="domain">
					<span class="code">
						<span class="scheme">https://</span><!--
						--><input type="text" name="domain" id="domain" class="regular-text code" value="<?php echo esc_attr( $domain ); ?>">
					</span>
				</label>
			</td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row">
				<label for="path"><?php esc_html_e( 'Path', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<input type="text" name="path" id="path" class="regular-text code" value="<?php echo esc_attr( $path ); ?>">
				<p class="description"><?php esc_html_e( 'Use "/" if you are unsure.', 'wp-multi-network' ); ?></p>
			</td>
		</tr>

		<?php do_action( 'wpmn_edit_network_details_metabox_after_group', $network ); ?>
	</table>

	<?php
}

/**
 * Renders the metabox for defining the main site for a new network.
 *
 * @since 1.7.0
 * @since NEXT Added support for selecting an existing site as root site.
 *
 * @return void
 */
function wpmn_edit_network_new_site_metabox() {
	?>

	<table class="edit-network form-table">
		<?php do_action( 'wpmn_edit_network_new_site_metabox_before_group' ); ?>

		<tr class="form-field root-site-new">
			<th scope="row">
				<label for="new_site"><?php esc_html_e( 'Site Name', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<input type="text" name="new_site" id="new_site" class="regular-text">
				<p class="description"><?php esc_html_e( 'A new site needs to be created at the root of this network.', 'wp-multi-network' ); ?></p>
			</td>
		</tr>

		<tr class="form-field root-site-existing wpmn-site-search-row">
			<th scope="row">
				<label for="existing_site_search"><?php esc_html_e( 'Find Site', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<span class="wpmn-site-search-field">
					<input type="search" id="existing_site_search" class="regular-text" autocomplete="off" aria-describedby="existing_site_search_help">
					<span id="existing_site_search_spinner" class="spinner" aria-hidden="true"></span>
				</span>
				<input type="hidden" name="existing_site_id" id="existing_site_id" value="">
				<p id="existing_site_search_help" class="description"><?php esc_html_e( 'Search subsites by domain or path.', 'wp-multi-network' ); ?></p>
			</td>
		</tr>
		<tr class="form-field root-site-existing">
			<th scope="row">
				<label for="existing_site_domain"><?php esc_html_e( 'Domain', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<input type="text" id="existing_site_domain" class="regular-text code" value="" disabled>
			</td>
		</tr>
		<tr class="form-field root-site-existing">
			<th scope="row">
				<label for="existing_site_path"><?php esc_html_e( 'Path', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<input type="text" id="existing_site_path" class="regular-text code" value="" disabled>
			</td>
		</tr>
		<tr class="form-field root-site-existing">
			<th scope="row">
				<label for="existing_site_name"><?php esc_html_e( 'Site Name', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<input type="text" id="existing_site_name" class="regular-text" value="" aria-describedby="existing_site_name_status" disabled>
				<span id="existing_site_name_status" class="description" role="status" aria-live="polite"></span>
				<p class="description"><?php esc_html_e( 'The selected site will become the root of the new network.', 'wp-multi-network' ); ?></p>
			</td>
		</tr>

		<?php do_action( 'wpmn_edit_network_new_site_metabox_after_group' ); ?>
	</table>

	<?php
}

/**
 * Renders the metabox for assigning sites to a network.
 *
 * @since 1.7.0
 *
 * @phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only searches.
 *
 * @param WP_Network $network Network being edited.
 * @return void
 */
function wpmn_edit_network_assign_sites_metabox( $network = null ) {
	if ( ! $network ) {
		return;
	}

	$network_id    = (int) ( $network->id ?? 0 );
	$root_id       = get_main_site_id( $network_id );
	$root_site     = $root_id ? get_site( $root_id ) : null;
	$networks      = get_networks();
	$networks      = is_array( $networks ) ? $networks : array();
	$network_names = array();
	foreach ( $networks as $known_network ) {
		$name = get_network_option( $known_network->id, 'site_name' );

		$network_names[ $known_network->id ] = $name ? $name : $known_network->domain . $known_network->path;
	}
	$search   = isset( $_GET['assigned_site_search'] ) && is_string( $_GET['assigned_site_search'] ) ? sanitize_text_field( wp_unslash( $_GET['assigned_site_search'] ) ) : '';
	$page     = isset( $_GET['assigned_site_page'] ) && is_scalar( $_GET['assigned_site_page'] ) ? max( 1, absint( $_GET['assigned_site_page'] ) ) : 1;
	$per_page = 20;
	$query    = array(
		'network_id'    => $network_id,
		'site__not_in'  => array( $root_id ),
		'number'        => $per_page,
		'offset'        => ( $page - 1 ) * $per_page,
		'no_found_rows' => false,
	);

	if ( '' !== $search ) {
		$query['search'] = $search;
	}

	$site_query   = new WP_Site_Query( $query );
	$sites        = $site_query->sites;
	$total        = $site_query->found_sites;
	$other_sites  = array();
	$other_search = isset( $_GET['available_site_search'] ) && is_string( $_GET['available_site_search'] ) ? sanitize_text_field( wp_unslash( $_GET['available_site_search'] ) ) : '';

	if ( strlen( $other_search ) >= 3 ) {
		$root_ids          = array();
		$other_network_ids = array();
		foreach ( $networks as $other_network ) {
			if ( (int) $other_network->id !== $network_id ) {
				$other_network_ids[] = (int) $other_network->id;
				$other_root_id       = get_main_site_id( $other_network->id );
				if ( $other_root_id ) {
					$root_ids[] = $other_root_id;
				}
			}
		}
		if ( $other_network_ids ) {
			$other_sites = get_sites(
				array(
					'network__in'  => $other_network_ids,
					'site__not_in' => $root_ids,
					'search'       => $other_search,
					'number'       => 10,
				)
			);
			$other_sites = is_array( $other_sites ) ? $other_sites : array();
		}
	}
	?>
	<div class="wpmn-site-assignment">
		<section class="wpmn-assignment-section">
			<h3><?php esc_html_e( 'Sites in this network', 'wp-multi-network' ); ?></h3>
			<?php if ( $root_site ) : ?>
				<p class="wpmn-root-site"><strong><?php esc_html_e( 'Primary site', 'wp-multi-network' ); ?></strong> <?php echo esc_html( $root_site->domain . $root_site->path ); ?> <span class="description"><?php esc_html_e( 'Cannot be moved.', 'wp-multi-network' ); ?></span></p>
			<?php endif; ?>
			<p class="search-box">
				<label class="screen-reader-text" for="assigned-site-search"><?php esc_html_e( 'Search sites in this network', 'wp-multi-network' ); ?></label>
				<input type="search" id="assigned-site-search" name="assigned_site_search" form="wpmn-assigned-site-search-form" value="<?php echo esc_attr( $search ); ?>">
				<button type="submit" class="button" form="wpmn-assigned-site-search-form"><?php esc_html_e( 'Search Sites', 'wp-multi-network' ); ?></button>
			</p>
			<table class="widefat striped"><thead><tr><th class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select sites to move', 'wp-multi-network' ); ?></span></th><th><?php esc_html_e( 'Site', 'wp-multi-network' ); ?></th></tr></thead><tbody>
			<?php foreach ( $sites as $site ) : ?>
				<?php
				// Translators: %s: subsite URL.
				$move_label = sprintf( __( 'Move %s', 'wp-multi-network' ), $site->domain . $site->path );
				?>
				<tr><th class="check-column"><input type="checkbox" name="move_sites[]" value="<?php echo esc_attr( strval( $site->id ) ); ?>" aria-label="<?php echo esc_attr( $move_label ); ?>"></th><td><?php echo esc_html( get_blog_option( $site->id, 'blogname' ) ); ?> <span class="description"><?php echo esc_html( $site->domain . $site->path ); ?></span></td></tr>
			<?php endforeach; ?>
			<?php
			if ( empty( $sites ) ) :
				?>
				<tr><td colspan="2"><?php esc_html_e( 'No subsites found.', 'wp-multi-network' ); ?></td></tr><?php endif; ?>
			</tbody></table>
			<?php if ( $total > $per_page ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
				<?php
				$pagination = paginate_links(
					array(
						'base'    => add_query_arg(
							array(
								'page'                  => 'networks',
								'action'                => 'edit_network',
								'id'                    => $network_id,
								'assigned_site_search'  => $search,
								'available_site_search' => $other_search,
								'assigned_site_page'    => '%#%',
							),
							network_admin_url( 'admin.php' )
						),
						'current' => $page,
						'total'   => (int) ceil( $total / $per_page ),
					)
				);
				if ( $pagination ) {
					echo wp_kses_post( $pagination );
				}
				?>
				</div></div>
			<?php endif; ?>
			<p><label for="move-to-network"><?php esc_html_e( 'Move selected sites to', 'wp-multi-network' ); ?></label>
			<select name="move_to_network" id="move-to-network"><option value=""><?php esc_html_e( 'Choose a network', 'wp-multi-network' ); ?></option>
			<?php foreach ( $networks as $other_network ) : ?>
				<?php if ( (int) $other_network->id !== $network_id ) : ?>
					<option value="<?php echo esc_attr( strval( $other_network->id ) ); ?>"><?php echo esc_html( $network_names[ $other_network->id ] ); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
			</select></p>
		</section>
		<section class="wpmn-assignment-section">
			<h3><?php esc_html_e( 'Bring a subsite into this network', 'wp-multi-network' ); ?></h3>
			<p class="search-box"><label class="screen-reader-text" for="available-site-search"><?php esc_html_e( 'Search subsites in other networks', 'wp-multi-network' ); ?></label><input type="search" id="available-site-search" name="available_site_search" form="wpmn-available-site-search-form" value="<?php echo esc_attr( $other_search ); ?>"><button type="submit" class="button" form="wpmn-available-site-search-form"><?php esc_html_e( 'Search Sites', 'wp-multi-network' ); ?></button></p>
			<?php
			if ( strlen( $other_search ) < 3 ) :
				?>
				<p class="description"><?php esc_html_e( 'Enter at least three characters of a domain or path.', 'wp-multi-network' ); ?></p>
				<?php
			elseif ( empty( $other_sites ) ) :
				?>
				<p><?php esc_html_e( 'No eligible subsites found.', 'wp-multi-network' ); ?></p>
			<?php else : ?>
				<fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Choose a subsite to move into this network', 'wp-multi-network' ); ?></legend>
				<?php foreach ( $other_sites as $site ) : ?>
					<p><label><input type="radio" name="move_here_site_id" value="<?php echo esc_attr( strval( $site->id ) ); ?>"> <?php echo esc_html( $site->domain . $site->path ); ?> <span class="description"><?php echo esc_html( $network_names[ $site->network_id ] ); ?></span></label></p>
				<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
		</section>
		<p class="description"><?php esc_html_e( 'Site moves take effect when you update the network.', 'wp-multi-network' ); ?></p>
	</div>

	<?php
}

/**
 * Renders the metabox used to publish the network.
 *
 * @since 1.7.0
 *
 * @param WP_Network $network Optional. Network object. Default null.
 * @return void
 */
function wpmn_edit_network_publish_metabox( $network = null ) {
	if ( empty( $network ) ) {
		$network_id  = 0;
		$button_text = esc_html__( 'Create', 'wp-multi-network' );
		$action      = 'create';
	} else {
		$network_id  = $network->id;
		$button_text = esc_html__( 'Update', 'wp-multi-network' );
		$action      = 'update';
	}

	$cancel_url = add_query_arg(
		array(
			'page' => 'networks',
		),
		network_admin_url( 'admin.php' )
	);

	?>

	<div class="submitbox">
		<div id="minor-publishing">
			<div id="misc-publishing-actions">
				<?php

				if ( ! empty( $network ) ) {
					?>

					<div class="misc-pub-section misc-pub-section-first" id="network">
						<span>
							<?php
							printf(
								/* translators: %s: network name */
								__( 'Name: <strong>%s</strong>', 'wp-multi-network' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								esc_html( get_network_option( $network->id, 'site_name' ) )
							);
							?>
						</span>
					</div>
					<div class="misc-pub-section misc-pub-section-last" id="sites">
						<span>
							<?php
							printf(
								/* translators: %s: network site count */
								__( 'Sites: <strong>%s</strong>', 'wp-multi-network' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								esc_html( get_network_option( $network->id, 'blog_count' ) )
							);
							?>
						</span>
					</div>

					<?php
				} else {
					?>

					<div class="misc-pub-section misc-pub-section-first" id="sites">
						<span><?php esc_html_e( 'Creating a network with 1 site.', 'wp-multi-network' ); ?></span>
					</div>

					<?php
				}

				?>
			</div>

			<div class="clear"></div>
		</div>

		<div id="major-publishing-actions">
			<a class="button" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
			<div id="publishing-action">
				<?php

				wp_nonce_field( 'edit_network', 'network_edit' );

				submit_button( $button_text, 'primary', 'submit', false );

				?>
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<input type="hidden" name="network_id" value="<?php echo esc_attr( strval( $network_id ) ); ?>">
			</div>
			<div class="clear"></div>
		</div>
	</div>

	<?php
}
