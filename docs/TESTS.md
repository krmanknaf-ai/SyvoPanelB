# Test Matrix

Automated/static checks:

- PHP syntax check on every PHP file.
- JavaScript syntax check with Node.
- Duplicate-class scan.
- Dangerous-function scan.
- Deterministic URL/fingerprint design review.

Live acceptance checks are performed after installation on Syvo and are reported in the final release notes.

Synthetic stress checks should be run in staging, not against production, for 100+ users/businesses and concurrent queue execution.
