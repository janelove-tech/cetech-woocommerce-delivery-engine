# Phase 2H3 — Customer-Safe Order Delivery Summary Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2H2-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2H3 adds a **customer-safe, read-only delivery summary** on WooCommerce order view surfaces, built from protected snapshots persisted in Phase 2H1 and parsed in Phase 2H2.

| Area | Files / behaviour |
|------|-------------------|
| Feature flag | `enable_customer_order_delivery_summary` (default **false**) |
| Summary builder | `CustomerOrderDeliverySummaryBuilder` + summary value objects |
| Customer renderer | `CustomerOrderDeliverySummaryRenderer` |
| System Status | Customer summary rows |
| Diagnostics | Customer summary misconfiguration warnings |

## What was intentionally not added

- Shipment records, shipment tables, tracking timelines, tracking numbers
- Customer shipment pages, public REST/Store API
- Frontend JS/CSS, carrier APIs, driver/OTP/QR/GPS/POD flows
- Email delivery summary (deferred)
- Automatic order completion, snapshot repair, quote recalculation
- Order or order-item meta writes/mutations
- Schema migrations

## New customer summary feature flag

### `enable_customer_order_delivery_summary` (default: false)

| State | Behaviour |
|-------|-----------|
| **false** | No customer-facing summary; hook not registered |
| **true** | Read-only summary on thank-you / My Account order view when valid snapshot meta exists |

Uses existing snapshot reader/integrity from Phase 2H2. Does **not** require snapshot persistence flag to be currently enabled (historical orders may still display), but diagnostics warn when summary is on and persistence flag is off.

## Customer summary builder / renderer behaviour

### Builder (`CustomerOrderDeliverySummaryBuilder`)

1. Reads line/package snapshots via `OrderDeliverySnapshotReader`
2. Classifies with `OrderDeliverySnapshotIntegrity`
3. Includes lines only when status is `present_valid` or `selection_only`
4. Includes package snapshot only when status is `present_valid`
5. Skips malformed, partial, version-mismatch, quote-missing, and missing snapshots **silently**
6. Maps to customer-safe value objects with no internal IDs

### Renderer (`CustomerOrderDeliverySummaryRenderer`)

- Registers `woocommerce_order_details_after_order_table` when flag is on
- Renders semantic HTML with WooCommerce-compatible table classes
- Escapes all output
- Shows nothing when builder returns null (missing/unsafe snapshots)

## Display locations

| Surface | Hook |
|---------|------|
| Thank-you / order received | `woocommerce_order_details_after_order_table` |
| My Account → View order | `woocommerce_order_details_after_order_table` |

Admin order meta box (Phase 2H2) is unchanged. No checkout fields. **No email output** in this phase.

## Safe fields displayed

**Per product line:**

- Product name (from order item)
- Delivery option public label
- Fulfilment availability label (customer-safe phrasing)
- Fulfilment choice label
- Offer public description (when present)
- Estimate text
- Customer-safe status phrase (`Delivery price confirmed` / `Fulfilment choice recorded`)
- Quoted delivery amount (only for valid quoted snapshots)
- Recorded timestamp (human-readable, site date/time format)

**Package/shipping block:**

- Shipping method label (defaults to **Delivery**)
- Package delivery total (when in snapshot)
- Recorded timestamp (when useful)

## Forbidden / private fields (never shown)

- product_id, variation_id, delivery_offer_id, rule_id, destination_zone_id
- rate_card_id/code
- supplier/origin/logistics names or details
- internal_notes, internal costs, hashes, raw JSON
- customer payment data, secrets, tokens, live carrier data
- Integrity status labels or parse errors

## Missing / malformed snapshot behaviour

| Condition | Customer sees |
|-----------|---------------|
| No snapshot meta | Nothing (default) |
| Malformed / partial / version-mismatch / quote-missing | Nothing (skipped silently) |
| Valid quoted snapshot | Safe summary with amounts where applicable |
| Selection-only snapshot | Safe summary without delivery price or tracking implication |

No generic error message is shown by default (prefer silent omission).

## System Status changes

New rows:

- Customer order delivery summary flag
- Customer order delivery summary renderer
- Customer summary mode: **Read-only snapshot display** / Disabled
- Email delivery summary: **Not enabled**
- Shipment tracking timeline: **Not enabled**
- Public shipment page: **Not enabled**

Does not inspect live orders or run broad scans.

## Diagnostics changes

When customer summary flag is true, warns if:

- Snapshot persistence flag is disabled
- `OrderDeliverySnapshotReader` missing
- `OrderDeliverySnapshotIntegrity` missing
- `CustomerOrderDeliverySummaryRenderer` missing

Phase 2H1/2H2 diagnostics preserved.

## HPOS compatibility

Uses `WC_Order`, `WC_Order_Item_Product`, and `get_meta()` via existing reader. No direct order table queries or meta writes.

## No shipment / tracking / public REST boundary

No shipment records, timelines, tracking numbers, customer shipment pages, REST routes, or frontend assets added.

## No email output

Email delivery summary deferred to a later phase.

## How to test

### Flag off (default)

Complete checkout / view order → no **Delivery details** section.

### Flag on + valid snapshot order

Enable `enable_customer_order_delivery_summary` and ensure order has valid Phase 2H1 snapshots. View thank-you page and My Account order → **Delivery details** section with safe fields only.

### Flag on + order without snapshots

View order → no delivery section (silent).

### Malformed snapshot (staging)

Corrupt line snapshot JSON → customer page loads with no delivery section; no PHP errors; meta unchanged.

### Confirm no meta mutation

Compare order meta before/after viewing customer order page → unchanged.

## Known limitations

- No email summary
- No tracking/shipment status
- Package block only when package snapshot is `present_valid`
- Mixed carts may show lines without package block if package snapshot missing
- Selection-only lines never show quoted amounts even if amount field exists in JSON
- Requires WooCommerce order details template (standard thank-you / My Account)

## Recommended next phase

**Phase 2H4 (suggested):** Customer-safe email delivery summary using the same builder, with explicit email-safe formatting and opt-in flag.
