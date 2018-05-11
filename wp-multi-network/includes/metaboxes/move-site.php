<?php

/**
 * Metaboxes related to moving a site to a different network
 *
 * @package Plugins/Networks/Metaboxes/Site/Move
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Metabox for assigning which network a site is part of
 *
 * @since 1.7.0
 *
 * @param WP_Site $site Results of get_site()
 */
function wpmn_move_site_list_metabox( $site = null ) {

	// Get all networks
	$networks = get_networks(); ?>

	<table class="move-site widefat">
		<tr>
			<th><?php esc_html_e( 'New Network', 'wp-multi-network' ); ?></th>
			<td>
				<select name="to" id="to">
					<option value="0"><?php

						esc_html_e( '&mdash; No Network &mdash;', 'wp-multi-network' );

					?></option><?php

					// Loop through networks
					foreach ( $networks as $new_network ) :

						// Option value is network ID
						?><option value="<?php echo esc_attr( $new_network->id ); ?>" <?php selected( $site->network_id, $new_network->id ); ?>><?php

						// Include scheme, domain, & path
						echo wp_get_scheme() . esc_html( $new_network->domain . '/' . ltrim( $new_network->path, '/' ) );

						?></option><?php

					endforeach;

				?></select>
			</td>
		</tr>
	</table>

<?php
}

/**
 * Metabox used to publish the move-site page
 *
 * @since 1.7.0
 *
 * @param WP_Site $site
 */
function wpmn_move_site_assign_metabox( $site = null ) {
?>

	<div class="submitbox">
		<div id="minor-publishing">
			<div id="misc-publishing-actions">
				<div class="misc-pub-section curtime misc-pub-section-first">
					<span><?php printf( __( 'Created: <strong>%1$s</strong>',  'wp-multi-network' ), $site->registered ); ?></span>
				</div>
				<div class="misc-pub-section misc-pub-section-last" id="domain">
					<span><?php printf( __( 'Domain: <strong>%1$s</strong>', 'wp-multi-network' ), $site->domain ); ?></span>
				</div>
				<div class="misc-pub-section misc-pub-section-last" id="path">
					<span><?php printf( __( 'Path: <strong>%1$s</strong>', 'wp-multi-network' ), $site->path ); ?></span>
				</div>
			</div>

			<div class="clear"></div>
		</div>

		<div id="major-publishing-actions">
			<a class="button" href="./sites.php"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
			<div id="publishing-action">
				<?php

				wp_nonce_field( 'edit_network', 'network_edit' );

				submit_button( esc_attr__( 'Move', 'wp-multi-network' ), 'primary', 'move', false );

				?>
				<input type="hidden" name="action" value="update">
				<input type="hidden" name="from" value="<?php echo esc_attr( $site->network_id ); ?>">
			</div>
			<div class="clear"></div>
		</div>
	</div>

<?php
}
