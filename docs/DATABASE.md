# Database Schema

All custom tables use `$wpdb->prefix . 'syvo_'`.

### `locations`

Stores the imported source-backed province/county/city tree, normalized names, stable slugs, source codes and optional coordinates.

### `directory_pages`

Stores deterministic page fingerprints, location type, category term, URL, SEO metadata, cached AI content, dirty state and business count. Fingerprint is `sha256(category_id|location_type|location_id)` and is unique.

### `jobs`

Stores job type, unique hash, entity, JSON payload, status, retry count, lock time and safe failure message.

### `business_index`

Query-oriented denormalized index for location filtering, search text, completeness and coordinates. WordPress CPT remains the source of truth.

### `ai_logs`

Operational AI log only. No API key, password, national ID or authorization token is written.

### `rate_limits`

Short-lived hashed buckets for registration and reverse-geocoding abuse controls.
