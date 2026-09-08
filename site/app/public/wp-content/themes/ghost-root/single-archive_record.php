<?php defined('ABSPATH') || exit; get_header(); while(have_posts()): the_post(); ?>
<article class="gr-section"><a class="gr-back" href="<?php echo esc_url(home_url('/archive/')); ?>">← RECOVERED FILESYSTEM</a><?php gr_page_head('CLASSIFIED DOCUMENT / '.gr_meta('record_node'),get_the_title()); ?>
<div class="gr-document-layout"><div class="gr-document gr-prose"><p class="gr-document-stamp"><?php echo esc_html(gr_meta('record_access').' / '.gr_meta('record_status')); ?></p><?php the_content(); ?></div><dl class="gr-data-list"><?php foreach(['record_node','record_access','record_status','record_date'] as $key): ?><div><dt><?php echo esc_html(gr_label($key)); ?></dt><dd><?php echo esc_html(gr_meta($key)); ?></dd></div><?php endforeach; ?></dl></div></article>
<?php endwhile; get_footer(); ?>
