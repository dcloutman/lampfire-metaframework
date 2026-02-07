# Controllers Instructions
---
description: This file describes the coding of web controllers.
applyTo: **/src/lib/Controller/*.php
---
Controllers handle HTTP requests and responses. Follow these guidelines:
- Must be in namespace `App\Controllers`.
- Use strict types.
- Extend `ApiController` for API endpoints.
- Use dependency injection for services.
- Follow RESTful principles for API design. 'GET', 'POST', 'PUT', 'DELETE' methods should be handled by `get()`, `post()`, `put()`, `delete()` controller methods respectively.
- Implement CRUD operations with proper authorization checks.
- Use proper HTTP status codes in responses.
- Must not place business logic in controllers. Use services instead.
- Must use gateways to access data sources. Do not write SQL queries or network API calls inside controllers.
- All tables in `000001_create_users_table.php` must have RESTful endpoints.
- Follow PSR-12 coding standards.