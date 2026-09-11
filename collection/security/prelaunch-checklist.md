# Prelaunch gates

Current status: **NOT READY TO MINT**. Design completion does not satisfy the production gates below. A checked item needs evidence and an accountable reviewer. Names and dates remain unassigned until the project owner appoints them.

## Design gates

- [x] Canonical thesis, brand, lore, visual invariants, and Genesis concepts written.
- [x] Supply, allocation totals, exact trait quotas, and special reservations specified.
- [x] Offline 3,333-vector feasibility check completed with legal combinations and no repeated full vectors.
- [x] Chain, metadata, mint/state, authority, storage, website, launch, and operational plans written.
- [ ] Final commercial price, rights/license, budget, canonical domain, staff, and launch date approved.

## Artwork

- [ ] 3,333 final identities and all four states rendered at consistent dimensions/colorspace.
- [ ] All 30 legendary full vectors/scenes and all three Genesis scenes signed off.
- [ ] Source asset hashes, author rights, tool/model provenance, and renderer digest archived.
- [ ] Exact byte/pixel duplicates rejected; all perceptual flags adjudicated.
- [ ] 96 contact sheets plus individual full-size/64px review complete; approvals recorded.
- [ ] All origin quotas and reserve proportions match the frozen manifest.

## Metadata and storage

- [ ] 13,332 production JSON documents parse with duplicate-key rejection.
- [ ] IDs 1–3333 present once per state; no missing/extra image or metadata files.
- [ ] Attributes normalized; state, ID, image path, and fixed origin values agree.
- [ ] Contract/chain linkage and optional canonical external URLs verified.
- [ ] Every image resolves and hashes match through two gateways.
- [ ] Two independent pins active, CAR restoration tested, funded renewal owner assigned.
- [ ] Final CIDs and any actual Arweave transaction IDs recorded; future art public by design.

## Contract

- [ ] Reproducible pinned build, source verification, ABI and constructor arguments archived.
- [ ] Unit, fuzz, stateful invariant and adversarial callback tests pass.
- [ ] All mint paths enforce IDs, max supply, reserve limits, wallet limits, exact payments, and close time.
- [ ] Treasury, chapter signer, ownership and multisig acceptance independently verified.
- [ ] Four state paths, replay rejection, holder check, fallback dates, and ERC4906 events tested.
- [ ] Pause scope, unexpected ETH, withdrawal and royalty information tested.
- [ ] Independent review completed; material findings resolved and retested.
- [ ] Base Sepolia fresh-wallet mint, reserve claim, transfer and all states rehearsed.

## Mint website

- [ ] Wallet connection/account change/network change work on desktop and mobile.
- [ ] Exact-art selection conflict, insufficient funds, rejected signature, revert and RPC failure render correctly.
- [ ] Price/supply/counts/caps/address derive from verified chain/config data.
- [ ] Simulation, confirmed receipt, actual token ID and recipient verified.
- [ ] Keyboard, focus, contrast, reduced motion, alt text, and mobile wallet return flow pass.
- [ ] CSP, pinned dependencies/lockfile, provenance, API limits, session nonces, and bundle-secret scan pass.
- [ ] Controlled canonical domain and collection explorer link prominently displayed.
- [ ] Fresh ordinary wallet and smart-contract wallet tests pass.

## Operations

- [ ] Named incident commander, technical responder, comms lead and backups assigned.
- [ ] Multisig signer availability and key-loss rehearsal complete.
- [ ] Production addresses, source hashes, release manifest and authority matrix archived and signed.
- [ ] Domain/social account recovery, phish-report triage, storage and RPC alerts rehearsed.
- [ ] Collector rules disclose reserve recipients/policy, retained authorities, unsold-close behavior, and royalties.
- [ ] Launch funding and promised post-mint delivery do not depend on a sellout or royalties.

Production approval: unassigned. Production date: unset. No gate is waived by this specification.

## Current Solana launch gates

- [ ] Five mints per wallet in EACH phase (5 early + 5 public, 10 combined): two independent on-chain counters (early mintLimit id 1, public id 2), fixed guard/machine identity, no reset on phase change or transfer.
- [ ] SDK source and program tests verify the per-phase mintLimit counter seeds (distinct id → distinct PDA), group-label-required routing / no ungrouped default mint, direct-client and authority paths; the sixth mint in a phase must fail.
- [ ] SDK broadcast/deployment integration complete and reviewed. Currently incomplete; mint execution disabled.
- [ ] Approved full 3,333 render, complete final metadata, permanent metadata URIs, both real start dates, finalized allowlist/root, reviewed cost quote, and explicit mainnet authorization exist. All remain unresolved.

Local model tests establish policy expectations only; they do not demonstrate deployed enforcement.
