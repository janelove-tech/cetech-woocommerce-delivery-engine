# Phase 2C3 — Product Rule Resolver Hardening + Admin Runtime Readiness

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2C2-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2C3 hardens the admin-only product rule resolver foundation for safe transient storage, a stable result contract, clearer diagnostics, and improved admin test UI. No storefront, cart, checkout, or shipping behaviour was added.

| Area | Changes |
|------|---------|
| Serialization | `ProductRuleResolutionResult::toArray()` / `fromArray()`, `ResolvedProductDeliveryRule::toArray()` / `fromArray()` |
| Result contract | `CONTRACT_VERSION`, `hierarchy_explanation`, `chosen_explanations`, skip `code` values |
| Resolver explanations | Tie-break reasons (specificity, priority, rule ID) in chosen/skipped messages |
| Admin test tool | Stores plain arrays in transients; renders contract version, hierarchy policy, explanations |
| Diagnostics | Valid JSON `[]` → delivery-without-offers; malformed JSON only → invalid JSON; reduced duplicate/competing overlap |
| System Status | Read-only “Runtime readiness” table for resolver presence and contract version |

## What was intentionally not added

- Product delivery selector or product edit panel/metabox
- Product metadata writes or frontend display
- Cart-line delivery selections, fingerprinting, shipping packages
- Custom shipping methods, checkout validation, order snapshots
- Shipment creation/management, tracking UI, customer timeline
- Public REST/Store API, WPML/WCML/WoodMart/WCFM/VitePOS real adapters
- WooCommerce Blocks support, frontend JS/CSS
- Schema version change, migrations, demo data
- Automatic resolver runs against product catalog in System Status

## Serialization hardening

**Problem (Phase 2C2):** Admin resolution test stored `ProductRuleResolutionResult` PHP objects in form-draft transients.

**Fix:**

1. `handle_resolution_test()` stores `$result->toArray()` only.
2. Rendering uses `ProductRuleResolutionResult::fromArray()` to rehydrate for display.
3. Arrays contain no `internal_notes`, supplier/origin private notes, or customer/order data.
4. Existing one-minute, per-user, admin-only transient behaviour preserved via `AdminNoticeService::stash_form_draft()`.

## Resolver result contract

`ProductRuleResolutionResult::CONTRACT_VERSION = '1'`

| Field | Purpose |
|-------|---------|
| `contract_version` | Stable contract identifier for future runtime phases |
| `input_target_type` / `input_target_id` / `input_target_label` | Resolved input |
| `candidate_hierarchy` | Ordered search targets (type, id, label, order) |
| `hierarchy_explanation` | Admin-safe policy text for hierarchy |
| `matched_rules` | All eligible active rules (safe fields only) |
| `chosen_rules` | One winner per `fulfilment_availability` |
| `chosen_explanations` | Why each winner was selected |
| `skipped_rules` | Rule ID, reason, optional `code` (`inactive`, `missing_target`, `superseded`) |
| `warnings` | Non-fatal resolver warnings |
| `no_match_message` | Clear message when no rules apply |

Selection rules unchanged: specificity (variation > product > category) → priority ASC → rule ID ASC.

## Admin test tool hardening

**Location:** Delivery Engine → Product Rules → **Test product rule resolution**

Improvements:

- Contract version displayed
- Hierarchy policy + ordered candidate list
- “No match” in info notice styling
- Warnings in warning notice styling
- Matched rule count
- Chosen rules table includes “Why chosen” column
- Skipped rules show optional skip codes
- All output escaped; no frontend assets enqueued
- Nonce + `manage_product_delivery_rules` unchanged

## Diagnostics fixes

| Issue | Fix |
|-------|-----|
| Valid JSON `[]` reported as invalid JSON | `is_offer_ids_json_invalid()` uses `json_last_error()` only |
| Empty/null offer IDs | Still reports `product_rule_delivery_without_offers` |
| Duplicate + competing overlap | Skip `product_rule_competing_same_priority` when competing group equals full duplicate group for same target + availability |

Existing duplicate active rule checks remain intact. No schema constraints added.

## Privacy / customer boundary rules

- Resolver and test tool remain wp-admin only
- Result arrays exclude `internal_notes` and private supplier/origin notes
- No customer, order, payment, or session data in transients or output
- No product metadata writes
- No cart/checkout/shipping hooks
- System Status resolver entry is read-only (class existence + contract version only)

## How to test product, variation, and category resolution

1. Create active rules at variation, product, and category levels with different priorities.
2. Go to **Delivery Engine → Product Rules**.
3. **Product:** enter product ID — product rule wins over category for same availability.
4. **Variation:** enter variation ID — variation > parent product > categories (term ID order).
5. **Category:** enter category term ID — category rules only.
6. Confirm chosen rules grouped by `international_fulfilment`, `in_store`, `in_warehouse`.
7. Confirm “Why chosen” explains specificity/priority/ID tie-break.
8. Confirm superseded rules appear under skipped with `[superseded]` code.

## How to test transient / result rendering

1. Run resolution test; confirm result renders after redirect.
2. Refresh page — result should disappear (transient consumed).
3. Confirm no PHP object serialization warnings in debug log.
4. Test invalid target — error notice + preserved inputs, no result block.

## How to confirm product pages / cart / checkout unchanged

- No new frontend JS/CSS or templates
- No WooCommerce product edit UI
- Browse shop, product, cart, checkout — no rule-driven delivery behaviour
- System Status shows resolver as “admin test tool only”

## System Status smoke checklist

**Delivery Engine → System Status → Runtime readiness (admin/test only):**

- Product rule resolver registered: Yes
- Resolver contract version: 1
- Resolver storefront usage: None (admin test tool only)
- Resolver test location: Product Rules page

No automatic product catalog resolution runs.

## Known limitations

- Resolver still returns at most one rule **per** fulfilment availability (not a single global winner)
- Category hierarchy order is by term ID, not menu order
- List/diagnostics capped at 500 rules
- Competing-same-priority diagnostic still shown when duplicate set is larger than same-priority subset
- Runtime cart/product consumption deferred to a later phase

## Recommended next phase

**Phase 2D (or equivalent):** Product delivery selector / product-page wiring that consumes hardened resolver output for a single availability context — still without full cart/checkout until subsequent phases.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Test tool stores and renders array results (not PHP objects)
- [ ] Contract version, hierarchy policy, and explanations display
- [ ] No-match and warnings render clearly
- [ ] Skipped rules show reasons and codes
- [ ] Valid JSON `[]` on delivery rule → “delivery without offers” (not invalid JSON)
- [ ] Malformed JSON → “invalid offer IDs JSON”
- [ ] Duplicate-only rules do not also show redundant competing diagnostic
- [ ] System Status shows runtime readiness table
- [ ] Storefront/cart/checkout unchanged
- [ ] No configuration records modified by test runs
