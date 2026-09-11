/**
 * Assemble the production collection + GHOST//0001 metadata objects.
 *
 * Reuses the already-validated attribute vector from the test-batch metadata
 * (validated against design-witness.csv by validate_test_batch.py). The ONLY
 * production changes are: a real Arweave image URI (never pending://), the
 * ghostroot.site external URL, and an explicit PRE_RELEASE media_status flag.
 *
 * media_status = PRE_RELEASE_CANARY_ART_MUTABLE — the current 0001 render is a
 * pre-release canary image, NOT final collection-approved artwork. The Core
 * asset keeps its update authority so this URI can be replaced after the visual
 * system is approved. No immutability is implied anywhere.
 */
import { readFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { EXPECTED_SIGNER, ROYALTY_BPS, SYMBOL } from "./guards.js";

const HERE = dirname(fileURLToPath(import.meta.url));
const COLLECTION_DIR = `${HERE}/../..`;

export const MEDIA_STATUS = "PRE_RELEASE_CANARY_ART_MUTABLE";

export function buildCollectionMetadata(imageUri?: string) {
  const md: Record<string, unknown> = {
    name: "GHOST//ROOT",
    symbol: SYMBOL,
    description:
      "GHOST//ROOT — a 3,333-identity Solana / Metaplex Core collection of " +
      "reconstructed archive identities. This on-chain collection is the " +
      "production home for the GHOST//ROOT identities; individual token media " +
      "is finalized through the project's visual QA process.",
    external_url: "https://ghostroot.site",
    seller_fee_basis_points: ROYALTY_BPS,
    media_status: MEDIA_STATUS,
    properties: {
      category: "image",
      creators: [{ address: EXPECTED_SIGNER, share: 100 }],
    },
  };
  // Only attach a collection image if a finalized one is supplied. Never invent one.
  if (imageUri) (md.properties as Record<string, unknown>).files = [{ uri: imageUri, type: "image/png" }];
  if (imageUri) md.image = imageUri;
  return md;
}

export function buildAssetMetadata(imageUri: string) {
  if (!imageUri || /pending:\/\/|localhost|127\.0\.0\.1|\/home\/|raw\.githubusercontent/i.test(imageUri))
    throw new Error(`ABORT: refusing to build 0001 metadata with a non-permanent image URI: ${imageUri}`);
  // Reuse the validated attribute vector (display names already checked vs the witness).
  const test = JSON.parse(
    readFileSync(`${COLLECTION_DIR}/metadata/test-batch/0001.json`, "utf8")
  ) as { description: string; attributes: { trait_type: string; value: string }[] };
  return {
    name: "GHOST//0001",
    symbol: SYMBOL,
    description: test.description,
    image: imageUri,
    external_url: "https://ghostroot.site/identity/1/",
    attributes: test.attributes,
    media_status: MEDIA_STATUS,
    token_id: 1,
    properties: {
      category: "image",
      files: [{ uri: imageUri, type: "image/png" }],
      creators: [{ address: EXPECTED_SIGNER, share: 100 }],
    },
  };
}
