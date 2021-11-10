<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

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
		if( !is_array($post_types) ) {
			return '';
		}

		$post_id = get_the_ID();
		//Shouldn't need this but just in case
		if( false === $post_id ) {
			return '';
		}

		$class_name = '';
		//Shouldn't need this as default block attr but just in case
		if( isset($attributes['className']) ) {
			$class_name = $attributes['className'];
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2><?php echo esc_html_x( 'Post Counts', 'heading', $text_domain ); ?></h2>

			<ul>
				<?php
				foreach ( $post_types as $post_type_slug => $post_type_object ) :
					$post_count = wp_count_posts( $post_type_slug );
					?>
					<li>
						<?php
						//Could use _n() function but probably would be messy due to both %d and %s in string
						if( $post_count === 1 ): ?>
							<?php printf( esc_html_x( 'There is only %d %s.', 'paragraph', $text_domain ), intval( $post_count ), esc_html( $post_type_object->labels->singular_name ) ); ?>
						<?php else: ?>
							<?php printf( esc_html_x( 'There are %d %s.', 'paragraph', $text_domain ), intval( $post_count ), esc_html( $post_type_object->labels->name ) ); ?>
						<?php endif; ?>

					</li>
				<?php endforeach; ?>
			</ul>

			<p><?php printf( esc_html_x( 'The current post ID is %d', 'paragraph', $text_domain ), intval( $post_id ) ); ?></p>

			<?php
			$query = new WP_Query(
					[
							'post_type'     => [ 'post', 'page' ],
							'post_status'   => 'any',
							'date_query'    => [
									[
											'hour'    => 9,
											'compare' => '>=',
									],
									[
											'hour'    => 17,
											'compare' => '<=',
									],
							],
							'tag'           => 'foo',
							'category_name' => 'baz',
							'post__not_in'  => [ $post_id ],
							'meta_value'    => 'Accepted',
					]
			);

			if ( $query->found_posts ) :
				?>
				<h2><?php echo esc_html_x( 'Any 5 posts with the tag of foo and the category of baz', 'heading', $text_domain ); ?></h2>
				<ul>
					<?php foreach ( array_slice( $query->posts, 0, 5 ) as $post ) : ?>
						<li><?php echo esc_html( $post->post_title ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

		</div>
		<?php

		return ob_get_clean();
	}
}
