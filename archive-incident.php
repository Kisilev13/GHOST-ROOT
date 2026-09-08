<?php defined('ABSPATH') || exit; get_header(); ?>
<section class="gr-section"><?php gr_page_head('ROOT / INCIDENT REGISTER', 'THE NETWORK REMEMBERS.', 'Unscheduled activity. Unexplained access. Records that refuse to disappear.'); ?>
<?php echo do_shortcode('[ghost_root_incidents limit="50"]'); ?>
<p class="gr-muted gr-caption">Incident records are fictional narrative artifacts of the ROOT network.</p></section>
<?php get_footer(); ?>
