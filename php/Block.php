<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;
use WP_Post;
use WP_Error;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Random post selector counter
	 */
	private $random_counter;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
				$this->plugin->dir(),
				[
						'render_callback' => [ $this, 'render_callback' ],
				]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes for the block.
	 * @param string $content The block content, if any.
	 * @param WP_Block $block The instance of this block.
	 *
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {

		//Plugin text domain not set so just using this as an example
		$text_domain = 'wxp-challenge';

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		if ( ! is_array( $post_types ) ) {
			return '';
		}

		$post_id = get_the_ID();
		//Shouldn't need this but just in case
		if ( false === $post_id ) {
			return '';
		}

		$class_name = '';
		//Shouldn't need this as default block attr but just in case
		if ( isset( $attributes['className'] ) ) {
			$class_name = $attributes['className'];
		}

		/**
		 * Maybe this should be moved into theme template file as it's technically frontend rendering?
		 * or at least create a templates folder in plugin to help make this easier to maintain?
		 */
		ob_start();
		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2><?php echo esc_html_x( 'Post Counts', 'heading', $text_domain ); ?></h2>

			<ul>
				<?php
				foreach ( $post_types as $post_type_slug => $post_type_object ) :
					$post_count = $this->get_post_type_count( $post_type_slug );
					?>
					<li>
						<?php
						//Could use _n() function but probably would be messy due to both %d and %s in string
						if ( $post_count === 1 ): ?>
							<?php printf( esc_html_x( 'There is only %d %s.', 'paragraph', $text_domain ), intval( $post_count ), esc_html( $post_type_object->labels->singular_name ) ); ?>
						<?php else: ?>
							<?php printf( esc_html_x( 'There are %d %s.', 'paragraph', $text_domain ), intval( $post_count ), esc_html( $post_type_object->labels->name ) ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<p><?php printf( esc_html_x( 'The current post ID is %d', 'paragraph', $text_domain ), intval( $post_id ) ); ?></p>

			<?php
			$tag_slug        = 'foo';
			$cat_slug        = 'baz';
			$meta_value      = 'Accepted';
			$post_date_limit = strtotime( 'last week' ); //short timeframe if site published large number of posts per week

			$query = new WP_Query(
					[
							'post_type'              => [ 'post', 'page' ],
							'post_status'            => 'any',
						//Sure we want to show private, draft, ect?
							'posts_per_page'         => 6,
						//If using random example below set to larger data set such as 1000. Increased by one (to 6) due to PHP post_id filtering
							'date_query'             => [
									[
											'hour'    => 9,
											'compare' => '>=',
									],
									[
											'hour'    => 17,
											'compare' => '<=',
									],
								/**
								 * Uncomment if site has a large post count say over 100k
								 * Will help to force mysql to use type_status_date index,
								 * limiting subset of data used in taxonomy query, post order/limit
								 *
								 * @see https://docs.wpvip.com/how-tos/optimize-core-queries-at-scale/
								 */
								//'after' => [
								//		'year' 	=> date( 'Y', $post_date_limit ),
								//		'month' => date( 'n', $post_date_limit ),
								//		'day' 	=> date( 'j', $post_date_limit ),
								//]
							],
							'tag'                    => $tag_slug,

						//Optimize query as not main loop query
							'no_found_rows'          => true,
						// counts posts, remove if pagination required
							'update_post_term_cache' => false,
						// grabs terms, remove if terms required (category, tag...)
							'update_post_meta_cache' => false,
						// grabs post meta, remove if post meta required
					]
			);

			if ( $query->have_posts() ) :
				?>

				<h2><?php echo esc_html_x( 'Any 5 posts with the tag of foo and the category of baz', 'heading', $text_domain ); ?></h2>
				<ul>
					<?php foreach ( $query->posts as $post_obj ) :
						if ( ! $post_obj instanceof WP_Post ) {
							continue;
						}

						//Filter by post_id
						if ( $post_obj->ID === $post_id ) {
							//Skip post
							continue;
						}

						//Filter by category, uses cached taxonomy data from wp_query
						if ( false === $this->has_cat_term( $post_obj->ID, $cat_slug ) ) {
							//Skip post
							continue;
						}

						//Filter by meta value
						if ( false === $this->has_meta_value( $post_obj->ID, $meta_value ) ) {
							//Skip post
							continue;
						}
						?>
						<li><?php echo esc_html( $post_obj->post_title ); ?></li>
					<?php endforeach; ?>
				</ul>

				<?php
				/**
				 * Random post selection example
				 * NOTE set posts_per_page to a higher value to improve random selection
				 * or limit data set by some other parameter
				 */

				//Going to cache this one, although PHP is quick, random selection may benefit from object cache
				$cache  = [
				    'key'   => "random-posts-{$post_id}-{$cat_slug}-{$meta_value}",
				    'group' => 'xwp-code-challenge',
				];

				//Try and get cache
				$random_posts = wp_cache_get( $cache['key'], $cache['group'], false );

				//Found cached result?
				if ( false === $random_posts ) {
					$random_posts = $this->get_random_posts( $query->posts, [
							'cat_term' => $cat_slug,
							'meta_val' => $meta_value,
							'post_id'  => $post_id,
					] );

					//Set new cache value
					wp_cache_set( $cache['key'], $random_posts, $cache['group'], 5 * MINUTE_IN_SECONDS );
				}


				?>
				<ul>
					<?php foreach ( $random_posts as $post_obj ) : ?>
						<li><?php echo esc_html( $post_obj->post_title ); ?></li>
					<?php endforeach; ?>
				</ul>

			<?php endif; ?>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * get_post_type_count
	 *
	 * Helper to get post type count for supplied post type.
	 * if $post_status param is not provided, total count for ALL post status
	 * will be returned
	 *
	 * @param string $post_type_slug
	 * @param string $post_status
	 *
	 * @return int $total_count
	 * @access private
	 * @var array $post_counts
	 * @var int $total_count
	 * @author Ben Moody
	 */
	private function get_post_type_count( $post_type_slug, $post_status = 'any' ) {

		$total_count = 0;
		$post_counts = wp_count_posts( $post_type_slug );

		if ( ( $post_status !== 'any' ) && isset( $post_counts[ $post_status ] ) ) {
			return $post_counts[ $post_status ];
		}

		if ( ( $post_status === 'any' ) ) {
			foreach ( $post_counts as $status => $count ) {
				$total_count = $total_count + intval( $count );
			}
		}

		return $total_count;
	}

	/**
	 * has_cat_term
	 *
	 * Helper to see if provided post has category term attached
	 *
	 * @param int $post_id
	 * @param string $term_slug
	 *
	 * @return bool
	 * @access private
	 * @var WP_Error|bool $cat_result
	 * @author Ben Moody
	 */
	private function has_cat_term( $post_id, $term_slug ) {

		$cat_result = is_object_in_term( $post_id, 'category', $term_slug );

		if ( is_wp_error( $cat_result ) || ( false === $cat_result ) ) {
			return false;
		}

		return true;
	}

	/**
	 * has_meta_value
	 *
	 * Helper to see if a post has meta field with provided value
	 *
	 * @param int $post_id
	 * @param mixed $meta_value
	 *
	 * @return bool
	 * @access private
	 * @var WP_Error|array $post_meta
	 * @author Ben Moody
	 */
	private function has_meta_value( $post_id, $meta_value ) {

		$post_meta = get_post_meta( $post_id );

		if ( ! is_array( $post_meta ) ) {
			return false;
		}

		//we only have the value not the key so going to have to search all meta data :(
		foreach ( $post_meta as $meta_key => $meta_val ) {
			if( !isset($meta_val[0]) ) {
				continue;
			}

			if ( $meta_val[0] === $meta_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * get_random_posts
	 *
	 * Helper to keep grabbing random (filtered) posts until we have the data
	 * set size requested by $total_posts
	 *
	 * NOTE, to prevent a loop the method will retry random selection 1000
	 * times before giving up and returning the posts we found
	 *
	 * @param array $posts
	 * @param array $filters
	 * @param int $total_posts
	 *
	 * @return array $found_posts
	 * @access private
	 * @var WP_Error|WP_Post $random_post
	 * @var array $found_posts
	 * @author Ben Moody
	 */
	private function get_random_posts( $posts, $filters = [], $total_posts = 5 ) {

		$filters = wp_parse_args( $filters, [
				'cat_term' => '',
				'meta_val' => '',
				'post_id'  => 0,
		] );

		$found_posts          = [];
		$this->random_counter = 0; //reset counter
		$attempts_limit       = 1000;
		while ( count( $found_posts ) < $total_posts ) {

			//Have we hit the attempts limit, prevents infinite search loop
			if ( $this->random_counter === $attempts_limit ) {
				break;
			}

			//keep track of how many random search attempts we've made
			$this->random_counter ++;

			$random_post = $this->get_random_post( $posts, $filters );

			if ( ! is_wp_error( $random_post ) && !isset( $found_posts[ $random_post->ID ] ) ) {
				$found_posts[ $random_post->ID ] = $random_post;
			}

		}

		return $found_posts;
	}

	/**
	 * get_random_post
	 *
	 * As we are filtering posts using PHP for performance reasons we must
	 * check each random post against the filtering params and reselect if a
	 * post doesn't match
	 *
	 * @param array $posts
	 * @param array $filters
	 *
	 * @return WP_Error|WP_Post
	 * @access private
	 * @var WP_Post $rando_pluck
	 * @author Ben Moody
	 */
	private function get_random_post( $posts, $filters ) {

		$rando_pluck_key = array_rand( $posts, 1 );

		$rando_pluck = $posts[ $rando_pluck_key ];

		//Filter by post_id
		if ( $rando_pluck->ID === $filters['post_id'] ) {
			//No good try again
			return new WP_Error(
					'get_random_post',
					'skip post value'
			);
		}

		//Filter by category, uses cached taxonomy data from wp_query
		if ( false === $this->has_cat_term( $rando_pluck->ID, $filters['cat_term'] ) ) {
			//No good try again
			return new WP_Error(
					'get_random_post',
					'category term skip'
			);
		}

		//Filter by meta value
		if ( false === $this->has_meta_value( $rando_pluck->ID, $filters['meta_val'] ) ) {
			//No good try again
			return new WP_Error(
					'get_random_post',
					'meta value skip'
			);
		}

		return $rando_pluck;
	}
}
