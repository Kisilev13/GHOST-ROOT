# Metadata production contract

This directory intentionally contains no production token JSON. No media has been rendered or uploaded. The following is a JSON template for Genesis ID 1 in state 0; replace the media CID and omit or resolve external URLs before running the production validator.

```json
{
  "token_id": 1,
  "name": "GHOST//0001",
  "description": "THE WITNESS. A recovered identity from the vanished ROOT network. You were never supposed to find them.",
  "image": "ipfs://REPLACE_WITH_VERIFIED_MEDIA_CID/0/1.png",
  "collection": {"name": "GHOST//ROOT", "symbol": "GHRT"},
  "attributes": [
    {"trait_type": "Entity", "value": "SYNTHETIC"},
    {"trait_type": "Access", "value": "ROOT"},
    {"trait_type": "Architecture", "value": "SINGULAR"},
    {"trait_type": "Face Material", "value": "PORCELAIN"},
    {"trait_type": "Eyes", "value": "BIOMETRIC"},
    {"trait_type": "Interface", "value": "FORENSIC PLATE"},
    {"trait_type": "Implant", "value": "MEMORY SPINDLE"},
    {"trait_type": "Mantle", "value": "ARCHIVE COAT"},
    {"trait_type": "Background", "value": "EVIDENCE VOID"},
    {"trait_type": "Signal", "value": "LOCKED"},
    {"trait_type": "Origin Corruption", "value": "INTACT"},
    {"trait_type": "State", "value": "DORMANT"},
    {"trait_type": "Rarity Band", "value": "1/1"}
  ]
}
```

The `collection` field is a project extension, not ERC-721 membership verification. The chain/contract pair in the signed deployment manifest identifies the actual collection. State directories 0–3 correspond exactly to DORMANT, ACTIVE, COMPROMISED, ROOTED. Every directory contains 1.json through 3333.json with the same origin values.

`schema.json` is a structural contract only. The production validator still needs duplicate-key parsing, reference-CID parsing, semantic trait validation, cross-state consistency, file counts, image hashes/dimensions, and deployed-contract linkage. The placeholder above must fail production validation.
