<?php

function switch_blog_and_network ( $new_blog, $prev_blog_id ){
	$site_object = get_site( $new_blog );
	if ( !($site_object instanceof WP_Site ) ){
		return;
	} 
	if( get_current_network_id() != $site_object->site_id ) {
	      switch_to_network( $site_object->site_id );
	} 
}
add_action( 'switch_blog', 'switch_blog_and_network', 10, 2 ); 