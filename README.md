# SIGNOVA Platform

Foundation repository for SIGNOVA Basic / Starter experience.

## Structure

- backend/   Laravel API
- frontend/  Next.js / PWA
- infra/     Nginx, systemd, deployment config
- docs/      Architecture, ADR, runbooks
- scripts/   Maintenance and operational scripts

## Architecture Principles

- API-first
- Mobile-first / PWA-first
- PostgreSQL as primary RDBMS
- Redis for cache/queue/lock
- Modular monolith
- Tenant-scoped data
- Capability-driven authorization
- Offline-first client
- Bahasa Indonesia frontend
