# Gateways Instructions
  ---
description: This file describes the coding of data gateways.
applyTo: **/src/lib/Gateways/*.php
---
Gateways handle data access. Follow these guidelines:
- Must be in namespace `App\Gateways`.
- Use strict types.
- Use dependency injection for services.
- Must not contain business logic. Use services instead.
- Must use proper error handling for data access.
- Follow PSR-12 coding standards.
- All tables in `000001_create_users_table.php` must have corresponding data gateways to handle CRUD and query operations.
