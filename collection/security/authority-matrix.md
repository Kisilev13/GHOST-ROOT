# Authority matrix — recommended Base implementation

All addresses are unassigned. This is the intended authority policy to verify against source and deployment; it is not a record of completed revocation.

| Capability | Authority | Needed after mint? | Planned action / limit |
| --- | --- | --- | --- |
| Contract upgrade | None; no proxy | No | No upgrade path in edition contract |
| Collection metadata root | Constructor-only storage | No | No setter after deployment |
| Individual metadata path | Contract state machine | Yes | Select only among four fixed directories |
| State advancement | Current token owner | Yes | One forward step; never an approved marketplace operator |
| Early chapter eligibility | Separate 2-of-3 attestor multisig | Temporarily | Only scoped attestations; automatic public fallback removes permanent dependence |
| Origin traits / rarity | None after media commitment | No | Frozen source media and JSON at constructor CID |
| Royalty rate / destination | Constructor-fixed 500 bps and treasury | No mutation needed | No setter; royalty payment remains marketplace-dependent |
| Transfer / approval | Token owner and normal ERC-721 approvals | Yes | Standard ERC-721 rules; no team seizure delegate |
| Freeze / forced burn | None | No | Not implemented |
| Mint availability | 2-of-3 mint-operations owner | Until fixed close | Pause/resume mint/claims only; record events; retire administration after reconciliation |
| Price / supply / sale dates | Constructor constants/storage without setters | No mutation needed | Fixed; cannot reopen unsold inventory |
| Allowlist / reserves | Fixed Merkle roots | Until fixed close | Claims limited by leaf and global counters; no owner replacement root |
| Reserve recipient | Fixed leaf for each ID | Until claimed | No arbitrary receiver override |
| Withdrawal trigger | Treasury or mint owner | Yes | Always pays immutable treasury; cannot redirect |
| Treasury custody | Dedicated 2-of-3 multisig | Yes | Hardware-wallet custody, independent signers, published address |
| Website/fiction content | Restricted publishing team | Yes | Cannot alter committed media or contract state; protect domain/build pipeline |

Ownership renunciation must not disable treasury-triggered withdrawal or holder progression. Prove this in tests before using it. The constructor attestor address may itself be a multisig with changeable internal signers; disclose that external key-management power. Its on-chain effect remains bounded by the token contract.

Founders' proposed 180-day no-transfer period is a public commitment, not an enforced lock in the current design. If actual vesting is desired, add a separately reviewed recipient/vesting design before roots and terms are finalized. Do not silently install transfer restrictions on the whole collection.

Genesis archive custody can transfer those three tokens under the archive multisig's ordinary owner powers. Publish policy and any transfer; do not describe those assets as irreversibly locked.

Solana alternative authorities and controller tradeoffs are in `../solana-alternative.md`.
