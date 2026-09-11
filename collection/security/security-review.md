# GHOST//ROOT — design security review

Review date: 2026-09-07. Scope: the collection design and planned implementation. No deployed contract, mint website, final media, or live account was tested. No confirmed implementation vulnerabilities or security certification are claimed. The risks below are requirements to resolve and prove in subsequent engineering.

## Trust boundaries and principal risks

| Risk | Concrete failure mode | Required control | Proof before release |
| --- | --- | --- | --- |
| Hidden supply path | Reserve helper or owner mint bypasses paid cap | Single lifetime issuance accounting; fixed ID ranges and reserve commitments | Unit/fuzz/invariant exercise of every external mint path |
| Mint cap reset | Balance-based check lets transfer-outs restore allowance | Two independent per-phase paid counters (early + public, 5 each), never balance-derived | Mint/transfer/mint and AL/public interleavings; sixth mint in a phase must fail |
| Reentrant mint | Receiver callback remints selected IDs or exceeds quantity/cap | Full batch effects before callbacks; nonreentrant external paths | Adversarial receiver tests with last available IDs |
| Payment redirection | Admin or frontend substitutes treasury | Constructor treasury with no recipient setter; chain-derived transaction details | Verify deployment arguments and receipt accounting |
| Metadata replacement | Team points holders to arbitrary new media | Constructor-only CID; four committed state paths | ABI/write-path inspection and URI invariant tests |
| Unauthorized state advance | Approved operator or stale proof mutates another holder's identity | Current-owner caller check; fixed step/schedule; domain-bound token claim | Cross-account and transfer/approval/replay controls |
| Compromised attestor | Team signs false puzzle eligibility | Authority only grants early transition within fixed dates; public fallback | Forged, wrong-domain, missing-signature and fallback tests |
| Centralized mint censorship | Mint owner pauses legitimate claims | Publicly documented narrow pause role and fixed sale end | Test transfers/evolution unaffected; publish role addresses |
| Frontend compromise | Wallet signs a substituted contract or unexpected value | Canonical domain/address, reviewed CSP/build, source verification | Fresh-wallet rehearsal and exact transaction inspection |
| Supply-chain compromise | A package/postinstall/CI step leaks credentials or alters wallet calls | Exact dependency pinning, lockfile, provenance review, minimal CI secrets | Dependency review and reproducible build record |
| Storage failure | Files disappear despite an unchanged CID | Two independent pins, offline CAR, funded renewals | Full hash readback and restore rehearsal |
| Art/reveal manipulation | Team replaces reserves or advertises random fairness | Pre-mint published art, exact selection, fixed reserve record | Compare release manifest to contract/public UI |
| ARG boundary failure | Players probe real hosts or encounter real secrets | Isolated synthetic labs, published scope, egress restrictions | Independent puzzle review and safe local walkthrough |
| Physical card impersonation | Cloned NFC URL represented as identity proof | Card is an art link; wallet ownership proof is separate | Clone demonstration and accurate product copy |

## Account and key handling

Use a dedicated deployer with only necessary funding and no unrelated valuable assets. Prefer hardware wallets and separate multisigs for treasury, mint operations, and chapter attestations. Proposed thresholds are 2-of-3 with independent custody; roles and actual signers must be assigned and rehearsed. A multisig does not help if every signer shares one compromised machine or cloud backup.

Never place seed phrases, keys, production secrets, recovery files, or signing material in code, configs, prompts, NFT metadata, repository history, or frontend bundles. Public addresses and content hashes are appropriate release data. Keep deployment signing interactive through a hardware wallet/approved signer; do not build a web admin endpoint that accepts a private key.

Use phishing-resistant account security for source control, domain registrar, DNS, hosting, X, and Discord. Limit publishing permissions. No support agent asks a collector for a key, seed phrase, wallet approval, or “verification payment.”

## Review plan

Build locally and test with synthetic accounts first. Before any live devnet/testnet workflow, define exact owned assets/accounts, network/RPC scope, authorization acknowledgement, rate/concurrency/request/time caps, evidence directory, and kill conditions under the user's workspace rules. No program scope or authorization is fabricated here.

Recommended owned-test workflow cap: one pending transaction per test wallet, at most 50 submitted test transactions per rehearsal, at most 2 read requests/second, concurrency 2, 30-second RPC timeout, and 30-minute rehearsal timeout. Stop on wrong chain, unexpected destination/value, unexpected signature request, unexpected real-user data, repeated provider errors, or failed invariants. These defaults are a proposed future test plan, not authorization to run it.

For any authorization claim, prove owner A succeeds, owner B succeeds, A cannot mutate B, unauthenticated/unsigned attempts fail, and an approved operator or stale former owner is rejected for holder-only evolution. Use only the test accounts. Save minimal reproducible requests/receipts with secrets and unrelated PII redacted.

An independent reviewer should inspect the final bytecode/source, deployment parameters, all mint paths, metadata immutability, signatures, time boundaries, emergency powers, dependencies, and frontend transaction construction. Resolve all material findings, rerun the affected tests, and archive review/commit hashes before production.

## Residual limits

Wallet caps do not establish personhood. Exact-art selection is subject to transaction ordering. RPC or sequencer outages can interrupt access. Marketplaces may cache stale metadata or ignore royalties. The immutable contract cannot receive a patch. The team controls early puzzle attestations and off-chain narrative publication. The optional NFC URL is clonable. State and ownership are public; private profiles are not needed to participate.

These limits belong in clear collector-facing protocol copy. They are not grounds to invent a critical vulnerability, a guaranteed exploit, or an unsupported “fully trustless” label.
