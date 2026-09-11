/**
 * Exact primary-revenue arithmetic for the two-tier launch. All math is done in
 * integer lamports (BigInt) so there is no floating-point drift; SOL strings are
 * derived only for display. GROSS revenue only — never called profit.
 */
import {
  EARLY_LAMPORTS,
  PUBLIC_LAMPORTS,
  CANDY_MACHINE_SUPPLY,
  lamportsToSol,
} from "./launch_config.js";

export interface RevenueRow {
  label: string;
  items: number;
  lamports: bigint;
  sol: string;
}

/** Item count for a whole-number percent of the 3332 public inventory (floored). */
export function itemsForPercent(percent: number, supply = CANDY_MACHINE_SUPPLY): number {
  return Math.floor((supply * percent) / 100);
}

/** Public-tier gross scenarios at 0.15 SOL across sold-through percentages. */
export function publicTierScenarios(percents: number[] = [10, 25, 50, 75, 100]): RevenueRow[] {
  return percents.map((p) => {
    const items = p === 100 ? CANDY_MACHINE_SUPPLY : itemsForPercent(p);
    const lamports = BigInt(items) * PUBLIC_LAMPORTS;
    return { label: `${p}% sold @0.15`, items, lamports, sol: lamportsToSol(lamports) };
  });
}

/** Hypothetical: every item sold at the early price (comparison only). */
export function allAtEarly(): RevenueRow {
  const lamports = BigInt(CANDY_MACHINE_SUPPLY) * EARLY_LAMPORTS;
  return { label: "100% @0.10 (early-price comparison)", items: CANDY_MACHINE_SUPPLY, lamports, sol: lamportsToSol(lamports) };
}

/** Hypothetical: every item sold at the public price (full sellout). */
export function allAtPublic(): RevenueRow {
  const lamports = BigInt(CANDY_MACHINE_SUPPLY) * PUBLIC_LAMPORTS;
  return { label: "100% @0.15 (public sellout)", items: CANDY_MACHINE_SUPPLY, lamports, sol: lamportsToSol(lamports) };
}

/** Mixed sellout: N early mints @0.10 + the remaining inventory @0.15. */
export function mixed(earlyCount: number, supply = CANDY_MACHINE_SUPPLY): RevenueRow {
  if (earlyCount < 0 || earlyCount > supply) throw new Error(`earlyCount ${earlyCount} out of range 0..${supply}`);
  const remainder = supply - earlyCount;
  const lamports = BigInt(earlyCount) * EARLY_LAMPORTS + BigInt(remainder) * PUBLIC_LAMPORTS;
  return {
    label: `${earlyCount} early @0.10 + ${remainder} @0.15`,
    items: supply,
    lamports,
    sol: lamportsToSol(lamports),
  };
}

export function mixedExamples(counts: number[] = [250, 500, 1000]): RevenueRow[] {
  return counts.map((c) => mixed(c));
}
