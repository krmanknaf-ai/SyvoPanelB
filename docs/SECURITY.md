# Security Notes

Implemented controls include:

- Nonce checks on authenticated writes.
- Object-level authorization for business edits/deletes.
- Dedicated owner role with narrow custom capabilities.
- Owner-only frontend workflow with wp-admin guard.
- National ID validation, encrypted storage and HMAC fingerprinting.
- National ID never returned from public REST/profile endpoints.
- Strict image MIME and size checks; SVG and executable content are not accepted.
- `wp_handle_upload()` and WordPress image metadata generation.
- Scoped frontend CSS and namespaced JavaScript.
- SQL queries use `$wpdb->prepare()` where inputs are interpolated.
- No `eval`, shell execution, deserialization or browser-session scraping.
- No consumer ChatGPT/Claude login automation.
- AI keys are not hardcoded or logged.
- Reverse-geocoding rate limiting and response caching.
- Registration rate limiting, honeypot and validation.
- Privacy exporter/eraser integration.
- Uninstall retains real data unless explicit cleanup is enabled.
