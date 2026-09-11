# GHOST//ROOT — mainnet deployment receipt

**Solana mainnet-beta · Metaplex Core.** `media_status = PRE_RELEASE_CANARY_ART_MUTABLE`
— the GHOST//0001 image is a pre-release canary render, NOT final collection-approved
artwork; update authority is retained so the URI can be replaced after visual QA.

- network: mainnet-beta (genesis 5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d)
- signer / update authority / royalty recipient: `CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R`
- collection address: `ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p`
- collection creation signature: `2WSEUtJpZqkMwWy2z34QjAVJjgVES3QgU5u6AdkzrBnWMTtvRMXqSLHuSorek85dTh3X37ncttHzf9s8hzMqCoEf`
- collection metadata URI: https://gateway.irys.xyz/6n5rdx2aAC7e3SvfeEqmdW2gBLn9ReXL6zzQmiEb3rGs
- royalty: 500 bps (5%), recipient @100%, ruleSet None
- GHOST//0001 asset address: `GUhpsP3cHvtJ5JJzKDnfcr83tvHJPn3kA2ib17MtM9B4`
- mint signature: `3BGkD2VZciRr67hcGt3hmRt5fCfyv2HDJBFARMyv9DTYNm87kVWULL3GDfF5LF1AZRbtJaq5WQhxAApWzi2y2yAL`
- 0001 metadata URI: https://gateway.irys.xyz/FJ7usKYkBwz1Zxpdau8QmAqpoF9YQM6YopPT9ZigC2i4
- 0001 image URI: https://gateway.irys.xyz/3nJ6cLveY2NBusZaRPvkqPFGhrrdgBFNLCy5qEqW5hMF
- owner: `CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R`
- balance after: 0.195783509 SOL
- explorer (collection): https://explorer.solana.com/address/ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p
- explorer (asset): https://explorer.solana.com/address/GUhpsP3cHvtJ5JJzKDnfcr83tvHJPn3kA2ib17MtM9B4

## Verification: 17/17 passed
- PASS network is mainnet-beta (5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d)
- PASS collection exists + deserializes
- PASS collection name GHOST//ROOT (GHOST//ROOT)
- PASS collection uri matches receipt (https://gateway.irys.xyz/6n5rdx2aAC7e3SvfeEqmdW2gBLn9ReXL6zzQmiEb3rGs)
- PASS collection update authority = signer (CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R)
- PASS royalties plugin present
- PASS royalty = 500 bps (500)
- PASS royalty recipient = signer @100% ({"address":"CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R","percentage":100})
- PASS asset exists + deserializes
- PASS asset name GHOST//0001 (GHOST//0001)
- PASS asset uri matches receipt (https://gateway.irys.xyz/FJ7usKYkBwz1Zxpdau8QmAqpoF9YQM6YopPT9ZigC2i4)
- PASS asset owner = signer (CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R)
- PASS asset belongs to collection ({"type":"Collection","address":"ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p"})
- PASS metadata HTTP 200 (HTTP 200)
- PASS metadata name matches (GHOST//0001)
- PASS image HTTP 200 (https://gateway.irys.xyz/3nJ6cLveY2NBusZaRPvkqPFGhrrdgBFNLCy5qEqW5hMF)
- PASS uploaded image sha256 == local frozen image (364fcba479fb vs 364fcba479fb)

Exactly one collection and one canary were created. No 0002–3333 minted; no Candy Machine; not MINT_READY.
