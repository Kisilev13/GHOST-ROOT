# Rarity model and accounting

Rarity is a fixed publication of origin, not a prediction of resale value. The band counts and visual requirements are canonical in `collection-bible.md`; all per-trait numbers are in `rarity-report.csv`.

## Per-trait calculation

For a trait with exact target count `n` in `N = 3333`:

```text
target_percentage = 100 × n / N
expected_count = n             # exact quota target, not a Bernoulli estimate
marginal_rarity_bits = -log2(n / N)
visual_importance = authored integer from 1 through 5
```

This report is labeled `design_target`. Measured frequencies, source-image hashes, and actual counts must be generated from the frozen production manifest later. The offline assignment witness is evidence of constraint feasibility, not a render report.

Examples: HUMAN occurs 1,400 times (42.004200%); SIGNAL CROWN 30 times (0.900090%); SINGULAR 3 times (0.090009%). Three unique Genesis characters share the SINGULAR architecture trait; each character itself is an authored 1/1. A Genesis edition of one is not another scored numerical serial trait.

## Ranking policy

Primary grouping is Architecture band. Do not add Architecture and Access scores: their dependence would inflate rarity. Do not sum raw marginal probabilities as though all categories were independent.

An optional descriptive score within a band may use conditional frequencies for Entity, Face Material, Eyes, Interface, Implant, and Mantle:

```text
I_band(t) = -log2(count_of_trait_in_band / band_size)
visual_score = sum(importance(category) × I_band(trait)) / sum(importance)
```

Exclude Architecture, Access, State, Background, Signal, Corruption, ID, Shard, and fingerprint from that combined score. Correlations can still remain; label it a descriptive visual index, not an objective value or statistical independence model. Do not publish this score until actual frozen assignments exist. Do not numerically rank the three Genesis works against one another.

## Distribution protections

- COMMON portraits have full intact anatomy and the strongest clean-avatar compositions.
- Rare color is never sufficient for a higher band. Geometry and narrative authorship carry the distinction.
- 30 legendaries are approved individually; 3 Genesis are reserved explicitly.
- HUMAN diversity is reviewed for equal quality and is not a rarity taxonomy.
- 198 non-Genesis reserve tokens receive a disclosed representative band mix; all 30 legendaries remain available in paid inventory.
- No random art reveal: buyers inspect the exact identity they try to mint. Transaction ordering is competitive and does not provide equal chances.
- Evolution keeps every origin frequency unchanged. `ROOTED` can reach 3,333; `Access ROOT` remains 33.

## Required release analysis

Produce edition-wide and within-band counts, pairwise co-occurrence tables, constraint-violation reports, reserve-versus-paid distributions, exact-pixel duplicate reports, and perceptual similarity review. Recount every state to ensure origin values are preserved. A gap between targets and final counts blocks release, even when the images otherwise look good.
