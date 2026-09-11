# Trait architecture

`traits.yaml` defines all **63 origin traits across 11 categories**. Each token selects exactly one value in each category, including explicit NONE values. Every trait has the requested ID, name, category, weight, maximum, requires, excludes, and tags, plus its exact target count, percentage, rarity information score, and visual importance.

## Category contract

| Category | Options | Importance, 1–5 | Meaning |
| --- | ---: | ---: | --- |
| Entity | 4 | 5 | Class-specific anatomy |
| Access | 4 | 1 | Historical permission; correlated with Architecture |
| Architecture | 6 | 5 | Composition/topology, determines band |
| Face Material | 6 | 4 | Physical surface and construction |
| Eyes | 6 | 4 | Optical geometry |
| Interface | 6 | 3 | Face-mounted structure, including NONE |
| Implant | 7 | 3 | Attached mechanism, including NONE |
| Mantle | 6 | 4 | Shoulder silhouette; includes signature collar |
| Background | 7 | 2 | Archive location and depth |
| Signal | 5 | 1 | Origin signal condition; fixed, not a live telemetry reading |
| Origin Corruption | 6 | 2 | Recovered surface condition; independent of current State |

State is separate mutable narrative data with four legal values. Shard, fingerprint, token ID, wallet, edition number, and generation seed are not scored traits. No arbitrarily unique hex signature may inflate rarity.

## Rule language

Requirements are an AND of groups. Within each `any_of` group, at least one listed trait must be selected. Each group refers to one category. An empty list means no requirement. Exclusions mean no co-occurrence, regardless of which side declared the exclusion. Unknown references, cycles in normal evaluation order, or ambiguous expressions fail validation.

```yaml
id: interface.respirator
display_name: RESPIRATOR
category: interface
weight: 600
max_count: 600
requires:
  - any_of: [entity.human, entity.synthetic]
excludes: []
tags: [interface]
```

`weight` is a relative planning preference. It is not a percentage and is not multiplied independently across categories. Exact quota assignment takes precedence. Here `max_count = target_count`; a final render missing even one target is rejected. If constraints make a quota impossible, revise and version the design; never silently relax a maximum.

## Critical compatibility rules

| Trait | Requires | Excludes / rationale |
| --- | --- | --- |
| BIO-SYNTH | HUMAN or SYNTHETIC | Material must support living/reconstructed tissue |
| PORCELAIN / CERAMIC | HUMAN, SYNTHETIC, or HOLLOW | SPECTER uses carbon, reconstructed, or phase-glass volume |
| PHASE GLASS / SIGNAL-BURN eyes | SPECTER | Needs class-specific refractive/double-contour anatomy |
| BIOMETRIC / THERMAL eyes | HUMAN or SYNTHETIC | Uses intact optical socket geometry |
| OPTICAL ARRAY eyes | SYNTHETIC or HOLLOW | ANTENNA implant excluded to avoid socket collision |
| RESPIRATOR | HUMAN or SYNTHETIC | Cannot be pasted across the HOLLOW void |
| NEURAL VEIL | SYNTHETIC, HOLLOW, or SPECTER | Shape-adapted volume required |
| SKELETAL INTERFACE | HOLLOW or SPECTER | SIGNAL CROWN excluded to keep the opening readable |
| SPINAL BUS | Any eligible class | NULL MASK excluded due to connector occlusion |
| ROOT PORT | SYSTEM or ROOT access | Restricts privileged historical hardware |
| MEMORY SPINDLE | ADMIN, SYSTEM, or ROOT access | Retained memory hardware |
| SIGNAL CROWN | ROOT access | SKELETAL INTERFACE excluded |
| UNSUPPORTED FRAME | SPECTER | Requires phase-volume anatomy |
| SCAN SHEAR | Any eligible class | FRACTURED eyes excluded to limit competing distortion |
| ABSENCE | HOLLOW or SPECTER | Real missing volume |

These are art constraints, not technical claims about real technology. Additional asset-level collision masks can reject a composition; they cannot authorize an illegal trait combination.

## Architecture/access joint table

| Architecture | USER | ADMIN | SYSTEM | ROOT | Total |
| --- | ---: | ---: | ---: | ---: | ---: |
| SEALED | 1,800 | 0 | 0 | 0 | 1,800 |
| INSET | 600 | 300 | 0 | 0 | 900 |
| DOUBLE PLANE | 0 | 420 | 0 | 0 | 420 |
| SUSPENDED CORE | 0 | 0 | 180 | 0 | 180 |
| SEVERED HALO | 0 | 0 | 0 | 30 | 30 |
| SINGULAR | 0 | 0 | 0 | 3 | 3 |
| Total | 2,400 | 720 | 180 | 33 | 3,333 |

This joint structure prevents accidental common ROOTs and double-counting access scarcity. It also makes quota feasibility explicit instead of hoping random draws converge.

## Reservations and accounting

Initialize IDs 1–3 from `genesis.yaml`. Initialize all 30 explicit slots in `legendary-reservations.yaml` before normal generation; their ROOT/SEVERED HALO assignments are fixed. Complete and approve their remaining vectors before production generation. The current checker may fill those unfinished fields only to demonstrate feasibility; those suggestions are not approved character art direction.

The 198 other reserve IDs have fixed band proportions in `collection.yaml`. All fixed values consume the same global quotas as ordinary tokens. For example, Genesis already consumes one SYNTHETIC, one HOLLOW, one SPECTER, three ROOT, and three SINGULAR; it does not create extra counts above the table.

## Production solving

Apply fixed reservations, then fill categories in the declared dependency order using a deterministic capacity-constrained matching solver with backtracking across categories where necessary. After each full candidate, verify all rules in both directions, every exact quota, every special ID, and all genotype duplicates. Fail closed on exhausted search. Assign semantic art variants only from approved libraries; never tweak a fingerprint to make a duplicate pass.

The included offline checker builds a full assignment witness with capacity matching and a bounded retry strategy. Its success establishes that these current quotas and declared trait rules can coexist. It does not prove that all layered artwork variants exist or that the resulting images are sufficiently different.
