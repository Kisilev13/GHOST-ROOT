# GHOST//ROOT — Two-Tier Candy Machine Launch System

**State: `PREPARED_NOT_DEPLOYED`.** Nothing here deploys, broadcasts, sets dates, mints, or spends SOL. Every path is dry-run and aborts while launch blockers remain.

## What this is

A Metaplex Core **Candy Machine + Candy Guard** launch prepared for the existing collection, using **two price/access tiers** (Candy Guard *groups*) instead of one flat price. Both tiers draw from the **same 3,332-item inventory** (IDs `0002–3333`; `GHOST//0001` is the already-minted canary and is excluded). Groups change **price and access**, never total supply.

| Tier | Label | Price | Lamports | Shared lifetime cap | Dates | Allowlist |
| --- | --- | --- | --- | ---: | --- | --- |
| Early / allowlist | `early` | 0.10 SOL | `100000000` | 5 | `null` (NOT_SCHEDULED) | `PENDING_ALLOWLIST` |
| Public | `public` | 0.15 SOL | `150000000` | 5 | `null` (NOT_SCHEDULED) | n/a |

Treasury (payment destination for both) and authority: `CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R`.
Collection: `ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p`. Royalty: 500 bps.

## Buyer-pays model

The connected buyer is the **minter**, the **transaction payer**, the payer of **Core asset creation/rent**, and the payer of **network fees**. The treasury only *receives* the `solPayment`. The creator/authority never sponsors ordinary buyer mint costs.

## Shared lifetime wallet cap (inherited)

**Five total mints per wallet across the entire launch.** The Mint Limit **id 1, limit 5** is defined **once, in the DEFAULT guard set**; `early` and `public` define no mintLimit and **inherit** it. The MintCounter PDA seeds — `['mint_limit', id, user, candyGuard, candyMachine]`, source-verified from installed `mpl-core-candy-machine@0.3.0` — exclude the group label, so both phases resolve to the **same** wallet counter for the fixed Candy Guard + Candy Machine. After N early mints, at most 5 − N public mints remain. Transfers do not restore allowance. The counter is keyed by the mint payer/user.

The default set holds **only** the mintLimit — no solPayment, no addressGate — because any restrictive default guard is inherited by every group (an inherited addressGate would block ordinary buyers). The PDA/seed derivation is **verified from source** (`test_pda.ts`); the program-runtime behaviors (inheritance merge, group-label-required routing / no ungrouped default mint, counter decrement) remain **untested offline**. `executeMint` unconditionally refuses execution. See [MINT-LIMIT-POLICY.md](MINT-LIMIT-POLICY.md).

## Launch blockers

All ten remain unresolved: approved full 3,333 render; complete final metadata; permanent metadata URIs; early start date; public start date; finalized allowlist/root; reviewed cost quote; explicit mainnet deployment authorization; SDK broadcast/deployment integration incomplete (real tx builders now exist in `tx_builders.ts`, but the signed send path + on-chain config-line loading + a localnet/devnet rehearsal are not done); on-chain program enforcement untested. Unit tests do not clear these blockers. (The former "shared counter enforcement unverified" blocker is resolved at the PDA/seed level and narrowed to program-runtime testing.)

## Files

| File | Role |
| --- | --- |
| `collection/launch/launch-config.json` | Source of truth: grouped pricing, addresses, supply, default-guard policy, blockers |
| `collection/launch/allowlist/early-wallets.csv` | Empty allowlist source (no fabricated wallets) |
| `collection/launch/economics.md` | Generated gross-revenue report (both tiers) |
| `scripts/mainnet/launch_config.ts` | Typed loader + strict validator |
| `scripts/mainnet/guard_groups.ts` | Pure Candy Guard group/default builder (inheritance) + no-cheap-path proof |
| `scripts/mainnet/mint_counter_pda.ts` | SDK-backed MintCounter PDA derivation + source-contract assertion |
| `scripts/mainnet/tx_builders.ts` | Real deploy tx builders (create+wrap, addConfigLines, updateCandyGuard) — build-only, never sends |
| `scripts/mainnet/creator_costs.ts` | Creator setup/loading cost estimator (real serialized sizes) |
| `scripts/mainnet/launch_blockers.ts` | Pure blocker evaluation (aborts deploy) |
| `scripts/mainnet/economics.ts` | Exact integer-lamports revenue math |
| `scripts/mainnet/allowlist_builder.ts` | Deterministic validate/dedup/hash; Merkle only when wallets exist |
| `scripts/mainnet/mint_helper.ts` | Phase-select buyer mint: fresh fetch, price reconcile, verify asset |
| `scripts/mainnet/deploy_candy_machine.ts` | Guarded prepare (dry-run; aborts on blockers) |
| `scripts/mainnet/launch_dry_run.ts` | Full dry-run report |
| `scripts/mainnet/build_economics_report.ts` | Regenerates `economics.md` |
| `scripts/mainnet/allowlist_cli.ts` | Runs the allowlist builder |
| `scripts/mainnet/test_launch.ts` | prepared descriptor, inheritance-model, phase-transition, and offline execution tests |
| `scripts/mainnet/test_pda.ts` | SDK-backed MintCounter PDA proofs (source contract + counter equality) |

## Commands (all safe / no chain)

```bash
cd collection/scripts/mainnet
npm run test:launch        # launch, inheritance, and phase-transition tests
npm run test:pda           # SDK-backed MintCounter PDA proofs (offline)
npm run launch:dry-run     # full dry-run report (also runs tests)
npm run launch:prepare     # guarded prepare — aborts on blockers
npm run launch:allowlist   # PENDING_ALLOWLIST until real wallets exist
npm run launch:economics   # regenerate economics.md
```

All commands above are local. No dependency installation or network lookup is part of this reconciliation. SDK integration and program-level proof remain separate prerequisites, along with all real readiness artifacts. Passing tests never authorizes deployment.
