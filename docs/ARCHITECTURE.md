# Syvo Business Directory — Architecture

## Runtime

The plugin is a standalone WordPress plugin under `Syvo\\BusinessDirectory` and never modifies the active theme, `functions.php`, Code Snippets, Elementor, Rank Math or WooCommerce.

Core entities:

- `syvo_business` CPT for businesses and WordPress ownership.
- `syvo_bd_category` hierarchical taxonomy for categories/subcategories.
- `syvo_bd_service` taxonomy for services.
- `wp_syvo_locations` for province/county/city hierarchy and coordinates.
- `wp_syvo_directory_pages` for deterministic Category + Location virtual pages.
- `wp_syvo_jobs` for idempotent asynchronous work.
- `wp_syvo_business_index` for query-oriented search fields.
- `wp_syvo_ai_logs` for safe AI operational logging.
- `wp_syvo_rate_limits` for abuse control.

## URL model

City pages use exactly:

`/hiper/{category-slug}/{city-slug}/`

Subcategory + city:

`/hiper/{parent-category}/{subcategory}/{city}/`

County and province routes use explicit namespaces to avoid city collisions.

The directory pages are virtual routes backed by `wp_syvo_directory_pages`, so the site does not create one WordPress Page post for every Category + Location combination.

## Queue

Jobs are idempotent via `(job_type, unique_hash)` and are processed with a short lock/claim step. Failed jobs retry with backoff up to their configured attempt limit. WordPress AI calls never run in page render.

## AI

The plugin uses the WordPress 7.x AI Client (`wp_ai_client_prompt()`) when available. Provider credentials are managed by WordPress' AI/Connector system, not duplicated by this plugin. AI is used only for classification, page SEO copy, and image ALT assistance. Generated output is constrained to supplied facts.

## Maps

The map layer is provider-independent. The browser renders local OSM tiles without a CDN JavaScript dependency. Reverse geocoding is server-side and cached. The default provider is Nominatim and is configurable. For production-scale traffic, a dedicated commercial or self-hosted provider should be configured.

## Compatibility

Elementor and Hello Elementor remain untouched. The plugin renders inside the normal WordPress header/footer lifecycle and works as shortcodes or virtual routes. Rank Math titles, descriptions, canonical URLs and structured data are added through compatibility hooks when Rank Math is present.
