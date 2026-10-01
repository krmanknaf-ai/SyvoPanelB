# REST API — `syvo-bd/v1`

Public reads:

- `GET /locations/provinces`
- `GET /locations/counties?province_id=...`
- `GET /locations/cities?county_id=...`
- `GET /categories`
- `GET /reverse-geocode?lat=...&lng=...`
- `GET /search`
- `GET /businesses/{id}`

Authenticated routes:

- `POST /register`
- `GET /draft`
- `POST /draft`
- `GET /businesses/mine`
- `POST /businesses`
- `PUT|PATCH /businesses/{id}`
- `DELETE /businesses/{id}`
- `POST /upload`
- `GET /diagnostics` (administrator only)

Authenticated writes require the WordPress REST nonce. Object-level authorization is enforced against the business owner for every business write.
