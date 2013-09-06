<?php

/**
 * Plugin Name: Lineup Manager
 * Description: Currate groups of posts
 * Author: Micah Ernst
 * Author URI: http://www.micahernst.com
 * Version: 0.1
 */

class Lineup_Manager {

	/**
	 * Array of possibe locations where a lineup can live
	 */	
	var $data = array();

	/**
	 * Hooks and filters
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'restrict_manage_posts', array( $this, 'manage_posts_filter' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );

		// ajax actions
		add_action( 'wp_ajax_lineup_manager_get_item', array( $this, 'get_item' ) );
		add_action( 'wp_ajax_lineup_manager_get_posts', array( $this, 'get_posts') );
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
					'title',
					'custom-fields'
				)
			)
		);
	}

	/**
	 * Enable some scripts/styles used with our meta boxes
	 *
	 * @return void
	 */
	public function scripts() {

		wp_enqueue_script( 'lineup-manager', plugins_url( 'lineup-manager') . '/js/main.js', array( 'jquery', 'jquery-ui-sortable' ), null, true );

		wp_localize_script( 'lineup-manager', 'lineupManager', array( 'data' => $this->data ) );

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
		$selected_location = get_post_meta( $post->ID, 'lineup_location', true );

		if( empty( $selected_location ) ) $selected_location = key( $this->data );

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

		<div class="lineup">

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
				<select name="lineup_location" id="lineup-location-select">
					<?php foreach( $this->data as $location => $args ) : ?>
						<option value="<?php echo esc_attr( $location ); ?>" <?php selected( $location, $selected_location ); ?>><?php echo esc_html( $args['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</fieldset>

			<fieldset class="field-lineup-layout">
				<label>Layout</label>
				<select name="lineup_layout" id="lineup-layout-select">
					<?php foreach( $this->data[$selected_location]['layouts'] as $layout => $args ) : ?>
						<option value="<?php echo esc_attr( $layout ); ?>" <?php selected( $selected_layout, $layout ); ?>><?php echo esc_html( $args['name'] ); ?></option>
					<?php endforeach; ?>
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
					<img src="<?php echo esc_url( home_url() . '/wp-includes/images/wpspin.gif' ); ?>"/>
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
	public function get_item() {

		// check_ajax_referer()

		if( isset( $_REQUEST['id'] ) ) {

			 $post = get_post( intval( $_REQUEST['id'] ) );

			 if( $post )
			 	die( $this->render_li( $post ) );
		}
	}

	/**
	 *
	 */
	public function get_posts() {

		//check_ajax_referer()

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
						'<div class="item">%s<a href="#" class="add" data-id="%d">Add</a></div>',
						esc_html( $post->post_title ),
						intval( $post->ID )
					);
				}

				die( $html );
			}
		}
	}

	/**
	 * Get layouts based on a passed location
	 *
	 * @return void
	 */
	public function get_layouts() {

	}

	/**
	 * Helper method to build list items
	 *
	 * @return string HTML markup for li
	 */
	public function render_li( $post ) {
		return sprintf(
			'<li data-id="%d">' .
				'<span class="title">%s</span>' .
				'<nav>' .
					'<a href="%s" target="_blank">Edit</a> | ' .
					'<a href="#" class="remove">Remove</a> | ' .
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
	 * @return array The posts for the given lineup
	 */
	public static function get_lineup( $location ) {

		if( empty( $location ) )
			return false;

		$lineup_query = new WP_Query( array(
			'post_type' => 'lineup',
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'tax_query' => array(
				array(
					'tax' => 'location',
					'field' => 'slug',
					'terms' => sanitize_text_field( $location )
				)
			)
		));

		if( $lineup_query->have_posts() ) {
			
			$lineup = array_shift( $lineup_query->posts );

			$post_ids = get_post_meta( $lineup->ID, 'lineup_post_ids', true );

			if( $post_ids ) {

				// should this be cached?
				$post_query = new WP_Query( array(
					'post__in' => array_map( 'intval', explode( ',', $post_ids ) ),
					'orderby' => 'post__in'
				));

				// got some posts, lets send them back
				if( $post_query->have_posts() ) {
					return $post_query->posts;
				}
			}
		}

		// we failed!
		return false;
	}


	/**
	 * Save our custom fields and custom taxonomy for lineups
	 *
	 * @param $post_id init
	 * @param $post object
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

		$fields = array( 'lineup_location', 'lineup_layout', 'lineup_post_ids' );

		foreach( $fields as $field ) {
			if( !empty( $_POST[$field] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
			}
		}
	}

	/**
	 * Add a possible lineup location
	 *
	 * @return void
	 */
	public function add_location( $slug, $args ) {

		// parse defaults?

		$this->data[$slug] = $args;
	}

	/**
	 *
	 */
	public function add_layout( $slug, $locations, $args ) {
		foreach( $locations as $location ) {
			if( isset( $this->data[$location] ) ) {
				$this->data[$location]['layouts'][$slug] = $args;
			}
		}
	}

}


$lm = new Lineup_Manager();

// slug, args
$lm->add_location( 'home', array(
	'name' => 'Home',
	'url' => home_url()
));

$lm->add_location( 'tech', array(
	'name' => 'Technology',
	'url' => home_url() . '/technology/'
));

// slug, locations, args
$lm->add_layout( 'lead', array( 'home' ), array(
	'name' => 'Lead',
	'limit' => 3
));

$lm->add_layout( 'belt', array( 'home' ), array(
	'name' => 'Belt',
	'limit' => 4
));

$lm->add_layout( 'three-up', array( 'tech' ), array(
	'name' => 'Three Up',
	'limit' => 3
));

$lm->add_layout( 'six-up', array( 'tech' ), array(
	'name' => 'Six Up',
	'limit' => 6
));

//die_r( $lm->locations );