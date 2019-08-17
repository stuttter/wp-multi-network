<?php
/**
 * Metaboxes related to moving a site to a different network.
 *
 * @package WPMN
 * @since 1.7.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the metabox for assigning which network a given site should be moved to.
 *
 * @since 1.7.0
 *
 * @param WP_Site $site Optional. Site object. Default null.
 */
function wpmn_move_site_list_metabox( $site = null ) {
	$networks = get_networks();
	?>

	<table class="move-site widefat">
		<tr>
			<th><?php esc_html_e( 'New Network', 'wp-multi-network' ); ?></th>
			<td>
				<select name="to" id="to">
					<option value="0">
						<?php esc_html_e( '&mdash; No Network &mdash;', 'wp-multi-network' ); ?>
					</option>
					<?php

					foreach ( $networks as $new_network ) {
						?>
						<option value="<?php echo esc_attr( $new_network->id ); ?>" <?php selected( $site->network_id, $new_network->id ); ?>>
							<?php echo esc_html( wp_get_scheme() ) . esc_html( $new_network->domain . '/' . ltrim( $new_network->path, '/' ) ); ?>
						</option>
						<?php
					}

					?>
				</select>
			</td>
		</tr>
	</table>

	<?php
}

/**
 * Renders the metabox used to publish the move-site page.
 *
 * @since 1.7.0
 *
 * @param WP_Site $site Optional. Site object. Default null.
 */
function wpmn_move_site_assign_metabox( $site = null ) {
	?>

	<div class="submitbox">
		<div id="minor-publishing">
			<div id="misc-publishing-actions">
				<div class="misc-pub-section curtime misc-pub-section-first">
					<span>
						<?php
						printf(
							/* translators: %s: site registration date */
							esc_html__( 'Created: <strong>%1$s</strong>', 'wp-multi-network' ),
							esc_html( $site->registered )
						);
						?>
					</span>
				</div>
				<div class="misc-pub-section misc-pub-section-last" id="domain">
					<span>
						<?php
						printf(
							/* translators: %s: site domain */
							esc_html__( 'Domain: <strong>%1$s</strong>', 'wp-multi-network' ),
							esc_html( $site->domain )
						);
						?>
					</span>
				</div>
				<div class="misc-pub-section misc-pub-section-last" id="path">
					<span>
						<?php
						printf(
							/* translators: %s: site path */
							esc_html__( 'Path: <strong>%1$s</strong>', 'wp-multi-network' ),
							esc_html( $site->path )
						);
						?>
					</span>
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
