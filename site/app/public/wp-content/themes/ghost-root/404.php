<?php defined('ABSPATH') || exit; get_header(); ?>
<section class="gr-section gr-error-page gr-section--fill"><?php gr_page_head('ERROR 404 / SIGNAL LOST','YOU WERE NEVER HERE.','This record is missing, unreleased or never existed.'); ?><a class="gr-button" href="<?php echo esc_url(home_url('/archive/')); ?>">RETURN TO THE ARCHIVE →</a></section><?php get_footer(); ?>
