# Phase 2H4 — Customer-Safe Email Delivery Summary Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2H3-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2H4 adds a **customer-safe, read-only delivery summary** to WooCommerce **customer order emails**, reusing the Phase 2H3 summary builder and protected snapshots from Phase 2H1.

| Area | Files / behaviour |
|------|-------------------|
| Feature flag | `enable_customer_email_delivery_summary` (default **false**) |
| Email renderer | `CustomerOrderDeliveryEmailSummaryRenderer` |
| System Status | Email summary rows |
| Diagnostics | Email summary misconfiguration warnings |

## What was intentionally not added

- Shipment records, shipment tables, tracking timelines, tracking numbers
- Customer shipment pages, public REST/Store API
- Frontend JS/CSS, carrier APIs, driver/OTP/QR/GPS/POD flows
- Email recipient/subject/header/attachment/sending changes
- Automatic order completion, snapshot repair, quote recalculation
- Order or order-item meta writes/mutations
- Schema migrations

## New customer email summary feature flag

### `enable_customer_email_delivery_summary` (default: false)

| State | Behaviour |
|-------|-----------|
| **false** | No email summary; hook not registered |
| **true** | Read-only summary in customer order emails when valid snapshot meta exists |

Uses `CustomerOrderDeliverySummaryBuilder` (same safe fields as thank-you / My Account). Does not require snapshot persistence flag to be currently enabled for runtime display of historical snapshots; diagnostics warn when persistence flag is off.

## Email renderer behaviour

`CustomerOrderDeliveryEmailSummaryRenderer`:

1. Registers `woocommerce_email_after_order_table` when flag is on
2. Skips admin emails (`$sent_to_admin === true`)
3. Skips non-customer emails when `WC_Email::is_customer_email()` is available
4. Builds summary via `CustomerOrderDeliverySummaryBuilder`
5. Renders **HTML** (inline email-safe tables) or **plain text** when `$plain_text` is true
6. Shows nothing when builder returns null
7. Omits recorded timestamps in email output (reduces noise)

Does **not** alter recipients, subjects, headers, attachments, or sending behaviour.

## Email hook used

| Hook | Priority | Args |
|------|----------|------|
| `woocommerce_email_after_order_table` | 15 | `$order`, `$sent_to_admin`, `$plain_text`, `$email` |

## Customer email-only behaviour

- **Rendered:** customer processing, completed, on-hold, invoice, and other customer-facing order emails
- **Not rendered:** admin new-order, cancelled/failed admin notices, and any email with `$sent_to_admin === true`

## Safe fields displayed

Same customer-safe set as Phase 2H3 (minus recorded timestamps in email):

- Product name
- Delivery option public label
- Fulfilment availability/choice labels
- Offer public description
- Estimate text
- Customer-safe status phrases
- Quoted delivery amount (quoted snapshots only)
- Package shipping method label (**Delivery** default)
- Package delivery total

## Forbidden / private fields (never shown)

Same as Phase 2H3: no internal IDs, rate-card data, supplier/origin/logistics details, hashes, raw JSON, payment data, secrets, carrier/tracking data, or integrity labels.

## Missing / malformed snapshot behaviour

| Condition | Email shows |
|-----------|-------------|
| No snapshot | Nothing |
| Malformed / partial / version-mismatch / quote-missing | Nothing |
| Valid quoted snapshot | Safe summary |
| Selection-only | Safe summary without price implication |

No generic error message; no parse details exposed.

## Plain-text email behaviour

When WooCommerce sends plain-text email (`$plain_text === true`), renderer outputs a simple text block with the same safe fields (labels + values, no HTML).

## System Status changes

New/updated rows:

- Customer email delivery summary flag
- Customer email delivery summary renderer
- Email summary mode: **Read-only snapshot display** / Disabled
- Shipment tracking in emails: **Not enabled**
- Carrier email updates: **Not enabled**
- Email delivery summary: Enabled (customer emails only) / Not enabled

Does not inspect live orders or run broad scans.

## Diagnostics changes

When email summary flag is true, warns if:

- Customer order delivery summary flag is disabled
- Snapshot persistence flag is disabled
- `CustomerOrderDeliverySummaryBuilder` missing
- `OrderDeliverySnapshotReader` missing
- `OrderDeliverySnapshotIntegrity` missing
- `CustomerOrderDeliveryEmailSummaryRenderer` missing

Phase 2H1/2H2/2H3 diagnostics preserved.

## HPOS compatibility

Uses existing reader/builder on `WC_Order` CRUD meta only. No direct order table queries or meta writes.

## No shipment / tracking / public REST boundary

No shipment records, timelines, tracking numbers, customer shipment pages, REST routes, or frontend assets added.

## No order mutation

Read-only display only. No snapshot generation, repair, quote recalculation, or order changes.

## How to test

### Flag off (default)

Trigger customer processing/completed email → no delivery summary section.

### Flag on + valid snapshot order

Enable `enable_customer_email_delivery_summary`. Place/view order with valid snapshots. Send or preview customer processing/completed email → **Delivery details** section with safe fields only.

### Flag on + missing snapshots

Email sends normally with no delivery section.

### Malformed snapshot (staging)

Corrupt snapshot JSON → email sends with no delivery section; no PHP errors; meta unchanged.

### Admin email excluded

Preview **New order** (admin) email → no delivery summary block.

### Confirm no meta mutation

Compare order meta before/after email send → unchanged.

### Confirm email transport unchanged

Recipients, subject, headers unchanged (only body content appended when summary exists).

## Known limitations

- Recorded timestamps omitted from email output intentionally
- Email HTML uses minimal inline styles (theme/WC email template may vary)
- Requires WooCommerce email template to fire `woocommerce_email_after_order_table`
- Email flag independent of customer page summary flag at runtime (diagnostics recommend both on)
- No tracking/shipment status in emails

## Recommended next phase

**V1 release-candidate audit:** full flag matrix, HPOS/email smoke tests, privacy review, and documentation pass across Phases 2A–2H4 before shipment/tracking work.
