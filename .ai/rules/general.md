---
paths:
  - '**/*'
---

# General

## Enforce Discogs API usage constraints
Follow `docs/decisions/0001-discogs-api-usage.md` for every Discogs integration and UI change. The MVP is private, single-user, and non-commercial; suppress API content at six hours, purge removed Restricted Data only after a successful full reconciliation, render required attribution/source links, and do not proceed with multi-user or commercial use without written Discogs permission and a superseding decision.
