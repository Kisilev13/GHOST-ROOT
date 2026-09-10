<?php defined('ABSPATH') || exit; get_header(); ?>
<section class="gr-section"><?php gr_page_head('RECOVERED FILESYSTEM / ARCHIVE', 'SOME THINGS SURVIVED.', 'Fragments, dossiers and transmissions. Everything the network failed to erase.'); ?>
<?php gr_filters(['type'=>['TRANSMISSION','DOSSIER','INCIDENT','SYSTEM LOG','IMAGE','PROTOCOL','REDACTED FILE'],'node'=>['NODE-00','NODE-01','NODE-12','NODE-14','NODE-22','NODE-30','NODE-31'],'access'=>['USER','OPERATOR','ADMIN','SYSTEM','ROOT'],'status'=>['SEALED','DECODING','ARCHIVED','OPEN','REDACTED','ACTIVE']],'/archive/'); ?>
<div class="gr-archive-grid"><?php
while(have_posts()): the_post();
    $terms   = wp_get_post_terms(get_the_ID(), 'archive_type', ['fields' => 'names']);
    $types   = is_wp_error($terms) ? [] : $terms;
    $type    = $types[0] ?? '';
    $markers = ['DOSSIER'=>'▰','TRANSMISSION'=>'≋','SYSTEM LOG'=>'_','INCIDENT'=>'×','REDACTED FILE'=>'█','PROTOCOL'=>'//','IMAGE'=>'□'];
    ?><article class="gr-archive-record" data-type="<?php echo esc_attr($type); ?>"><a href="<?php the_permalink(); ?>">
        <p class="gr-eyebrow"><span class="gr-record-type"><i class="gr-record-marker" aria-hidden="true"><?php echo esc_html($markers[$type] ?? '·'); ?></i><?php echo esc_html(implode(' / ', $types)); ?></span><span aria-hidden="true">↗</span></p>
        <h2><?php the_title(); ?></h2>
        <p class="gr-archive-excerpt"><?php echo esc_html(wp_trim_words(get_the_content(), 24)); ?></p>
        <div class="gr-mono gr-record-foot"><span><?php echo esc_html(gr_meta('record_node') ?: 'NODE-??'); ?></span><span><?php echo esc_html(gr_meta('record_status') ?: 'ARCHIVED'); ?></span></div>
    </a></article><?php
endwhile; ?></div>
<?php global $wp_query; if(!$wp_query->found_posts) echo '<p class="gr-empty">NO RECORDS RECOVERED FOR THESE FILTERS.</p>'; gr_pagination(); ?></section><?php get_footer(); ?>
