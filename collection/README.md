# GHOST//ROOT — design release 0.1.0

**You were never supposed to find them.**

This is the canonical creative and engineering specification requested for the first project phase. It is not a deployed collection or a production-readiness certificate. No final portraits, production metadata, contracts, wallet integration, storage uploads, or chain transactions have been produced.

Read in this order:

1. [Collection Bible](collection-bible.md) — thesis, identity, supply, culture, and creative rules.
2. [Art Bible](art-bible.md), [lore](lore.md), and [Genesis dossiers](genesis.md).
3. [Trait architecture](trait-architecture.md), [machine-readable traits](traits.yaml), [rarity model](rarity-model.md), and [target frequencies](rarity-report.csv).
4. [Technical implementation plan](technical-implementation-plan.md), [contract specification](contracts/README.md), [evolution](dynamic-states.md), and [mint experience](mint-site/experience.md).
5. [Launch plan](launch/launch-plan.md), [security review](security/security-review.md), and [prelaunch gates](security/prelaunch-checklist.md).

`collection.yaml` and `traits.yaml` use JSON syntax, a valid subset of YAML 1.2, so the offline checker needs only Python's standard library. Source references were checked on 2026-09-07; exact dependency versions and deployment parameters remain release gates.

Run the design check from this directory:

```sh
python3 scripts/validate_design.py
```

It checks quotas, dependency references, reserved characters, and a deterministic full-supply assignment witness. It cannot establish visual quality, rendered uniqueness, production metadata validity, or contract security. `rarity-report.csv` reports design targets, not measured final-art frequencies. See [validation evidence](security/design-validation.md) for results, rejection controls, hashes, and limits.

Decisions: 3,333 identities; symbol `GHRT`; Base ERC-721 recommended; precommitted media for four holder-selected sequential states; 201 disclosed reserves; 3,132 paid allocations; proposed flat 0.004 ETH mint price subject to budget review before deployment. Price, dates, legal clearance, team assignments, canonical domain, and production addresses are not final.
