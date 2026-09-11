# Incident runbook

Status: operational template; actual people, addresses and contact paths must be assigned before release. Role labels alone do not meet the launch gate.

| Role | Owner | Responsibility |
| --- | --- | --- |
| Incident commander | UNASSIGNED | Declare incident, coordinate actions and review resumption |
| Contract / wallet responder | UNASSIGNED | Inspect chain events, coordinate mint-operations multisig |
| Web / infrastructure responder | UNASSIGNED | Disable affected UI, isolate deployment, restore verified build |
| Storage custodian | UNASSIGNED | Re-pin verified CIDs and restore CAR archives |
| Communications lead + backup | UNASSIGNED | Publish factual updates on previously established channels |

## Detection and first response

Alert on unexpected owner/authority activity, unexplained mint/state failures, treasury mismatch, deployment change, content-hash mismatch, stale indexing, gateway outages, phishing reports, and compromised project accounts. Treat price/floor movements as market activity, not a security alert by default.

On a credible report: timestamp it, preserve minimal logs/transaction hashes, identify the affected component, and pause mint functions if the mint-operations multisig judges that necessary. Disable the mint UI if its integrity is uncertain. Stop scheduled promotion. Never ask holders to transfer tokens, approve an emergency contract, share a recovery phrase, or use a new DM link.

## Scenario actions

| Scenario | Action | Limits |
| --- | --- | --- |
| Frontend or domain compromise | Remove signing UI, revoke publishing sessions, restore a verified build from signed hashes | A restored site does not reverse submitted transactions |
| Treasury or mint key concern | Convene remaining multisig signers, inspect pending transactions and role permissions | Immutable treasury cannot be swapped by the token contract; multisig key management must be rehearsed |
| Bad chapter attestations | Stop issuing new attestations, explain bounded early-access effect | Already valid early access cannot be clawed back; fixed public fallback remains available |
| Gateway/pin loss | Re-pin original CAR under the same CID; switch display gateway after hash check | Never substitute new media for a frozen CID |
| Marketplace indexing lag | Verify chain/URI first, request documented refresh and show last indexed block | Do not change token state merely to manipulate an indexer |
| Contract defect | Pause remaining mint paths, obtain independent assessment, publish verified facts | Nonupgradeable bytecode has no patch lever; transfers/evolution are not administratively frozen |
| Social compromise or fake collection | Use other pre-established channels, revoke compromised access, report through platform processes | No retaliation, third-party probing, or invented “rescue mint” |

## Communications and recovery

Real security notice format: affected service; first observed time/timezone; verified impact; actions taken; what holders should check; next update time; official unchanged addresses. Use plain language rather than story roleplay. Do not publish victim details, secrets, or unconfirmed loss figures.

Resume only after the affected controls are verified, independent review is obtained where appropriate, the multisig records its decision, and the existing sale window still permits minting. No unilateral reopening or collection replacement. Publish a postmortem with confirmed timeline, root cause, bounded impact, remediation, and remaining limits.

## Routine post-mint cadence

During mint: staffed transaction and availability monitoring. First week after close: daily aggregate failure/metadata review. Thereafter: weekly storage/indexing checks and monthly access/backup review, adjusted to incident history and actual budget. Minimize retained telemetry and avoid collecting unrelated holder data.

Archive signed `release-manifest.json`, `deployment-addresses.md`, `authority-matrix.md`, `security-review.md`, incident logs, and postmortems. Store sensitive evidence privately, with redactions and access controls. Production evidence must live under the explicitly authorized workflow's timestamped lane.
