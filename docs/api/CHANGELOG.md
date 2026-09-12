# SIGNOVA API Changelog

## Unreleased - API v1

### Added
- Draft quotation revision API with immutable version history, backend-owned pricing, and state guard.
- Draft quotation header update API with DRAFT-state guard and tenant-scoped validation.
- Tenant-scoped Quotation draft API with backend-owned pricing.
- Tenant-scoped Customer API.
- Tenant-scoped Catalog Barang & Jasa API.
- Catalog category API.
- Tenant unit read API.
- Canonical catalog codes with Bahasa Indonesia display labels.
- API request correlation ID.
- Standard API error envelope.
- Canonical error registry.
- Capability authorization baseline.
- Active tenant context resolution.
- Explicit multi-tenant selection requirement.
- Standard OpenAPI contract foundation.

### Security
- Tenant ambiguity is rejected.
- Capability authorization is fail-closed.
- Internal exception messages are not exposed.
