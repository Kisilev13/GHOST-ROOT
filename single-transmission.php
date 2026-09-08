<?php
/**
 * Single transmission — /transmission/{slug}/
 *
 * @package GhostRoot\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="gr-section">
		<a class="gr-back" href="<?php echo esc_url( home_url( '/signal/' ) ); ?>">&larr; SIGNAL MAP</a>
		<?php gr_page_head( 'INTERCEPTED TRANSMISSION / DECODING', get_the_title() ); ?>
		<div class="gr-document-layout">
			<div class="gr-document gr-prose">
				<p class="gr-document-stamp">RECOVERED SIGNAL / PARTIAL DECODE</p>
				<pre class="gr-transmission-body"><?php echo esc_html( wp_strip_all_tags( get_the_content() ) ); ?></pre>
			</div>
			<dl class="gr-data-list">
				<div><dt>CHANNEL</dt><dd><?php echo esc_html( get_the_title() ); ?></dd></div>
				<div><dt>STATUS</dt><dd>DECODING</dd></div>
				<div><dt>ORIGIN</dt><dd>[REDACTED]</dd></div>
				<div><dt>RECOVERED</dt><dd><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></dd></div>
			</dl>
		</div>
		<nav class="gr-adjacent" aria-label="Transmission navigation">
			<?php previous_post_link( '%link', '&larr; %title' ); ?>
			<?php next_post_link( '%link', '%title &rarr;' ); ?>
		</nav>
		<p class="gr-caption gr-muted">Fictional transmission / GHOST//ROOT narrative archive.</p>
	</article>
<?php
endwhile;
get_footer();
