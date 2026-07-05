# Phase 2C2 — Product Rule Resolver Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2C1-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2C2 adds an **admin-only product rule resolver** and a **test tool** on the Product Rules list page. Rules are read and ranked; nothing is wired to storefront, cart, checkout, or shipping.

| Area | Files / behaviour |
|------|-------------------|
| Resolver service | `ProductDeliveryRuleResolver` |
| Result types | `ProductRuleResolutionResult`, `ResolvedProductDeliveryRule` |
| Repository reads | `listActive()`, `findActiveByTargets()` |
| Admin test tool | Product Rules list → **Test product rule resolution** |
| Validation | `ProductDeliveryRuleValidator::validate_resolution_test_input()` |
| Diagnostics | Delivery-without-offers, invalid offer JSON, competing same-priority rules |

## What was intentionally not added

- Product delivery selector on storefront
- WooCommerce product edit metaboxes/panels or metadata writes
- Cart-line selections, fingerprinting, shipping packages
- Custom shipping methods, checkout validation, order snapshots
- Price calculation or customer-facing delivery output
- Public REST/Store API, frontend JS/CSS
- Schema changes or migrations

## Resolver service behavior

`ProductDeliveryRuleResolver::resolve( string $target_type, int $target_id ): ProductRuleResolutionResult`

1. Validates input target type and ID.
2. Requires WooCommerce; fails with admin-safe error if unavailable.
3. Validates input target exists via `ProductTargetResolver`.
4. Builds candidate target hierarchy (see below).
5. Loads **active** rules for all hierarchy targets via `findActiveByTargets()`.
6. Skips rules whose own target no longer exists in WooCommerce.
7. Groups eligible rules by `fulfilment_availability`.
8. Picks one **chosen rule per availability** using selection rules below.
9. Returns structured result: hierarchy, matched rules, chosen rules, skipped rules, warnings, no-match message.

Does **not** expose `internal_notes` or calculate prices.

## Resolution hierarchy

| Input target | Search order |
|--------------|--------------|
| **variation** | 1. Variation ID → 2. Parent product ID → 3. Each parent product category (sorted by ID) |
| **product** | 1. Product ID → 2. Each product category (sorted by ID) |
| **category** | 1. Category ID only |

Category IDs come from `WC_Product::get_category_ids()`.

## Selection rules

Within each `fulfilment_availability` group, the winning rule is the one with:

1. **Highest target specificity:** variation (3) > product (2) > category (1)
2. **Lowest priority number** (ascending)
3. **Lowest rule ID** (stable tie-breaker)

Only **active** rules are considered. Rules with missing WooCommerce targets are skipped, not chosen.

## Admin test tool behavior

**Location:** Delivery Engine → Product Rules (list page, below rules table)

**Inputs:** `target_type`, `target_id`  
**Action:** `cetech_de_test_product_rule_resolution`  
**Capability:** `manage_product_delivery_rules`  
**Security:** Nonce + capability via `AdminActionHandler::verify_post()`

**Output (read-only):**

- Input target label
- Ordered candidate hierarchy
- Chosen rule per fulfilment availability (ID, target, choice, offers, profile/supplier/origin codes, priority)
- Warnings and skipped-rule reasons
- No-match message when applicable

Test input and result are stored in the existing admin form-draft transient (`SLUG . '_resolve'`) for one request cycle. No configuration records are modified.

## Validation rules (test tool)

| Rule | Behaviour |
|------|-----------|
| `test_target_type` | Required; product, variation, or category |
| `test_target_id` | Required positive integer |
| Target existence | Checked via `ProductTargetResolver` when WooCommerce available |
| WooCommerce unavailable | Safe admin error |

Errors flash as admin notices; test inputs preserved in draft.

## Repository read helpers

Added to `ProductDeliveryRuleRepositoryInterface` / `WpdbProductDeliveryRuleRepository`:

- `listActive( array $filters = [] )` — wraps `list()` with `status = active`
- `findActiveByTargets( array $targets )` — single prepared query with OR clauses per target; active only

No schema or write-method changes.

## Diagnostics updates

New read-only checks in `ConfigurationHealthChecker::check_product_rules()`:

| Code | Severity | Check |
|------|----------|-------|
| `product_rule_delivery_without_offers` | warning | Active rule, delivery choice, empty offer IDs |
| `product_rule_invalid_offer_ids_json` | warning | Active rule, delivery choice, non-empty but invalid JSON |
| `product_rule_competing_same_priority` | warning | Multiple active rules: same target + availability + priority |

Existing checks (missing targets, duplicates, FK issues, etc.) unchanged.

## Privacy / customer boundary rules

- Resolver and test tool are wp-admin only
- `ResolvedProductDeliveryRule` omits `internal_notes`
- Supplier/origin shown as safe codes only in test output
- No cart/session/order/product meta writes
- No frontend hooks or REST routes
- `ProductDeliveryRuleResolver` not registered for storefront use

## How to test resolver

1. Create active rules at product, category, and (if applicable) variation levels with different priorities.
2. **Product Rules → Test product rule resolution**
3. **Product target:** enter product ID — expect product rule chosen over category rule for same availability.
4. **Variation target:** enter variation ID — expect variation rule over parent product over category.
5. **Category target:** enter category ID — only category rules considered.
6. Confirm chosen rule per `international_fulfilment`, `in_store`, `in_warehouse` when rules exist.
7. Confirm warnings for competing same-priority rules and delivery-without-offers.

## How to test permissions

- User with `manage_product_delivery_rules` can run test tool
- User without capability cannot access page or POST actions

## How to confirm product pages/cart/checkout unchanged

- No new frontend assets or templates
- No WooCommerce product edit UI changes
- Browse shop/product/cart/checkout — no delivery selector or rule-driven behavior

## Known limitations

- Resolver does not pick a single “winning” availability — returns at most one rule **per** fulfilment availability
- Category order in hierarchy is by term ID, not menu order
- List/diagnostics still capped at 500 rules
- Test result stored in transient may not survive object serialization edge cases on all hosts (re-run test if result missing)
- Runtime cart/product consumption deferred to a later phase

## Recommended next phase

**Phase 2C3 (or equivalent):** Product delivery selector / product-page admin or storefront wiring that **consumes** `ProductDeliveryRuleResolver` for a single resolved availability context — still without full cart/checkout until subsequent phases.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Test tool visible on Product Rules list page
- [ ] Resolution test for product, variation, and category targets
- [ ] Hierarchy and chosen rules display correctly
- [ ] Invalid target shows error notice + preserved inputs
- [ ] No configuration data changed by test runs
- [ ] Configuration Health shows new product-rule diagnostics when applicable
- [ ] Storefront/cart/checkout unchanged
