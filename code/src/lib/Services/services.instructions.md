# Services Instructions
---
description: This file describes the coding of services.
applyTo: **/src/lib/Services/*.php
---
Services contain business logic. Follow these guidelines:
- Must be in namespace `App\Services`.
- Use strict types.
- Use dependency injection for gateways and other services.
- Must contain business logic. Do not place business logic in controllers or gateways.
- Follow PSR-12 coding standards.
