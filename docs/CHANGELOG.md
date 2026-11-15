# Changelog

## 1.2.2

Theme 1.2.1 expands visitor color selection from a single accent to a generated site-wide palette covering primary, deep surfaces, hover, emphasized text, two soft surfaces, borders, page background, cards and contrasting foreground. A native free color picker lets visitors choose any color, persists it locally and recalculates the palette for light or dark mode. Extreme white and black selections retain a tested 4.5:1 foreground contrast on primary surfaces.

No commerce, accounting, product or Agah code changed.

## 1.2.1

Theme 1.2.0 adds separate floating visitor-personalization and accessibility panels inspired by the supplied Novin reference. New visitors start in light mode regardless of the operating-system color preference; explicit choices persist locally. Personalization covers light/dark mode, accent, readable font and text size. Accessibility covers high contrast, text spacing, reduced motion and readable font. The back-to-top control is vertically separated from personalization, while accessibility stays on the opposite side, including at 360px.

No pricing, checkout, product, accounting or Agah behavior changed. Dedicated browser checks and 16 axe light/dark responsive scans pass.

## 1.1.0

Initial implementation against the final 1.1 project specification: three independent WP packages, exact Gold component arithmetic, explicit rate/price policies and units, WC HPOS orders, scoped expiring quotes, one-use payment start, durable receipt/review handling, native stock holds/refunds, Persian customer/admin tools, report exports, modular theme and proposed Agah simulator/outbox/inbox.

Corrections found by actual tests: unchanged Woo stock-hold updates no longer falsely report no inventory; SQL migration columns follow dbDelta line structure; classic checkout is explicitly configured without silently replacing existing pages; private headers are emitted after query resolution; dark-mode brand variables no longer override dark surfaces; contrast and Select2 labels corrected; quote supersession updates an exact existing UUID to avoid insertion-gap deadlocks between new orders; payment deadlines and invoice identities are snapshotted; received payment retries recover persisted receipts.

No live Agah, bank, SMS or external rate service certification is asserted. See TEST_REPORT and KNOWN_LIMITATIONS.
