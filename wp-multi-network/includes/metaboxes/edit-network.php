<?php

/**
 * Metaboxes related to editing a network
 *
 * @package Plugins/Networks/Metaboxes/Network/Edit
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Metabox for assigning properties of a network
 *
 * @since 1.7.0
 *
 * @param WP_Network $network Results of get_network()
 */
function wpmn_edit_network_details_metabox( $network = null ) {

	// Domain
	$domain = ! empty( $network->domain )
		? Requests_IDNAEncoder::encode( $network->domain )
		: '';

	// Path
	$path = ! empty( $network->path )
		? $network->path
		: '/'; ?>

	<table class="edit-network form-table">
		<tr class="form-field form-required">
			<th scope="row">
				<label for="domain"><?php esc_html_e( 'Domain', 'wp-multi-network' ); ?></label>
			</th>
			<td>
				<label for="domain">
					<span class="scheme"><?php echo wp_get_scheme(); ?></span>
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
	</table>

<?php
}

/**
 * Metabox for assigning properties of a network
 *
 * @since 1.7.0
 */
function wpmn_edit_network_new_site_metabox() {
?>

	<table class="edit-network form-table">
		<tr class="form-field form-required">
			<th scope="row">
				<label for="new_site"><?php esc_html_e( 'Site Name', 'wp-multi-network' ); ?>:</label>
			</th>
			<td>
				<input type="text" name="new_site" id="new_site" class="regular-text">
				<p class="description"><?php esc_html_e( 'A new site needs to be created at the root of this network.', 'wp-multi-network' ); ?></p>
			</td>
		</tr>
	</table>

<?php
}

/**
 * Metabox for assigning sites to a network
 *
 * @since 1.7.0
 *
 * @param WP_Network $network
 */
function wpmn_edit_network_assign_sites_metabox( $network = null ) {

	// To
	$to = get_sites( array(
		'site__not_in' => get_main_site_id( $network->id ),
		'network_id'   => $network->id
	) );

	// From
	$from = get_sites( array(
		'network__not_in' => array( $network->id )
	) );

	?>

	<table class="assign-sites widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Available Subsites', 'wp-multi-network' ); ?></th>
				<th>&nbsp;</th>
				<th><?php esc_html_e( 'Network Subsites',   'wp-multi-network' ); ?></th>
			</tr>
		</thead>
		<tr>
			<td class="column-available">
				<select name="from[]" id="from" multiple>

					<?php foreach ( $from as $site ) : ?>

						<?php if ( ( (int) $site->network_id !== (int) $network->id ) && ! is_main_site_for_network( $site->id ) ) : ?>

							<option value="<?php echo esc_attr( $site->id ); ?>">
								<?php echo esc_html( sprintf( '%1$s (%2$s%3$s)', $site->name, $site->domain, $site->path ) ); ?>
							</option>

						<?php endif; ?>

					<?php endforeach; ?>

				</select>
			</td>
			<td class="column-actions">
				<input type="button" name="assign" id="assign" class="button assign" value="&rarr;">
				<input type="button" name="unassign" id="unassign" class="button unassign" value="&larr;">
			</td>
			<td class="column-assigned">
				<select name="to[]" id="to" multiple>

					<?php foreach ( $to as $site ) : ?>

						<?php if ( (int) $site->network_id === (int) $network->id ) : ?>

							<option value="<?php echo esc_attr( $site->id ); ?>" <?php disabled( is_main_site_for_network( $site->id ) ); ?>>
								<?php echo esc_html( sprintf( '%1$s (%2$s%3$s)', $site->name, $site->domain, $site->path ) ); ?>
							</option>

						<?php endif; ?>

					<?php endforeach; ?>

				</select>
			</td>
		</tr>
	</table>

<?php
}

/**
 * Metabox used to publish the network
 *
 * @since 1.7.0
 *
 * @param WP_Network $network
 */
function wpmn_edit_network_publish_metabox( $network = null ) {

	// Network ID
	$network_id = empty( $network )
		? 0
		: $network->id;

	// Button text
	$button_text = empty( $network )
		? esc_html__( 'Create', 'wp-multi-network' )
		: esc_html__( 'Update', 'wp-multi-network' );

	// Button action
	$action = empty( $network )
		? 'create'
		: 'update';

	// Cancel URL
	$cancel_url = add_query_arg( array(
		'page' => 'networks'
	), network_admin_url( 'admin.php' ) ); ?>

	<div class="submitbox">
		<div id="minor-publishing">
			<div id="misc-publishing-actions">

				<?php if ( ! empty( $network ) ) : ?>

					<div class="misc-pub-section misc-pub-section-first" id="network">
						<span><?php printf( __( 'Name: <strong>%1$s</strong>',  'wp-multi-network' ), get_network_option( $network->id, 'site_name' ) ); ?></span>
					</div>
					<div class="misc-pub-section misc-pub-section-last" id="sites">
						<span><?php printf( __( 'Sites: <strong>%1$s</strong>', 'wp-multi-network' ), get_network_option( $network->id, 'blog_count' ) ); ?></span>
					</div>

				<?php else : ?>

					<div class="misc-pub-section misc-pub-section-first" id="sites">
						<span><?php esc_html_e( 'Creating a network with 1 new site.', 'wp-multi-network' ); ?></span>
					</div>

				<?php endif; ?>

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
