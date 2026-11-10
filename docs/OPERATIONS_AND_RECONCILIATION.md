# Operations and reconciliation

Daily: inspect rate health/age, cron last execution, pending/sending/dead notifications, pending/retry/dead Agah events, review receipts, Woo failed payments and fulfilment exceptions. Match verified gateway receipts to Woo orders and their immutable quote amount. Compare paid Woo order IDs with financial event keys and external invoice IDs; no invoice is implied solely by Woo paid status.

Quote flow: requested → issued → payment_started → paid; exception branches superseded/expired/failed/payment_review. Issuance and start are serialized per order. Woo pending status keeps native stock holds visible. Late verified callbacks use recorded start/deadline and actually verified amount. A review receipt must be matched with the provider before capture/retry/refund. Requote is explicit after failed attempts; never reuse a consumed link.

Standalone stock uses Woo held-stock and its one-time stock-reduced/refund flags. Agah-connected stock must be owned exclusively by real Agah reservation/invoice operations; Woo can be display cache only. The connector suppresses Woo stock reduction/restoration for explicitly Agah-authoritative orders. This code path is simulator-tested and cannot be turned into a live authority with the supplied API-less alpha12.

Outbox: pending → mock_delivered or retry → dead. Each row has immutable event UUID/payload hash, store/company and next_try; retries retain the same idempotency key. Same key with a different payload is conflict. Dead rows can be retried by authorized admin from the Connector page after correcting the underlying issue. The payload is never edited to make an old key represent a new sale. `mock_delivered` always remains labelled MOCK_ONLY.

For unknown remote results, lookup or repeat the original idempotency key. An invoice event atomically consumes reservation and posts stock/accounting once. Return after invoice uses a credit document; before invoice, cancellation releases a reservation. Cross-company events, unsupported schemas, conflicting replays and stale real stock fail closed. Changing SKU does not change local financial identity; mapping history retains prior remote IDs/versions.

Authority change requires an explicit administrator, no open pending/on-hold/quote orders, mapping and balance review, and approval. Actual connected activation is BLOCKED_EXTERNAL until a live API and tests exist. Disabling Connector after a connected configuration leaves Core in degraded mode; there is no implicit promotion of cached Woo prices. To return to standalone, reconcile first and invoke the controlled authority service.

Social/SMS/OTP: select only official provider APIs and an approved adapter, opt in to marketing separately, never infer consent from an order. No unofficial social automation is built in. Transactional email has a real WP-mail dispatcher; actual delivery depends on the operator's SMTP/provider and is not certified by a mock inbox test.
