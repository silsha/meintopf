<?php
/*
Plugin Name: mEintopf
Version: 0.01.1
Description: soup.io for the real world
Author: Severin Schols
Author URI: http://severin.schols.de/
License: MIT
*/

//include_once('libs/simplepie_1.3.1.mini.php');

register_activation_hook( __FILE__, 'meintopf_activate' );
add_action( 'init', 'meintopf_init' );

// Activate the plugin
function meintopf_activate() {
	$options = array("feeds" => array());
	add_option("meintopf_options",$options);
	$args = array(
		'public' => false,
		'exclude_from_search' => false,
		'publicly_queryable' => false,
		'show_ui' => false, 
		'show_in_menu' => false, 
		'query_var' => true,
		'rewrite' => array( 'slug' => 'book' ),
		'capability_type' => 'post',
		'has_archive' => false, 
		'hierarchical' => false,
		'menu_position' => null,
		'map_meta_cap' => false,
		'query_var' => false,
		'can_export' => false,
		'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'custom-fields'  )
	);
	register_post_type( 'meintopf_item', $args );
}

// Init the plugin each time
function meintopf_init() {
	add_action('admin_menu', 'meintopf_admin_menu_entries');
}

// Add entries to the admin menu
function meintopf_admin_menu_entries() {
	add_menu_page('mEintopf', 'mEintopf', 'publish_posts', 'meintopf', 'meintopf_menu_page',"",'1'); 

}

// Show the admin menu page
function meintopf_menu_page() {
	if (isset($_GET['action']) && $_GET['action'] == "fetch") {
		meintopf_reader_fetch_feeds();
	} else {
		if( isset($_POST['feedurl']) ) {
			meintopf_add_feed($_POST['feedurl']);
		}
		echo "<div><h2>Add Feed</h2><form action=\"\" method=\"post\"><label>Feed-URL:</label><input type=\"text\" name=\"feedurl\"><input type=\"submit\"></form></div><hr>";
		meintopf_reader();
	}
}

function meintopf_reader() {
	$args = array(
    'posts_per_page'  => 20,
    'offset'          => 0,
    'orderby'         => 'post_date',
    'order'           => 'DESC',
    'post_type'       => 'meintopf_item',
    'post_status'     => '%',
    'suppress_filters' => true );
	$myposts = get_posts( $args );
	foreach( $myposts as $post ) {
		echo "<div class=\"meintopf_reader_item\">";
		if ($post->post_title)
			echo "<h3>$post->post_title</h3>";
		echo "<div class=\"meintopf_reader_content\">$post->post_content</div><a href=\"#\">Repost</a></div>";
	} 
}

// Fetch updates
function meintopf_reader_fetch_feeds() {
	// get the list of feeds
	$feeds = meintopf_get_feeds();
	
	// Make sure it's not empty.
	if (count($feeds) <> 0) {
		$feed = new SimplePie();
		$feed->set_feed_url($feeds);
		$feed->set_cache_duration (60);
		$success = $feed->init();
		
		// We can haz feed items
		if ($success) {
			$feed->handle_content_type();
			foreach($feed->get_items() as $item) {
				// Check if we have that post already
				$args = array(
					'guid' => $item->get_id(),
					'post_type' => 'meintopf_item'
				);
				$my_query = null;
				$my_query = new WP_Query($args);
				if ( !$my_query->have_posts() ) { // No, we don't. Insert it.
					// Create the post itself
					$post = array(
						'post_type' =>'meintopf_item',
						'post_title' => $item->get_title(),
						'post_content' => $item->get_content(),
						'post_date_gmt' => $item->get_gmdate('Y-m-d H:i:s'),
						'guid' => $item->get_id()
					);
					$id = wp_insert_post($post); // Insert the post
					
					// more metadata
					$author = $item->get_feed()->get_title();
					if ($author_obj = $item->get_author())
						$author = $author_obj->get_name();
					$meta = array(
						'permalink' => $item->get_permalink(),
						'author' => $author,
						'feed_url' => $item->get_feed()->subscribe_url(),
						'feed_title' => $item->get_feed()->get_title()
					);
					update_post_meta($id, 'meintopf_item_metadata', $meta);
				}
				wp_reset_query();
			}
		}
	}
}

/* ******************
 *	Manage Feeds
 * ******************/

function meintopf_get_feeds() {
	$options = get_option("meintopf_options");
	return $options["feeds"];
}

function meintopf_add_feed($feed) {
	$options = get_option("meintopf_options");
	$options['feeds'][] = $feed;
	update_option('meintopf_options',$options);
}
?>
