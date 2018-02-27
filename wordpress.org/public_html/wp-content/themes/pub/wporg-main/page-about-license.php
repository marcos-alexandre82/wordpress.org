<?php
/**
 * Template Name: License
 *
 * Page template for displaying the License page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

add_filter( 'jetpack_open_graph_tags', function( $tags ) {
	$tags['og:title']            = _esc_html__( 'The GNU Public License', 'wporg' );
	$tags['og:description']      = _esc_html__( 'WordPress is an open source software project, and a fierce believer in the values of the open web. WordPress uses the GNU Public License, which provides a platform for technical expansion and encourages adaptation and innovation. Learn more about this license, and discover what can and cannot be done under it.', 'wporg' );
	$tags['twitter:text:title']  = $tags['og:title'];
	$tags['twitter:description'] = $tags['og:description'];

	return $tags;
} );

get_header();
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _esc_html_e( 'GNU Public License', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
						<?php
						/* translators: 1: Link to Free Software Foundation; 2: Link to GPL text */
						printf( wp_kses_post( ___( 'The license under which the WordPress software is released is the GPLv2 (or later) from the <a href="%1$s">Free Software Foundation</a>. A copy of the license is included with every copy of WordPress, but you can also <a href="%2$s">read the text of the license here</a>.', 'wporg' ) ), esc_url( 'https://www.fsf.org/' ), esc_url( 'https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html' ) );
						?>
					</p>

					<p>
						<?php
						/* translators: 1: Link to Drupal; 2: Link to Drupal's licensing faq */
						printf( wp_kses_post( ___( 'Part of this license outlines requirements for derivative works, such as plugins or themes. Derivatives of WordPress code inherit the GPL license. <a href="%1$s">Drupal</a>, which has the same GPL license as WordPress, has an excellent page on <a href="%2$s">licensing as it applies to themes and modules</a> (their word for plugins).', 'wporg' ) ), esc_url( 'https://drupal.org/' ), esc_url( 'https://drupal.org/licensing/faq/' ) );
						?>
					</p>

					<p>
						<?php
						/* translators: 1: Link to Serendipity; 2: Link to Habari */
						printf( wp_kses_post( ___( 'There is some legal grey area regarding what is considered a derivative work, but we feel strongly that plugins and themes are derivative work and thus inherit the GPL license. If you disagree, you might want to consider a non-GPL platform such as <a href="%1$s">Serendipity</a> (BSD license) or <a href="%2$s">Habari</a> (Apache license) instead.', 'wporg' ) ), esc_url( 'https://www.s9y.org/' ), esc_url( 'http://habariproject.org/en/' ) );
						?>
					</p>
				</section>
			</div><!-- .entry-content -->

			<?php
			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					esc_html__( 'Edit %s', 'wporg' ),
					the_title( '<span class="screen-reader-text">"', '"</span>', false )
				),
				'<footer class="entry-footer"><span class="edit-link">',
				'</span></footer><!-- .entry-footer -->'
			);
			?>
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();