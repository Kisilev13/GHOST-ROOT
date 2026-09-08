<?php defined('ABSPATH') || exit; ?>
</main>
<footer class="gr-footer">
    <div><a href="<?php echo esc_url(home_url('/')); ?>" class="gr-logo">GHOST//ROOT</a><p>YOU WERE NEVER HERE.</p></div>
    <div class="gr-footer-links"><a href="<?php echo esc_url(home_url('/protocol/')); ?>">PROTOCOL</a><a href="<?php echo esc_url(home_url('/verify/')); ?>">VERIFY THE SOURCE ↗</a><?php
        if (class_exists('\GhostRoot\OpenSea\Verification')) {
            $gr_osv = \GhostRoot\OpenSea\Verification::quick(); // cached-only, never blocks
            if (in_array($gr_osv['state'], ['VERIFIED', 'PARTIAL'], true) && !empty($gr_osv['opensea_url'])) {
                echo '<a href="' . esc_url($gr_osv['opensea_url']) . '" target="_blank" rel="noopener nofollow">OPENSEA ↗</a>';
            }
        }
    ?></div>
    <div class="gr-footer-state"><span>ENVIRONMENT / PRE-MINT</span><span>BLOCKCHAIN / NOT YET DEPLOYED</span><?php
        if (isset($gr_osv)) {
            $gr_ms = ['AWAITING_SOLANA_COLLECTION_DEPLOYMENT' => 'AWAITING DEPLOYMENT', 'NOT_INDEXED' => 'NOT INDEXED', 'VERIFIED' => 'ACTIVE', 'PARTIAL' => 'INDEXING', 'MISMATCH' => 'UNVERIFIED'][$gr_osv['state']] ?? 'PENDING';
            echo '<span>SECONDARY MARKET / ' . esc_html($gr_ms) . '</span>';
        }
    ?></div>
</footer>
<?php wp_footer(); ?>
</body></html>
