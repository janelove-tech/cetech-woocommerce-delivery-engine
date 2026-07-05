# Phase 2B3 — Suppliers and Origins

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `1` (unchanged)  
**Sources:** `docs/PHASE-2B2-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2B3 delivers **admin-only CRUD** for private operational suppliers and origins.

| Area | Files / behaviour |
|------|-------------------|
| Validation | `SupplierValidator`, `OriginValidator` |
| Admin page | `SuppliersOriginsPage` (combined suppliers + origins) |
| Repositories | Real `save()` for suppliers and origins |
| Menu | Suppliers & Origins submenu |
| System Status | Supplier and origin counts + zero-record warnings |
| Privacy | Repository docblocks, admin page notice, audit `internal_notes` redaction |

## What was intentionally not added

- Rate Cards CRUD
- Product rules, product selector, cart/checkout/shipping/order/shipment behaviour
- Storefront pickup selection or Store Pickup product behaviour
- Customer-facing output of supplier/origin data
- Demo/sample data auto-creation
- Frontend JS/CSS assets
- Schema version change or new migrations

## Admin page created

| Menu | Slug | Capability |
|------|------|------------|
| Suppliers & Origins | `cetech-delivery-engine-suppliers-origins` | `manage_private_sources` |

Single page with two list tables (Suppliers, Origins) and separate add/edit forms via `?entity=supplier|origin&action=add|edit&id=`.

## Capability used

| Capability | Used for |
|------------|----------|
| `manage_private_sources` | All supplier/origin CRUD, page access, POST actions |

## Supplier fields supported

### Actual schema (`delivery_engine_suppliers`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| code | `internal_code` | Required stable code |
| internal_name | `internal_name` | Required |
| contact_email | `contact_email` | Optional; validated |
| contact_phone | `contact_phone` | Optional |
| internal_notes | `internal_notes` | Private admin text |
| status | `status` | `RecordStatus` enum |

### Prompt fields not in schema (list shows —)

| Prompt field | Status |
|--------------|--------|
| supplier_type | Not in schema; omitted from forms |
| contact_name | Not in schema; omitted from forms |
| country_code | Not in schema; omitted from forms |

## Origin fields supported

### Actual schema (`delivery_engine_origins`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| code | `internal_code` | Required stable code |
| internal_name | `internal_name` | Required |
| supplier_id | `supplier_id` | **Required** (schema NOT NULL) |
| country_code | `country_code` | Optional 2-letter ISO |
| address_summary, region, city | `internal_address` | JSON `{summary, region, city}` |
| dispatch_lead_days_min | `dispatch_lead_days_min` | Optional non-negative int |
| dispatch_lead_days_max | `dispatch_lead_days_max` | Optional non-negative int |
| internal_notes | `internal_notes` | Private admin text |
| status | `status` | `RecordStatus` enum |

### Prompt fields not in schema

| Prompt field | Status |
|--------------|--------|
| origin_type | Not in schema; list shows — |
| dispatch_lead_time_* naming | Schema uses `dispatch_lead_days_min/max` |

## Validation rules

### Suppliers

- Code: required; `/^[a-z0-9_-]+$/`
- Internal name: required
- Contact email: validated with `is_email()` when supplied
- Status: `active`, `inactive`, or `archived`
- Duplicate codes rejected

### Origins

- Code: required stable code
- Internal name: required
- Supplier: required; must reference existing supplier
- Country code: optional 2-letter ISO when supplied
- Dispatch lead days: non-negative; min ≤ max when both set
- Status enum validated
- Duplicate codes rejected

## Supplier/origin privacy rules

- Repositories documented as **admin/infrastructure only** — no customer templates, REST/Store API, emails, or checkout reads.
- `SuppliersOriginsPage` is wp-admin only (`manage_private_sources`).
- No frontend hooks register supplier/origin repositories.
- `internal_notes` omitted from audit log payloads.
- Contact email/phone in audit are admin operational history only (wp-admin restricted).

## Audit logging behaviour

| Entity | Actions |
|--------|---------|
| `supplier` | created, updated, deactivated |
| `origin` | created, updated, deactivated |

Uses Patch 1 isolation: audit failure warns but does not roll back CRUD. `internal_notes` always redacted from payload.

## Persistence patterns (Phase 2B1 Patch 1)

- Insert/update failures return `0`; no false success
- No-change updates succeed when record exists
- Already-inactive deactivate is no-op success
- Validation failures stash form drafts

## How to test CRUD

1. `composer dump-autoload -o`
2. Log in as Administrator with WooCommerce active.
3. **Delivery Engine → Suppliers & Origins**
4. Create supplier → edit → deactivate.
5. Create origin linked to supplier → edit → deactivate.
6. Confirm rows in `delivery_engine_suppliers` and `delivery_engine_origins`.
7. Confirm audit entries (without `internal_notes` in payload).

## How to test permissions

- User without `manage_private_sources` cannot access page (`wp_die`).
- POST actions require valid nonce and capability.

## How to confirm storefront unchanged

- No cart, checkout, shipping, product, or order hooks added.
- No frontend assets.
- Browse product/cart/checkout as customer — no supplier/origin data visible.

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
| Menu shows 6 submenus | … + Suppliers & Origins |
| Create supplier | Success; audit without internal_notes |
| Create origin with supplier | Success; JSON in internal_address |
| System Status | Supplier/origin counts; warnings when zero |
| Storefront | No private data exposed |

## TODOs for Phase 2B4

- Rate Cards CRUD (still no live checkout pricing)
- Product Rules CRUD
- Schema extension migration if supplier_type, origin_type, or supplier country columns are needed
- Separate `view_private_origins` read-only admin access (optional)
- Parent menu capability edge case for custom roles (deferred)
