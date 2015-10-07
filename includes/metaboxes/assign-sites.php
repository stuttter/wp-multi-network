<?php

/**
 * Metabox for assigning sites to a network
 *
 * @since 1.7.0
 *
 * @param  object $network
 * @global object $wpdb
 */
function wpmn_assign_sites_list_metabox( $network = null ) {
	global $wpdb;

	// Bail if no network
	if ( empty( $network ) ) {
		esc_html__( 'Invalid network id.', 'wp-multi-network' );
	}

	$sites = $wpdb->get_results( "SELECT * FROM {$wpdb->blogs}" );
	if ( empty( $sites ) ) {
		esc_html__( 'Site table inaccessible.', 'wp-multi-network' );
	}

	foreach ( $sites as $key => $site ) {
		$table_name = $wpdb->get_blog_prefix( $site->blog_id ) . "options";
		$site_name  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE option_name = %s", 'blogname' ) );

		if ( empty( $site_name ) ) {
			esc_html__( 'Invalid blog.', 'wp-multi-network' );
		}

		$sites[ $key ]->name = stripslashes( $site_name->option_value );
	} ?>

	<h3><?php esc_html_e( 'Assign Sites to:', 'wp-multi-network' ); ?> <?php echo wp_get_scheme(); echo esc_html( $network->domain . $network->path ); ?></h3>

	<table class="assign-sites widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Available', 'wp-multi-network' ); ?></th>
				<th>&nbsp;</th>
				<th><?php esc_html_e( 'Assigned', 'wp-multi-network' ); ?></th>
			</tr>
		</thead>
		<tr>
			<td class="column-available">
				<select name="from[]" id="from" multiple>

					<?php foreach ( $sites as $site ) : ?>

						<?php if ( $site->site_id != $network->id ) : ?>

							<option value="<?php echo esc_attr( $site->blog_id ); ?>"><?php echo esc_html( sprintf( '%1$s (%2$s%3$s)', $site->name, $site->domain, $site->path ) ); ?></option>

						<?php endif; ?>

					<?php endforeach; ?>

				</select>
			</td>
			<td class="column-actions">
				<input type="button" name="unassign" id="unassign" class="button" value="&larr;" />
				<input type="button" name="assign" id="assign" class="button" value="&rarr;" />
			</td>
			<td class="column-assigned">
				<select name="to[]" id="to" multiple>

					<?php foreach ( $sites as $site ) : ?>

						<option value="<?php echo esc_attr( $site->blog_id ); ?>" <?php disabled( $site->site_id, $network->id ); ?>><?php echo esc_html( sprintf( '%1$s (%2$s%3$s)', $site->name, $site->domain, $site->path ) ); ?></option>

					<?php endforeach; ?>

				</select>
			</td>
		</tr>
	</table>

<?php
}


function wpmn_assign_sites_move_metabox( $network = null ) {
	if ( has_action( 'add_move_blog_option' ) ) : ?>

		<table class="widefat">
			<thead>
				<tr scope="col"><th colspan="2"><?php esc_html_e( 'Options', 'wp-multi-network' ); ?>:</th></tr>
			</thead>

			<?php do_action( 'add_move_blog_option', $site->blog_id ); ?>
		</table>

	<?php endif;
}

/**
 * Metabox used to publish the assign-sites page
 *
 * @since 1.7.0
 *
 * @param object $network
 */
function wpmn_assign_sites_publish_metabox( $network = null ) {
?>

	<div class="submitbox">
		<div id="minor-publishing">
			<div id="misc-publishing-actions">
				<div class="misc-pub-section misc-pub-section-first" id="network">
					<span><?php printf( __( 'Name: <strong>%1$s</strong>',  'wp-user-profiles' ), get_site_option( 'site_name'  ) ); ?></span>
				</div>
				<div class="misc-pub-section misc-pub-section-last" id="sites">
					<span><?php printf( __( 'Sites: <strong>%1$s</strong>', 'wp-user-profiles' ), get_site_option( 'blog_count' ) ); ?></span>
				</div>
			</div>

			<div class="clear"></div>
		</div>

		<div id="major-publishing-actions">
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'networks' ), network_admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-multi-network' ); ?></a>
			<div id="publishing-action">
				<?php submit_button( esc_attr__( 'Update', 'wp-multi-network' ), 'primary', 'reassign', false ); ?>
				<input type="hidden" name="action" value="update" />
				<input type="hidden" name="user_id" id="network_id" value="<?php echo esc_attr( $network->ID ); ?>" />
			</div>
			<div class="clear"></div>
		</div>
	</div>

<?php
}