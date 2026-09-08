<?php /* Template Name: Terminal */ defined('ABSPATH') || exit; get_header(); ?>
<section class="gr-section gr-console-page gr-section--fill"><?php gr_page_head('DIRECT CONNECTION / GUEST ACCESS','THE SYSTEM IS LISTENING.','A recovered interface. An open channel. Start with help.'); ?><?php echo do_shortcode('[ghost_root_terminal]'); ?><div class="gr-command-guide"><span>TRY A COMMAND</span><code>status</code><code>identity 1842</code><code>incident 31</code><code>root</code></div></section>
<?php get_footer(); ?>
