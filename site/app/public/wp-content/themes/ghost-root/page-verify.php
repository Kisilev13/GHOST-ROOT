<?php /* Template Name: Verify */
defined('ABSPATH') || exit;
get_header();
$fields = class_exists('\\GhostRoot\\Settings\\Config') ? \GhostRoot\Settings\Config::verification() : [];
$published = count(array_filter($fields));
?>
<section class="gr-section gr-section--fill">
<?php gr_page_head('SOURCE VERIFICATION / PUBLIC RECORD','TRUST NOTHING.','Check the source. Verify the addresses. Initialization remains closed until deployment is complete.'); ?>
<div class="gr-verification-panel">
    <div class="gr-window-bar"><span>DEPLOYMENT STATUS</span><span>NOT YET DEPLOYED</span></div>
    <dl class="gr-verification">
    <?php foreach($fields as $key => $value): $pending = str_ends_with($key, '_hash'); ?>
        <div>
            <dt class="gr-eyebrow"><?php echo esc_html(gr_label($key)); ?></dt>
            <dd><code><?php echo esc_html($value ?: ($pending ? '[ PENDING ]' : '[ LOCKED ]')); ?></code></dd>
            <?php if($value): ?><dd><button type="button" class="gr-copy" data-copy="<?php echo esc_attr($value); ?>" aria-label="<?php echo esc_attr('Copy ' . gr_label($key)); ?>">COPY ↗</button></dd><?php endif; ?>
        </div>
    <?php endforeach; ?>
    </dl>
    <div class="gr-verification-summary"><strong><?php echo $published ? esc_html($published . ' / ' . count($fields) . ' PUBLISHED') : '0 / ' . count($fields) . ' VERIFIED'; ?></strong><span><?php echo $published ? 'Published values require independent verification.' : 'AWAITING DEPLOYMENT EVIDENCE'; ?></span></div>
</div>
<p class="gr-mono" data-copy-status role="status" aria-atomic="true"></p>
<div class="gr-notice"><strong>BLOCKCHAIN STATUS: NOT YET DEPLOYED</strong><p>Collection, Candy Machine, treasury, permanent storage and authorities have not been verified. No production mint is available.</p></div>
<?php if (class_exists('\\GhostRoot\\OpenSea\\Verification')):
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
    $indexed = null !== $osv['indexed_items'] ? number_format_i18n($osv['indexed_items']) : '—';
?>
<div class="gr-verification-panel" data-market-state="<?php echo esc_attr(strtolower($osv['state'])); ?>">
    <div class="gr-window-bar"><span>MARKETPLACE INDEX</span><span><?php echo esc_html($label); ?></span></div>
    <dl class="gr-verification">
        <div><dt class="gr-eyebrow">MARKET</dt><dd><code>OPENSEA</code></dd></div>
        <div><dt class="gr-eyebrow">CHAIN</dt><dd><code><?php echo esc_html(strtoupper((string) $osv['chain'])); ?></code></dd></div>
        <div><dt class="gr-eyebrow">OPENSEA SLUG</dt><dd><code><?php echo esc_html($osv['slug'] ?: 'NOT RESOLVED'); ?></code></dd></div>
        <div><dt class="gr-eyebrow">INDEXED ITEMS</dt><dd><code><?php echo esc_html($indexed); ?></code></dd></div>
        <?php foreach ((array) $osv['checks'] as $c): ?>
        <div>
            <dt class="gr-eyebrow"><?php echo esc_html(gr_label((string) $c['field'])); ?></dt>
            <dd><code><?php echo esc_html(!empty($c['ok']) ? 'MATCH' : 'MISMATCH'); ?> — <?php echo esc_html((string) $c['actual']); ?></code></dd>
        </div>
        <?php endforeach; ?>
    </dl>
    <div class="gr-verification-summary"><strong>MARKET: OPENSEA</strong><span><?php echo esc_html($label); ?></span></div>
</div>
<?php if (!empty($osv['notes'])): ?>
<div class="gr-notice">
    <strong>MARKETPLACE VERIFICATION: <?php echo esc_html($label); ?></strong>
    <?php foreach ((array) $osv['notes'] as $n): ?><p><?php echo esc_html((string) $n); ?></p><?php endforeach; ?>
    <?php if (in_array($osv['state'], ['VERIFIED','PARTIAL'], true) && !empty($osv['opensea_url'])): ?>
    <p><a class="gr-text-link" href="<?php echo esc_url($osv['opensea_url']); ?>" target="_blank" rel="noopener nofollow">VIEW ON OPENSEA <span aria-hidden="true">↗</span></a></p>
    <?php endif; ?>
</div>
<?php endif; endif; ?>
<?php echo do_shortcode('[ghost_root_marketplace]'); ?>
</section><?php get_footer(); ?>
