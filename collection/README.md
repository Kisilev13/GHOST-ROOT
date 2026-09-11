# GHOST//ROOT — design release 0.1.0

**You were never supposed to find them.**

Current launch state: **PREPARED_NOT_DEPLOYED** (the separate GHOST//0001 canary already exists). Production policy is **five mints per wallet in each phase — 5 early + 5 public (independent counters, 10 combined)**, at 0.10 SOL early and 0.15 SOL public, from one 3,332-item Candy Machine inventory. Counter enforcement is prepared but not verified; SDK deployment integration is incomplete. See [launch system](launch/CANDY-MACHINE-LAUNCH.md) and [mint-limit policy](launch/MINT-LIMIT-POLICY.md). Older EVM design sections are historical references and do not override the current Solana launch configuration.

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

Current launch decisions are in `launch/launch-config.json`. Earlier Base, reserve-pool, and flat-price design choices are superseded for this Solana launch.
