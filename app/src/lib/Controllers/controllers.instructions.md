# Controllers Instructions
---
description: This file describes the coding of web controllers.
applyTo: **/src/lib/Controller/*.php
---
Controllers handle HTTP requests and responses. Follow these guidelines:
- Must be within namespace `App\Controllers`.
- Routes should be defined in the `routePrefix` property. Routes should automatically be registered based on this property.
- Do not use decorators or annotations for route definitions.
- Controllers have been arranged into three sub-sections:`Api`, `App`, and `Auth`
    - `Api` sub-section contains controllers for RESTful API endpoints.
    - `App` sub-section contains controllers for web application routes.
    - `Auth` sub-section contains controllers for authentication and authorization.
    - `IndexController` is the default controller for a given directory.
- Use strict types.
- Controllers should be organized into classes by data entity. Each class should handle all HTTP methods for that entity.
- The application should detect new controllers without having to manually register new routes.
- Use dependency injection for services.
- Follow RESTful principles for API design. 'GET', 'POST', 'PUT', 'DELETE' methods should be handled by `get()`, `post()`, `put()`, `delete()` controller methods respectively.
- Implement CRUD operations with proper authorization checks.
- Use proper HTTP status codes in responses.
- Must not place business logic in controllers. Use services instead.
- Must use gateways to access data sources. Do not write SQL queries or network API calls inside controllers.
- All tables in `000001_create_users_table.php` must have RESTful endpoints.
- Follow PSR-12 coding standards.