# GHOST//ROOT mint-limit policy

State: `PREPARED_NOT_DEPLOYED`. The authoritative policy is **5 mints per wallet in EACH phase — 5 early + 5 public, independent (10 combined)**. It supersedes the earlier "5 total lifetime" wallet policy everywhere in the active launch specification.

| Early mints | Public mints available |
| ---: | ---: |
| 0 | 5 |
| 1 | 5 |
| 2 | 5 |
| 3 | 5 |
| 4 | 5 |
| 5 | 5 |

The two phases are **independent**: minting up to 5 in `early` does not reduce the `public` allowance, and vice-versa. Maximum per wallet across the whole launch is **10** (5 + 5).

Early price stays **0.10 SOL**; public price stays **0.15 SOL**. Both consume the same **3,332-item inventory (0002–3333)**. GHOST//0001 is an existing canary, excluded from this inventory. Transfers never reset mint history. Eligibility and holdings do not grant fresh allowance.

## Prepared production mechanism (independent per-group counters)

Each group defines its **OWN** Mint Limit: `early` uses **id 1**, `public` uses **id 2**, both limit 5. The `default` guard set is **empty**. Because the MintCounter PDA seeds **include the id** (verified below), the two phases resolve to **different** wallet counters for one fixed Candy Guard / Candy Machine pair, giving two independent 5-mint allowances.

```
default: {}                          # EMPTY — nothing inherited into the groups
early:
  solPayment: 0.10 SOL -> treasury   # + allowList (pending) + startDate (pending)
  mintLimit:  { id: 1, limit: 5 }    # early's own counter
public:
  solPayment: 0.15 SOL -> treasury   # + startDate (pending)
  mintLimit:  { id: 2, limit: 5 }    # public's own counter
```

The default set is deliberately empty because any guard placed there is inherited by every group: an inherited `mintLimit` would collapse the two phases onto one shared counter (destroying the 5+5 independence), an inherited `solPayment` would open a second ungrouped price, and an inherited `addressGate` would block ordinary (non-authority) buyers. "No ungrouped/free default mint route" is provided by the Candy Guard program requiring a valid group label to mint whenever groups exist (program-level; see blocker below). Client preflight rejects a missing/mismatched or collided resolved guard ID but is never the source of enforcement.

### SDK status (2026-09-11)

`@metaplex-foundation/mpl-core-candy-machine@0.3.0` (the latest published version) is **installed and pinned** in `collection/scripts/mainnet` alongside `@metaplex-foundation/mpl-core@1.10.0` and `@metaplex-foundation/umi@1.5.1`. Note a **peer-range mismatch**: 0.3.0 declares `peerDependencies.umi ">= 0.8.2 <= 1"`, while umi 1.5.1 is required by mpl-core 1.10.0 — the peer range is stale (declared when umi 1.0 was current) and no newer candy-machine release exists to widen it; the project runs umi 1.5.1 and relies on the source/type verification below rather than the declared range.

`executeMint` still always aborts before reading the supplied wallet, importing an SDK signer, signing, or broadcasting. Preparation always reports the SDK-integration and program-enforcement blockers; command-line readiness flags cannot remove them.

## Proof status

1. **DONE — MintCounter PDA seeds source-verified.** `findMintCounterPda` in the installed 0.3.0 source (`generated/accounts/mintCounter.js`) derives from exactly `['mint_limit', id(u8), user(pubkey), candyGuard(pubkey), candyMachine(pubkey)]` under program `CMAGAKJ67e9hRZgfC5SFTbZH8MgEmtqazKXjmkaJjWTJ`. The **group label, price, and phase are NOT seeds**, but the **id IS** — so early (id 1) and public (id 2) derive **different** counters for the same wallet, while the same id + wallet + guard + machine always derives one counter. Asserted from source and by derivation in `test_pda.ts` (`earlyCounterPda != publicCounterPda`; different wallet → different PDA). Each counter is keyed by the mint **payer/user**, not the recipient.
2. **DESIGN DONE, PROGRAM ROUTING UNTESTED.** The independent-counter design is implemented (empty default; each group carries its own distinct mintLimit id). The actual program-side "a valid group label is required to mint when groups exist" routing and each counter's decrement are Candy Guard **program** behaviors, not executable offline — retained under `onchain_program_enforcement_untested`.
3. **NOT RUN.** Running the program in an isolated local validator across all phase sequences, direct-client mints, unknown groups, ungrouped mints, transfers, failed txs, and concurrent 5th/6th attempts per phase. Needs a localnet/devnet rehearsal (forbidden this phase).
4. **PENDING.** Preserve guard address, machine address, and both counter IDs (1, 2) for the whole sale; no reset / replacement machine-guard / direct authority mint / alternate Core create path may grant a mint outside the caps. Restrict bypass authorities in the reviewed integration and prove the controls.
5. **PENDING.** GHOST//0001 was minted outside this machine and is not counted by either counter; never silently grant its owner an extra mint outside the per-phase caps.

Frontend counters, inventory balances, and off-chain allowlists are insufficient — enforcement is the on-chain per-phase mintLimit guards.

## Unresolved readiness

All eight original blockers remain: approved full 3,333 render; final metadata; permanent URIs; early start date; public start date; finalized allowlist/root; reviewed cost quote; explicit mainnet deployment authorization. Two engineering blockers remain: **SDK broadcast/deployment integration incomplete** (real tx builders now exist in `tx_builders.ts`, but the signed send path, on-chain resumable config-line loading, and a localnet/devnet rehearsal are not done) and **on-chain program enforcement untested** (group-label-required routing / no default bypass, and each per-phase counter decrement rejecting the sixth). Passing local tests is not deployment readiness.

## Local validation boundary

Tests check config/descriptor rejection, a clearly labeled specification model of the independent per-phase counters, disabled mint execution, unchanged prices/inventory, and offline guards. The model is not the actual Candy Guard program. A network-disabled namespace and syscall trace are used for this validation run; no wallets or secrets are loaded. Test-process guards reject network/signing APIs. Nothing signs or spends SOL.
