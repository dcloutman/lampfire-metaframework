# Lampfire Framework Gateways Instructions
  ---
description: This file describes the coding of data gateways.
applyTo: **/src/lib/Gateways/*.php
---
Gateways handle data access. Follow these guidelines:
- Must be in namespace `Lampfire\Gateways`.
- Use strict types.
- Use dependency injection for services.
- Must not contain business logic. Use services instead.
- Must use proper error handling for data access.
- Follow PSR-12 coding standards.
- All tables in `000001_create_users_table.php` must have corresponding data gateways to handle CRUD and query operations.
- Use prepared statements for database queries to prevent SQL injection.
- Include PHPDoc comments for all classes and methods to describe their purpose and parameters.
- Use meaningful class and method names that reflect their functionality. Method names should be verbs that indicate the action performed (e.g., `getUserById`, `createPermissionSet`).
- Ensure that the results from calls to PDO::prepare(), execute(), fetch(), and fetchAll() are correctly evaluated to detect and handle errors.
- Use transactions for operations that involve multiple related database changes to ensure data integrity.
