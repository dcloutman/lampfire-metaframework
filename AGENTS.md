# LLM Guidelines
Lampfire is a forkable PHP application starter with a REST API and an administrative panel for authentication and authorization management.

## Project Structure
- `framework/` contains core framework code.
- `app/` contains userland application code.
- `cli.php` and `cli/` contain CLI tooling. Both are part of the framework, but have different autoloading needs that code in `framework`.

## Functional Requirements
- Use PHP 8.4+, Slim controllers, Twig templates, PDO for MySQL, and PHPUnit.
- Use Composer autoloading with `Lampfire\` for framework code and `App\` for userland code.
- Use `.env` files for configuration.
- Use Twig in CLI templates to generate app files, including initial `.env` and `.sql` files.
- Support admin CRUD users, password resets, permissions, permission sets, user groups, and user-group to permission-set mapping.
- Keep REST endpoints entity-oriented with `get`, `getById`, `post`, `put`, `delete`, and `search` with optional GraphQL support.
- Use Twig for web views and templating. Do not generate HTML in PHP.
- Provide Docker development with PHP and MariaDB.
- Provide CLI support for setup and core administrative tasks.

## Non-Functional Requirements
- Keep code readable, strongly typed, PSR-12 compliant, and indented with 4 spaces.
- Use strict types in PHP files: `declare(strict_types=1);`.
- Do not use `empty()`, ever. Use explicit, type-safe checks instead.
- Prefer object-oriented design, clear variable names, and small unit-testable methods.

## Security Requirements
- Security is the top priority.
- Use Argon2 for password hashing.
- Use non-sequential GUID user IDs.
- JWT is banned; use Paseto tokens only.
- Keep dependencies updated.

## UX / UI
- Keep UI neutral, minimal, and free of external UI dependencies unless explicitly requested.

## Documentation Style Guidelines
- Documentation and comments must be clear, complete English sentences.
- Every sentence must include a subject and a verb. Sentence fragments are unacceptable.
- Use correct capitalization and ending punctuation in all documentation and comments.
- Do not merge sentences with a semicolon. Avoid parenthetical statements.
- Avoid emojis, slang, and informal language.

## Additional Instructions
- Gateway layer: [See gateway.instructions.md](./gateway.instructions.md)
- Services layer: [See services.instructions.md](./services.instructions.md)
- Controller layer: [See controller.instructions.md](./controller.instructions.md)
