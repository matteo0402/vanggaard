---
paths:
  - 'database/seeders/**'
---

# Seeders

## Provision the owner explicitly
Do not seed an authenticatable account with known credentials. The first private owner must be created interactively with `php artisan app:create-owner`; public registration remains disabled.
