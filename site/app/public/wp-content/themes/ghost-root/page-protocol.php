<?php /* Template Name: Protocol */ defined('ABSPATH') || exit; get_header(); ?>
<section class="gr-section"><?php gr_page_head('ROOT / COLLECTION PROTOCOL','READ BEFORE INITIALIZING.','The collection model, authority boundaries and facts that can be verified.'); ?>
<div class="gr-protocol-layout"><aside class="gr-protocol-index"><a href="#supply">01 / SUPPLY</a><a href="#mutation">02 / MUTATION</a><a href="#storage">03 / METADATA</a><a href="#authority">04 / AUTHORITY</a></aside><div class="gr-protocol-content">
<section id="supply"><p class="gr-eyebrow">01 / COLLECTION PARAMETERS</p><h2>3,333 IDENTITIES.</h2>
<?php if(class_exists('\GhostRoot\Support\Vocab')): $bands = \GhostRoot\Support\Vocab::RARITY_BANDS; $max = max($bands); ?>
<div class="gr-rarity-chart" role="img" aria-label="Rarity band distribution across 3,333 identities">
<?php foreach($bands as $band => $count): ?>
    <div class="gr-rarity-row" data-band="<?php echo esc_attr($band); ?>">
        <span class="gr-rarity-name"><?php echo esc_html($band); ?></span>
        <span class="gr-rarity-bar"><i style="width:<?php echo esc_attr(max(1.4, round($count / $max * 100, 2))); ?>%"></i></span>
        <span class="gr-rarity-count"><?php echo esc_html(number_format($count)); ?></span>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<dl class="gr-data-list gr-protocol-params"><div><dt>NETWORK</dt><dd>SOLANA</dd></div><div><dt>NFT STANDARD</dt><dd>METAPLEX CORE</dd></div><div><dt>MINT ARCHITECTURE</dt><dd>CORE CANDY MACHINE</dd></div></dl>
<p>These are collection design targets. The identity archive contains 3,333 recovered records. Minted supply is not reported before deployment.</p></section>
<section id="mutation"><p class="gr-eyebrow">02 / STATE TRANSITIONS</p><h2>THEY DO NOT STAY THE SAME.</h2>
<ol class="gr-state-diagram"><li>DORMANT</li><li>ACTIVE</li><li>COMPROMISED</li><li>ROOTED</li></ol>
<p>States advance one step at a time. Each transition records the previous state, next state, time, trigger, reference and actor. Public puzzle answers cannot change identity state.</p><p>Local narrative state is separate from on-chain metadata. No blockchain mutation is performed by this website.</p></section>
<section id="storage"><p class="gr-eyebrow">03 / METADATA & STORAGE</p><h2>EVERY FRAGMENT HAS A SOURCE.</h2><p>Each identity has a stable numeric ID, typed traits and a fingerprint. JSON is validated before import. The current archive is a pre-mint dataset and contains example storage references.</p><p>Current identity imagery consists of procedural reconstruction previews. Final collection artwork has not yet been committed.</p><p>Permanent artwork and metadata storage, final royalties and reserve allocations will be published before mint. They are not finalized or implied by this preview.</p><a class="gr-text-link" href="<?php echo esc_url(home_url('/verify/')); ?>">CHECK PUBLISHED HASHES ↗</a></section>
<section id="authority"><p class="gr-eyebrow">04 / AUTHORITY BOUNDARIES</p><h2>ACCESS IS NOT OWNERSHIP.</h2><p>WordPress manages the archive and presentation. Wallet signing happens in the visitor’s wallet through a separately reviewed integration. WordPress holds no private keys, seed phrases or signing authority.</p><p>Collection authority, treasury, multisig, update policy and Candy Guards require deployment and independent verification. Initialization remains closed until that work is complete.</p></section>
</div></div></section><?php get_footer(); ?>
