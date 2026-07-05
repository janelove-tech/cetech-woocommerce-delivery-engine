# Phase 2G2 — WooCommerce Shipping Method Foundation + Quoted Rate Calculation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2G1-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2G2 introduces a **guarded WooCommerce shipping method** that calculates one package shipping rate from captured cart delivery selections and the Phase 2G1 `RateQuoteEngine`.

| Area | Files / behaviour |
|------|-------------------|
| Feature flag | `enable_woocommerce_shipping_rate_calculation` (default **false**) |
| Runtime gate | `ShippingRateCalculationGate` |
| Destination resolver | `DestinationZoneMatcher`, `PackageDestinationZoneResolver` |
| Rate calculator | `SelectedOfferShippingRateCalculator` |
| WC shipping method | `SelectedOfferShippingMethod` (`delivery_engine_selected_offer`) |
| Integration | `SelectedOfferShippingIntegration` |
| Admin test matcher | `DestinationZoneTestMatcher` delegates to `DestinationZoneMatcher` |
| System Status | Shipping calculation rows |
| Diagnostics | Shipping flag misconfiguration warnings |

## What was intentionally not added

- Order delivery snapshots or order item meta
- Shipment records, tracking timelines, customer shipment pages
- Public REST/Store API, frontend JS/CSS
- Checkout fields/selectors, cart mutation in shipping calculator
- Carrier/live API integrations
- Schema migrations
- Multiple customer-facing methods (Air/Sea/local as separate WC methods)
- Free/zero fallback when quotes fail

## New shipping calculation feature flag

### `enable_woocommerce_shipping_rate_calculation` (default: false)

| State | Behaviour |
|-------|-----------|
| **false** | No custom shipping method registered; no plugin shipping rates |
| **true** + upstream flags **off** | Method not registered; diagnostics warn; no rates |
| **true** + all upstream flags **on** | Method registers; rates calculated at cart/checkout shipping step |

Required upstream flags (all must be true for runtime):

- `enable_product_delivery_selector`
- `enable_cart_delivery_selection_capture`
- `enable_checkout_delivery_selection_validation`

## Shipping method ID / title

| Property | Value |
|----------|-------|
| Method ID | `delivery_engine_selected_offer` |
| Customer rate label | **Delivery** (configurable per shipping zone instance) |
| Admin method title | Delivery Engine — Selected Offer |

No supplier/origin/rate-card/internal data in the public label.

## Rate calculation flow

1. WooCommerce calls `SelectedOfferShippingMethod::calculate_shipping()` for each package.
2. `SelectedOfferShippingRateCalculator` runs only when `ShippingRateCalculationGate::is_runtime_active()`.
3. Resolve `destination_zone_id` from package destination via `PackageDestinationZoneResolver`.
4. For each cart line in the package:
   - **Skip** if line is outside capture scope or has no applicable product rules.
   - **Block package** if capture-applicable line has missing/invalid/stale/unavailable selection.
   - **Quote** if line has valid captured selection with `delivery_offer_id`.
5. Build `RateQuoteRequest` per quotable line (currency from WooCommerce; optional logistics dimensions from server-side product rule by `rule_id`).
6. Call `RateQuoteEngine::quote()` and sum successful line amounts.
7. If all required quotable lines succeed and at least one line quoted → add **one** WC rate with total cost.
8. Otherwise → **no rate added** (no zero/free fallback).

## Destination zone resolution

`PackageDestinationZoneResolver` uses `DestinationZoneMatcher`:

- Loads active destination zones and their rules
- Matches package `country`, `state`, `city`, `postcode` (same rules as admin zone tester)
- Picks lowest priority number among matching zones
- Uses an **explicit active fallback zone** (`is_fallback`) only when no rule-based zone matches

If destination cannot be resolved → **no shipping quote** (no fake zone, no free fallback).

## Missing quote / no-free-fallback behaviour

- Unresolved destination → no rate
- Invalid/missing/stale cart selection on capture-applicable lines → no rate
- Any `RateQuoteEngine` failure on a quotable line → no rate
- No quotable lines in package → no rate
- Never adds a zero-cost rate as a fallback for failures

Checkout validation (Phase 2F1) remains responsible for blocking unsafe checkout when selections are invalid.

## Mixed cart behaviour

| Line type | Shipping calculator |
|-----------|---------------------|
| Valid captured selection with `delivery_offer_id` | Included in quote sum |
| Valid selection without `delivery_offer_id` (e.g. pickup-only) | Skipped (not part of delivery shipping quote) |
| No applicable product delivery rules | Skipped |
| Capture-applicable but missing/invalid/stale selection | **Blocks** entire package rate |
| Variable/non-capture products without selection | Skipped |

If any blocking line exists, **no** plugin shipping rate is offered for that package.

## Customer-safe output rules

- Public shipping label: **Delivery** only
- No rate card code/ID, supplier, origin, or internal notes in labels
- Logger context strips supplier/origin identifiers

## Forbidden / private fields

Not exposed in shipping rates or customer-facing output:

- Supplier/origin names or IDs
- Rate card internal codes/IDs
- Logistics profile private data
- Internal notes

## System Status changes

New/updated rows under **Runtime readiness**:

- WooCommerce selected-offer shipping flag
- Selected-offer shipping method registered
- Shipping method ID: `delivery_engine_selected_offer`
- Shipping calculation mode (disabled / upstream not ready / RateQuoteEngine-backed)
- Destination zone resolver availability
- Missing quote behavior: no rate / no free fallback
- Order snapshot: not enabled
- Shipment creation: not enabled
- Cart/checkout totals: WooCommerce shipping rates only when runtime active

## Diagnostics changes

When `enable_woocommerce_shipping_rate_calculation` is true, warns if:

- Upstream selector/capture/checkout flags disabled
- `RateQuoteEngine` missing
- Destination resolver classes missing
- Zero active destination zones
- Zero active rate cards

## No order snapshot / shipment boundary

This phase does **not** write order meta, order item meta, shipment records, or customer timelines. Shipping rates are calculated transiently during WooCommerce's shipping step only.

## How to test

### Flag off (default)

1. Confirm `enable_woocommerce_shipping_rate_calculation` is false.
2. Cart/checkout → no **Delivery** method from this plugin.
3. System Status → shipping method **Not registered**.

### Upstream on, shipping flag off

1. Enable selector + capture + checkout validation.
2. Keep shipping calculation flag **false**.
3. Confirm no plugin shipping method; cart selections still work.

### All flags on + matching rate card

1. Enable all four flags.
2. Add **Delivery Engine — Selected Offer** to a WooCommerce shipping zone.
3. Configure active zone rules, rate card, product rules, and product selector.
4. Add product with valid delivery selection to cart; enter matching destination at checkout/cart shipping calculator.
5. Expect **Delivery** rate with quoted amount.

### Missing rate card

1. Use offer/zone/currency with no active rate card.
2. Expect **no** plugin shipping rate (not zero/free).

### Unresolved destination

1. Use destination that matches no zone and no fallback.
2. Expect **no** plugin shipping rate.

### Confirm no order meta / shipments

1. Complete test checkout (if desired in dev).
2. Verify no new order item meta keys from this plugin; no shipment records created.

## Known limitations

- Simple products only for capture scope (variable products deferred)
- One aggregated **Delivery** rate per package (no per-line shipping breakdown in UI)
- Destination resolution requires configured zones/rules (or explicit fallback zone)
- Merchant must manually add the method to WooCommerce shipping zones when enabling
- Pickup-only selections without `delivery_offer_id` are excluded from delivery shipping quote
- No order-time persistence of quoted amounts

## Recommended next phase

**Phase 2H (suggested):** Order delivery snapshot foundation — persist validated selection + quoted shipping context on order placement without shipment workflow.
