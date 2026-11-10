# Data dictionary

All custom names use the installation's `$wpdb->prefix`, not a fixed `wp_`.

| Location | Purpose / significant fields |
|---|---|
| WC product `_gc_product` | category, price_type, net/gross/stone weights, purity, stone/services, fixed price, making/profit modes/values/bases, component discounts, supply status, rate source |
| WC product `_gc_purity`, `_gc_net_weight`, `_gc_category` | Searchable, normalized product facets, updated with WC CRUD |
| `gc_rate_sources` | Nonsecret HTTPS provider configuration, health, last success. Secrets are environment variable references only |
| `gc_rate_ticks` | Rate version, source, precise decimal payload, effective/fetched/expiry UTC and explicit units |
| `gc_quotes` | UUID, WC order, monotonically increasing version, lifecycle, hashed token, immutable snapshot, integer IRR, UTC issue/expiry/start, intent, transaction |
| `gc_quote_events` | Append-only lifecycle metadata, timestamps, quote UUID |
| `gc_payment_receipts` | Unique provider+transaction, order, verified amount, intent, receipt time, paid/review state |
| `gc_security_audit` | Actor, action, object, redacted metadata, hash linked to previous event |
| `gc_notifications` | Unique semantic event key; encrypted recipient/subject/body; pending/sending/sent/retry/dead |
| `gc_integration_outbox` | Event UUID, store/company binding, payload/hash, state, retry counters, next try, safe error code |
| `gc_integration_inbox` | Unique provider/store/company/remote event, payload hash, processing result |
| `gc_external_mapping` | Stable local type/id and remote id, scope, mapping version/history; SKU is not the primary financial key |
| `gc_support` | Customer-owned order, request kind/status, bounded plain-text message |
| WC order/item meta | Issued snapshots, immutable invoice snapshot, receipt/refund references, authority, sync status, attribution and fulfilment tracking |
| Woo `wc_reserved_stock` | Native concurrency-safe product holds; financial source of stock remains Woo in standalone |

Amounts sent to WC are integer IRR strings. Agah proposed JSON uses integer strings at scale 2 (`1234500` = 12345 IRR), quantities at scale 4 (`27500` = 2.75 GRAM). PHP numeric floats are rejected by the Gold parser. The Agah amount helper accepts integer IRR; sub-rial settlement requires an explicit later version of the gateway/price contract.

Schema version is migration-controlled; new columns/tables are additive. Deactivation/uninstall does not remove rows. Customer invoice data and support messages are PII: include them in backup access controls and the owner's retention policy. Do not purge financial audit/receipt data using an arbitrary technical default.
