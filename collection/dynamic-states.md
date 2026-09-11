# Finite evolution — season one

Design: four pre-rendered states, one monotonic on-chain integer per token, current-holder consent on every transition, and no administrator URI replacement. The UI shows both **State: ROOTED** and **Access: USER** without treating them as the same field.

## Transition contract

Let `C` be the immutable public-sale closing timestamp, set before mint. Timestamps below are durations from C, not an administrator-controlled moving deadline.

| Transition | Trigger and eligibility | Authorized actor / proof | Metadata and art | Reversible? |
| --- | --- | --- | --- | --- |
| DORMANT → ACTIVE | At or after C; holder selects INITIALIZE | Current owner sends transaction; no curator proof | State 0→1; seam illuminates, internal optics become readable | No |
| ACTIVE → COMPROMISED | Early at C+30 days with chapter-one eligibility; open to all at C+44 days | Current owner; valid chapter attestation before public opening, no attestation after | State 1→2; controlled conflicting evidence layer, preserved face/collar | No |
| COMPROMISED → ROOTED | Early at C+60 days with chapter-two eligibility; open to all at C+74 days | Current owner; same scoped attestation policy | State 2→3; conflicting structures settle into a stable arrangement, visible repaired seam | No |

Holders can leave a GHOST in any state indefinitely. Late holders can advance through each eligible state in separate transactions without an expiring access window. No burn, token spending, balance requirement, NFT approval, forced global update, or price increase is involved; ordinary chain transaction fees still apply.

All three Genesis characters follow the same timeline. ROOT access does not accelerate it. A progression rank is never a rarity rank.

## Early-access proof

Use OpenZeppelin EIP712 and SignatureChecker, with an immutable 2-of-3 chapter-attestor multisig supporting standard contract-signature verification. The team controls attestation decisions. This is an editorial oracle, not trustless validation of puzzle work.

Attested data: explicit action identifier, release digest, token ID, from-state, to-state, chapter ID, valid-after timestamp, expiration timestamp. The EIP712 domain binds name/version, chain ID, and verifying contract. Contract independently checks its fixed phase dates and exact one-step transition. A permissive signer cannot skip states, change files, mint tokens, move assets, or bypass the fixed earliest chapter start.

Eligibility intentionally follows the token, not a former holder's wallet: an unused chapter attestation is transferable with the identity. The UI must disclose this. Puzzle attribution to a contributor remains an optional off-chain archive credit. The transaction itself always requires the current on-chain owner; mempool observers or approved marketplaces cannot consume a claim on the holder's behalf. No shared global puzzle-answer hash becomes a reusable unrestricted state privilege.

The monotonic state consumes each transition once, preventing same-token same-state replay. Cross-token/contract/chain/release replay fails by claim binding. Test transfer-out-and-back, sale with an outstanding attestation, expired signatures, EOA and multisig holders, and malicious ERC1271 responders. At public fallback time, skip signature verification entirely so an unavailable attestor cannot block progression.

## On-chain versus off-chain

On-chain: owner, current state, fixed date schedule, fixed attestor address, signature checks, fixed media-root selection, events. Off-chain: puzzle submissions, curator judgment, website chapter prose, immutable media bytes hosted via IPFS, optional collector credits.

Events: `StateAdvanced(tokenId, previousState, nextState, actor, chapterId)` plus `MetadataUpdate(tokenId)`. Archive indexing records block/transaction/log coordinates and supports reorg rollback. The off-chain incident log is supplemental and cannot override contract state.

The fixed base CID includes **all 13,332 state metadata files** and a separate fixed media CID includes all images. Public access to future art is deliberate. Stage hidden lore through separate encrypted story artifacts and publish keys with chapters; cryptographic story mechanics are independent of ownership and metadata permanence.

## Abuse and continuity

Attestor compromise can unfairly grant early access within a fixed chapter window. It cannot seize tokens or replace art. Loss of the attestor prevents early grants, but public fallback times preserve holder access. A chapter can never depend forever on a support ticket.

Mint pause does not halt transfer or evolution. A bug in the immutable state machine cannot be patched by changing a proxy; independent review and full-state rehearsals are required. If later narrative needs exceed these four states, use optional new archive material rather than quietly rewriting this edition.
