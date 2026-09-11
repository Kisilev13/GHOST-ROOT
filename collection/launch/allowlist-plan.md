# Allowlist — 1,110 paid eligibility slots

The allowlist grants one paid mint during a 48-hour phase from nonreserved inventory. It does not guarantee a particular identity, a legendary, a discount, or financial advantage. A wallet may later make one more paid mint in public, for a lifetime total of two.

| Route | Slots | Evidence of participation |
| --- | ---: | --- |
| Archive discovery | 444 | Completed puzzle path or an equally weighted accessible narrative-analysis route |
| Meaningful contributions | 333 | Accepted translation, archive accessibility work, original writing, tooling, or substantive visual critique |
| Relevant partner communities | 222 | Actual agreed communities with disclosed criteria, one reviewed submission per person |
| Juried creative responses | 111 | Original response to “I was here,” judged on concept and craft rather than likes |
| Total | 1,110 | Initial slots; all unused mint capacity rolls into public inventory |

No route requires paying for another collection, inviting users, daily posting, solving a real intrusion, or buying a physical card. Do not conflate these 333 contribution-based paid slots with the separate 99 compensation tokens reserved for artists/contributors.

## Selection and abuse controls

Accept one application record per participant and one final wallet binding. Score on published criteria; deduplicate clearly repeated submissions with human review; provide an appeal path and reasonable time-zone windows. If a route is oversubscribed, choose by a published rubric and documented tie-resolution procedure before final wallet selection. Do not invent an unverified random draw claim.

Known duplicate applications cannot accumulate multiple awards, but address caps are not personhood proof. Do not promise Sybil elimination. Rate-limit submissions, use one-use session nonces and expiring signed wallet binding, and review copied work. CAPTCHA, IP addresses, or wallet funding patterns alone are not proof of identity and should not be sole grounds for exclusion.

Wallet signatures bind canonical domain, chain, action `bind-allowlist`, one-use nonce, and expiration. Verify smart-contract wallets through a standard contract-signature path. No token approvals or transfers are requested. Support a wallet correction deadline before root freeze; after freeze, the published rules control eligibility.

Publish allocation counts and rubric results using consented pseudonyms. Keep application contact data and unnecessary wallet links out of public CSVs. Deliver Merkle proofs only to the eligible wallet through authenticated retrieval or a downloadable signed record; an unprotected address enumeration API is unnecessary. The root and smart-contract claims are publicly verifiable, but a full applicant database is not part of the NFT metadata.

Before deployment, finalize selected wallets and 201 reserve recipients, freeze and review both roots, and reproduce them from signed source records. If Merkle leaves bind the contract address, compute the ordinary CREATE address from the dedicated deployer's fixed nonce before building the trees, verify it on rehearsal, and abort deployment if the nonce changes. Do not create a CREATE2 init-code/root circular dependency. Record leaf encoding, hash scheme, sorted-pair policy, and root in the release manifest.

Use exact, nonoverlapping sale timestamps and fixed root verification on-chain. The frontend is an aid; calling the contract directly must enforce the same limits. Test repeated claims, incorrect leaf parameters, replay after transfer, AL/public combined caps, and competing transactions for the final ID and final allowlist slot.
