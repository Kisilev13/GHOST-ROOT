# GHOST//ROOT — Solana devnet canary rehearsal receipt

**DEVNET ONLY. Every address below holds no value.** This is a disposable
rehearsal of the Metaplex Core deployment path, not the production
collection. Visual QA remains `REPEAT_VISUAL_PROTOTYPE_STAGE` — this
rehearsal is deliberately independent of that gate. See `collection/devnet/README.md`.

## Network

The **public Solana devnet faucet was confirmed dry** at rehearsal time —
`requestAirdrop` returned `"the airdrop faucet has run dry"` directly from
`api.devnet.solana.com`, for every request size down to 1000 lamports, across
several retries and a UTC day rollover. The official faucet's own
`https://faucet.solana.com` "I am an AI Agent" guidance names exactly three
sanctioned programmatic paths for agents: the same CLI airdrop (exhausted),
a proof-of-work faucet (`devnet-pow`, whose own bootstrap step calls the same
exhausted airdrop endpoint before it can mine), and a **local validator**
("unlimited SOL, no rate limits"). This rehearsal used the third: a local
`solana-test-validator` that **cloned the real `mpl-core` program live from
public devnet** (`CoREENxT6tW1HoK8ypY1SxRMZTcVPm7R94rH4PZNhX7d`, verified
`executable: true` on public devnet before cloning) — the identical on-chain
program, same behavior, running on a private local ledger instead of the
shared public cluster. `explorer.solana.com` links below therefore only
resolve from this machine while the validator is running; they are included
for completeness, not as public proof-of-deployment links.

| | |
| --- | --- |
| Cluster | `local-devnet-clone` (see network note above) |
| RPC | `http://127.0.0.1:8899` |
| Cloned program | `CoREENxT6tW1HoK8ypY1SxRMZTcVPm7R94rH4PZNhX7d` (mpl-core, cloned from `https://api.devnet.solana.com`) |
| Devnet signer (public) | `ARWTv4NkEdcoTzg4SyoiDqcoHMDtB8Kcw7PXNsrJfJvy` |
| Devnet signer balance before deployment | 0 SOL (public devnet) / 500,000,005 SOL (local validator genesis + funding — local-only, no value) |
| Devnet signer balance after deployment | ~500,000,004.99 SOL (local-only; minus real tx fees) |

## Collection — `GHOST//ROOT DEVNET`

| | |
| --- | --- |
| Address | `5Cd1TTYVm9Z3rviKREP4iWRcrPVRgad9Jdmk3gghyRSc` |
| Update authority | `ARWTv4NkEdcoTzg4SyoiDqcoHMDtB8Kcw7PXNsrJfJvy` (devnet signer) |
| Metadata URI | https://raw.githubusercontent.com/Kisilev13/GHOST-ROOT/b4e4258fcad3b1ede358448c39a9eb7dbb29c854/collection/devnet/metadata/collection.json |
| Royalty | **500 bps (5%)**, recipient `ARWTv4NkEdcoTzg4SyoiDqcoHMDtB8Kcw7PXNsrJfJvy`, ruleSet `None` |
| Creation tx | `SXFjsPUEiJ4odoQ4wgHUrL6Fk8MPbbpuaD3KYd2MbNNxcTaU4VKMpU5pVrUgn8dVKwPXyvyTGZxaMeSUyPEoL1S` |
| Explorer (local only) | https://explorer.solana.com/address/5Cd1TTYVm9Z3rviKREP4iWRcrPVRgad9Jdmk3gghyRSc?cluster=custom&customUrl=http%3A%2F%2F127.0.0.1%3A8899 |

## Canary — `GHOST//0001`

| | |
| --- | --- |
| Asset address | `CL1uGYY47uGMibazR6PfFKjj7eqaF9VJjpk7VcvL7yfR` |
| Metadata URI | https://raw.githubusercontent.com/Kisilev13/GHOST-ROOT/a60303f6829977d3a90ca13a234b202d9a6aa271/collection/devnet/metadata/0001.json |
| Image URI | https://raw.githubusercontent.com/Kisilev13/GHOST-ROOT/8bd80a8a7b49735b9f50ddec7dada8a69bd682fb/collection/devnet/assets/0001.png |
| Owner (post-mint) | `ARWTv4NkEdcoTzg4SyoiDqcoHMDtB8Kcw7PXNsrJfJvy` (devnet signer) |
| Collection membership | `5Cd1TTYVm9Z3rviKREP4iWRcrPVRgad9Jdmk3gghyRSc` (`GHOST//ROOT DEVNET`) |
| Mint tx | `ULsfLeGomS8JW5fLoLgFqjHcn5pz5eihywZK17mfwNPr8wDFKHnkWcUXKJ8xEZ4wJVS6KKDgXK1foRu9aoULERY` |
| Explorer (local only) | https://explorer.solana.com/address/CL1uGYY47uGMibazR6PfFKjj7eqaF9VJjpk7VcvL7yfR?cluster=custom&customUrl=http%3A%2F%2F127.0.0.1%3A8899 |

## Independent read-back (`verify_devnet_canary.ts`)

16/16 checks passed against a fresh RPC read (not deploy script stdout):
network≠mainnet, collection exists/name/uri/update-authority correct,
Royalties plugin present at 500 bps with the correct recipient, canary
exists/name/uri correct, canary's on-chain `updateAuthority` is
`{type: "Collection", address: <this collection>}` (membership), canary
owner correct, canary metadata URI resolves (HTTP 200) with a matching
`name`, canary image URI resolves (HTTP 200).

## Optional transfer test (Phase 13)

Performed once, to a second devnet-only wallet (`ghost-root-devnet-2.json`,
public `DAYR8dzFKv8Q2epVmCqH2SVXHfTzjRQVfAix6bgZWZYP`). No second NFT created.

| | |
| --- | --- |
| From | `ARWTv4NkEdcoTzg4SyoiDqcoHMDtB8Kcw7PXNsrJfJvy` |
| To | `DAYR8dzFKv8Q2epVmCqH2SVXHfTzjRQVfAix6bgZWZYP` |
| Transfer tx | `pftbBxhT5fSQDEWsfmHgtGnnsnsZrm3McxWuHocv4M4E8RRpoAAq6dMgi51PGwnYUgDSDDvpkNzZDbn9SP9Gukd` |
| Independently re-fetched owner after transfer | `DAYR8dzFKv8Q2epVmCqH2SVXHfTzjRQVfAix6bgZWZYP` — matches, PASS |

Machine-readable version: `collection/devnet/deployment-receipt.json`.
