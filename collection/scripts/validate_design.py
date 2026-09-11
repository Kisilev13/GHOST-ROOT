#!/usr/bin/env python3
"""Offline design validation and feasibility witness. Does not generate NFT art."""
import csv
import hashlib
import json
import math
from collections import Counter, deque
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(name):
    return json.loads((ROOT / name).read_text(encoding="utf-8"))


def need(condition, message):
    if not condition:
        raise ValueError(message)


def key(seed, *parts):
    return hashlib.sha256(json.dumps([seed, *parts], separators=(",", ":")).encode()).digest()


def legal(row, lookup, partial=False):
    chosen = set(row.values())
    for tid in chosen:
        trait = lookup[tid]
        for group in trait["requires"]:
            options = group["any_of"]
            category = lookup[options[0]]["category"]
            if partial and category not in row:
                continue
            if not chosen.intersection(options):
                return False
        if chosen.intersection(trait["excludes"]):
            return False
    return True


class Flow:
    def __init__(self, count):
        self.edges = [[] for _ in range(count)]

    def add(self, a, b, capacity):
        edge = [b, capacity, len(self.edges[b])]
        self.edges[a].append(edge)
        self.edges[b].append([a, 0, len(self.edges[a]) - 1])
        return edge

    def run(self, source, sink):
        total = 0
        while True:
            level = [-1] * len(self.edges)
            level[source] = 0
            queue = deque([source])
            while queue:
                a = queue.popleft()
                for b, capacity, _ in self.edges[a]:
                    if capacity and level[b] == -1:
                        level[b] = level[a] + 1
                        queue.append(b)
            if level[sink] == -1:
                return total
            position = [0] * len(self.edges)

            def send(a, amount):
                if a == sink:
                    return amount
                while position[a] < len(self.edges[a]):
                    edge = self.edges[a][position[a]]
                    b, capacity, reverse = edge
                    if capacity and level[b] == level[a] + 1:
                        sent = send(b, min(amount, capacity))
                        if sent:
                            edge[1] -= sent
                            self.edges[b][reverse][1] += sent
                            return sent
                    position[a] += 1
                return 0

            while sent := send(source, 10**9):
                total += sent


def validate_spec(config, spec, genesis, legends):
    n = config["supply"]
    need(n == spec["supply"] == 3333, "Supply mismatch")
    need(config["token_ids"] == {"first": 1, "last": n}, "ID range mismatch")
    need(sum(a["count"] for a in config["allocations"]) == n, "Allocation sum mismatch")
    need(sum(b["count"] for b in config["rarity_bands"]) == n, "Band sum mismatch")
    lookup = {t["id"]: t for t in spec["traits"]}
    need(len(lookup) == len(spec["traits"]), "Duplicate trait IDs")
    categories = spec["category_order"]
    need(len(set(categories)) == len(categories), "Duplicate categories")
    need(set(categories) == {c["id"] for c in spec["categories"]}, "Category mismatch")
    required = {"id", "display_name", "category", "weight", "max_count", "requires", "excludes", "tags"}
    for trait in lookup.values():
        need(required <= trait.keys(), "Missing required trait fields")
        need(trait["category"] in categories, "Unknown category")
        need(0 < trait["target_count"] == trait["expected_count"] <= trait["max_count"], "Invalid quota")
        need(trait["weight"] >= 0 and 1 <= trait["visual_importance"] <= 5, "Invalid weight/importance")
        need(abs(trait["target_percentage"] - 100 * trait["target_count"] / n) < 0.000001, "Incorrect percentage")
        need(abs(trait["rarity_score_bits"] + math.log2(trait["target_count"] / n)) < 0.00000001, "Incorrect rarity bits")
        refs = list(trait["excludes"])
        for group in trait["requires"]:
            need(set(group) == {"any_of"} and group["any_of"], "Invalid requirement group")
            refs.extend(group["any_of"])
            need(all(x in lookup for x in group["any_of"]), "Unknown requirement")
            rc = {lookup[x]["category"] for x in group["any_of"]}
            need(len(rc) == 1, "OR group crosses categories")
            need(categories.index(next(iter(rc))) < categories.index(trait["category"]), "Cyclic/forward requirement")
        need(all(x in lookup for x in refs), "Unknown rule reference")
    for category in categories:
        values = [t for t in lookup.values() if t["category"] == category]
        need(sum(t["target_count"] for t in values) == n, f"Quota sum mismatch: {category}")
        need(len({t["display_name"] for t in values}) == len(values), "Duplicate display names within category")
    for band in config["rarity_bands"]:
        need(lookup[band["architecture"]]["target_count"] == band["count"], "Band/trait mismatch")
    need({g["token_id"] for g in genesis} == {1, 2, 3}, "Genesis IDs mismatch")
    need(len(legends) == 30, "Legendary count mismatch")
    ids = [g["token_id"] for g in genesis] + [g["token_id"] for g in legends]
    need(len(set(ids)) == 33 and all(1 <= i <= n for i in ids), "Invalid special reservation")
    need(set(ids) == set(config["generation"]["special_ids"]), "Special ID list mismatch")
    need(all(g["token_id"] > 201 for g in legends), "Legendary allocated outside paid inventory")
    for g in genesis:
        need(set(g["traits"]) == set(categories), "Incomplete Genesis vector")
        need(legal(g["traits"], lookup), "Illegal Genesis traits")
        need(g["traits"]["architecture"] == "architecture.singular", "Wrong Genesis architecture")
    return lookup


def solve(config, spec, genesis, legends, lookup, attempt):
    rows = {i: {} for i in range(1, config["supply"] + 1)}
    for g in genesis + legends:
        rows[g["token_id"]].update(g["traits"])
    for reservation in config["reserved_non_genesis_architecture"]:
        for token in range(reservation["first_id"], reservation["last_id"] + 1):
            rows[token]["architecture"] = reservation["trait"]
    seed = config["generation"]["design_seed"]
    for category in spec["category_order"]:
        traits = sorted((t for t in lookup.values() if t["category"] == category), key=lambda t: t["id"])
        fixed = Counter(row[category] for row in rows.values() if category in row)
        tokens = sorted((i for i in rows if category not in rows[i]), key=lambda i: key(seed, attempt, category, i))
        t0, r0 = 1, 1 + len(traits)
        sink = r0 + len(tokens)
        flow = Flow(sink + 1)
        links = []
        for j, trait in enumerate(traits):
            remaining = trait["target_count"] - fixed[trait["id"]]
            need(remaining >= 0, "Reservations exceed a trait quota")
            flow.add(0, t0 + j, remaining)
            order = sorted(enumerate(tokens), key=lambda pair: key(seed, attempt, trait["id"], pair[1]))
            for k, token in order:
                candidate = {**rows[token], category: trait["id"]}
                if legal(candidate, lookup, partial=True):
                    edge = flow.add(t0 + j, r0 + k, 1)
                    links.append((token, trait["id"], edge))
        for k in range(len(tokens)):
            flow.add(r0 + k, sink, 1)
        need(flow.run(0, sink) == len(tokens), f"No assignment at {category}, attempt {attempt}")
        for token, tid, edge in links:
            if edge[1] == 0:
                rows[token][category] = tid
    return rows


def verify_rows(rows, config, spec, lookup, genesis, legends):
    need(set(rows) == set(range(1, config["supply"] + 1)), "Witness IDs mismatch")
    counts = Counter()
    vectors = []
    for token, row in rows.items():
        need(set(row) == set(spec["category_order"]), f"Incomplete token {token}")
        need(legal(row, lookup), f"Rule violation at {token}")
        counts.update(row.values())
        vectors.append(tuple(row[c] for c in spec["category_order"]))
    for tid, trait in lookup.items():
        need(counts[tid] == trait["target_count"], f"Count mismatch: {tid}")
    for reserved in genesis + legends:
        need(all(rows[reserved["token_id"]][c] == t for c, t in reserved["traits"].items()), "Special overwritten")
    need({i for i, row in rows.items() if row["architecture"] == "architecture.singular"} == {1, 2, 3}, "Unexpected Genesis")
    need({i for i, row in rows.items() if row["architecture"] == "architecture.severed_halo"} == {g["token_id"] for g in legends}, "Unexpected legendary")
    need(len(set(vectors)) == len(vectors), "Duplicate full trait vectors in witness")
    return counts


def repair_duplicates(rows, config, spec, lookup, genesis, legends):
    """Swap unfixed visible traits; preserve all quotas and fixed reservations."""
    order = spec["category_order"]
    frozen = {g["token_id"]: set(g["traits"]) for g in genesis + legends}
    for reservation in config["reserved_non_genesis_architecture"]:
        for token in range(reservation["first_id"], reservation["last_id"] + 1):
            frozen.setdefault(token, set()).add("architecture")
    vector = lambda row: tuple(row[c] for c in order)
    counts = Counter(vector(row) for row in rows.values())
    swaps = 0
    seed = config["generation"]["design_seed"]
    for token in sorted(rows):
        old = vector(rows[token])
        if counts[old] <= 1:
            continue
        changed = False
        for category in ["material", "eyes", "mantle", "interface", "implant", "entity"]:
            if category in frozen.get(token, set()):
                continue
            candidates = sorted(rows, key=lambda other: key(seed, "deduplicate", token, category, other))
            for other in candidates:
                if other == token or category in frozen.get(other, set()):
                    continue
                if rows[token][category] == rows[other][category]:
                    continue
                left, right = dict(rows[token]), dict(rows[other])
                left[category], right[category] = right[category], left[category]
                if not legal(left, lookup) or not legal(right, lookup):
                    continue
                lv, rv, ov = vector(left), vector(right), vector(rows[other])
                if lv == rv or counts[lv] or counts[rv]:
                    continue
                counts[old] -= 1
                counts[ov] -= 1
                counts[lv] += 1
                counts[rv] += 1
                rows[token], rows[other] = left, right
                swaps += 1
                changed = True
                break
            if changed:
                break
        need(changed, f"Cannot repair duplicate at token {token} without relaxing rules")
    return swaps


def main():
    config, spec = read("collection.yaml"), read("traits.yaml")
    genesis = read("genesis.yaml")["characters"]
    legends = read("legendary-reservations.yaml")["reservations"]
    lookup = validate_spec(config, spec, genesis, legends)
    failures = []
    for attempt in range(8):
        try:
            rows = solve(config, spec, genesis, legends, lookup, attempt)
            swaps = repair_duplicates(rows, config, spec, lookup, genesis, legends)
            verify_rows(rows, config, spec, lookup, genesis, legends)
            break
        except ValueError as error:
            failures.append(str(error))
    else:
        raise ValueError("Bounded design search exhausted; no quotas relaxed: " + "; ".join(failures))
    with (ROOT / "rarity-report.csv").open("w", newline="", encoding="utf-8") as handle:
        fields = ["status", "category", "trait_id", "display_name", "target_percentage", "expected_count", "max_count", "rarity_score_bits", "visual_importance"]
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        for t in spec["traits"]:
            writer.writerow({"status": "design_target", "trait_id": t["id"], **{f: t[f] for f in fields if f not in {"status", "trait_id"}}})
    with (ROOT / "design-witness.csv").open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["token_id", "status", *spec["category_order"]])
        for token, row in sorted(rows.items()):
            writer.writerow([token, "feasibility_only_not_final_art", *[row[c] for c in spec["category_order"]]])
    result = {"status": "PASS_DESIGN_CONSTRAINTS_ONLY", "supply": len(rows), "trait_count": len(lookup), "category_count": len(spec["category_order"]), "reserved_specials": 33, "duplicate_trait_vectors": 0, "duplicate_repair_swaps": swaps, "successful_attempt": attempt, "earlier_search_failures": failures, "production_images_checked": 0, "production_metadata_checked": 0, "contract_tests_run": 0}
    (ROOT / "design-check.json").write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
