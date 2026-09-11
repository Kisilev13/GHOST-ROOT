/**
 * Source-backed MintCounter PDA derivation, using the INSTALLED Candy Guard SDK
 * (@metaplex-foundation/mpl-core-candy-machine@0.3.0). This is the single place that
 * proves the shared-counter claim from real code rather than documentation:
 *
 *   findMintCounterPda seeds (generated/accounts/mintCounter.js):
 *     ['mint_limit', id(u8), user(pubkey), candyGuard(pubkey), candyMachine(pubkey)]
 *
 * The mintLimit id IS a seed; the GROUP LABEL, the PRICE, and the launch PHASE are NOT.
 * Therefore, since `early` uses id 1 and `public` uses id 2, the two phases derive
 * DIFFERENT counter PDAs for the same wallet and enforce INDEPENDENT per-phase allowances
 * (5 early + 5 public). Two groups sharing one id would instead collapse to one counter.
 *
 * PDA derivation is pure ed25519/sha256 (tweetnacl + hashing) — no RPC, no signing, no
 * SOL. Safe to run under offline_guard.cjs.
 */
import { readFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { createUmi } from "@metaplex-foundation/umi-bundle-defaults";
import { publicKey, type Umi, type PublicKey } from "@metaplex-foundation/umi";
import { mplCandyMachine, findMintCounterPda } from "@metaplex-foundation/mpl-core-candy-machine";

export const CANDY_GUARD_PROGRAM_ID = "CMAGAKJ67e9hRZgfC5SFTbZH8MgEmtqazKXjmkaJjWTJ";

/**
 * Minimal umi wired for PDA derivation only. The endpoint is a placeholder and is NEVER
 * contacted — findMintCounterPda uses context.eddsa.findPda + a registered program id.
 */
export function pdaUmi(): Umi {
  const umi = createUmi("http://localhost:8899");
  umi.use(mplCandyMachine());
  return umi;
}

export interface CounterSeeds { id: number; user: string; candyGuard: string; candyMachine: string; }

/** Derive the MintCounter PDA address for the given seeds via the installed SDK. */
export function deriveMintCounterPda(umi: Umi, s: CounterSeeds): PublicKey {
  const [pda] = findMintCounterPda(umi, {
    id: s.id,
    user: publicKey(s.user),
    candyGuard: publicKey(s.candyGuard),
    candyMachine: publicKey(s.candyMachine),
  });
  return pda;
}

/**
 * Source-level assertion: read the installed findMintCounterPda implementation and prove
 * its seeds are exactly ['mint_limit', id, user, candyGuard, candyMachine] and that the
 * group label / price / phase are NOT part of the seeds. Throws Error("ABORT: ...") if the
 * installed implementation contradicts the documented (and here-relied-on) seed layout.
 */
export function assertMintCounterSeedSourceContract(): { seeds: string[]; programId: string; source: string } {
  const here = dirname(fileURLToPath(import.meta.url));
  const file = `${here}/node_modules/@metaplex-foundation/mpl-core-candy-machine/dist/src/generated/accounts/mintCounter.js`;
  const src = readFileSync(file, "utf8");
  const start = src.indexOf("function findMintCounterPda");
  if (start < 0) throw new Error("ABORT: findMintCounterPda not found in installed SDK source");
  const body = src.slice(start, start + 600);

  const required = [
    "'mint_limit'",   // the literal seed prefix
    "seeds.id",       // the mintLimit id (u8)
    "seeds.user",     // the minting wallet
    "seeds.candyGuard",
    "seeds.candyMachine",
  ];
  for (const token of required)
    if (!body.includes(token))
      throw new Error(`ABORT: installed findMintCounterPda seeds missing '${token}' — source contradicts the shared-counter contract; STOP`);

  // The counter must NOT be scoped by anything phase/price/label specific.
  for (const forbidden of ["group", "label", "price", "lamports", "startDate", "phase"])
    if (body.includes(forbidden))
      throw new Error(`ABORT: installed findMintCounterPda seeds unexpectedly reference '${forbidden}' — counter is not phase-shared; STOP`);

  if (!body.includes(CANDY_GUARD_PROGRAM_ID))
    throw new Error(`ABORT: findMintCounterPda program id != ${CANDY_GUARD_PROGRAM_ID}`);

  return {
    seeds: ["mint_limit", "id", "user", "candyGuard", "candyMachine"],
    programId: CANDY_GUARD_PROGRAM_ID,
    source: file,
  };
}
