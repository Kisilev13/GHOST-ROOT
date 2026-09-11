# Deployment record

No production contracts have been deployed. No production wallets, treasury, domain, storage
identifiers, or production dates have been created. A disposable **devnet-only** rehearsal has
been deployed (below); it proves the deployment path, not production readiness — visual QA
remains `REPEAT_VISUAL_PROTOTYPE_STAGE`.

| Item | Value / status |
| --- | --- |
| Production chain | Solana, mainnet-beta |
| Standard | Metaplex Core (mpl_core) |
| Rehearsal chain | Solana devnet |
| Royalty | 500 bps (5%) |
| Testnet collection | **DEPLOYED, DEVNET ONLY, DISPOSABLE** — `5Cd1TTYVm9Z3rviKREP4iWRcrPVRgad9Jdmk3gghyRSc` ("GHOST//ROOT DEVNET"), one canary asset (`GHOST//0001`, `CL1uGYY47uGMibazR6PfFKjj7eqaF9VJjpk7VcvL7yfR`) minted and independently verified as a collection member; royalty read back at 500 bps. Rehearsal ran on a local `solana-test-validator` that cloned the real `mpl-core` program from public devnet (its faucet was confirmed dry at rehearsal time — see `collection/devnet/deployment-receipt.md` for the full explanation and all addresses/signatures). Holds no value; never confuse with the production line below. |
| Production collection (Core collection asset) | UNDEPLOYED |
| Candy Machine | UNDEPLOYED |
| Dedicated deployer wallet | UNASSIGNED |
| Treasury multisig | UNASSIGNED |
| Mint-operations multisig | UNASSIGNED |
| Chapter-attestor multisig | UNASSIGNED |
| Genesis archive custody | UNASSIGNED |
| Canonical domain | ghostroot.site (see [[opensea-integration]] / live site — not yet reflected in an on-chain record) |
| Media CID | NOT UPLOADED |
| Metadata CID | NOT UPLOADED |
| Arweave backup transaction | NOT UPLOADED |
| Verified collection address / explorer URL | NOT AVAILABLE |
| Deployment transaction signature | NOT AVAILABLE |
| OpenSea collection slug | NOT RESOLVED — see `opensea/reports/discovery.md` (`AWAITING_SOLANA_COLLECTION_DEPLOYMENT`) |

## Superseded

**2026-09-10:** prior design phase (through `release_version 0.1.0`, 2026-09-07) recommended
Base (chain ID 8453) / Base Sepolia (84532) / ERC-721 as the production chain, with an
already-documented Solana/Metaplex Core alternative (`solana-alternative.md`). That
recommendation is superseded — GHOST//ROOT is a Solana / Metaplex Core collection. The live
ghostroot.site site, its OpenSea secondary-market integration, and this file are now
Solana-only. No EVM contract exists or is planned; OpenSea is a secondary marketplace, never
the mint engine.

Fill the table above from verified deployment receipts and independent parameter review. Copy
known addresses, never type a production recipient from memory. Preserve superseded records
with dates; do not overwrite history to conceal a configuration change.
