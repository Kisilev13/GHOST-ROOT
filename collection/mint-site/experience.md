# The Recovery Terminal — mint-site specification

Build an archive a collector would visit without connecting a wallet. Its primary object is a portrait with a legible dossier. Wallet connection is contextual to recovery and holder actions. This document defines the experience; no working mint website exists yet.

## Arrival

Desktop: a monumental portrait occupies the left 58% of the opening viewport; the right side is a compact evidence panel. Bone text on archive black, one vermilion seam, thin rules, generous empty space. Motion is a single slow reconstruction at first entry, disabled for reduced motion. A persistent status strip gives canonical domain, network, and verified collection address. Before deployment it says “DESIGN PREVIEW — MINT UNAVAILABLE,” with no fabricated address or progress count.

```text
GHOST//ROOT                         ARCHIVE / INCIDENT LOG / VERIFY
You were never supposed to find them.

[ FORENSIC PORTRAIT ]               IDENTITY 1842
                                   ENTITY: HUMAN
                                   ACCESS: USER
                                   STATE: DORMANT
                                   [ INSPECT RECORD ]

CANONICAL DOMAIN · BASE · VERIFIED COLLECTION ADDRESS
```

ID 1842 here is layout copy, not a claim about an approved generated character. Bind every field to its real manifest record in production.

## Rooms, routes, and copy

| Route | Experience | Action |
| --- | --- | --- |
| `/` | Latest public transmission and one curated portrait | ENTER THE ARCHIVE |
| `/archive` | Fast, filterable portrait grid; Entity/Architecture/Access/availability filters | Inspect the identity you want |
| `/identity/:id` | Portrait, four-state preview, origin attributes, current holder state, provenance | RECOVER THIS IDENTITY / VIEW ON EXPLORER |
| `/incidents` | Chronological fiction with clear published dates and accessibility transcripts | Read or solve |
| `/terminal` | Optional puzzle interface with visible instructions and hints | Submit an answer without a wallet until credit is claimed |
| `/protocol` | Plain-language price, reserves, cap, license, evolution, and authorities | Understand the rules |
| `/verify` | Canonical domain/address, signed release digest, explorer link, phishing guidance | VERIFY BEFORE SIGNING |
| `/my-records` | Optional local holder view; no compulsory public profile | INITIALIZE / ADVANCE |

The terminal accepts a small documented set of fictional commands; it is not a remote shell. Hidden lore remains reachable through accessible navigation and transcripts. No scroll trapping, autoplay sound, flashing glitch sequences, or wallet connection required to read a price.

## Mint drawer

Display the selected token's actual portrait, token ID, Base network, fixed on-chain price, estimated fee separately, quantity, lifetime paid wallet allowance, current phase allowance, mint close time with timezone, edition maximum, actual minted count, and verified address/explorer link. Separate paid mints from reserve claims in counts. During unsold close say “ARCHIVE CLOSED — X OF 3,333 RECOVERED.”

The mint is exact-art selection. The collection does not hold an ID while a wallet dialog is open. If another transaction wins it, explain the conflict and let the user choose again; do not silently mint a replacement token. Do not auto-resubmit a paid transaction after a timeout.

## Transaction state machine

```text
DISCONNECTED → CONNECTED → WRONG NETWORK or READY
READY → SIMULATING → AWAITING SIGNATURE → SUBMITTED → CONFIRMED
                     ↘ REJECTED              ↘ REVERTED / STILL PENDING
CONFIRMED → verified Transfer recipient/ID → RECOVERY RECEIPT
```

After account/network change, clear stale proofs, selected transaction request, and displayed eligibility; refetch before signing. Smart-contract wallets and hardware wallets are supported intentionally, not treated as bots because they have code.

Error copy:

- User rejects: “Nothing was submitted. Your identity selection is still here.”
- Insufficient funds: “This recovery needs the mint amount plus the network fee.”
- Wrong network: “This archive is on Base. Review the network switch in your wallet.”
- ID taken: “Another transaction recovered this identity first. Choose another record.”
- Wallet cap: “This wallet has used its two paid recoveries. Transfers do not reset that limit.”
- RPC failure: “We cannot confirm archive status. Check your transaction before trying again.”
- State advance: “This changes the character's story state permanently. It does not change Access or rarity.”

Success: “GHOST//1842 RECOVERED. I WAS HERE.” Show actual transaction hash, verified ID and recipient, portrait download, and inspect-record link. Never say confirmed based only on a returned transaction hash or a server response.

## Security and accessibility acceptance

Use a reviewed CSP with script nonces/hashes, no unsafe eval, self-hosted fonts, `object-src 'none'`, `base-uri 'none'`, `frame-ancestors 'none'`, and explicit image/RPC/WalletConnect endpoints. Derive required connector iframe/connect allowances from real integration tests. Do not insert arbitrary RPC URLs or metadata HTML into the page. Treat NFT metadata and puzzle submissions as untrusted input; escape text, sanitize necessary rendered Markdown, block scripts, and disallow user-controlled redirect destinations.

Keep third-party analytics off transaction routes. Restrict RPC credentials to their service/origin where supported; a public frontend identifier is not a secret credential. Pin dependencies and lockfile, verify build provenance, scan the built bundle for secret patterns, isolate CI signing credentials, and keep deployment keys outside the repository.

Initial application API limits: public archive endpoints 60 requests/minute per client; challenge submissions 5/minute per session and wallet; no more than 3 in-flight submissions; request timeout 10 seconds; body limit 16 KiB. Support NAT/shared-network accessibility and bounded appeal/manual review; these are implementation defaults to tune in staging, not live testing authorization or strong Sybil prevention.

Keyboard access, visible focus, real labels, contrast testing, readable 16px mobile prose, reduced-motion support, alt text, and announcement of transaction status are release criteria. At 390px width, portrait precedes dossier, action drawer has no horizontal scrolling, and wallet switch-back restores the pending transaction view. Test desktop extensions, mobile in-app wallets, WalletConnect return flow, and a fresh smart-account wallet on testnet.

The canonical domain is prominently displayed after it is actually selected and controlled. Display “We never ask for your recovery phrase or private key” near wallet actions. Support does not send mint links by direct message.
