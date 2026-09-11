# Design validation evidence

Completed: 2026-09-07T09:28:01Z. Environment: local Python 3.14.7. Scope: specification data and an offline assignment witness. No internet-facing tests or blockchain transactions were submitted.

| Check | Result |
| --- | --- |
| Allocation sum | 3,333 |
| Rarity-band sum | 3,333 |
| Trait categories / values | 11 / 63 |
| Exact per-category quotas | All sum to 3,333 and match the witness |
| Dependency references and ordering | Valid |
| Genesis full vectors | All three legal and preserved |
| Special reservations | 3 Genesis plus 30 legendary slots preserved |
| Full-supply assignment | 3,333 complete legal origin vectors |
| Repeated full vectors | Zero after 22 deterministic quota-preserving visible-trait swaps |
| Reserve-band audit | 108 common, 54 uncommon, 26 rare, 10 epic outside Genesis |
| Independent CSV recount | Passed |
| Repeated same-environment build | Identical hashes for witness, rarity report, and design-check JSON |
| Malformed input controls | Wrong quota, unknown rule ID, illegal Genesis combination, allocation overflow, duplicate special ID all rejected |
| Structured file parse | Seven JSON / JSON-compatible YAML files parse |
| Local Markdown links | No broken targets at verification |

Commands: `python3 -B scripts/validate_design.py`, followed by an independent CSV count/reserve audit and file SHA-256 comparison. Input-rejection controls used in-memory copies; canonical configuration was not altered. Final specification data was not relaxed to make validation pass.

Artifact hashes:

```text
design-witness.csv  47796ae444caecd0c69365c87dea2d7dae47cd4ae7964d6d74f565e042e1f4b2
rarity-report.csv   2504dc2a1a5103c5577bc8619701b391086b4613304a2bc76b55f99b16e64d5a
design-check.json   51e39460ebc09a85d32954c7e1d376a0da83d46ae8675b00b5a8986638ffbc85
```

## Limits

The witness is not the production generation manifest. Unfinished legendary traits filled by the solver are feasibility suggestions, not approved character specifications. The check proves feasibility for the declared rules, not a globally complete constraint-solving algorithm for arbitrary future rules.

No rendered-image duplicates, dimensions, anatomy, material continuity, contact sheets, production metadata URIs, CID availability, JSON Schema engine validation, contracts, wallets, or testnet behaviors have been checked. Those remain explicit gates. No security vulnerability, security certification, testnet success, or mint readiness is inferred from these design results.

Next highest-value implementation step: approve four entity bases and 12 common portraits against the art bible, while completing the 30 legendary vectors. The production generator and contract can then be built against approved source assets and this verified specification.
