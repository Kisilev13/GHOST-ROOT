<?php /* Template Name: Verify */ defined('ABSPATH') || exit; get_header(); ?>
<section class="gr-section gr-section--fill"><?php gr_page_head('SOURCE VERIFICATION / PUBLIC RECORD','TRUST NOTHING.','Check the source. Verify the addresses. Initialization remains closed until deployment is complete.'); ?>
<div class="gr-verification"><?php if(class_exists('\GhostRoot\Settings\Config')) foreach(\GhostRoot\Settings\Config::verification() as $key=>$value): ?><div><span class="gr-eyebrow"><?php echo esc_html(gr_label($key)); ?></span><code><?php echo esc_html($value ?: 'NOT YET DEPLOYED'); ?></code><?php if($value): ?><button type="button" class="gr-copy" data-copy="<?php echo esc_attr($value); ?>" aria-label="<?php echo esc_attr('Copy '.gr_label($key)); ?>">COPY ↗</button><?php endif; ?></div><?php endforeach; ?></div><p class="gr-mono" data-copy-status role="status"></p>
<div class="gr-notice"><strong>BLOCKCHAIN STATUS: NOT YET DEPLOYED</strong><p>Collection, Candy Machine, treasury, permanent storage and authorities have not been verified. No production mint is available.</p></div>
<?php
/* --- Marketplace verification (OpenSea, secondary market) --------------- */
if (class_exists('\GhostRoot\OpenSea\Verification')):
    $osv = \GhostRoot\OpenSea\Verification::report();
    $state_copy = [
        'AWAITING_SOLANA_COLLECTION_DEPLOYMENT' => 'AWAITING DEPLOYMENT',
        'NOT_INDEXED' => 'NOT INDEXED',
        'PENDING'     => 'PENDING',
        'PARTIAL'     => 'PARTIAL',
        'VERIFIED'    => 'VERIFIED',
        'MISMATCH'    => 'MISMATCH',
        'ERROR'       => 'UNAVAILABLE',
    ];
    $label = $state_copy[$osv['state']] ?? $osv['state'];
?>
<div class="gr-verification gr-verification--market" data-market-state="<?php echo esc_attr(strtolower($osv['state'])); ?>">
    <div><span class="gr-eyebrow">MARKETPLACE INDEX</span><code><?php echo esc_html($label); ?></code></div>
    <div><span class="gr-eyebrow">MARKET</span><code>OPENSEA</code></div>
    <div><span class="gr-eyebrow">CHAIN</span><code><?php echo esc_html(strtoupper($osv['chain'])); ?></code></div>
    <div><span class="gr-eyebrow">OPENSEA SLUG</span><code><?php echo esc_html($osv['slug'] ?: 'NOT RESOLVED'); ?></code></div>
    <div><span class="gr-eyebrow">INDEXED ITEMS</span><code><?php echo esc_html(null !== $osv['indexed_items'] ? number_format_i18n($osv['indexed_items']) : '—'); ?></code></div>
    <?php foreach ($osv['checks'] as $c): ?>
    <div><span class="gr-eyebrow"><?php echo esc_html(gr_label($c['field'])); ?></span><code><?php echo esc_html($c['ok'] ? 'MATCH' : 'MISMATCH'); ?> — <?php echo esc_html($c['actual']); ?></code></div>
    <?php endforeach; ?>
</div>
<?php if (!empty($osv['notes'])): ?>
<div class="gr-notice gr-notice--<?php echo 'MISMATCH' === $osv['state'] ? 'alert' : 'info'; ?>">
    <strong>MARKETPLACE VERIFICATION: <?php echo esc_html($label); ?></strong>
    <?php foreach ($osv['notes'] as $n): ?><p><?php echo esc_html($n); ?></p><?php endforeach; ?>
    <?php if (in_array($osv['state'], ['VERIFIED','PARTIAL'], true) && !empty($osv['opensea_url'])): ?>
    <p><a class="gr-text-link" href="<?php echo esc_url($osv['opensea_url']); ?>" target="_blank" rel="noopener nofollow">VIEW ON OPENSEA ↗</a></p>
    <?php endif; ?>
</div>
<?php endif; endif; ?>
<?php echo do_shortcode('[ghost_root_marketplace]'); ?>
</section><?php get_footer(); ?>
