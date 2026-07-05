# Phase 2H2 — Order Snapshot Admin Display + Integrity Review

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2H1-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2H2 adds a **read-only admin view** of protected order delivery snapshots created in Phase 2H1, plus snapshot parsing and integrity classification helpers.

| Area | Files / behaviour |
|------|-------------------|
| Snapshot reader | `OrderDeliverySnapshotReader`, `OrderDeliveryLineReadResult`, `OrderDeliveryPackageReadResult` |
| Integrity checker | `OrderDeliverySnapshotIntegrity` |
| Admin display | `OrderDeliverySnapshotAdminDisplay` (WooCommerce order meta box) |
| System Status | Snapshot display/reader/integrity rows |
| Diagnostics | Reader/display/integrity missing warnings when snapshot flag is on |

## What was intentionally not added

- Shipment records, shipment tables, tracking timelines
- Customer shipment pages, public REST/Store API
- Frontend JS/CSS, carrier APIs, driver/OTP/QR/GPS/POD flows
- Automatic order completion, snapshot repair, quote recalculation
- Snapshot meta writes or mutations
- Order list column/badge (deferred — avoids broad order queries)
- Raw JSON display (deferred)
- Schema migrations

## Feature flag behaviour

Uses existing **`enable_order_delivery_snapshot_persistence`** from Phase 2H1.

| Rule | Behaviour |
|------|-----------|
| Admin display registration | Registers when WooCommerce is active and user is in admin — **even when snapshot flag is off** |
| Historical review | Allows reviewing snapshots on orders created when persistence was previously enabled |
| Writes | **None** — display is read-only |

## Snapshot reader / parser behaviour

`OrderDeliverySnapshotReader` reads protected meta via HPOS-compatible CRUD:

- `$item->get_meta( OrderDeliverySnapshot::META_LINE_SNAPSHOT, true )`
- `$order->get_meta( OrderDeliverySnapshot::META_ORDER_QUOTE_SNAPSHOT, true )`

Processing:

1. Detect missing meta
2. `json_decode()` safely — malformed JSON → error
3. Validate stored meta version and JSON `snapshot_version` / `contract_version`
4. Validate required keys and scalar types
5. Normalize into `OrderDeliveryLineSnapshot` / `OrderDeliveryPackageSnapshot` value objects

Does **not** write meta, query order tables directly, or scan unrelated orders.

## Admin order display location

**WooCommerce order edit screen** meta box:

- Title: **Delivery Engine — Order Snapshots**
- Screen: `wc_get_page_screen_id( 'shop-order' )` (HPOS + legacy compatible)
- Capability: `edit_shop_order`, `read_shop_order`, or `manage_woocommerce` for the order

## Displayed safe fields

**Summary:** line snapshots status, package snapshot status, snapshot version

**Per line item:**

- Status (integrity classification)
- Fulfilment availability / choice
- Delivery offer public label and description
- Estimate text
- Quantity, quote status, quoted amount, currency
- Snapshotted at

**Package snapshot:**

- Status, shipping method label, package delivery amount, quote status, snapshotted at

**Internal IDs (collapsed `<details>` block):**

- product_id, variation_id, delivery_offer_id, rule_id, destination_zone_id, rate_card_id/code, shipping method ID

## Forbidden / private fields (never shown)

- Supplier/origin/logistics names or details
- internal_notes, internal costs
- Customer payment data, secrets, tokens, hashes
- Raw snapshot JSON (not shown in this phase)

## Integrity / status behaviour

`OrderDeliverySnapshotIntegrity` classifies snapshots read-only:

| Status | Meaning |
|--------|---------|
| `present_valid` | Parsed snapshot with expected quote data |
| `missing` | No snapshot meta |
| `malformed` | Invalid JSON |
| `version_mismatch` | Meta or JSON version mismatch |
| `partial` | Missing required fields |
| `quote_missing` | Offer present but no quoted amount / selection-only with offer ID |
| `selection_only` | Pickup/selection-only snapshot without delivery quote |

No automatic repair, recalculation, or meta mutation.

## System Status changes

New rows:

- Order snapshot admin display: registered / not registered
- Snapshot reader registered / not registered
- Snapshot integrity checker registered / not registered
- Snapshot display mode: **Read-only admin only**
- Shipment creation / tracking timeline / public order delivery page: **Not enabled** (unchanged)

Does not inspect live orders or run broad scans.

## Diagnostics changes

When `enable_order_delivery_snapshot_persistence` is true, additionally warns if:

- `OrderDeliverySnapshotReader` missing
- `OrderDeliverySnapshotAdminDisplay` missing
- `OrderDeliverySnapshotIntegrity` missing

Phase 2H1 diagnostics preserved.

## HPOS compatibility

Uses `WC_Order`, `WC_Order_Item_Product`, and `get_meta()` only. No direct postmeta/order table queries. Works on legacy post-based orders and HPOS order screens.

## No shipment / tracking / public REST boundary

No shipment records, timelines, customer pages, REST routes, or frontend assets added.

## How to test

### Order with snapshot meta

1. Enable all five Phase 2E–2H1 flags and place a test order with captured delivery selections.
2. Open **WooCommerce → Orders → [order]**.
3. Find meta box **Delivery Engine — Order Snapshots**.
4. Confirm line and package fields match protected meta.
5. Expand **Internal IDs** only if needed — verify rate card IDs stay in collapsed section.

### Order without snapshot meta

1. Open an order placed before snapshots or with snapshot flag off.
2. Meta box shows **No delivery snapshot meta found on this order**.

### Malformed snapshot meta (safe test)

1. On a staging order, manually set `_cetech_de_delivery_snapshot` to invalid JSON via WP CLI or order meta tools.
2. Reload order admin — status should show **Malformed** without PHP errors.
3. Confirm meta value unchanged after page load.

### Confirm no meta mutation

1. Note snapshot meta values before opening order admin.
2. Open and reload order edit screen multiple times.
3. Verify meta bytes unchanged — display is read-only.

## Known limitations

- No order list column/badge (deferred)
- No raw JSON viewer
- No customer-facing order confirmation summary (recommended next)
- Line `quoted_amount` in snapshot may differ from WooCommerce shipping total (documented in 2H1)
- Meta box always visible on order screen when WooCommerce active (shows “missing” when no data)

## Recommended next phase

**Phase 2H3 (suggested):** Customer-safe order confirmation / My Account delivery summary using public labels only (no internal IDs), still read-only from stored snapshots.
