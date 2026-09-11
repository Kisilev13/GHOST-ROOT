# Technical implementation plan

Status: architecture specification, not implemented or audited. Public documentation checked 2026-09-07. No RPC calls, live security testing, deployments, or uploads were performed. The workspace has no live-testing authorization package; project deployment and any live test require explicitly scoped accounts and environments.

## 1. Chain decision

Recommend **Base + nonupgradeable ERC-721** for this version. The decisive requirements are a hard lifetime supply cap, fixed media paths, and a small holder-controlled state machine. A single reviewed Solidity implementation can enforce all three. This is a project-specific architectural judgment, not a claim that a chain guarantees demand or that current collector liquidity has been measured.

| Option | Fit for this collection | Tradeoff / decision |
| --- | --- | --- |
| Base | EVM implementation of immutable edition rules and frequent holder state changes | Recommended; validate actual transaction cost and intended marketplace behavior on rehearsal |
| Solana | Core assets and guarded minting are a strong native NFT path | Strong alternative if audience research favors Solana; constrained evolution requires a custom controller or openly retained update authority |
| Ethereum L1 | Same Solidity architecture with direct L1 execution | Appropriate for a smaller art-led edition; repeated interactions expose holders to variable L1 execution fees |
| Arbitrum One | Solidity architecture is portable to an Ethereum rollup | Technically suitable; no demonstrated audience or product requirement favors a second EVM launch |
| Polygon | EVM-compatible architecture | Suitable if distribution partners establish a need; its security/settlement model must not be described as identical to an Ethereum rollup |
| Unconventional alternative | Ethereum L1 edition of 333 fully on-chain abstract forensic glyphs with the portrait archive as supplemental art | Strong permanence concept, but changes supply, art direction, and target collector; separate edition proposal, not a hidden fallback |

Base network IDs are 8453 and Base Sepolia 84532. [Base RPC documentation](https://docs.base.org/base-chain/api-reference/rpc-overview). Ethereum execution requires gas whose price varies; benchmark rather than promise fixed fiat costs. [Ethereum gas documentation](https://ethereum.org/developers/docs/gas/). Arbitrum and Polygon offer distinct architectures; these alternatives are not claims of equivalent settlement. [Arbitrum documentation](https://docs.arbitrum.io/), [Polygon architecture](https://docs.polygon.technology/pos/overview).

No bridges, wrapped duplicates, multichain genesis, fungible token, staking emissions, or burn-to-upgrade mechanic is needed for season one.

## 2. System boundaries

```text
Approved art + exact trait config + frozen generation seed
    → deterministic manifests and four rendered states
    → metadata validation and visual review
    → pinned IPFS directories + offline CAR archive
    → immutable ERC-721 media root and finite state rules
    → archive website reads chain + static media

Isolated puzzle service → scoped early-access attestations
Current holder + valid attestation OR fixed fallback time → advance one state
```

Ownership, current state, supply, mint counters, price, treasury, eligibility verification, and media-root selection are on-chain. Images, prose, puzzle adjudication, profile preferences, and shipping are off-chain. An off-chain attestation is not trustless proof of human effort. The on-chain contract confines its effect to early access to a predefined transition.

## 3. Implementation work packages

| Package | Deliverable | Exit criterion |
| --- | --- | --- |
| A — art direction | Four entity bases, 12 excellent common portraits, three Genesis concepts, 144-image pilot | Art director signs camera/material/thumbnail consistency |
| B — trait solver | `scripts/generate_collection.py`, asset resolver, exact quotas, reservations, deterministic output | Two clean offline builds match by hashes; impossible configs fail without relaxation |
| C — media and metadata | 3,333 identities × 4 state PNGs and JSONs; validator and contact sheets | All 13,332 images and 13,332 metadata documents pass; human approvals recorded |
| D — contract | `GhostRoot.sol`, deployment script, Foundry unit/fuzz/invariant suite | All tests and independent review pass; supply and authority paths inspected |
| E — archive/mint site | Responsive archive with real wallet integration, verified config, state receipts | Fresh-wallet Base Sepolia mint and evolution succeed on desktop and mobile |
| F — release operations | Durable storage, reviewed budget, multisigs, monitoring, incident runbook | Named owners, source hashes, deployed addresses, signed release manifest |

Art and infrastructure may progress together, but do not lock an on-chain media root before final art QA. Estimated planning window is 8–12 weeks with a dedicated artist, frontend engineer, contract engineer, and independent reviewer; estimate is conditional on staffing, revisions, and scope. Reserve additional review time rather than treating the estimate as a launch announcement.

## 4. Deterministic production generator contract

CLI specification:

```sh
python3 scripts/generate_collection.py --config collection.yaml --traits traits.yaml --assets assets/manifest.json --seed-file release-seed.txt --output build/release-001
python3 scripts/validate_metadata.py build/release-001/metadata --supply 3333 --states 4 --images build/release-001/images
```

These two production commands are specifications for subsequent engineering, not runnable files included here. The supplied `validate_design.py` is an offline feasibility tool only.

Use a deterministic PRNG or SHA-256 counter stream with explicit domain separation and rejection sampling for unbiased bounded integers. Freeze the algorithm version, UTF-8 seed encoding, sorted trait order, search tie-breaks, retry bounds, renderer binary/container digest, font hashes, and color transform. Never depend on process-randomized hash iteration or library defaults.

1. Validate config and source asset hashes; refuse missing/extra assets, invalid dimensions, or unrecognized rules.
2. Reserve and subtract all 33 special vectors, then the specified distribution slots. Fully finalize legendary vectors before this step.
3. Solve exact quotas under all dependencies using deterministic matching/backtracking; give an unsatisfiable-config report on exhaustion.
4. Reject duplicate origin vectors and missing class/context asset variants. Record any art-directed exceptions explicitly.
5. Render DORMANT/ACTIVE/COMPROMISED/ROOTED for each ID using approved state geometry/effects. State never modifies origin traits.
6. Hash decoded pixels and file bytes; perform perceptual similarity checks and manual near-duplicate adjudication.
7. Export rarity, special-token checks, source hashes, build parameters, and human-review status.
8. Generate final JSON after final media CIDs are known. Validate then pin metadata. Archive signed manifests separately to avoid self-referential hash/CID cycles.

Required release outputs:

```text
manifest.csv                     # token_id, origin vector, state paths, hashes, reservation
rarity-report.csv                # actual counts plus target deltas
duplicate-report.csv             # token pair, state, exact/perceptual measure, decision
release-manifest.json            # config/source/build hashes, seed commitment, URIs, signatures
images/{0,1,2,3}/{id}.png
metadata/{0,1,2,3}/{id}.json
contact-sheets/{state}/{sheet}.png
qa/approvals.csv
```

The public seed is reproducibility information, not mint randomness. Publish it with the final manifest before mint. AI source generation itself may be nondeterministic; the approved source bytes are fixed inputs to the reproducible compositor.

## 5. Metadata and storage

Use standard ERC-721 fields `name`, `description`, `image`, and `attributes`, plus project-defined `token_id`, `collection`, and optional `external_url`. Collection membership is established by the ERC-721 contract address; an off-chain collection label is descriptive, not verification. Do not inject a Solana verification flag into EVM metadata.

`name` uses padded display IDs; file paths use unpadded decimal integer IDs. `tokenURI(id)` selects `ipfs://METADATA_CID/{state_index}/{id}.json`. `image` selects `ipfs://MEDIA_CID/{state_index}/{id}.png`. No HTTP origin is the only media source. The optional external URL is omitted until a controlled canonical domain exists.

All 11 origin attributes plus State and Rarity Band appear exactly once, in a canonical order and with values from the trait catalog. The metadata validator must parse JSON with duplicate-key rejection; require IDs 1–3333 in every state; reject duplicated IDs, extra files, malformed attribute arrays, unknown names/values, wrong types, duplicate trait categories, malformed CIDs, absent images, identical images across different IDs, dimension mismatches, invalid collection linkage, and origin changes across states. Assert 13,332 files for four states, not merely 3,333 filenames anywhere under the tree.

The example in `metadata/README.md` is intentionally a template, not a resolving production NFT. JSON Schema validates structure; semantic validation still checks catalog membership, state/ID/path agreement, rendered hashes, and collection address after deployment.

Storage recommendation: **IPFS with two independent pinning providers plus an offline CAR archive**; optionally fund an Arweave backup. Pinning keeps content retained; content addressing alone does not keep a node serving it. [IPFS pinning documentation](https://docs.ipfs.tech/how-to/pin-files/).

Hash all images before upload; pin the media directory and record its CID; create metadata referencing that CID; pin metadata and record its CID. Commit the final root into the deployment constructor. Store asset hashes and metadata hashes in the release manifest. Do not insert the final manifest's own hash inside itself. Record Arweave transaction IDs only after actual upload/confirmation; no placeholder is evidence of storage.

Preload/read every file through two independent gateways and verify hashes before release. Retain CAR recovery procedures, pin-renewal alerts, provider contacts, and a funded continuity plan. All future state artwork is public before mint: secret story keys belong in separate encrypted narrative artifacts. A CID or unlinked URL is not confidentiality.

## 6. Wallet and website implementation

Use a current reviewed React/TypeScript setup with Wagmi for wallet state and Viem for typed contract reads/writes, plus maintained injected and WalletConnect connectors. Select exact versions at engineering kickoff, review advisories and package provenance, then commit a lockfile. No guessed version number or unreviewed “latest” range belongs in the release. Wagmi documents the React/Viem integration. [Wagmi getting started](https://wagmi.sh/react/getting-started).

The frontend verifies chain ID, deployed contract identity/code, and release config; it reads price and caps from chain rather than duplicating editable constants. Simulate the exact mint call when supported, display ETH value and expected asset, submit, then verify the receipt and Transfer events. Simulation does not guarantee later success. Distinguish pending/preconfirmation from a confirmed receipt. Revalidate after account or network changes.

Use static hosting for the archive where possible. Only eligibility/session/puzzle features need an API; bind signed sessions to canonical domain, action, address, chain, nonce, and expiration. Protect nonce consumption atomically; support contract wallets using standard signature verification. Never request a key or seed phrase.

See `mint-site/experience.md` for states, copy, mobile layout, accessibility, CSP, rate limits, and transaction failure behavior.

## 7. Release gates and residual risk

Base sequencer availability, RPC providers, wallet software, storage services, and marketplace indexing remain dependencies. Benchmark the intended wallets and marketplaces instead of asserting universal compatibility. A immutable contract cannot receive a rescue patch; independent review and rehearsals are prerequisites.

The project retains limited mint pause authority and early chapter-attestation authority, documented separately. It does not retain arbitrary metadata replacement, transfer, freeze, forced-burn, royalty-setting, or unlimited mint authority in the recommended design. These are requirements to prove in implementation, not properties verified by this design release.

Do not deploy until final art, metadata, testnet behavior, operational assignments, final price/domain, rights, budget, and prelaunch checklist are complete.
