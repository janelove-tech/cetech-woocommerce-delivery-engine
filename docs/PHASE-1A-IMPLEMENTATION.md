# Phase 1A — Core Foundation Skeleton

**Status:** Complete  
**Plugin version:** 0.1.0  
**Sources:** `docs/AI-HANDOFF.md`, `docs/PROJECT-RULES.md`, `docs/ARCHITECTURE-PLAN.md`

## What was created

Phase 1A delivers a **minimal, installable WordPress/WooCommerce plugin skeleton** with no customer-facing delivery behaviour.

| Area | Files / behaviour |
|------|-------------------|
| Bootstrap | `cetech-woocommerce-delivery-engine.php`, `Plugin`, `Activator`, `Deactivator`, `ServiceContainer`, `FeatureFlags` |
| Core | `Requirements`, `FeaturesCompatibility`, `Capabilities`, `HealthCheckRegistry`, `AdminNoticeManager` |
| Integrations | `IntegrationInterface`, `IntegrationRegistry`, `NullIntegration` (detection only) |
| Support | `Logger`, `AdminNotice` |
| Packaging | `composer.json`, `readme.txt` |

### Runtime behaviour

- Defines plugin constants (`CETECH_DE_*`)
- Loads Composer PSR-4 autoload from `vendor/autoload.php`
- Checks PHP >= 8.1
- Checks WooCommerce is active before loading delivery features
- Registers HPOS compatibility via WooCommerce `FeaturesUtil` when available
- Registers granular capabilities on activation (administrator + shop_manager)
- Persists default feature flags in `wp_options` with `cetech_de_` prefix
- Sets placeholder schema option `cetech_de_db_version` = `0`
- Runs health checks in memory (no admin diagnostics UI yet)
- Shows safe admin notices only (missing autoload, PHP, WooCommerce, activation success)

## What was intentionally not created

Per Phase 1A scope, the following are **excluded**:

- Database domain tables and migrations (beyond `cetech_de_db_version` placeholder)
- Product delivery selector, offers, zones, rate cards, logistics profiles, suppliers/origins
- Product rules, cart-line selection, shipping packages, custom shipping method
- Checkout validation, order snapshots, shipment records, tracking UI, customer timeline
- Admin CRUD screens and Delivery Engine menu
- Real WPML, WCML, WoodMart, WCFM, VitePOS, or Blocks adapters
- Frontend JavaScript/CSS assets (except minimal inline admin dismiss handler for notices)
- Proof of delivery, OTP, QR, GPS, drivers, live quotes, carrier APIs, automatic completion

## How to activate and test the skeleton

### 1. Install Composer autoload

From the plugin directory:

```bash
composer install
composer dump-autoload -o
```

### 2. Verify PHP syntax

```bash
find src -name "*.php" -print0 | xargs -0 -n1 php -l
php -l cetech-woocommerce-delivery-engine.php
```

On Windows PowerShell:

```powershell
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php -l cetech-woocommerce-delivery-engine.php
```

### 3. WordPress activation checklist

| Step | Expected result |
|------|-----------------|
| Copy plugin to `wp-content/plugins/cetech-woocommerce-delivery-engine/` | Folder present |
| Run `composer install` | `vendor/autoload.php` exists |
| Activate without WooCommerce | Admin error notice; no fatal error |
| Activate with WooCommerce | Success notice; plugin active |
| Check WooCommerce > Status > Features (HPOS) | Plugin lists HPOS compatible when WC supports FeaturesUtil |
| Deactivate plugin | No data deleted; rewrite rules flushed |
| Reactivate | Capabilities remain; flags not reset |

### 4. Verify options and capabilities

After activation:

- `cetech_de_db_version` = `0`
- `cetech_de_enable_product_delivery_selector` = `0` (false)
- `cetech_de_enable_classic_checkout_adapter` = `1` (true)
- Administrator and shop_manager roles include `manage_delivery_settings`, `manage_shipments`, etc.

### 5. Confirm no storefront impact

- Product pages unchanged
- Cart/checkout shipping unchanged
- No new shipping methods registered
- No custom database tables created

## Rollback / deactivation behaviour

- **Deactivation:** flushes rewrite rules; does **not** delete options, capabilities, or data
- **Rollback:** deactivate plugin via Plugins screen; optionally remove plugin folder
- **Data:** feature flags and `cetech_de_db_version` remain in `wp_options` until uninstall handler is added in a later phase

## Next phase (1B) preview

Phase 1B is expected to add:

- Migration runner shell and schema version bump workflow
- `uninstall.php` policy stub (delete data off by default)
- Optional admin “System Status” read-only page using `HealthCheckRegistry`
- Logger wiring to WooCommerce logs viewer path documentation

Do not begin domain tables or delivery business logic until Phase 2 per `ARCHITECTURE-PLAN.md`.
