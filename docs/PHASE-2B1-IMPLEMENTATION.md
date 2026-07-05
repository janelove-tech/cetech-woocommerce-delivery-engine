# Phase 2B1 — Admin CRUD Foundation + Logistics Profiles + Delivery Offers

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `1` (unchanged)  
**Sources:** `docs/PHASE-2A-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2B1 delivers **admin-only CRUD** for logistics profiles and delivery offers using the Phase 2A schema. No customer-facing behaviour was added.

| Area | Files / behaviour |
|------|-------------------|
| Admin foundation | `AdminActionHandler`, `AdminPageRenderer`, `AdminFormHelper`, `AdminNoticeService`, `ConfigurationAuditLogger` |
| Validation | `LogisticsProfileValidator`, `DeliveryOfferValidator` |
| Admin pages | `LogisticsProfilesPage`, `DeliveryOffersPage` |
| Menu | System Status, Logistics Profiles, Delivery Offers |
| Repositories | Real `save()` for logistics profiles and delivery offers only |
| System Status | Configuration record counts and zero-record warnings |
| Audit | Create/update/deactivate logging for both entity types |

## Admin pages created

| Menu | Slug | Capability |
|------|------|------------|
| System Status | `cetech-delivery-engine-system-status` | `manage_delivery_settings` |
| Logistics Profiles | `cetech-delivery-engine-logistics-profiles` | `manage_logistics_profiles` |
| Delivery Offers | `cetech-delivery-engine-delivery-offers` | `manage_delivery_offers` |

## Fields supported

### Logistics Profiles (`delivery_engine_logistics_profiles`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| code | `internal_code` | Lowercase stable code |
| name | `internal_name` | Required |
| description | `description` | Optional |
| parcel_size_class | `parcel_size_class` | Optional |
| handling_type | `handling_class` | Schema uses `handling_class` |
| route_eligibility | `route_eligibility` | JSON array of `DeliveryRoute` values |
| consolidation_policy | `consolidation_rule` | Schema uses `consolidation_rule`, max 64 chars |
| status | `status` | `RecordStatus` enum |

### Delivery Offers (`delivery_engine_delivery_offers`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| code | `internal_code` | Required stable code |
| public_label | `public_label` + `internal_name` | Both set from public label |
| description | `public_description` | Optional |
| route | `route` | `DeliveryRoute` enum |
| service_level | `service_level` | Optional text |
| carrier_visibility | `carrier_visibility` | `CarrierVisibility` enum |
| carrier_display_name | `carrier_name` | Required when visibility = `named` |
| processing_min/max days | `default_processing_min/max` | Optional non-negative ints |
| transit_min/max days | `default_transit_min/max` | Optional |
| final_mile_min/max days | `default_final_mile_min/max` | Optional |
| display_priority | `display_priority` | Default 100 |
| status | `status` | `RecordStatus` enum |

On insert, delivery offers also receive defaults: `tax_class=''`, `price_basis='manual'`, `duration_unit='business_days'`.

## Validation rules

- **Code:** required; `/^[a-z0-9_-]+$/`
- **Name / public label:** required
- **Status / route / carrier visibility:** must match backed enum values
- **Day ranges:** non-negative integers; min ≤ max when both provided
- **Carrier display name:** required when `carrier_visibility = named`
- **Consolidation policy:** max 64 characters
- **Duplicate codes:** rejected on create/update
- Invalid input shows admin error notice; no invalid DB write

## Audit logging behaviour

`ConfigurationAuditLogger` writes to `audit_log` on:

- `created`, `updated`, `deactivated` for `logistics_profile`
- `created`, `updated`, `deactivated` for `delivery_offer`

Payloads exclude supplier/origin/internal cost fields. Uses `AuditLogRepository::append()` validation.

## What was intentionally not added

- Destination zones, rules, suppliers, origins, pickup locations, rate cards CRUD
- Product rules, product selector, cart/checkout/shipping/order/shipment behaviour
- Rate cards, prices, shipping method registration
- Demo/sample data auto-creation
- Frontend JS/CSS assets (except native WP admin form confirm on deactivate)
- WPML/WCML/WoodMart/WCFM/VitePOS integrations

Other repositories still throw on `save()` until later phases.

## Schema mismatches / assumptions

| Prompt term | Actual schema column |
|-------------|---------------------|
| handling_type | `handling_class` |
| consolidation_policy | `consolidation_rule` |
| carrier_display_name | `carrier_name` |
| description (offers) | `public_description` |
| processing/transit/final mile days | `default_*_min/max` |
| name (offers) | `public_label` (+ `internal_name` mirrored) |

`dispatch_type` exists on logistics profiles but is not exposed in 2B1 forms (reserved for later).

## How to test CRUD

1. `composer dump-autoload -o`
2. Log in as Administrator with WooCommerce active.
3. **Delivery Engine → Logistics Profiles** — Add New, fill required fields, save.
4. Edit record, change status, save.
5. Deactivate record — status becomes `inactive`.
6. Repeat for **Delivery Offers** including named carrier visibility rule.
7. Confirm rows in database tables and entries in `delivery_engine_audit_log`.

## How to test permissions

- User without `manage_logistics_profiles` cannot access Logistics Profiles (`wp_die`).
- User without `manage_delivery_offers` cannot access Delivery Offers.
- POST actions require valid nonce and capability.

## How to test storefront unchanged

- Product pages, cart, checkout, shipping methods unchanged.
- No new frontend hooks or assets.
- `enable_product_delivery_selector` remains off by default.

## Commands

```bash
composer dump-autoload -o
```

```powershell
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php -l cetech-woocommerce-delivery-engine.php
```

## WordPress admin checklist

| Step | Expected |
|------|----------|
| Menu shows 3 submenus | System Status, Logistics Profiles, Delivery Offers |
| Create logistics profile | Success notice; row in DB; audit log entry |
| Create delivery offer | Success notice; no rate card created |
| System Status | Shows counts; warnings when zero |
| Storefront | No delivery UI changes |

## TODOs for Phase 2B2

- Destination Zones + destination rules CRUD (`DestinationRuleRepository::replaceForZone()`)
- Suppliers, origins, pickup locations CRUD
- Rate Cards CRUD (still no live pricing/checkout)
- Configuration health diagnostics beyond counts
- Parent menu capability: top-level menu uses `manage_delivery_settings`; custom roles with only `manage_logistics_profiles` or `manage_delivery_offers` may not see the parent menu (deferred)

## Phase 2B1 Patch 1

Hardening fixes from Phase 2B1 verification. Schema version unchanged; no new migrations.

### Update result verification

- `AbstractWpdbRepository::update_row()` returns `false` on `$wpdb->update()` failure, `true` when rows changed, and `true` on zero rows changed only when the target record still exists.
- `insert_row()` returns a positive insert ID only when insert succeeds and `$wpdb->insert_id` is valid; otherwise `0`.
- `WpdbLogisticsProfileRepository::save()` and `WpdbDeliveryOfferRepository::save()` return `0` when update or insert fails. Admin pages show an error notice and skip success messaging and audit logging.

### Audit isolation

- `ConfigurationAuditLogger::log()` returns `bool`, wraps `append()` in try/catch, and logs failures via `Logger` without private data or stack traces.
- Admin pages use `flash_warning()` for “saved/deactivated, but audit logging failed.” CRUD success is not rolled back when audit logging fails.

### Deactivate / already-inactive handling

- `mark_inactive()` returns `true` when the record exists and is already `inactive` (no-op).
- Returns `false` when the record does not exist or the status update fails.
- Admin shows “already inactive” on no-op deactivate; audit log is written only when status actually changes to inactive.

### Nonce / capability failure UX

- `AdminActionHandler::verify_post()` requires a redirect page slug. Capability or nonce failure flashes an error and redirects immediately; no write proceeds.

### Form value preservation

- **Implemented:** validation and duplicate-code failures stash submitted input in a per-user transient (`AdminNoticeService::stash_form_draft()` / `consume_form_draft()`). The add/edit form repopulates from the draft on the next GET.

### Parent menu capability note

- Not changed in this patch. Documented TODO above for Phase 2B2+ if custom capability-only roles need the parent menu visible.
