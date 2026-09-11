# GhostRoot contract implementation specification

No deployable smart contract is supplied in this phase. This document is the engineering interface and test plan.

## Primitives and layout

Use a nonupgradeable OpenZeppelin ERC721, ERC2981, Ownable2Step, ReentrancyGuard, and narrowly applied Pausable. Avoid ERC721Enumerable, ERC721URIStorage per-token mutable URIs, proxy machinery, custom signature algorithms, and unused roles. One immutable treasury address, one immutable chapter-attestor multisig address, and one mint-operations owner are sufficient. OpenZeppelin provides the token and access-control primitives; collection-specific mint/state code still needs review. [ERC-721](https://docs.openzeppelin.com/contracts/5.x/erc721), [access control](https://docs.openzeppelin.com/contracts/5.x/access-control), [utility protections](https://docs.openzeppelin.com/contracts/5.x/api/utils).

Store the final metadata directory root in constructor-only storage with no setter. Solidity dynamic strings cannot simply be declared `immutable`; immutability here comes from the absence of any write path after construction. Deployment tests must demonstrate that URI inputs and state names are correct before funds are accepted.

Constructor inputs: treasury, chapter attestor, final metadata base URI, public collection URI, release digest, allowlist Merkle root, reserve-claim Merkle root, fixed price, fixed allowlist/public time windows, fixed chapter open/fallback times. Reject zero addresses, empty roots for enabled claims, invalid times, nonpositive price, or inconsistent release settings. Publish all arguments and verified source.

## Mint functions

`mintAllowlist(uint256[] ids, bytes32[] proof)`:

- IDs must be >201 and <=3333, not already minted; quantity exactly one in this phase.
- Sender must prove the fixed allowlist leaf for this contract/release/chain; use an audited Merkle helper and unambiguous ABI encoding.
- Enforce one allowlist claim per wallet and global allowlist counter <=1110.
- Enforce `paidMintedBy[sender] + quantity <= 2` across both phases.
- Require the fixed allowlist time window and `msg.value == price * quantity`.

`mintPublic(uint256[] ids)`:

- Same allowed ID set and lifetime counter; quantity one or two; reject duplicate IDs within a batch.
- Require the nonoverlapping public window.
- Paid supply <=3132; unused allowlist capacity is automatically available because all unminted nonreserve IDs share this pool. No mutable cap increase is required.

`claimReserved(id, recipient, proof)`:

- ID must be 1–201, reserved claim must bind ID and exact recipient, and ID must never have been minted.
- Anyone may relay a valid proof to that fixed recipient; no discretionary owner receiver override.
- A reserve leaf is not permission to mint a paid ID. Fixed root binds all 201 recipients before deployment.
- Bound every batch if batched; never put a 201-token loop on an unbounded public entry point.
- Reserve claims are available during prelaunch and until the same final sale close, subject to mint pause. Unclaimed reserves expire with unminted paid inventory; report fewer actual mints rather than reminting or expanding the collection.

All paths use a single internal lifetime issuance check: valid ID, unused ID, `totalMinted + quantity <= 3333`. Account for the whole batch before `_safeMint` callbacks, protect reentrancy, and maintain correct revert rollback. There is no owner mint, airdrop helper with a separate supply counter, burn-to-remint, or reminting of an expired reserve. No public burn method is required.

These are maximum mintable quantities, not a guarantee all tokens mint. After the fixed close, report the real minted count and permanently unavailable unminted IDs. Do not label an undersubscribed close a sellout.

## Payments and royalty policy

Planning price is 0.004 ETH per paid token; freeze the approved value at construction. No in-sale owner price change. Maintain gross paid mint receipts independently of balance. Reject under/overpayment rather than making an external refund callback during mint.

`withdraw()` is authorized for the immutable treasury or mint owner, always pays the immutable treasury, and is protected against reentrancy. Failure to pay reverts accounting. An owner cannot redirect payment by passing an arbitrary recipient. Use actual balance for withdrawal so forced ETH is recoverable; record unexpected excess separately from paid mint revenue. A reverting `receive` does not make forced ETH impossible.

ERC2981 reports 500 bps to the same treasury, with no postdeployment setter. It communicates royalty information; marketplaces are not technically forced to pay it. Do not fund required operations on assumed secondary royalties. [ERC-2981](https://eips.ethereum.org/EIPS/eip-2981).

## Evolution interface

`advance(tokenId, nextState, claim, signature)` uses the rules in `dynamic-states.md`. Only `msg.sender == ownerOf(tokenId)` can advance; NFT-approved operators are not enough. No batch advancement. No cross-chain relayer or meta-transaction extension at launch. Reject unknown token IDs.

`tokenURI(id)` uses the fixed base URI plus state index and ID. All four images/metadata documents are fixed before mint; advancing selects a path, never a new administrator URL. Emit `StateAdvanced` and ERC4906 `MetadataUpdate(tokenId)` and advertise its interface. Indexers may still need refresh/retry; the event is a notification mechanism. [ERC-4906](https://eips.ethereum.org/EIPS/eip-4906).

## Pause and authority

Apply `whenNotPaused` to mint/claim functions only. Do not inherit ERC721Pausable if it would also block transfers against this policy. Transfer/approval and public-time state advancement continue through a mint incident. Owner can pause/resume until mint close; ownership changes use two-step acceptance. After mint close and reconciliation, transfer/renounce the exhausted mint administration according to the authority matrix. Document that pause can censor mint availability while retained.

## Foundry verification plan

Unit tests: each mint path and boundary ID (0,1,201,202,3333,3334), exact price, both phase edges, wrong proof/recipient, repeated reserve claim, duplicate batch IDs, AL→public cap, global cap, transfers not restoring paid allowance, wrong caller, owner changes, failed withdrawal, pause scope, nonexistent tokenURI, state paths, royalty info, supported interfaces, permanently closed sale.

Fuzz tests: quantities/IDs, repeated transfers and mints, payment values, malformed proofs, signature bytes, old/next states, timestamps, unexpected ETH, receivers that revert/reenter on `_safeMint`, treasury callback behavior, reserve/public path interleavings, and attestations presented under wrong contract/chain/release/token/state.

Stateful invariant handlers use multiple owned accounts and adversarial receiver contracts:

```text
totalMinted <= 3333
paidMinted <= 3132 and reservedMinted <= 201
totalMinted == paidMinted + reservedMinted
each token ID is issued at most once
paidMintedBy[wallet] <= 2, even after transfer
allowlistMintedBy[wallet] <= 1 and allowlistMinted <= 1110
grossMintReceipts == price * paidMinted
balance + successfulWithdrawals >= grossMintReceipts  # forced ETH allowed
state[id] never decreases and is in [0,3]
origin metadata paths remain rooted in the constructor CID
unauthorized callers cannot mint, redirect funds, or evolve others' tokens
```

Use property-based sequences that try every external mint path; do not rely on one happy-path 3,333-token loop as supply proof. Keep reserve commitments and allocation recipient data outside test fixtures until reviewed; use clearly synthetic accounts in tests. Run independent review, static analysis, pinned reproducible compilation, and Base Sepolia rehearsals before production.
