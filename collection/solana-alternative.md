# Solana — the production chain and its authority tradeoffs

**Superseded 2026-09-10: this file's authority/guard analysis is now the production plan, not
an alternative.** GHOST//ROOT is a Solana / Metaplex Core collection; Base is not used. Use
**Metaplex MPL Core** and **Core Candy Machine**, rehearse on devnet before mainnet, and keep
this file — not `deployment-addresses.md`'s now-corrected Base row — as the authority-model
reference. Do not deploy any EVM contract as a competing or fallback edition.

Core provides asset/collection accounts with plugins. Core Candy Machine distributes Core assets and Candy Guards add paid-mint constraints. [Core overview](https://www.metaplex.com/docs/smart-contracts/core), [Core Candy Machine](https://www.metaplex.com/docs/smart-contracts/core-candy-machine).

Proposed guards: start/end dates, allowlist, mint limit, allocation and SOL payment. Use a shared mint-limit counter identity across sale groups where supported and prove its cross-group behavior. Do not assume independently configured groups share limits. Reserve claims must consume the same finite edition inventory; owner-created extras are not an acceptable hidden allocation. Test every enabled/default group, guard bypass path, authority instruction, and direct creation path.

Exact-ID selection differs from normal vending-machine distribution; preserve the visible-art selection experience only if the chosen Core mint path can verify the intended asset reliably. Otherwise explicitly redesign distribution and explain its fairness limits before collectors commit. Do not claim stock Candy Machine implements an arbitrary-ID sale interface without proving that behavior.

| Capability | Proposed authority | Needed after mint? | Planned action |
| --- | --- | --- | --- |
| Collection update / membership | Dedicated 2-of-3 multisig during construction | Only if retained by design | Revoke or transfer to a reviewed constrained program after rehearsing all membership paths |
| Asset metadata evolution | Constrained controller PDA | Yes for state transitions | Only owner-consented transitions among committed URIs; no general URI setter |
| Royalty policy | Explicit plugin authority | Only if intentionally mutable | Set and freeze intended policy; disclose actual marketplace/transfer restrictions |
| Freeze / forced transfer / forced burn | None | No | Do not install permanent powers or hidden delegates |
| Mint config | Dedicated launch multisig | Until close/reconciliation | Disable minting and remove unused privileges; archive configuration |
| Treasury | Separate multisig | Yes | Published payment destination, hardware-wallet signers |
| Program upgrade | Multisig during development | No for immutable release | Independently review, then revoke if controller behavior is final; disclose any retention |

The stock Core collection does **not** by itself establish this project's immutable maximum or holder-only four-state policy. A Candy Machine's item cap does not prove that a retained collection authority cannot add assets through another path. Core plugins have distinct owner/authority/permanent management rules; inspect them rather than treating every delegate as equivalent. [Core plugins](https://www.metaplex.com/docs/smart-contracts/core/plugins).

To retain dynamic evolution while removing arbitrary mutation, a reviewed controller must own the relevant authority and validate collection, asset, current owner signature, current state, target URI commitment, and replay protection on every CPI. Test wrong program/account owners, substituted collections, unchecked CPI targets, unsafe remaining accounts, signer seeds, delegate inheritance, and upgrade authority. No generic custom-plugin assumption: use supported Core mechanisms and a separately reviewed Solana program where necessary.

Alternatively retain a multisig update authority and disclose that the team can rewrite metadata. That is simpler operationally but does not meet the recommended immutable-media authority promise; it must be an explicit product-spec revision.

JSON must follow the current Core schema, including media and normalized attributes. Collection verification is established by on-chain collection membership, not a self-asserted JSON label. Validate on devnet and with intended marketplace rendering. [Core JSON schema](https://www.metaplex.com/docs/smart-contracts/core/json-schema).
