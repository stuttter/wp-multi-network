<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Metabox for assigning which network a site is part of
 *
 * @since 1.7.0
 *
 * @global type $wpdb
 *
 * @param object $site Results of get_blog_details()
 */
function wpmn_move_site_list_metabox( $site = null ) {
	global $wpdb;

	// Get all networks
	$networks = $wpdb->get_results( "SELECT * FROM {$wpdb->site}" ); ?>

	<table class="move-site widefat">
		<tr>
			<th><?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?></th>
			<td>
				<select name="to" id="to">

					<?php if ( ENABLE_NETWORK_ZERO || empty( $site->site_id ) ) : ?>

						<option value="0">
							<?php esc_html_e( '&mdash; No Network &mdash;', 'wp-multi-network' ); ?>
						</option>

					<?php endif; ?>

					<?php foreach ( $networks as $new_network ) : ?>

						<option value="<?php echo esc_attr( $new_network->id ); ?>" <?php selected( $site->site_id, $new_network->id ); ?>>
							<?php echo esc_html( $new_network->domain ); ?>
						</option>

					<?php endforeach; ?>

				</select>
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
 * @param object $site
 */
function wpmn_move_site_assign_metabox( $site = null ) {
?>

	<div class="submitbox">
		<div id="minor-publishing">
			<div id="misc-publishing-actions">
				<div class="misc-pub-section curtime misc-pub-section-first">
					<span><?php printf( __( 'Created: <strong>%1$s</strong>',  'wp-user-profiles' ), $site->registered ); ?></span>
				</div>
				<div class="misc-pub-section misc-pub-section-last" id="domain">
					<span><?php printf( __( 'Domain: <strong>%1$s</strong>', 'wp-user-profiles' ), $site->domain ); ?></span>
				</div>
				<div class="misc-pub-section misc-pub-section-last" id="path">
					<span><?php printf( __( 'Path: <strong>%1$s</strong>', 'wp-user-profiles' ), $site->path ); ?></span>
				</div>
			</div>

			<div class="clear"></div>
		</div>

		<div id="major-publishing-actions">
			<a class="button" href="./sites.php"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
			<div id="publishing-action">
				<?php submit_button( esc_attr__( 'Move', 'wp-multi-network' ), 'primary', 'move', false ); ?>
				<input type="hidden" name="action" value="update" />
				<input type="hidden" name="from" value="<?php echo esc_attr( $site->site_id ); ?>" />
			</div>
			<div class="clear"></div>
		</div>
	</div>

<?php
}
