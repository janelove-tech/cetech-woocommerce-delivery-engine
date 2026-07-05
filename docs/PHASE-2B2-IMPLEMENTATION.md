# Phase 2B2 — Destination Zones, Destination Rules, Pickup Locations

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `1` (unchanged)  
**Sources:** `docs/PHASE-2B1-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2B2 delivers **admin-only CRUD** for destination zones (with attached rules) and pickup locations.

| Area | Files / behaviour |
|------|-------------------|
| Enums | `DestinationRuleType`, `DestinationRuleMatchMode` |
| Validation | `DestinationZoneValidator`, `DestinationRuleValidator`, `PickupLocationValidator` |
| Admin pages | `DestinationZonesPage`, `PickupLocationsPage` |
| Zone test tool | `DestinationZoneTestMatcher` (admin-only, read-only) |
| Repositories | Real `save()` for zones and pickup locations; `replaceForZone()` for rules |
| Menu | Destination Zones, Pickup Locations submenus |
| System Status | Zone, rule, and pickup counts + zero-record warnings |

## What was intentionally not added

- Suppliers, origins, rate cards CRUD
- Product rules, product selector, cart/checkout/shipping/order/shipment behaviour
- Live rate calculation or checkout zone enforcement
- Demo/sample data auto-creation
- Frontend JS/CSS assets
- Schema version change or new migrations

## Admin pages created

| Menu | Slug | Capability |
|------|------|------------|
| Destination Zones | `cetech-delivery-engine-destination-zones` | `manage_delivery_zones` |
| Pickup Locations | `cetech-delivery-engine-pickup-locations` | `manage_delivery_zones` |

Existing menus unchanged: System Status, Logistics Profiles, Delivery Offers.

## Capabilities used

| Capability | Used for |
|------------|----------|
| `manage_delivery_zones` | Destination Zones, Pickup Locations, zone test tool |
| `manage_delivery_settings` | System Status (unchanged) |
| `manage_logistics_profiles` | Logistics Profiles (unchanged) |
| `manage_delivery_offers` | Delivery Offers (unchanged) |

## Fields supported

### Destination Zones (`delivery_engine_destination_zones`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| code | `internal_code` | Required stable code |
| name | `internal_name` | Required |
| public_label | `public_label` | Optional |
| priority | `priority` | Integer, default 100 |
| is_fallback | `is_fallback` | Boolean 0/1 |
| is_remote_area | `remote_area_flag` | Boolean 0/1 |
| status | `status` | `RecordStatus` enum |

**Geographic matching** is not stored on the zone row. Country/region/city/postcode are configured via **destination rules** (see below).

### Destination Rules (`delivery_engine_destination_rules`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| rule_type | `rule_type` | `country`, `region`, `city`, `postcode` |
| rule_value | `rule_value` | Match value (country = 2-letter ISO) |
| match_mode | `match_mode` | `exact` or `prefix` (postcode only) |
| priority | `priority` | Rule ordering within zone |

Rules are edited on the zone add/edit form and persisted via `replaceForZone()` after the zone save succeeds.

### Pickup Locations (`delivery_engine_pickup_locations`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| code | `internal_code` | Required stable code |
| location_name | `location_name` | Required public name |
| address_line_1 … postcode | `public_address` | JSON-encoded structured address (see below) |
| public_opening_hours | `public_opening_hours` | Plain text |
| public_pickup_instructions | `public_pickup_instructions` | Plain text |
| contact_phone | `contact_phone` | Optional |
| contact_email | `contact_email` | Optional; validated if supplied |
| status | `status` | `RecordStatus` enum |

## Schema mappings / mismatches

| Prompt term | Actual schema | Phase 2B2 handling |
|-------------|---------------|-------------------|
| country_code, region, city on zone | Not on `destination_zones` table | Stored as destination rules |
| description (zones) | No `description` column | Use `public_label` for optional label |
| is_remote_area | `remote_area_flag` | Mapped on save |
| postcode_pattern on zone | Not on zone table | Use `postcode` rule_type with `prefix` match_mode |
| status on rules | No `status` column on rules | Omitted; rules exist while zone is active |
| address_line_1, city, etc. (pickup) | Single `public_address` longtext | JSON object in `public_address` |
| public_name | `location_name` | Direct mapping |
| operating_hours | `public_opening_hours` | Direct mapping |
| readiness_estimate | Column exists | Not exposed in 2B2 forms (reserved) |

## Validation rules

### Destination zones

- Code: required; `/^[a-z0-9_-]+$/`
- Name: required
- Status: `active`, `inactive`, or `archived`
- Priority: integer when supplied
- Duplicate codes rejected

### Destination rules

- Empty rows ignored
- Rule type and value required when row is non-empty
- Country values must be 2-letter ISO codes
- `prefix` match mode allowed only for postcodes
- Invalid rows block save with admin error; form draft preserved

### Pickup locations

- Code: required stable code
- Location name: required
- Country code: optional 2-letter ISO when supplied
- Email: validated with `is_email()` when supplied
- Duplicate codes rejected

## Destination rules behaviour

- Rules belong to a zone via `zone_id`.
- On zone save: zone persisted first, then `replaceForZone()` runs inside a DB transaction (delete zone rules → insert new rows).
- Transaction rolls back on insert failure; admin shows error; prior rules unchanged on rollback.
- Audit log action `replaced` for entity type `destination_rules` with zone ID as `entity_id`.
- All rules for a zone must match (AND) in the admin test matcher.

## Zone test tool status

**Implemented** on the Destination Zones list page.

- POST action with nonce (`manage_delivery_zones`)
- Inputs: country code, region, city, postcode
- Output: best matching active zone by priority, or fallback zone, or “No matching zone”
- Read-only; no price calculation; no data changes

## Audit logging behaviour

| Entity | Actions |
|--------|---------|
| `destination_zone` | `created`, `updated`, `deactivated` |
| `destination_rules` | `replaced` (previous/new rule arrays) |
| `pickup_location` | `created`, `updated`, `deactivated` |

Uses `ConfigurationAuditLogger` with Patch 1 isolation (CRUD success not rolled back on audit failure).

## Persistence patterns (from Phase 2B1 Patch 1)

- Insert/update failures return `0` / `false`; admin does not show false success
- No-change updates succeed when record exists
- Already-inactive deactivate is a no-op success
- Validation failures stash form drafts via transients

## How to test CRUD

1. `composer dump-autoload -o`
2. Log in as Administrator with WooCommerce active.
3. **Delivery Engine → Destination Zones** — create zone with country/region rules; edit; deactivate.
4. Confirm rows in `delivery_engine_destination_zones` and `delivery_engine_destination_rules`.
5. Run **Test destination match** with an address that should match.
6. **Delivery Engine → Pickup Locations** — create, edit, deactivate; confirm DB rows and audit entries.

## How to test permissions

- User without `manage_delivery_zones` cannot access Destination Zones or Pickup Locations (`wp_die`).
- POST actions require valid nonce and capability.

## How to confirm storefront unchanged

- No cart, checkout, shipping, product, or order hooks added.
- No frontend assets.
- Feature flags unchanged.

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
| Menu shows 5 submenus | System Status, Logistics Profiles, Delivery Offers, Destination Zones, Pickup Locations |
| Create destination zone + rules | Success; rows in both tables; audit entries |
| Zone test tool | Returns matched zone or fallback / no match |
| Create pickup location | Success; JSON address in `public_address` |
| System Status | Shows zone/rule/pickup counts; warnings when zero |
| Storefront | No delivery UI changes |

## TODOs for Phase 2B3

- Suppliers and origins CRUD
- Rate Cards CRUD (still no live checkout pricing)
- Shared `DestinationZoneMatcher` service for runtime (checkout/rate cards)
- Parent menu capability edge case for custom roles (deferred from 2B1)
- Dynamic add/remove rule rows without page reload (optional UX)
- Expose `readiness_estimate` on pickup locations if needed
