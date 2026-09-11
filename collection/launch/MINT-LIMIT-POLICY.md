# GHOST//ROOT lifetime mint policy

State: `PREPARED_NOT_DEPLOYED`. The authoritative policy is **5 total mints per wallet across the entire launch**. It supersedes earlier wallet policies everywhere in the active launch specification.

| Early mints | Maximum remaining public mints |
| ---: | ---: |
| 0 | 5 |
| 1 | 4 |
| 2 | 3 |
| 3 | 2 |
| 4 | 1 |
| 5 | 0 |

Early price stays **0.10 SOL**; public price stays **0.15 SOL**. Both consume the same **3,332-item inventory (0002–3333)**. GHOST//0001 is an existing canary, excluded from this inventory. Transfers never reset mint history. Eligibility and holdings do not grant fresh allowance.

## Prepared production mechanism (inheritance model)

Define the lifetime Mint Limit **ONCE — ID 1, limit 5 — in the DEFAULT guard set**. The `early` and `public` groups define **no** mintLimit of their own; they **inherit** the default. Because the MintCounter PDA seeds do not include the group label (verified below), both phases resolve to the **same** wallet counter for one fixed Candy Guard / Candy Machine pair, so changing phase reuses the counter automatically.

```
default:
  mintLimit: { id: 1, limit: 5 }     # the only guard in default; inherited by every group
early:
  solPayment: 0.10 SOL -> treasury   # + allowList (pending) + startDate (pending)
public:
  solPayment: 0.15 SOL -> treasury   # + startDate (pending)
```

The default set carries **only** the mintLimit — deliberately **no** solPayment and **no** addressGate. Any restrictive guard placed in the default is inherited by every group: an inherited `addressGate` would block ordinary (non-authority) buyers, and an inherited `solPayment` would open a second ungrouped price. "No ungrouped/free default mint route" is instead provided by the Candy Guard program requiring a valid group label to mint whenever groups exist (program-level; see blocker below). Client preflight rejects a missing/mismatched resolved guard ID but is never the source of enforcement.

### SDK status (2026-09-11)

`@metaplex-foundation/mpl-core-candy-machine@0.3.0` (the latest published version) is now **installed and pinned** in `collection/scripts/mainnet` alongside `@metaplex-foundation/mpl-core@1.10.0` and `@metaplex-foundation/umi@1.5.1`. Note a **peer-range mismatch**: 0.3.0 declares `peerDependencies.umi ">= 0.8.2 <= 1"`, while umi 1.5.1 is required by mpl-core 1.10.0 — the peer range is stale (declared when umi 1.0 was current) and no newer candy-machine release exists to widen it; the project runs umi 1.5.1 and relies on the source/type verification below rather than the declared range.

`executeMint` still always aborts before reading the supplied wallet, importing an SDK signer, signing, or broadcasting. Preparation always reports the SDK-integration and program-enforcement blockers; command-line readiness flags cannot remove them.

## Proof status

1. **DONE — MintCounter PDA seeds source-verified.** `findMintCounterPda` in the installed 0.3.0 source (`generated/accounts/mintCounter.js`) derives from exactly `['mint_limit', id(u8), user(pubkey), candyGuard(pubkey), candyMachine(pubkey)]` under program `CMAGAKJ67e9hRZgfC5SFTbZH8MgEmtqazKXjmkaJjWTJ`. The **group label, price, and phase are NOT seeds**, so the same id + wallet + guard + machine derive one shared counter across phases. Asserted from source and by derivation in `test_pda.ts` (`earlyCounterPda == publicCounterPda`; different id / different wallet → different PDA). The counter is keyed by the mint **payer/user**, not the recipient.
2. **DESIGN DONE, PROGRAM MERGE UNTESTED.** The inheritance design is implemented (default-only mintLimit; groups inherit). The actual program-side merge of default into group guards, and the "a valid group label is required to mint when groups exist" routing, are Candy Guard **program** behaviors and are not executable offline — retained under `onchain_program_enforcement_untested`.
3. **NOT RUN.** Running the program in an isolated local validator across all phase sequences, direct-client mints, unknown groups, ungrouped mints, transfers, failed txs, and concurrent 5th/6th attempts. Needs a localnet/devnet rehearsal (forbidden this phase).
4. **PENDING.** Preserve guard address, machine address, and counter ID for the whole sale; no reset / replacement machine-guard / direct authority mint / alternate Core create path may grant a mint outside the cap. Restrict bypass authorities in the reviewed integration and prove the controls.
5. **PENDING.** Reconcile the canary owner's prior issuance if it counts toward the entire-launch allowance (GHOST//0001 was minted outside this machine; a new counter will not include it). Never silently grant the owner an extra mint.

A static split of phase quotas cannot satisfy all six required early/public examples; it must not replace this policy. Frontend counters, inventory balances, and off-chain allowlists are insufficient.

## Unresolved readiness

All eight original blockers remain: approved full 3,333 render; final metadata; permanent URIs; early start date; public start date; finalized allowlist/root; reviewed cost quote; explicit mainnet deployment authorization. Two engineering blockers remain: **SDK broadcast/deployment integration incomplete** (real tx builders now exist in `tx_builders.ts`, but the signed send path, on-chain resumable config-line loading, and a localnet/devnet rehearsal are not done) and **on-chain program enforcement untested** (inheritance merge, group-label-required routing / no default bypass, and counter decrement rejecting the sixth). The former `shared counter enforcement unverified` blocker is **resolved at the PDA/seed level** (proof #1) and narrowed to program-runtime testing. Passing local tests is not deployment readiness.

## Local validation boundary

Tests check config/descriptor rejection, a clearly labeled specification model, disabled mint execution, unchanged prices/inventory, and offline guards. The model assumes shared counter semantics and is not the actual Candy Guard program. A network-disabled namespace and syscall trace are used for this validation run; no wallets or secrets are loaded. Test-process guards reject network/signing APIs. Nothing signs or spends SOL.
