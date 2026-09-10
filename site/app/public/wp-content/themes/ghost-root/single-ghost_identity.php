<?php defined('ABSPATH') || exit; get_header(); while (have_posts()): the_post(); $genesis = gr_meta('rarity_band') === 'GENESIS'; ?>
<article data-rarity="<?php echo esc_attr(gr_meta('rarity_band')); ?>" class="gr-section gr-dossier <?php echo $genesis ? 'gr-genesis-dossier' : ''; ?>">
<a class="gr-back" href="<?php echo esc_url(home_url('/identities/')); ?>">← IDENTITY INDEX</a>
<?php
$gr_designation = $genesis ? (gr_meta('codename') ?: 'ORIGIN UNKNOWN') : (gr_meta('archetype') ?: '');
$gr_rarity = gr_meta('rarity_band') ?: 'STANDARD';
$gr_state  = gr_meta('state') ?: 'UNKNOWN';
?>
<div class="gr-dossier-layout"><div class="gr-dossier-art"><?php if(class_exists('\GhostRoot\Frontend\Shortcodes')) echo \GhostRoot\Frontend\Shortcodes::portrait(get_the_ID(), true); ?><p class="gr-mono">IDENTITY RECONSTRUCTION // V0</p></div>
<div class="gr-dossier-head"><p class="gr-eyebrow"><?php echo $genesis ? 'GENESIS / ORIGIN RECORD' : 'RECOVERED IDENTITY / FORENSIC DOSSIER'; ?></p><h1><?php the_title(); ?></h1><?php if($gr_designation): ?><p class="gr-codename"><?php echo esc_html($gr_designation); ?></p><?php endif; ?>
<p class="gr-dossier-tags"><span><?php echo esc_html($gr_rarity); ?></span><span aria-hidden="true">//</span><span><?php echo esc_html(gr_meta('access') ?: 'UNKNOWN'); ?></span></p>
<dl class="gr-dossier-summary" data-state="<?php echo esc_attr($gr_state); ?>"><div><dt>SIGNAL</dt><dd><?php echo esc_html(gr_meta('signal') ?: 'UNKNOWN'); ?></dd></div><div><dt>STATE</dt><dd><i aria-hidden="true"></i><?php echo esc_html($gr_state); ?></dd></div><div><dt>NODE</dt><dd><?php echo esc_html(gr_meta('recovery_node') ?: 'UNKNOWN'); ?></dd></div></dl></div></div>
<section class="gr-trait-matrix"><p class="gr-eyebrow">BIOMETRIC / TRAIT MATRIX</p>
<dl class="gr-data-list gr-identity-traits"><?php foreach(['entity','face','eyes','mask','implant','corruption','access','background','signal','rarity_band'] as $key): ?><div><dt><?php echo esc_html(gr_label($key === 'rarity_band' ? 'rarity' : $key)); ?></dt><dd><?php echo esc_html(gr_meta($key) ?: 'UNKNOWN'); ?></dd></div><?php endforeach; ?></dl>
<dl class="gr-data-list gr-trait-telemetry-list"><?php foreach(['fingerprint','recovery_node','last_signal'] as $key): ?><div><dt><?php echo esc_html(gr_label($key)); ?></dt><dd><?php echo esc_html(gr_meta($key) ?: 'UNKNOWN'); ?></dd></div><?php endforeach; ?></dl></section>
<?php echo do_shortcode('[ghost_root_market_state ghost_id="' . esc_attr((string) gr_meta('ghost_id')) . '"]'); ?>
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
