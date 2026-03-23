# Userland Code Instructions
--- 
description: This file describes the userland code style for the project.
applyTo: **/*.php
---

Classes in the `app` directory are the component of the application that end users will interact with directly. They should be under the `App` namespace.
- Must not use the `empty` function.
- Type safe conditions are preferred over `isset` or `!empty`. Avoid type juggling.
- Use strict comparisons (`===` and `!==`) instead of loose comparisons (`==` and `!=`).
- Use type declarations for function parameters and return types wherever possible.
- Follow PSR-12 coding standards.

