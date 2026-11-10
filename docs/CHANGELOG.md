# Changelog

## 1.1.0

Initial implementation against the final 1.1 project specification: three independent WP packages, exact Gold component arithmetic, explicit rate/price policies and units, WC HPOS orders, scoped expiring quotes, one-use payment start, durable receipt/review handling, native stock holds/refunds, Persian customer/admin tools, report exports, modular theme and proposed Agah simulator/outbox/inbox.

Corrections found by actual tests: unchanged Woo stock-hold updates no longer falsely report no inventory; SQL migration columns follow dbDelta line structure; classic checkout is explicitly configured without silently replacing existing pages; private headers are emitted after query resolution; dark-mode brand variables no longer override dark surfaces; contrast and Select2 labels corrected; quote supersession updates an exact existing UUID to avoid insertion-gap deadlocks between new orders; payment deadlines and invoice identities are snapshotted; received payment retries recover persisted receipts.

No live Agah, bank, SMS or external rate service certification is asserted. See TEST_REPORT and KNOWN_LIMITATIONS.
