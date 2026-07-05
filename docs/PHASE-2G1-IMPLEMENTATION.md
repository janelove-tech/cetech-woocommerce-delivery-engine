# Phase 2G1 — Rate Quote Engine Foundation + Admin Quote Test

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2F1-IMPLEMENTATION.md`, `docs/PHASE-2B4-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2G1 adds a **server-side rate quote engine** and an **admin-only quote test tool**. Quotes resolve from delivery offer, destination zone, quantity, currency, and optional logistics dimensions against stored active rate cards.

| Area | Files / behaviour |
|------|-------------------|
| Quote request | `RateQuoteRequest` |
| Quote line | `RateQuoteLine` |
| Quote result | `RateQuoteResult` |
| Quote engine | `RateQuoteEngine` |
| Repository lookup | `RateCardRepositoryInterface::listActiveForQuoteMatch()` |
| Admin quote test | Rate Cards page → **Test rate quote engine** |
| System Status | Rate quote engine rows |
| Diagnostics | Rate-card coverage warnings |

## What was intentionally not added

- WooCommerce shipping method registration
- Shipping package splitting or checkout shipping calculation
- Cart/checkout total changes
- Order delivery snapshots or order item meta
- Shipment creation or management
- Customer timeline, public REST/Store API
- Frontend JS/CSS, live carrier APIs
- Driver/OTP/QR/GPS/proof-of-delivery
- Schema migrations or database writes from quoting

## RateQuoteRequest fields

| Field | Required | Notes |
|-------|----------|-------|
| `delivery_offer_id` | Yes | Positive integer |
| `destination_zone_id` | Yes | Positive integer |
| `quantity` | Yes | Positive integer |
| `currency_code` | Yes | Uppercase 3-letter ISO via `CurrencyCode` |
| `product_id` | No | Optional intent context |
| `variation_id` | No | Optional intent context |
| `rule_id` | No | Optional intent context |
| `logistics_profile_id` | No | Optional match dimension |
| `supplier_id` | No | Optional match dimension |
| `origin_id` | No | Optional match dimension |
| `fulfilment_availability` | No | Reserved for future intent-based quoting |
| `fulfilment_choice` | No | Reserved for future intent-based quoting |

## RateQuoteResult fields

| Field | Notes |
|-------|-------|
| `success` | `true` when a quote amount was calculated |
| `amount` | `Money` value object (decimal string + currency) |
| `line` | `RateQuoteLine` with charge type, amount, quantity |
| `matched_rate_card_id` | Admin-safe internal ID |
| `matched_rate_card_code` | Admin-safe internal code |
| `charge_type` | `fixed_per_shipment` or `fixed_per_item` |
| `message` | Safe admin explanation |
| `error_code` | Admin/test only (e.g. `no_matching_rate_card`) |

Private supplier/origin/internal notes are never included in result messages.

## Matching rules (Phase 2B4 aligned)

1. **Active only** — rate card `status = active`
2. **Exact** `delivery_offer_id`
3. **Exact** `destination_zone_id`
4. **Exact** `base_currency`
5. **Optional dimensions** — `logistics_profile_id`, `supplier_id`, `origin_id`:
   - `NULL` on rate card = wildcard (matches any test value)
   - Exact positive value on rate card = must match when test value provided
6. **Effective window** — current UTC must fall within `effective_from` / `effective_to` when set
7. **Sort winner** — specificity DESC → priority ASC → id ASC

SQL prefilter via `listActiveForQuoteMatch()`; optional dimensions and effective dates applied in PHP.

## Charge calculation rules

| Charge type | Calculation |
|-------------|-------------|
| `fixed_per_shipment` | `base_amount` once |
| `fixed_per_item` | `base_amount × quantity` (uses `bcmul` when available) |

Amounts use `Money` with 4-decimal string precision. Negative or non-numeric base amounts fail the quote.

## Missing rate-card behavior

- Returns `success = false`
- Error code: `no_matching_rate_card`
- Message: **“No matching active rate card found. Delivery cannot be priced.”**
- **Never** returns zero or free shipping as a fallback

## Admin quote test behavior

**Location:** Delivery Engine → Rate Cards → **Test rate quote engine** (below existing rate card tester)

**Capability:** `manage_delivery_rate_cards`

**Inputs:** delivery offer, destination zone, quantity, currency, optional logistics profile/supplier/origin, optional product ID

**Behavior:**

- Nonce-protected POST
- Sanitized/validated inputs via `RateCardValidator::validate_quote_test_input()`
- Calls `RateQuoteEngine::quote()`
- Displays success/failure, amount, currency, matched rate card code/ID, charge type, explanation
- Read-only — no configuration, cart, session, checkout, or order writes

## System Status changes

New rows under **Runtime readiness (admin/test only)**:

| Row | Value |
|-----|-------|
| Rate quote engine registered | Yes when `RateQuoteEngine` class exists |
| Rate quote mode | Admin/test only; no WooCommerce shipping calculation |
| Rate quote admin test location | Delivery Engine → Rate Cards → Test rate quote engine |
| WooCommerce shipping method | Not registered |
| Cart/checkout totals | Not modified |
| Missing rate card behavior | Blocks quote / no free fallback |

## Diagnostics changes

| Code | Severity | Trigger |
|------|----------|---------|
| `active_product_rules_without_active_rate_cards` | Warning | Active product rules exist but zero active rate cards |
| `rate_quote_engine_missing_with_capture_or_checkout` | Warning | Cart capture or checkout validation enabled but engine class missing |
| `delivery_offer_without_active_rate_cards` | Warning | Active delivery offer has no active rate cards referencing it |

Existing diagnostics preserved.

## WooCommerce boundary

This phase does **not**:

- Register `WC_Shipping_Method`
- Hook cart/checkout total filters
- Write order or order item meta
- Inspect live customer carts
- Expose quotes on storefront, cart, or checkout

The existing **Test rate card** tool (`AdminRateCardTester`) remains unchanged for card-level matching preview.

## Customer-safe / private-field rules

- Quote results are admin-only
- No supplier/origin private notes in messages
- Matched rate card ID/code shown only in admin test output
- `RateQuoteEngine` does not call WooCommerce shipping APIs

## How to test successful quote

1. Ensure an **active** rate card exists for a delivery offer + destination zone + currency.
2. Go to **Delivery Engine → Rate Cards**.
3. Scroll to **Test rate quote engine**.
4. Select matching offer, zone, quantity, currency; optional dimensions if card is specific.
5. Click **Run quote test**.
6. Expect: `Success | Matched: … | Charge: … | Amount: …`

## How to test missing rate card

1. Use a delivery offer/zone/currency combination with **no** active matching rate card (or deactivate all matches).
2. Run quote test with those inputs.
3. Expect: **“No matching active rate card found. Delivery cannot be priced.”** with `[no_matching_rate_card]`
4. Confirm amount is **not** zero or “free”.

## How to confirm no cart/checkout/shipping/order changes

1. Add products to cart and proceed to checkout — totals unchanged by this phase.
2. Search codebase — no new `WC_Shipping_Method`, `woocommerce_package_rates`, cart total hooks, or order meta writes from quote classes.
3. System Status shows shipping method **Not registered**, cart/checkout totals **Not modified**.

## Known limitations

- Quote engine does not yet consume captured cart selection intent (`fulfilment_*`, `rule_id`) for pricing logic
- No destination zone resolution from customer address — zone ID must be supplied explicitly (admin test)
- Only `fixed_per_shipment` and `fixed_per_item` charge types supported
- No WooCommerce multi-currency integration — currency is explicit input
- Existing **Test rate card** tool and **Test rate quote engine** are separate (tester vs engine)

## Recommended next phase

**Phase 2G2 (suggested):** Wire rate quotes into cart/checkout shipping calculation behind feature flags, using captured delivery selection + destination zone resolution — still without order snapshots until a dedicated order phase.
