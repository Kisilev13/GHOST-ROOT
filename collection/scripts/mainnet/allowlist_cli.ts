#!/usr/bin/env -S npx tsx
/**
 * Allowlist builder CLI. Reads the early-wallets CSV, validates + dedups, records
 * the source hash, and (with --build-merkle) delegates to the audited metaplex
 * getMerkleRoot. Never fabricates wallets; an empty CSV stays PENDING_ALLOWLIST.
 *
 *   npx tsx allowlist_cli.ts                # validate + dedup + source hash
 *   npx tsx allowlist_cli.ts --build-merkle # also compute the merkle root
 */
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { buildAllowlistFromCsv } from "./allowlist_builder.js";

const HERE = dirname(fileURLToPath(import.meta.url));
const CSV = `${HERE}/../../launch/allowlist/early-wallets.csv`;

async function main() {
  const buildMerkle = process.argv.includes("--build-merkle");
  const res = await buildAllowlistFromCsv(CSV, { buildMerkle });
  console.log("=== EARLY ALLOWLIST BUILD ===");
  console.log(`source        ${CSV}`);
  console.log(`status        ${res.status}`);
  console.log(`unique        ${res.count}`);
  console.log(`dupes removed ${res.duplicatesRemoved}`);
  console.log(`source hash   ${res.sourceHash ?? "(none — no wallets)"}`);
  console.log(`merkle root   ${res.merkleRoot ?? "(none)"}`);
  res.notes.forEach((n) => console.log(`  - ${n}`));
  if (res.status === "PENDING_ALLOWLIST")
    console.log("\nEarly group remains BLOCKED (allowlist_required_but_pending) until real wallets are frozen.");
}
main().catch((e) => { console.error(e instanceof Error ? e.message : e); process.exit(1); });
