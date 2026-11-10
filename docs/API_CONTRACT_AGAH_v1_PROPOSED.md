# Agah v1 PROPOSED — NOT_IMPLEMENTED in alpha12

This document is a proposed contract, not a list of existing Agah endpoints. Supplied alpha12 was inspected statically only. No Agah code is modified or included. MockProvider is a test simulator; its reservation capability never authorizes production purchase.

Envelope: `schema_version:"1"`, source_version, occurred_at_utc, correlation_id, idempotency_key, store_uuid, company_id. Scope all credentials and all reads/writes to a single allowed company/store. TLS plus scoped OAuth/HMAC or mutual authentication, rotation, least privilege and rate limiting are prerequisites.

Money: `amount_minor:"1234500",currency:"IRR",scale:2`. Quantity: `qty_scaled:"27500",qty_scale:4,unit_code:"GRAM"`. The integer strings mean 12345 IRR and 2.75 grams. Woo's integer IRR boundary requires explicit reconciliation of any sub-rial price before a production gateway charge.

| Proposed /wp-json/agah-connect/v1 path | Contract |
|---|---|
| GET catalog/changes, prices/changes | Cursor/version/tombstone; stable item/variation mapping |
| GET inventory/availability | Logical warehouse, on_hand/reserved/available and version |
| POST inventory/reservations | Atomic bounded TTL, idempotency, explicit reservation id |
| GET inventory/reservations/{id} | Resolve timeout after commit |
| POST inventory/reservations/{id}/release | Exactly one release before invoice |
| POST sales/events | Single atomic sales invoice + stock movement + accounting + reservation consume |
| GET sales/events/{key} | Stable result for a prior idempotency key |
| GET sync/health | Clock, schema/source version, company binding and real reservation capabilities |

Price authority is selected explicitly: `AGAH_AUTHORITATIVE_FINAL_PRICE` uses the verified remote final amount; `AGAH_BASE_COMPONENTS` requires agreed component units, rounding/tax policy version and matching calculated total. Unknown contract or conflict denies a final price.

Never send both a separate physical stock commit and an invoice event that independently decrements stock. Refund after invoice is a credit/return with inventory/accounting effect, not deletion or reservation release. Logical warehouse is required; physical storage address is not.

Gold webhook ingress is `/wp-json/gold-agah/v1/webhook`: X-GC-Timestamp Unix seconds and X-GC-Signature = hex HMAC-SHA256(timestamp + "." + raw_body, secret). ±300-second skew limit, JSON scope/version and unique event_id. Secret is GC_AGAH_WEBHOOK_SECRET in environment/wp-config (minimum 32 bytes), never an option export. Inbox conflicting payload replay is rejected. Mock ingress records notices only and never overwrites real Woo stock/prices.

Live readiness requires actual OpenAPI, two separate staging databases, real atomic reservation and contract tests, selected bank Sandbox, company isolation and owner-controlled reconciliation. Until then status is MOCK_ONLY/BLOCKED_EXTERNAL. Degraded mode never automatically falls back to stale Woo cache.
