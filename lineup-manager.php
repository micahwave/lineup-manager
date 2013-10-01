<?php

/**
 * Plugin Name: Lineup Manager
 * Description: Currate groups of posts
 * Author: Micah Ernst
 * Author URI: http://www.micahernst.com
 * Version: 0.1
 */

if( !class_exists( 'Lineup_Manager' ) ) :

class Lineup_Manager {

	/**
	 * The possible locations where a lineup can appear
	 */
	var $locations = array();

	/**
	 * Layouts that a lineup can have. These are scoped to a location
	 */
	var $layouts = array();

	/**
	 * Hooks and filters
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'init' ), 999 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'restrict_manage_posts', array( $this, 'manage_posts_filter' ) );
		add_action( 'publish_lineup', array( $this, 'publish_lineup') );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );

		// ajax actions
		add_action( 'wp_ajax_lineup_manager_get_item', array( $this, 'ajax_get_item' ) );
		add_action( 'wp_ajax_lineup_manager_get_posts', array( $this, 'ajax_get_posts') );
	}

	/**
	 * Register our custom post type and post type taxonomy
	 *
	 * @return void
	 */
	public function init() {

		// post type to store lineup
		register_post_type(
			'lineup',
			array(
				'labels' => array(
					'name' => 'Lineups',
					'singular_name' => 'Lineup',
					'add_new_item' => 'Add New Lineup'
				),
				'public' => true,
				'supports' => array(
					'title'
				)
			)
		);

		// create lineup locations tax
		register_taxonomy( 'lineup_location', 'lineup', array(
			'label' => 'Locations',
			'public' => false,
			'show_ui' => false,
			'hierarchical' => false
		));

		// add each of our locations as a taxonomy term if the dont already exist
		$hash = md5( serialize( $this->locations ) );

		if( get_option( 'lineup_manager_locations' ) != $hash ) {

			foreach( $this->locations as $slug => $args ) {
				wp_insert_term(
					sanitize_text_field( $args['name'] ),
					'lineup_location',
					array(
						'slug' => sanitize_key( $slug )
					)
				);
			}

			// save this so we dont try to add locations every page load
			add_option( 'lineup_manager_locations', $hash );
		}

	}

	/**
	 * Enable some scripts/styles used with our meta boxes
	 *
	 * @return void
	 */
	public function scripts() {

		wp_enqueue_script( 'lineup-manager', plugins_url( 'lineup-manager') . '/js/main.js', array( 'jquery', 'jquery-ui-sortable' ), null, true );

		wp_localize_script( 'lineup-manager', 'lineupManager', array( 'layouts' => $this->layouts ) );

		wp_enqueue_style( 'lineup-manager', plugins_url( 'lineup-manager' ) . '/css/screen.css' );
	}

	/**
	 * Send the user to a specified URL when they click the preview button
	 */
	public function template_redirect() {

		if( is_preview() && get_post_type() == 'lineup' ) {

			// get the url from the location term
		}
	}

	/**
	 * Add meta boxes to our custom post type
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
	
		// remove meta box for our taxonomy
		remove_meta_box( 'tagsdiv-lineup_location', 'lineup', 'side' );

		add_meta_box( 'lineup', 'Lineup', array( $this, 'lineup_meta_box' ), 'lineup', 'normal', 'high' );
	}

	/**
	 * Meta box that lets users choose and sort posts into a specific order
	 *
	 * @return void
	 */
	public function lineup_meta_box( $post ) {

		wp_nonce_field( 'lineup_manager', 'lineup_manager_nonce' );

		// get selected location
		$locations = wp_get_object_terms( $post->ID, 'lineup_location' );

		// do we have locations?
		if( $locations ) {

			$location = array_shift( $locations );

			$selected_location = $location->slug;

		}

		if( empty( $selected_location ) ) $selected_location = key( $this->locations );

		// get selected layout
		$selected_layout = get_post_meta( $post->ID, 'lineup_layout', true );

		// get the currently selected post ids for this lineup
		$post_ids = get_post_meta( $post->ID, 'lineup_post_ids', true );

		// if we have ids, get the actual posts
		if( $post_ids ) {

			$posts = get_posts( array(
				'posts_per_page' => 100,
				'post__in' => array_map( 'intval', explode( ',', $post_ids ) ),
				'orderby' => 'post__in'
			));
		}

		// get recent posts for our select
		$recent_posts = get_posts( array(
			'posts_per_page' => 20
		));

		?>

		<div id="lineup-order">

			<h2>Selected Posts</h2>

			<input type="hidden" name="lineup_post_ids" id="lineup-post-ids" value="<?php echo esc_attr( $post_ids ); ?>">

			<ol class="selected-posts">
				<?php if( !empty( $posts ) ) : ?>
					<?php foreach( $posts as $post ) echo $this->render_li( $post ); ?>
				<?php else : ?>
					<p class="notice">No posts added.</p>
				<?php endif; ?>
			</ol>

		</div>

		<div id="lineup-options">

			<h2>Settings</h2>

			<fieldset class="field-lineup-location">
				<label>Location</label>
				<?php

				if( count( $this->locations ) ) {
					echo '<select name="lineup_location" id="lineup-location-select">';
					foreach( $this->locations as $slug => $location ) {

						// make sure the slug matches the value used in the term
						$slug = sanitize_key( $slug );

						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $slug ),
							selected( $slug, $selected_location, 0 ),
							esc_html( $location['name'] )
						);
					}
					echo '</select>';
				}

				?>
			</fieldset>

			<fieldset class="field-lineup-layout">
				<label>Layout</label>
				<?php if( isset( $this->layouts[$selected_location] ) ) : ?>
				<select name="lineup_layout" id="lineup-layout-select">
				<?php else : ?>
				<select name="lineup_layout" id="lineup-layout-select" disabled="disabled">
					<option>None available</option>
				<?php endif; ?>
					<?php if( isset( $this->layouts[$selected_location] ) ) : foreach( $this->layouts[$selected_location] as $layout => $args ) : ?>
						<option value="<?php echo esc_attr( $layout ); ?>" <?php selected( $selected_layout, $layout ); ?>><?php echo esc_html( $args['name'] ); ?></option>
					<?php endforeach; endif; ?>
				</select>
			</fieldset>

			<fieldset class="field-recent-posts">
				<label>Add Recent Posts</label>
				<select id="lineup-recent-post">
					<option>Choose a Post</option>
					<?php foreach( $recent_posts as $post ) : ?>
					<option value="<?php echo intval( $post->ID ); ?>"><?php echo esc_html( $post->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</fieldset>

			<fieldset class="field-search-posts">
				<label>Search for Posts to Add</label>
				<input type="text" name="query" id="lineup-search-query" placeholder="Enter a term or phrase"/>
				<input type="button" class="button" id="lineup-search-submit" value="Search"/>
				<div class="search-results"></div>
				<div class="status">
					<img src="<?php echo home_url( '/wp-includes/images/wpspin.gif' ); ?>"/>
					Loading &hellip;
				</div>
			</fieldset>

		</div>

		<?php
	}

	/**
	 * Ajax callback for getting list item markup
	 *
	 * @return void
	 */
	public function ajax_get_item() {

		check_ajax_referer( 'lineup_manager' );

		if( isset( $_REQUEST['id'] ) ) {

			 $post = get_post( intval( $_REQUEST['id'] ) );

			 if( $post )
			 	die( $this->render_li( $post ) );
		}
	}

	/**
	 * Used for searching for posts within our UI
	 *
	 * @return void
	 */
	public function ajax_get_posts() {

		check_ajax_referer( 'lineup_manager' );

		if( isset( $_REQUEST['query'] ) ) {

			$args = apply_filters( 'lineup_post_search_args', array(
				's' => sanitize_text_field( $_REQUEST['query'] ),
				'posts_per_page' => 10
			));

			$posts = get_posts( $args );

			if( $posts ) {

				$html = '';

				foreach( $posts as $post ) {
					$html .= sprintf(
						'<div class="item"><a href="#" class="add" data-id="%d">Add</a><span>%s</span></div>',
						intval( $post->ID ),
						esc_html( $post->post_title )
					);
				}

				die( $html );
			}
		}
	}

	/**
	 * Helper method to build list items
	 *
	 * @param object The post
	 * @return string HTML markup for li
	 */
	public function render_li( $post ) {
		return sprintf(
			'<li data-id="%d">' .
				'<span class="title">%s</span>' .
				'<nav>' .
					'<a href="%s" target="_blank">Edit</a>' .
					'<a href="#" class="remove">Remove</a>' .
					'<a href="%s" target="_blank">View</a>' .
				'</nav>' .
			'</li>',
			intval( $post->ID ),
			esc_html( $post->post_title ),
			get_edit_post_link( $post->ID ),
			get_the_guid( $post->ID )
		);
	}

	/**
	 * Meta box with select box that lets users determine the location for the lineup
	 *
	 * @param object The post
	 * @return void
	 */
	public function location_meta_box( $post ) {

		$terms = wp_get_object_terms( $post->ID, 'location' );

		$selected = 0;

		if( count( $terms ) ) {
			$term = array_shift( $terms );
			$selected = $term->term_id;
		}

		$dropdown = wp_dropdown_categories( array(
			'taxonomy' => 'location',
			'hide_empty' => 0,
			'orderby' => 'ASC',
			'selected' => $selected,
			'echo' => 0,
			'name' => 'lineup_location'
		));

		echo '<p>' . $dropdown . '</p>';

	}

	/**
	 * Add a select box to the manage posts screen that allows users to filter based on lineup location
	 *
	 * @return void
	 */
	public function manage_posts_filter() {

		global $post_type;

		if( $post_type === 'lineup' ) {

			$locations = get_terms( 'location' );

			?>
			<select name="location">
				<option>View All Locations</option>
				<?php foreach( $locations as $location ) : ?>
					<option value="<?php echo esc_attr( $location->slug ); ?>"><?php echo esc_html( $location->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
		}
	}

	/**
	 * Get the lineup for a given location
	 *
	 * @param $location Slug for the location taxonomy term we should find the most recent post for
	 * @return object A lineup that contains posts and a layout
	 */
	public static function get_lineup( $location ) {

		if( empty( $location ) )
			return false;

		$cache_key = $location . '_lineup';

		// try to get the lineup from the cache
		$lineup = wp_cache_get( $cache_key, 'lineup_manager_cache' );

		if( !$lineup ) {

			$lineups = get_posts( array(
				'post_type' => 'lineup',
				'post_status' => 'publish',
				'posts_per_page' => 1,
				'tax_query' => array(
					array(
						'taxonomy' => 'lineup_location',
						'field' => 'slug',
						'terms' => sanitize_text_field( $location )
					)
				))
			);

			if( $lineups ) {

				$current_lineup = array_shift( $lineups );

				// get the ordered post ids
				$post_ids = get_post_meta( $current_lineup->ID, 'lineup_post_ids', true );

				if( $post_ids ) {

					// sanitize the ids
					$post_ids = array_map( 'intval', explode( ',', $post_ids ) );

					// get the posts based on ids
					$posts = get_posts( array(
						'post__in' => $post_ids,
						'orderby' => 'post__in',
						'posts_per_page' => count( $post_ids )
					));

					// build our lineup object
					if( $posts ) {

						$lineup = (object) array(
							'layout' => get_post_meta( $current_lineup->ID, 'lineup_layout', true ),
							'posts' => $posts
						);
					}
				}
			}

			// save the lineup to the cache
			// cache long and bust when need lineup added
			wp_cache_set(
				$cache_key,
				$lineup,
				'lineup_manager_cache',
				apply_filters( 'lineup_manager_cache_expire', ( 3600 * 6 ) ) // maybe someone wants to cache even harder
			);
		} 

		return $lineup;
	}

	/**
	 * Helper to get the location for a given post
	 *
	 * @param int Post ID
	 * @return object The location term
	 */
	public function get_lineup_location( $post_id ) {

		$locations = wp_get_object_terms( $post_id, 'lineup_location' );

		if( !is_wp_error( $locations ) ) {
			return array_shift( $locations );
		}
	}


	/**
	 * Save our custom fields and custom taxonomy for lineups
	 *
	 * @param int Post ID
	 * @param object The Post
	 * @return void
	 */
	public function save_post( $post_id, $post ) {

		// only do this for our post type
		if( $post->post_type != 'lineup' )
			return;

		if( !current_user_can( 'edit_post', $post_id ) )
			return;

		if( !isset( $_POST['lineup_manager_nonce'] ) )
			return;

		if( !wp_verify_nonce( $_POST['lineup_manager_nonce'], 'lineup_manager' ) )
			return;

		// save some custom fields
		$fields = array( 'lineup_layout', 'lineup_post_ids' );

		foreach( $fields as $field ) {
			if( !empty( $_POST[$field] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
			} else {
				delete_post_meta( $post_id, $field );
			}
		}

		// save our location term
		if( !empty( $_POST['lineup_location'] ) ) {

			$location = sanitize_key( $_POST['lineup_location'] );

			wp_set_object_terms(
				$post_id,
				$location,
				'lineup_location'
			);

			// bust and prime cache if post is published
			if( $post->post_status === 'publish' ) {
				$this->bust_cache( $location );
			}
		}
	}

	/**
	 * Hook to bust cache when a lineup is published
	 *
	 * @param int Post ID
	 * @return void
	 */
	public function publish_lineup( $post_id ) {

		$location = $this->get_lineup_location( $post_id );

		if( $location ) {
			$this->bust_cache( $location->slug );
		}
			
	}

	/**
	 * Bust and prime the cache for a given location
	 *
	 * @param string Location to bust
	 * @return void
	 */
	public function bust_cache( $location ) {

		// bust cache
		wp_cache_delete( $location . '_location', 'lineup_manager_cache' );

		// prime cache
		$lineup = $this->get_lineup( $location );
	}

	/**
	 * Add a possible lineup location
	 *
	 * @param string Slug of location to add
	 * @param array Options for the location
	 * @return void
	 */
	public function add_location( $slug, $args ) {
		$this->locations[$slug] = $args;
	}

	/**
	 * Add a layout to a location or multiple locations
	 *
	 * @param string Slug of layout to add
	 * @param array Locations to add the layout to
	 * @param array Options for the layout
	 * @return void
	 */
	public function add_layout( $slug, $locations, $args ) {
		foreach( $locations as $location ) {
			$this->layouts[$location][$slug] = $args;
		}
	}
}

endif;

/*
function sample_lineup_init() {

	global $lineup_manager;

	$lineup_manager = new Lineup_Manager();

	// slug, args
	$lineup_manager->add_location( 'home', array(
		'name' => 'Home',
		'url' => home_url()
	));

	$lineup_manager->add_location( 'tech', array(
		'name' => 'Technology',
		'url' => home_url( '/technology/' )
	));

	// slug, locations, args
	$lineup_manager->add_layout( 'lead', array( 'home' ), array(
		'name' => 'Lead',
		'limit' => 3
	));

	$lineup_manager->add_layout( 'belt', array( 'home' ), array(
		'name' => 'Belt',
		'limit' => 4
	));

	$lineup_manager->add_layout( 'three-up', array( 'tech' ), array(
		'name' => 'Three Up',
		'limit' => 3
	));

	$lineup_manager->add_layout( 'six-up', array( 'tech' ), array(
		'name' => 'Six Up',
		'limit' => 6
	));
}
add_action( 'init', 'sample_lineup_init' );
*/