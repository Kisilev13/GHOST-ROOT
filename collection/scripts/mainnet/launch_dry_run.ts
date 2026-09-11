#!/usr/bin/env -S npx tsx
/**
 * Full launch-system DRY RUN. Reads launch-config.json, builds the guard layout,
 * proves there is no cheap default path, computes launch blockers, and prints the
 * complete required report. Signs nothing, touches no chain, spends no SOL.
 *
 * State stays PREPARED_NOT_DEPLOYED.
 */
import { execFileSync } from "node:child_process";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import {
  loadLaunchConfig, CANDY_MACHINE_SUPPLY, ID_FIRST, ID_LAST, TREASURY,
  COLLECTION_ADDRESS, EARLY_LAMPORTS, PUBLIC_LAMPORTS, lamportsToSol,
} from "./launch_config.js";
import { buildGuardLayout, assertNoCheapDefaultPath, getGroup, effectiveMintLimit } from "./guard_groups.js";
import { computeBlockers } from "./launch_blockers.js";
import { isEarlyAllowlistBlocking } from "./launch_blockers.js";
import { publicTierScenarios, allAtEarly, allAtPublic, mixedExamples } from "./economics.js";
import { estimateCreatorCosts } from "./creator_costs.js";
import { buildBuyerQuote, type OnChainGroup } from "./mint_helper.js";

const HERE = dirname(fileURLToPath(import.meta.url));

function groupFromConfigForDisplay(label: "early" | "public"): OnChainGroup {
  return {
    label,
    solPaymentLamports: label === "early" ? EARLY_LAMPORTS : PUBLIC_LAMPORTS,
    solPaymentDestination: TREASURY,
    mintLimit: { id: label === "early" ? 1 : 2, limit: 5 },
  };
}

async function main() {
  const cfg = loadLaunchConfig();
  const layout = buildGuardLayout(cfg);
  assertNoCheapDefaultPath(layout, TREASURY);

  // Current-state readiness: nothing asserted ready => all-false (matches repo state).
  const readiness = {
    fullRenderApproved: false, finalMetadataComplete: false, permanentUrisAvailable: false,
    costQuoteReviewed: false, mainnetDeployAuthorized: false,
  };
  const blockers = computeBlockers(cfg, readiness);

  const early = getGroup(layout, "early");
  const pub = getGroup(layout, "public");
  const earlyQuote = buildBuyerQuote("early", groupFromConfigForDisplay("early"));
  const publicQuote = buildBuyerQuote("public", groupFromConfigForDisplay("public"));

  const L = (k: string, v: string) => console.log(`${k.padEnd(30)} ${v}`);

  console.log("\n================ GHOST//ROOT LAUNCH SYSTEM — FULL DRY RUN ================");
  L("state", cfg.state);
  L("proposed candy machine", cfg.addresses.candy_machine ?? "NOT DEPLOYED (assigned at create time)");
  L("existing collection", COLLECTION_ADDRESS);
  L("supply (candy machine)", `${CANDY_MACHINE_SUPPLY}  (IDs ${ID_FIRST}-${ID_LAST}; GHOST//0001 excluded)`);
  L("early price", `${lamportsToSol(EARLY_LAMPORTS)} SOL`);
  L("public price", `${lamportsToSol(PUBLIC_LAMPORTS)} SOL`);
  L("early exact lamports", `${early.guards.solPayment!.lamports}`);
  L("public exact lamports", `${pub.guards.solPayment!.lamports}`);
  L("treasury", TREASURY);
  L("per-wallet cap", "5 early + 5 public — INDEPENDENT counters (10 combined; each group defines its own mintLimit)");
  const earlyEff = effectiveMintLimit(layout, "early");
  const pubEff = effectiveMintLimit(layout, "public");
  L("early limiter", `${earlyEff.limit} (own mintLimit id ${earlyEff.id})`);
  L("public limiter", `${pubEff.limit} (own mintLimit id ${pubEff.id})`);
  L("enforcement status", cfg.wallet_limit.enforcement_status);
  L("counters", "early id 1, public id 2; PDA seeds ['mint_limit',id,user,candyGuard,candyMachine] (source-verified) include the id => DISTINCT counters per phase");
  L("allowlist readiness", isEarlyAllowlistBlocking(cfg) ? "PENDING_ALLOWLIST (no merkle root; early blocked)" : "READY");
  L("early launch date status", `${cfg.pricing.early.start_date ?? "null"} (${cfg.pricing.early.status})`);
  L("public launch date status", `${cfg.pricing.public.start_date ?? "null"} (${cfg.pricing.public.status})`);
  L("buyer-pays status", `minter+payer+rent+fees = connected buyer; creator sponsors buyer costs = ${cfg.buyer_pays.creator_sponsors_buyer_costs}`);
  L("default guard", `EMPTY (no mintLimit, no addressGate, no solPayment — nothing inherited into groups)`);
  L("bot tax", cfg.bot_tax.enabled ? "ENABLED" : "disabled (not yet enabled)");
  const costs = estimateCreatorCosts();
  L("est creator launch cost", `~${costs.recommendedReserveSol} SOL reserve (ESTIMATE from serialized sizes — pending reviewed on-chain quote)`);
  L("est buyer total @ early", `${earlyQuote.totalEstimatedSol} SOL  (price ${earlyQuote.mintPriceSol} + est core/network ${earlyQuote.estCoreAndNetworkSol})`);
  L("est buyer total @ public", `${publicQuote.totalEstimatedSol} SOL  (price ${publicQuote.mintPriceSol} + est core/network ${publicQuote.estCoreAndNetworkSol})`);

  console.log("\n---- projected GROSS revenue scenarios (public tier @0.15) ----");
  publicTierScenarios().forEach((r) => console.log(`  ${r.label.padEnd(22)} ${String(r.items).padStart(5)} items  ${r.sol} SOL`));
  console.log(`  full sellout @0.15     ${allAtPublic().items} items  ${allAtPublic().sol} SOL`);
  console.log(`  all-at-early @0.10      ${allAtEarly().items} items  ${allAtEarly().sol} SOL  (comparison only)`);
  console.log("  mixed:");
  mixedExamples().forEach((r) => console.log(`    ${r.label.padEnd(34)} ${r.sol} SOL`));

  console.log(`\n---- launch blockers (${blockers.length} unresolved) ----`);
  blockers.forEach((b) => console.log(`  - ${b.code}: ${b.reason}`));

  console.log("\n---- tests ----");
  try {
    const out = execFileSync(process.execPath, ["--require", "./offline_guard.cjs", "--import", "tsx", "test_launch.ts"], { cwd: HERE, encoding: "utf8" });
    const last = out.trim().split("\n").slice(-1)[0];
    console.log(`  ${last}`);
  } catch (e: any) {
    throw new Error(`ABORT: launch tests failed: ${e?.message ?? e}`);
  }

  console.log("\n---- decision ----");
  console.log(`  ${blockers.length > 0 ? "ABORT — blockers remain. No deploy, no guard, no dates, no mint, no SOL spent." : "clear (still requires explicit dual authorization to broadcast)"}`);
  console.log(`  final state: ${cfg.state}`);
  console.log("=========================================================================\n");
}
main().catch((e) => { console.error(e instanceof Error ? e.message : e); process.exit(1); });
