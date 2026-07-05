# Phase 2H1 — Order Delivery Snapshot Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2G2-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2H1 persists **stable delivery snapshots** onto WooCommerce order items and orders at checkout creation time, using captured cart delivery selections and order-time quote context.

| Area | Files / behaviour |
|------|-------------------|
| Feature flag | `enable_order_delivery_snapshot_persistence` (default **false**) |
| Runtime gate | `OrderDeliverySnapshotGate` |
| Snapshot contract | `OrderDeliverySnapshot`, `OrderDeliveryLineSnapshot`, `OrderDeliveryPackageSnapshot` |
| Builder | `OrderDeliverySnapshotBuilder` |
| Persister | `OrderDeliverySnapshotPersister` |
| System Status | Order snapshot rows |
| Diagnostics | Snapshot flag misconfiguration warnings |

## What was intentionally not added

- Shipment records, shipment tables, tracking timelines
- Customer shipment pages, public REST/Store API
- Frontend JS/CSS, carrier APIs, driver/OTP/QR/GPS/POD flows
- Automatic order completion, payment data capture
- Customer-facing duplicate order item meta (protected meta only)
- Admin order meta box UI (deferred; inspect meta via order editor tools)
- Schema migrations

## New order snapshot feature flag

### `enable_order_delivery_snapshot_persistence` (default: false)

| State | Behaviour |
|-------|-----------|
| **false** | No order/item snapshot meta written |
| **true** + upstream flags **off** | No persistence; diagnostics warn |
| **true** + all upstream flags **on** | Snapshots written at checkout order creation |

Required upstream flags (via `OrderDeliverySnapshotGate` → `ShippingRateCalculationGate`):

- `enable_product_delivery_selector`
- `enable_cart_delivery_selection_capture`
- `enable_checkout_delivery_selection_validation`
- `enable_woocommerce_shipping_rate_calculation`

## Snapshot data contract

### Per order line (`OrderDeliveryLineSnapshot`)

| Field | Notes |
|-------|-------|
| `contract_version` | Selection intent contract (`1`) |
| `snapshot_version` | Snapshot schema version (`1`) |
| `product_id`, `variation_id`, `quantity` | From cart/order line |
| `fulfilment_availability`, `fulfilment_choice` | From validated intent |
| `delivery_offer_id` | Nullable |
| `delivery_offer_public_label` | From cart summary |
| `delivery_offer_public_description` | From offer `public_description` at snapshot time |
| `estimate_text` | From cart summary |
| `rule_id`, `destination_zone_id` | Nullable IDs only |
| `currency_code` | Order currency |
| `quoted_amount` | When quote succeeds at snapshot time |
| `quote_status` | `quoted` or `selection_only` |
| `rate_card_id`, `rate_card_code` | Protected/internal only |
| `snapshotted_at` | UTC ISO-8601 |

**Not stored:** supplier/origin/logistics names, internal notes, internal costs, payment data, secrets.

### Order package (`OrderDeliveryPackageSnapshot`)

| Field | Notes |
|-------|-------|
| `shipping_method_id` | `delivery_engine_selected_offer` when selected |
| `shipping_method_label` | `Delivery` |
| `package_total_delivery_amount` | WooCommerce shipping line total |
| `currency_code`, `destination_zone_id` | Order context |
| `quote_status` | `success`, `not_applicable` |
| `snapshotted_at` | UTC ISO-8601 |

## Order / item meta keys

| Key | Location | Visibility |
|-----|----------|------------|
| `_cetech_de_delivery_snapshot` | Order item | Protected (hidden) JSON |
| `_cetech_de_delivery_snapshot_version` | Order item | Protected |
| `_cetech_de_delivery_quote_snapshot` | Order | Protected JSON |
| `_cetech_de_order_delivery_snapshot_version` | Order | Protected |

Uses `$item->add_meta_data( ..., true )` and `$order->update_meta_data()` (HPOS-compatible CRUD).

## Hooks used

| Hook | Purpose |
|------|---------|
| `woocommerce_checkout_create_order_line_item` | Persist line snapshot when item created |
| `woocommerce_checkout_order_created` | Persist order package snapshot |

Does not mutate order totals or payment flow.

## Snapshot source data

From cart item values at checkout:

- `cetech_de_delivery_selection`
- `cetech_de_delivery_selection_summary`
- `cetech_de_delivery_selection_hash` (validated via revalidator + hash check)

Quote context:

- `RateQuoteEngine` at order creation for line `quoted_amount` (does not change WC totals)
- WooCommerce selected shipping line total for package snapshot

## Validation before persistence

1. Snapshot flag + full upstream chain active
2. Cart item has captured selection
3. `CartDeliverySelectionRevalidator` returns `valid`
4. Intent normalizes successfully
5. Lines with `delivery_offer_id` require resolvable destination zone and successful quote — otherwise **no snapshot** (avoids misleading partial data)

Checkout validation (Phase 2F1) should already block unsafe orders when enabled.

## Customer-safe / private-field rules

- All snapshot JSON is **protected meta** (not customer-visible by default)
- Public labels/descriptions only from existing safe summary/offer fields
- Rate card ID/code stored in protected meta for admin/internal future use only
- No supplier/origin/logistics private data

## System Status changes

- Order delivery snapshot flag
- Order snapshot persistence registered
- Snapshot mode: order item protected meta only / disabled
- Order snapshot status with meta key reference
- Shipment creation: not enabled
- Tracking timeline: not enabled
- Public order delivery page: not enabled

## Diagnostics changes

When snapshot flag is true, warns if:

- Shipping calculation, checkout validation, cart capture, or selector flags disabled
- `OrderDeliverySnapshotPersister` missing
- WooCommerce unavailable

## HPOS compatibility

Uses `WC_Order`, `WC_Order_Item_Product` CRUD methods (`add_meta_data`, `update_meta_data`, `save`). No direct `wp_posts` / order table queries. Plugin declares HPOS compatibility via existing `FeaturesCompatibility`.

## No shipment / tracking / public REST boundary

No shipment records, timelines, customer pages, REST routes, or frontend assets added.

## How to test

### Flag off (default)

Place order → no `_cetech_de_*` snapshot meta on items/order.

### Upstream on, snapshot flag off

Full selector/capture/checkout/shipping chain active; snapshot flag false → no snapshot meta.

### Snapshot flag on + valid checkout

Enable all five flags; complete checkout with captured selections and selected-offer shipping.

Inspect order item meta (Screen Options → Custom Fields or database meta table via WC tools):

- `_cetech_de_delivery_snapshot` JSON on lines with valid selections
- `_cetech_de_delivery_quote_snapshot` on order when applicable

### Confirm no shipments

No shipment tables/records created; System Status shows shipment creation **Not enabled**.

## Known limitations

- No admin order UI for snapshots (deferred)
- Line quote captured via `RateQuoteEngine` at order creation (stable snapshot, separate from WC total calculation path)
- Pickup-only lines without `delivery_offer_id` may snapshot as `selection_only` without amount
- Variable product capture still deferred from Phase 2E1
- Package snapshot written when line snapshots exist or selected-offer shipping used

## Recommended next phase

**Phase 2H2 (suggested):** Read-only admin order snapshot display + optional customer-safe order confirmation summary using stored protected meta.
