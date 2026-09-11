#!/usr/bin/env -S npx tsx
/**
 * Stage E — guarded PREPARATION of the GHOST//ROOT Core Candy Machine + two-tier
 * Candy Guard. This script is DRY-RUN by default and, in the current state, will
 * ABORT before any broadcast because launch blockers remain unresolved.
 *
 * It NEVER:
 *   - deploys a Candy Machine
 *   - creates a Candy Guard on mainnet
 *   - loads config lines
 *   - sets launch dates
 *   - mints anything
 *   - spends SOL
 * unless EVERY launch blocker is cleared AND the operator passes BOTH
 * --confirm-mainnet and --authorize-deploy. Even then the actual create calls are
 * left as an explicit, separately-authorized step — this file stops at the plan.
 *
 * Supply invariant: the Candy Machine has itemsAvailable = 3332 (IDs 0002-3333);
 * GHOST//0001 is excluded (already minted as the canary). Groups change price/access
 * only — no separate reserved pool, no extra supply.
 */
import { loadLaunchConfig, CANDY_MACHINE_SUPPLY, ID_FIRST, ID_LAST, TREASURY, lamportsToSol } from "./launch_config.js";
import { buildGuardLayout, assertNoCheapDefaultPath, effectiveMintLimit } from "./guard_groups.js";
import { computeBlockers, type LaunchReadiness } from "./launch_blockers.js";
import { estimateCreatorCosts } from "./creator_costs.js";

function readReadiness(argv: string[]): LaunchReadiness {
  // Conservative: nothing is ready unless an explicit flag asserts it. The current
  // repository state (0 rendered identities, no CM metadata URIs) matches all-false.
  const has = (f: string) => argv.includes(f);
  return {
    fullRenderApproved: has("--render-approved"),
    finalMetadataComplete: has("--metadata-complete"),
    permanentUrisAvailable: has("--uris-available"),
    costQuoteReviewed: has("--cost-reviewed"),
    mainnetDeployAuthorized: has("--confirm-mainnet") && has("--authorize-deploy"),
  };
}

async function main() {
  const argv = process.argv.slice(2);
  const cfg = loadLaunchConfig();
  const layout = buildGuardLayout(cfg);
  assertNoCheapDefaultPath(layout, TREASURY); // proves no cheap ungrouped route

  const readiness = readReadiness(argv);
  const blockers = computeBlockers(cfg, readiness);

  console.log("\n=== CANDY MACHINE PREPARE (DRY-RUN) ===");
  console.log(`state                ${cfg.state}`);
  console.log(`collection           ${cfg.addresses.collection}`);
  console.log(`candy machine        ${cfg.addresses.candy_machine ?? "NOT DEPLOYED"}`);
  console.log(`items available      ${CANDY_MACHINE_SUPPLY}  (IDs ${ID_FIRST}-${ID_LAST}; GHOST//0001 excluded)`);
  console.log(`treasury             ${TREASURY}`);
  console.log("per-wallet cap       5 early + 5 public — INDEPENDENT counters (10 max/wallet); enforcement not yet verified");
  console.log(`royalty              ${cfg.royalty.basis_points} bps`);
  console.log("groups (each carries its own mintLimit):");
  for (const g of layout.groups) {
    const sp = g.guards.solPayment!;
    const ml = effectiveMintLimit(layout, g.label); // the group's own limiter
    console.log(`  - ${g.label.padEnd(7)} ${lamportsToSol(BigInt(sp.lamports))} SOL (${sp.lamports}) -> ${sp.destination}  cap=${ml.limit} (own mintLimit id ${ml.id})  startDate=${g.guards.startDate?.date ?? "null (NOT_SCHEDULED)"}  allowList=${g.guards.allowList ? "SET" : (g.label === "early" ? "PENDING_ALLOWLIST" : "n/a")}`);
  }
  console.log(`default guard         EMPTY (no mintLimit, no addressGate, no solPayment — nothing inherited into groups)`);
  console.log("no default route      ungrouped/free default mint blocked by program group-label requirement (UNTESTED offline)");
  console.log(`bot tax               ${cfg.bot_tax.enabled ? "ENABLED" : "disabled"}`);

  // Creator cost estimate (real serialized sizes; ESTIMATE until on-chain quote reviewed).
  const costs = estimateCreatorCosts();
  console.log(`\ncreator cost estimate (ESTIMATE — cost_quote_not_reviewed remains a blocker):`);
  for (const r of costs.rows) console.log(`  ${r.label.padEnd(30)} ${r.sol} SOL   ${r.note ?? ""}`);
  console.log(`  ${"recommended creator reserve".padEnd(30)} ${costs.recommendedReserveSol} SOL (incl. buffer)`);

  // Real transaction builders exist in tx_builders.ts (create + wrap guard, addConfigLines,
  // updateCandyGuard). They are constructed and sent ONLY at authorized deploy time with a
  // live connection — never here. This script signs and sends NOTHING.
  console.log(`\ndeploy transaction plan (builders in tx_builders.ts — NOT executed here):`);
  console.log("  1-5. create Core Candy Machine + create/wrap Candy Guard + configure early/public groups (each with its own solPayment + mintLimit)  (buildCreateMachineAndGuardTx)");
  console.log(`  6.   load ${CANDY_MACHINE_SUPPLY} config lines resumably in ${costs.addConfigLinesTxCount} txs @ ${costs.linesPerTx}/tx  (buildAddConfigLinesTxs)`);
  console.log("  7.   set launch dates once scheduled  (buildUpdateGuardDatesTx)");
  console.log("  8.   read-back verify machine + guard (fetchCandyMachine / fetchCandyGuard) — live-rpc step");

  console.log(`\nlaunch blockers      ${blockers.length} unresolved`);
  blockers.forEach((b) => console.log(`  - ${b.code}: ${b.reason}`));

  if (blockers.length > 0) {
    console.log("\nABORT: launch blockers remain. No Candy Machine created, no Candy Guard created, no dates set, no SOL spent.");
    console.log("State stays PREPARED_NOT_DEPLOYED.");
    return;
  }

  if (!(argv.includes("--confirm-mainnet") && argv.includes("--authorize-deploy"))) {
    console.log("\nAll blockers clear, but --confirm-mainnet + --authorize-deploy not both present. DRY-RUN only: signed nothing.");
    return;
  }

  // Reaching here requires a fully unblocked launch AND explicit dual authorization.
  // The actual createCandyMachine / createCandyGuard / setDates broadcast is a
  // deliberately separate, human-run step and is intentionally NOT wired here.
  console.log("\nAll blockers clear + dual authorization present.");
  console.log("Next (separate, human-run) step: broadcast createCandyMachine + createCandyGuard with the persisted signer.");
  console.log("This preparation script still signs NOTHING.");
}
main().catch((e) => { console.error(e instanceof Error ? e.message : e); process.exit(1); });
