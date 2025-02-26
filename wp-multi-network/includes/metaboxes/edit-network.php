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
 */
function wpmn_edit_network_details_metabox( $network = null ) {
	$domain = ! empty( $network->domain ) ? Requests_IDNAEncoder::encode( $network->domain ) : '';
	$path   = ! empty( $network->path ) ? $network->path : '/';

	?>

	<table class="edit-network form-table">
		<?php do_action( 'wpmn_edit_network_details_metabox_before_group', $network ); ?>

		<tr class="form-field form-required">
			<th scope="row">
				<label for="domain"><?php esc_html_e( 'Domain', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<label for="domain">
					<span class="scheme"><?php echo esc_html( wp_get_scheme() ); ?></span>
					<input type="text" name="domain" id="domain" class="regular-text" value="<?php echo esc_attr( $domain ); ?>">
				</label>
			</td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row">
				<label for="path"><?php esc_html_e( 'Path', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<input type="text" name="path" id="path" class="regular-text" value="<?php echo esc_attr( $path ); ?>">
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
 */
function wpmn_edit_network_new_site_metabox() {
	?>

	<table class="edit-network form-table">
		<?php do_action( 'wpmn_edit_network_new_site_metabox_before_group' ); ?>

		<tr class="form-field form-required">
			<th scope="row">
				<label for="new_site"><?php esc_html_e( 'Site Name', 'wp-multi-network' ); ?>:</label>
			</th>
			<td>
				<input type="text" name="new_site" id="new_site" class="regular-text">
				<p class="description"><?php esc_html_e( 'A new site needs to be created at the root of this network.', 'wp-multi-network' ); ?></p>
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
 * @param WP_Network $network Optional. Network object. Default null.
 */
function wpmn_edit_network_assign_sites_metabox( $network = null ) {

	// Get sites in this Network.
	$to = get_sites(
		array(
			'network_id' => $network->id,
		)
	);

	// Get sites not in this Network.
	$from = get_sites(
		array(
			'network__not_in' => array( $network->id ),
		)
	);

	?>

	<table class="assign-sites widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Sites in this Network', 'wp-multi-network' ); ?></th>
				<th>&nbsp;</th>
				<th><?php esc_html_e( 'Sites not in this Network', 'wp-multi-network' ); ?></th>
			</tr>
		</thead>
		<tr>
			<td class="column-assigned">
				<select name="to[]" id="to" multiple>
					<?php

					// Loop through to sites.
					foreach ( $to as $site ) :

						// Is main?
						$is_main = is_main_site_for_network( $site->id );

						// Get main helper text.
						$main_text = ! empty( $is_main )
							? esc_attr__( ' (Primary)', 'wp-multi-network' )
							: '';

						if ( (int) $site->network_id === (int) $network->id ) :

							?>

							<option value="<?php echo esc_attr( $site->id ); ?>" <?php disabled( $is_main ); ?>>
								<?php echo esc_html( sprintf( '%1$s - %2$s%3$s%4$s', get_blog_option( $site->id, 'blogname' ), $site->domain, $site->path, $main_text ) ); ?>
							</option>

							<?php

						endif;

					endforeach;

					?>
				</select>
			</td>
			<td class="column-actions">
				<input type="button" name="assign" id="assign" class="button assign" value="&rarr;">
				<input type="button" name="unassign" id="unassign" class="button unassign" value="&larr;">
			</td>
			<td class="column-available">
				<select name="from[]" id="from" multiple>
					<?php

					// Loop through from sites.
					foreach ( $from as $site ) :

						// Is main?
						$is_main = is_main_site_for_network( $site->id );

						// Get main helper text.
						$main_text = ! empty( $is_main )
							? esc_attr__( ' (Primary)', 'wp-multi-network' )
							: '';

						// Omit main sites for now.
						if ( ( (int) $site->network_id !== (int) $network->id ) && empty( $is_main ) ) :

							?>

							<option value="<?php echo esc_attr( $site->id ); ?>">
								<?php echo esc_html( sprintf( '%1$s - %2$s%3$s%4$s', get_blog_option( $site->id, 'blog_ame' ), $site->domain, $site->path, $main_text ) ); ?>
							</option>

							<?php

						endif;

					endforeach;

					?>
				</select>
			</td>
		</tr>
	</table>

	<?php
}

/**
 * Renders the metabox used to publish the network.
 *
 * @since 1.7.0
 *
 * @param WP_Network $network Optional. Network object. Default null.
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
						<span><?php esc_html_e( 'Creating a network with 1 new site.', 'wp-multi-network' ); ?></span>
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
				<input type="hidden" name="network_id" value="<?php echo esc_attr( $network_id ); ?>">
			</div>
			<div class="clear"></div>
		</div>
	</div>

	<?php
}
