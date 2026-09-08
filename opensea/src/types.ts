/**
 * OpenSea API v2 response shapes — the subset GHOST//ROOT consumes.
 *
 * These mirror the currently published schema (docs.opensea.io, verified live
 * 2026-09-08). Fields are widened to `| null | undefined` where the live API was
 * observed to omit or null them; every consumer must tolerate missing data
 * rather than render a zero (task section 21).
 */

export type Chain =
  | 'solana'
  | 'ethereum'
  | 'base'
  | 'polygon'
  | 'arbitrum'
  | 'optimism'
  | (string & {});

export interface OpenSeaContractRef {
  address: string;
  chain: Chain;
}

export interface OpenSeaFee {
  fee: number;
  recipient: string;
  required: boolean;
}

export interface PaymentToken {
  symbol: string;
  address: string;
  chain: Chain;
  decimals: number;
  name?: string;
  image?: string;
  eth_price?: string;
  usd_price?: string;
}

export interface CollectionDetailed {
  collection: string;
  name: string;
  description?: string | null;
  category?: string | null;
  image_url?: string | null;
  banner_image_url?: string | null;
  safelist_status?: string;
  is_disabled?: boolean;
  is_nsfw?: boolean;
  owner?: string | null;
  editors?: string[];
  trait_offers_enabled?: boolean;
  collection_offers_enabled?: boolean;
  opensea_url?: string;
  project_url?: string | null;
  wiki_url?: string | null;
  discord_url?: string | null;
  telegram_url?: string | null;
  twitter_username?: string | null;
  instagram_username?: string | null;
  contracts: OpenSeaContractRef[];
  total_supply?: number;
  unique_item_count?: number;
  created_date?: string;
  fees?: OpenSeaFee[];
  pricing_currencies?: {
    listing_currency?: PaymentToken;
    offer_currency?: PaymentToken;
  };
  rarity?: {
    calculated_at?: string;
    max_rank?: number;
    total_supply?: number;
    strategy_id?: string;
    strategy_version?: string;
  } | null;
}

export interface IntervalStat {
  interval: string;
  sales: number;
  volume: number;
  volume_symbol?: string;
  volume_change?: number;
  average_price?: number;
}

export interface CollectionStats {
  total: {
    volume: number;
    volume_symbol?: string;
    sales: number;
    average_price?: number;
    num_owners: number;
    market_cap?: number;
    floor_price?: number | null;
    floor_price_symbol?: string | null;
  };
  intervals: IntervalStat[];
}

export interface NftTrait {
  trait_type: string;
  value: string | number;
  display_type?: string | null;
  max_value?: number | null;
}

export interface Nft {
  identifier: string;
  collection: string;
  contract: string;
  token_standard: string;
  name?: string | null;
  description?: string | null;
  image_url?: string | null;
  display_image_url?: string | null;
  display_animation_url?: string | null;
  metadata_url?: string | null;
  opensea_url?: string | null;
  updated_at?: string;
  is_disabled?: boolean;
  is_nsfw?: boolean;
  traits?: NftTrait[] | null;
  owners?: { address: string; quantity: number }[];
  rarity?: { rank?: number; score?: number } | null;
}

export interface Holder {
  address: string;
  quantity: number;
  percentage?: number;
}

export interface AssetEvent {
  event_type: string;
  order_hash?: string;
  chain?: Chain;
  quantity?: number;
  event_timestamp?: number;
  transaction?: string | null;
  from_address?: string | null;
  to_address?: string | null;
  seller?: string | null;
  buyer?: string | null;
  payment?: {
    quantity: string;
    token_address: string;
    decimals: number;
    symbol: string;
  } | null;
  nft?: Pick<Nft, 'identifier' | 'name' | 'image_url' | 'opensea_url'> | null;
}

export interface Listing {
  order_hash: string;
  chain: Chain;
  type?: string;
  price?: {
    current: { currency: string; decimals: number; value: string };
  };
  protocol_data?: unknown;
}

export interface OpenSeaAccount {
  address?: string;
  username?: string | null;
  profile_image_url?: string | null;
  banner_image_url?: string | null;
  website?: string | null;
  social_media_accounts?: { platform: string; username: string }[];
  bio?: string | null;
  joined_date?: string;
}

export interface ChainInfo {
  chain: Chain;
  name: string;
  symbol: string;
  supports_swaps?: boolean;
  block_explorer?: string;
  block_explorer_url?: string;
  is_testnet?: boolean;
}

/** Cursor-paginated list envelope. Different endpoints use different keys. */
export interface Paginated<T> {
  items: T[];
  next: string | null;
}

export type VerificationState =
  | 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT'
  | 'NOT_INDEXED'
  | 'PENDING'
  | 'PARTIAL'
  | 'VERIFIED'
  | 'MISMATCH'
  | 'ERROR';

export interface VerificationCheck {
  field: string;
  expected: string;
  actual: string;
  ok: boolean;
  critical: boolean;
}

export interface VerificationReport {
  state: VerificationState;
  slug: string | null;
  chain: string;
  checked_at: string;
  checks: VerificationCheck[];
  notes: string[];
}
