/**
 * Creator-side launch cost estimator for the Core Candy Machine + Candy Guard.
 *
 * Uses ACTUAL serialized sizes from the installed SDK constants
 * (@metaplex-foundation/mpl-core-candy-machine): CONFIG_LINE_SIZE,
 * CANDY_MACHINE_HIDDEN_SECTION, CANDY_GUARD_DATA. Rent is the canonical Solana
 * rent-exempt formula; per-signature fee is the base 5000 lamports. All math is integer
 * lamports (BigInt). These are ESTIMATES until the reviewed on-chain quote clears the
 * `cost_quote_not_reviewed` blocker — priority fees and the exact serialized guard-data
 * length are environment-dependent.
 *
 * SCOPE: creator setup + loading only. The 3,332 buyer-created Core Asset costs
 * (mint price + Core asset creation/rent + per-mint network fee) are BUYER costs and are
 * reported separately (see buyerPerMintCosts) — never folded into the creator reserve.
 */
import {
  CONFIG_LINE_SIZE,
  CANDY_MACHINE_HIDDEN_SECTION,
  CANDY_GUARD_DATA,
} from "@metaplex-foundation/mpl-core-candy-machine";
import {
  CANDY_MACHINE_SUPPLY,
  EARLY_LAMPORTS,
  PUBLIC_LAMPORTS,
  lamportsToSol,
} from "./launch_config.js";
import { EST_CORE_RENT_LAMPORTS, EST_NETWORK_FEE_LAMPORTS } from "./mint_helper.js";

// Canonical Solana rent-exempt constants.
export const ACCOUNT_STORAGE_OVERHEAD = 128n;      // bytes added to every account
export const LAMPORTS_PER_BYTE_YEAR = 3480n;
export const EXEMPTION_THRESHOLD_X2 = 2n;           // 2-year exemption threshold
export const BASE_SIGNATURE_FEE = 5000n;            // lamports per signature (base)

/** Rent-exempt minimum for an account of `bytes` bytes (integer lamports). */
export function rentExemptLamports(bytes: bigint): bigint {
  return (bytes + ACCOUNT_STORAGE_OVERHEAD) * LAMPORTS_PER_BYTE_YEAR * EXEMPTION_THRESHOLD_X2;
}

/**
 * Bytes of the Candy Machine account when it stores `items` config lines (no hidden
 * settings). Matches candy-machine-core get_space_for_candy: header + count + config
 * lines + load bit-mask + mint-index array.
 */
export function candyMachineAccountBytes(items: number): bigint {
  const n = BigInt(items);
  return (
    BigInt(CANDY_MACHINE_HIDDEN_SECTION) +
    4n +                                   // u32: number of config lines inserted
    n * BigInt(CONFIG_LINE_SIZE) +         // config lines (name+uri, max lengths)
    (n + 7n) / 8n +                        // load-tracking bit mask (1 bit / item)
    4n +                                   // u32: mint-index array length
    n * 4n                                 // mint-index array (u32 / item)
  );
}

/**
 * Candy Guard account bytes = fixed base (discriminator + base + bump + authority) plus
 * the serialized guard data. The guard data length is estimated (default mintLimit + two
 * priced groups w/ startDate + one allowList root); the exact length is confirmed by the
 * reviewed on-chain quote.
 */
export const EST_GUARD_DATA_BYTES = 512n; // conservative headroom for default + 2 groups
export function candyGuardAccountBytes(): bigint {
  return BigInt(CANDY_GUARD_DATA) + EST_GUARD_DATA_BYTES;
}

export interface CostRow { label: string; lamports: bigint; sol: string; note?: string; }

export interface CreatorCostEstimate {
  items: number;
  configLineBytesPerItem: number;
  candyMachineBytes: bigint;
  candyGuardBytes: bigint;
  addConfigLinesTxCount: number;
  linesPerTx: number;
  rows: CostRow[];
  subtotalLamports: bigint;
  bufferLamports: bigint;
  recommendedReserveLamports: bigint;
  recommendedReserveSol: string;
}

const row = (label: string, lamports: bigint, note?: string): CostRow => ({
  label,
  lamports,
  sol: lamportsToSol(lamports),
  note,
});

/**
 * Estimate the total creator SOL to set up and load the launch.
 * @param items       Candy Machine inventory (default 3332).
 * @param linesPerTx  Config lines per addConfigLines transaction (default 10, conservative).
 * @param bufferSol   Safety buffer (priority fees, retries) added to the reserve.
 */
export function estimateCreatorCosts(
  items: number = CANDY_MACHINE_SUPPLY,
  linesPerTx: number = 10,
  bufferSol: number = 0.15,
): CreatorCostEstimate {
  if (items <= 0) throw new Error("ABORT: items must be > 0");
  if (linesPerTx <= 0) throw new Error("ABORT: linesPerTx must be > 0");

  const cmBytes = candyMachineAccountBytes(items);
  const guardBytes = candyGuardAccountBytes();
  const cmRent = rentExemptLamports(cmBytes);
  const guardRent = rentExemptLamports(guardBytes);

  const addConfigLinesTxCount = Math.ceil(items / linesPerTx);
  // Setup txs: createCandyMachine, createCandyGuard/wrap, updateCandyGuard (set dates) — 3.
  const setupTxCount = 3;
  const loadingFees = BigInt(addConfigLinesTxCount) * BASE_SIGNATURE_FEE;
  const setupFees = BigInt(setupTxCount) * BASE_SIGNATURE_FEE;

  const rows: CostRow[] = [
    row("Candy Machine account rent", cmRent, `${cmBytes} bytes (${items} config lines @ ${CONFIG_LINE_SIZE}B) — recoverable on close`),
    row("Candy Guard account rent", guardRent, `${guardBytes} bytes (base ${CANDY_GUARD_DATA} + est guard data ${EST_GUARD_DATA_BYTES}) — recoverable on close`),
    row("Config-line loading fees", loadingFees, `${addConfigLinesTxCount} addConfigLines txs @ ${linesPerTx} lines/tx × ${BASE_SIGNATURE_FEE} base`),
    row("Setup tx fees", setupFees, `${setupTxCount} txs (createCandyMachine, createCandyGuard/wrap, updateCandyGuard)`),
  ];

  const subtotal = rows.reduce((acc, r) => acc + r.lamports, 0n);
  const buffer = BigInt(Math.round(bufferSol * 1e9));
  const reserve = subtotal + buffer;

  return {
    items,
    configLineBytesPerItem: CONFIG_LINE_SIZE,
    candyMachineBytes: cmBytes,
    candyGuardBytes: guardBytes,
    addConfigLinesTxCount,
    linesPerTx,
    rows,
    subtotalLamports: subtotal,
    bufferLamports: buffer,
    recommendedReserveLamports: reserve,
    recommendedReserveSol: lamportsToSol(reserve),
  };
}

/**
 * BUYER per-mint costs (separate ledger — never a creator cost). Mint price is paid to the
 * treasury; Core asset creation/rent and the network fee are paid by the connected buyer.
 */
export function buyerPerMintCosts(): { early: CostRow[]; public: CostRow[] } {
  const make = (price: bigint) => [
    row("Mint price (solPayment → treasury)", price),
    row("Core asset creation/rent", EST_CORE_RENT_LAMPORTS, "paid by connected buyer"),
    row("Network fee", EST_NETWORK_FEE_LAMPORTS, "paid by connected buyer"),
  ];
  return { early: make(EARLY_LAMPORTS), public: make(PUBLIC_LAMPORTS) };
}
