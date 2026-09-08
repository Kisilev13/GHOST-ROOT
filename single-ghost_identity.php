<?php defined('ABSPATH') || exit; get_header(); while (have_posts()): the_post(); $genesis = gr_meta('rarity_band') === 'GENESIS'; ?>
<article class="gr-section gr-dossier <?php echo $genesis ? 'gr-genesis-dossier' : ''; ?>">
<a class="gr-back" href="<?php echo esc_url(home_url('/identities/')); ?>">← IDENTITY INDEX</a>
<div class="gr-dossier-layout"><div class="gr-dossier-art"><?php if(class_exists('\GhostRoot\Frontend\Shortcodes')) echo \GhostRoot\Frontend\Shortcodes::portrait(get_the_ID(), true); ?><p class="gr-mono">PROCEDURAL RECONSTRUCTION / FINAL ART PENDING</p></div>
<div><p class="gr-eyebrow"><?php echo $genesis ? 'GENESIS / ORIGIN RECORD / ROOT ACCESS' : 'RECOVERED IDENTITY / FORENSIC DOSSIER'; ?></p><h1><?php the_title(); ?></h1><?php if($genesis): ?><p class="gr-codename"><?php echo esc_html(gr_meta('codename') ?: 'ORIGIN UNKNOWN'); ?></p><?php endif; ?>
<dl class="gr-data-list"><?php foreach(['entity','face','eyes','mask','implant','corruption','access','signal','state','rarity_band','fingerprint','recovery_node','last_signal'] as $key): ?><div><dt><?php echo esc_html(gr_label($key)); ?></dt><dd><?php echo esc_html(gr_meta($key) ?: 'UNKNOWN'); ?></dd></div><?php endforeach; ?></dl>
<?php echo do_shortcode('[ghost_root_market_state ghost_id="' . esc_attr((string) gr_meta('ghost_id')) . '"]'); ?>
</div></div>
<?php if($genesis): ?><div class="gr-genesis-lore"><?php foreach(['origin','known_events','unknowns','visual_signature','quote','future_role'] as $key): ?><section><p class="gr-eyebrow"><?php echo esc_html(gr_label($key)); ?></p><div class="gr-prose"><?php echo wp_kses_post(wpautop(gr_meta($key) ?: 'RECORD NOT RECOVERED.')); ?></div></section><?php endforeach; ?></div><?php endif; ?>
<div class="gr-dossier-sections"><section><p class="gr-eyebrow">RECOVERY NOTE</p><div class="gr-prose"><?php the_content(); ?></div><h2>STATE HISTORY</h2><ol class="gr-history"><?php if(class_exists('\GhostRoot\State\StateManager')) foreach(\GhostRoot\State\StateManager::history(get_the_ID()) as $entry): ?><li><strong><?php echo esc_html(($entry['from'] ?: 'UNRECOVERED') . ' → ' . $entry['to']); ?></strong><span><?php echo esc_html($entry['timestamp'] . ' / ' . $entry['trigger']); ?></span></li><?php endforeach; ?></ol></section><section><p class="gr-eyebrow">ASSOCIATED INCIDENTS / RECOVERED FRAGMENTS</p>
<?php
$node = gr_meta('recovery_node');
$related = new WP_Query(['post_type'=>['incident','archive_record'],'post_status'=>'publish','posts_per_page'=>6,'no_found_rows'=>true,'meta_query'=>['relation'=>'OR',['key'=>'affected_nodes','value'=>$node,'compare'=>'LIKE'],['key'=>'record_node','value'=>$node]]]);
if(!$related->posts) echo '<p class="gr-muted">No associated records recovered for this node.</p>';
foreach($related->posts as $record) echo '<a class="gr-related-link" href="'.esc_url(get_permalink($record)).'">'.esc_html($record->post_title).' ↗</a>';
?>
</section></div><nav class="gr-adjacent" aria-label="Adjacent identities"><?php previous_post_link('%link','← %title'); next_post_link('%link','%title →'); ?></nav>
</article><?php endwhile; get_footer(); ?>
