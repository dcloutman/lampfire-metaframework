# Lampfire Secure Skeleton
This repository contains a project skeleton for PHP applications that provides authentication and authorization platform. It can either be extended into a business application or converted into a stand-alone authorization and authentication service. 

## Technology

- **PHP 8.4** with strict types and PSR-12 formatting.
- **Slim 4** for routing and HTTP handling.
- **Twig 3** for server-side templates.
- **MariaDB** accessed through PDO.
- **PHP-DI** for dependency injection with automatic constructor resolution.
- **Paseto v4** for authentication tokens. JWTs are not used.
- **Argon2id** for password hashing.
- **PHPUnit** for unit testing.
- **Monolog** for application logging.

## Project Structure

```
code/                   Application source code.
  src/
    lib/
      Controllers/      Slim route controllers organised by concern.
        Api/            RESTful API controllers.
        App/            Web application controllers.
          Admin/        Administrative panel controllers.
        Auth/           Authentication controllers.
      Gateways/         Data access layer (one class per database table).
      Services/         Business logic layer.
    public/
      index.php         Application entry point.
    templates/          Twig templates.
  migrations/           SQL migration files.
  composer.json
framework/              Lampfire framework (shared across projects).
  Config/               Configuration classes that read from the environment.
  Controllers/          Abstract base controllers.
  Database/             Database connection management.
  Gateways/             Abstract base gateways.
  Routing/              Convention-based route discovery.
  Services/             Abstract base services.
  Utilities/            Shared utilities such as UUID validation.
docker/                 Docker configuration files.
  php/                  PHP container Dockerfile and Apache virtual host.
  mariadb/init/         SQL scripts executed on first database container start.
cli/                    Command-line tool source and templates.
cli.php                 CLI entry point.
```

## Configurations

The `.env` file lives in the `.config/` directory at the project root. Copy `env_example` to `.config/.env` and fill in your values.

```bash
cp env_example .config/.env
```

The `.env` file is read by both Docker Compose and the PHP application. All environment variables are accessed through typed configuration classes in the framework. Application code should never call `getenv()` or read `$_ENV` directly.

### Required Variables

| Variable | Description |
|---|---|
| `DATABASE_NAME` | Database name. Default: `lampfire_dev`. |
| `DATABASE_HOST` | Database hostname. Use `db` when running in Docker. |
| `DATABASE_PORT` | Database port. Default: `3306`. |
| `DATABASE_APPLICATION_USER` | Database user for the application. |
| `DATABASE_APPLICATION_USER_PASSWORD` | Password for the database user. |
| `MARIADB_ROOT_PASSWORD` | Root password for the MariaDB container. |
| `APPLICATION_URL` | Public URL of the application. |
| `APP_DEBUG` | Set to `true` to display detailed error messages. Default: `false`. |
| `PASETO_KEY` | A 64-character hex string (256 bits of entropy). |

Generate a Paseto key:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Docker Development Environment

The project ships with a Docker Compose setup that provides two containers:

- **lampfire-web** — PHP 8.4 with Apache, mod_rewrite, Composer, and the PDO MySQL extension.
- **lampfire-db** — MariaDB 11 with the full application schema loaded on first start.

### Starting the Environment

```bash
cp env_example .config/.env
# Edit .config/.env and fill in passwords and PASETO_KEY.

docker compose up -d
```

The application is available at `http://localhost:8080`. MariaDB is exposed on `localhost:3306`.

### Stopping the Environment

```bash
docker compose down
```

### Rebuilding After Changes

```bash
docker compose up -d --build
```

### Viewing Logs

```bash
docker compose logs -f
```

### Resetting the Database

The database schema is loaded from `docker/mariadb/init/001-schema.sql` only when the volume is first created. To reset the database, remove the volume and restart:

```bash
docker compose down -v
docker compose up -d
```

## Routing

Routes are registered automatically through convention-based discovery. There is no manual route registration file. The framework recursively scans `code/src/lib/Controllers/` and its subdirectories, reading class properties to determine route prefixes, identifier patterns, and middleware.

### Class Properties

Each controller declares its routing configuration as protected properties inherited from `AbstractController`. The `$routePrefix` property is the single source of truth for the controller's URI.

```php
class UserController extends AbstractRestController
{
    protected string $routePrefix = '/api/users';
    protected array $routeMiddleware = [AuthMiddleware::class];
}
```

For join-table controllers with composite keys, override `$idPattern`:

```php
class PermissionSetPermissionController extends AbstractRestController
{
    protected string $routePrefix = '/api/permission-set-permissions';
    protected string $idPattern = '/{setId}/{permissionId}';
    protected array $routeMiddleware = [AuthMiddleware::class];
}
```

Admin controllers use the same pattern. The `$routePrefix` always contains the full URI path:

```php
class UserAdminController extends AbstractAdminController
{
    protected string $routePrefix = '/admin/users';
    protected array $routeMiddleware = [AuthMiddleware::class];
}
```

### Convention-Based Method Mapping

Methods with well-known names are mapped to routes automatically. No per-method decorators or attributes are required.

| Method | HTTP | Path |
|---|---|---|
| `get` | GET | `{routePrefix}` |
| `getById` | GET | `{routePrefix}{idPattern}` |
| `post` | POST | `{routePrefix}` |
| `put` | PUT | `{routePrefix}{idPattern}` |
| `patch` | PATCH | `{routePrefix}{idPattern}` |
| `delete` | DELETE | `{routePrefix}{idPattern}` |

This convention applies equally to API controllers that return JSON and admin controllers that render Twig templates. Both are RESTful; the only difference is the response format.

### Method Override for HTML Forms

HTML forms can only submit GET and POST requests natively. The application registers Slim's `MethodOverrideMiddleware` so that forms can include a hidden `_METHOD` field to submit PUT or DELETE requests:

```html
<form method="post" action="/admin/users/{{ user.user_id }}">
    <input type="hidden" name="_METHOD" value="PUT" />
    <!-- form fields -->
</form>
```

### Controller Directory Layout

Controllers are organized into subdirectories by concern. Namespaces follow PSR-4 conventions derived from the directory structure:

| Directory | Namespace | Purpose |
|---|---|---|
| `Controllers/` | `App\Controllers` | Public-facing routes. |
| `Controllers/Api/` | `App\Controllers\Api` | RESTful API endpoints. |
| `Controllers/App/` | `App\Controllers\App` | Authenticated web pages. |
| `Controllers/App/Admin/` | `App\Controllers\App\Admin` | Administrative panel. |
| `Controllers/Auth/` | `App\Controllers\Auth` | Authentication flows. |

## Migrations

Migrations are plain SQL files in `code/migrations/`. The initial schema is generated from the Twig template in `cli/templates/00001-initial-schema.sql.twig`.

## Superusers

Initially, the application needs at least one superuser. Only other superusers can add and remove superuser status from a user. The primary special ability of superusers is to add and remove application admins and to manage the privilege sets of application admins. There should be a CLI command to add and remove superuser status from a user.

## Application Admins

Application admins are less powerful than superusers, but they can assign any user to a user group or grant them privileges from a privilege set. They can also create, update, and delete permissions, permission sets, or user groups. Admins can also disable a user. Admins should not be able to modify application admin-specific privileges.

## Logging

Any changes to the records in the tables defined by the initial schema should be logged for auditing purposes.

## LLM Instructions

[AGENTS.md](AGENTS.md)
