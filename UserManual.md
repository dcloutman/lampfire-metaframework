# Lampfire User Manual

## Introduction
Lampfire is a forkable meta-framework that, from the beginning, provides a secure web interface for sign-in and user administration.
This manual provides instructions to four distinct classes of user

- Developers
- System Administators and Devops Engineers
- Application Administrators
- Regular Business Users

## Developer Documentation

<aside><blockquote>As a developer, I need to add product features to the system. I also want to know that the authorization and authentication is effective and well implemented so that I don't have to modify it, if possible.</blockquote></aside>

### Project Structure

```
app/                        Userland application code. Extend this after forking.
  src/
    lib/
      Controllers/
        Admin/              Administrative panel controllers.
        Api/                REST API controllers.
        Auth/               Authentication controllers.
      Gateways/             Data access layer (one class per database table).
      Services/             Business logic layer.
    public/
      index.php             Application entry point.
    templates/              Twig templates for web views.
  migrations/               SQL migration files, executed in alphabetical order.
  composer.json             Application dependencies and autoloading.
framework/                  Core Lampfire framework code. Do not modify.
  Config/                   Configuration classes that read the environment.
  Controllers/              Abstract base controllers.
  Database/                 PDO connection management.
  Gateways/                 Abstract base gateways for framework-owned entities.
  Routing/                  Convention-based route discovery.
  Services/                 Abstract base services.
  Utilities/                UUID validation, random string generation, etc.
cli/                        CLI command classes and Twig code-generation templates.
cli.php                     CLI entry point.
docker/                     Docker container configuration.
```

### Namespaces and Autoloading

| Namespace | Directory | Purpose |
|---|---|---|
| `App\` | `app/src/lib/` | Userland application code |
| `Lampfire\` | `framework/` | Framework code |
| `Cli\` | `cli/` | CLI command code |

Autoloading is configured in `app/composer.json`.

### Adding a REST API Controller

1. Create a class in `app/src/lib/Controllers/Api/` that extends `Lampfire\Controllers\AbstractRestController`.
2. Set `$routePrefix` to the URL prefix for the resource (for example, `/api/widgets`).
3. Set `$idPattern` if the default `/{id}` does not fit (for example, `/{widgetId}/{componentId}` for join-table controllers).
4. Populate `$routeMiddleware` with any middleware class names to apply to every route on this controller.
5. Implement any of the following conventionally named methods. `RouteDiscovery` maps each to an HTTP method automatically.

    | Method name | HTTP method | Path |
    |---|---|---|
    | `get` | GET | `{routePrefix}` |
    | `getById` | GET | `{routePrefix}{idPattern}` |
    | `post` | POST | `{routePrefix}` |
    | `put` | PUT | `{routePrefix}{idPattern}` |
    | `patch` | PATCH | `{routePrefix}{idPattern}` |
    | `delete` | DELETE | `{routePrefix}{idPattern}` |

6. Use `$this->prepareJsonResponse()` and `$this->prepareJsonErrorResponse()` to build responses.

### Adding a Gateway

Gateways live in `app/src/lib/Gateways/`. Each class handles data access for one database table and must use PDO prepared statements for all queries.

1. Extend `Lampfire\Gateways\AbstractDatabaseGateway`.
2. Inject a `PDO` instance through the constructor. PHP-DI resolves it automatically.
3. Name methods as verbs: `findById`, `findAll`, `create`, `update`, `delete`.

### Adding a Service

Services live in `app/src/lib/Services/`. They contain business logic and coordinate one or more gateways.

1. Extend `Lampfire\Services\AbstractService`.
2. Inject gateways through the constructor.

### Adding a Record

Records are typed wrappers around database rows. A record class defines the schema contract for one table, controls field visibility and mutability, and provides cursor-style result navigation through `next()`, `previous()`, `rewind()`, and `count()`.

Userland developers must not add or modify records in `framework/Records/`. If your fork introduces userland records, create them under `app/src/lib/Records/`.

#### Step 1: Create the record class

1. Extend `Lampfire\Records\AbstractDatabaseRecord`.
2. Add `use Lampfire\Records\DatabaseRecordConstructorTrait;`.
3. Define these protected properties.

| Property | Required | Purpose |
|---|---|---|
| `$query` | Yes | The active query used by cursor methods and `getByPrimaryKey()`. |
| `$primaryKeys` | Yes | Primary key columns in declaration order. |
| `$fields` | Yes | Allowed database field names for getters, setters, create, and update. |
| `$hiddenFields` | Optional | Fields blocked from dynamic getters and setters. |
| `$readOnlyFields` | Optional | Fields blocked from dynamic setters, create, and update. |

> The hidden-field property is currently named `$hiddenFields` in the framework base class. Use that exact property name.

#### Step 2: Define the query lifecycle

The cursor methods require an initialized statement. You initialize it by assigning `$query` and executing it through public methods that call `executeQuery()` in the base class.

Typical sequence:

1. Assign a SQL statement to `$query`.
2. Call a method that executes the statement.
3. Iterate results with `next()` or call `rewind()`.
4. Call `count()` only when you want to exhaust the statement and count all rows.

#### Step 3: Use dynamic field accessors safely

The base class maps camelCase accessors to snake_case fields automatically.

- `getUserId()` maps to `user_id`.
- `setPermissionToken()` maps to `permission_token`.

The record throws a `LogicException` when code tries to read hidden fields or write read-only fields.

#### Example record class

```php
<?php

declare(strict_types=1);

namespace Lampfire\Records;

class WidgetRecord extends AbstractDatabaseRecord
{
    use DatabaseRecordConstructorTrait;

    protected ?string $query = null;
    protected array $primaryKeys = ['widget_id'];
    protected array $fields = [
        'widget_id',
        'name',
        'enabled',
        'created_at',
        'updated_at',
    ];

    protected array $hiddenFields = [];
    protected array $readOnlyFields = [
        'widget_id',
        'created_at',
        'updated_at',
    ];

    public function findAll(): self
    {
        $this->query = 'SELECT widget_id, name, enabled, created_at, updated_at FROM Widgets ORDER BY name ASC';
        $this->executeQuery();

        return $this;
    }
}
```

#### Example usage in a service

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Lampfire\Records\WidgetRecord;

class WidgetService
{
    public function __construct(private readonly WidgetRecord $widgetRecord)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWidgets(): array
    {
        $items = [];

        $cursor = $this->widgetRecord->findAll();
        $current = $cursor->rewind();

        while ($current !== null) {
            $items[] = [
                'widget_id' => $current->getWidgetId(),
                'name' => $current->getName(),
                'enabled' => $current->getEnabled(),
            ];

            $current = $cursor->next();
        }

        return $items;
    }
}
```

#### Record pattern rules

- Keep SQL parameterized. Do not interpolate user input into query strings.
- Keep business rules in services. Record classes are for persistence behavior and record-state navigation.
- Keep table naming conventional. `AbstractDatabaseRecord` resolves table names as `<ClassNameWithoutRecord>s` for `create`, `update`, and `delete`.
- Keep primary key definitions complete and ordered. Composite keys must include every key column.
- Keep field lists explicit and accurate. Unknown fields are rejected.


### Adding a Database Migration

Migration files live in `app/migrations/`. The `php cli.php init` command runs them in alphabetical order. Use a zero-padded numeric prefix (for example, `00002-add-widgets-table.sql`). Write migrations to be idempotent where possible.

### Coding Standards

- Every PHP file must begin with `declare(strict_types=1);`.
- Follow PSR-12. Use 4-space indentation.
- Never use `empty()`. Use explicit, type-safe comparisons.
- Use `ramsey/uuid` to generate non-sequential GUIDs for primary keys. Do not use auto-incrementing integer IDs for user-facing entities.
- Never store plaintext passwords. Passwords are hashed with Argon2id via `password_hash(PASSWORD_ARGON2ID)`.
- Never issue or accept JWTs. Session tokens are Paseto v4 local tokens.
- Do not generate HTML in PHP. All HTML output must come from Twig templates.

### Security Architecture

- **Sessions.** Sessions use Paseto v4 local tokens stored in HTTP-only, SameSite=Lax cookies. The HTTP-only flag prevents JavaScript from reading the token. Tokens expire after two hours of inactivity. The symmetric key is `PASETO_KEY` in `.config/.env`. Rotating that key immediately invalidates all active sessions.
- **Passwords.** Hashed with Argon2id before storage. Plaintext passwords are never persisted.
- **CSRF.** Admin web forms include a CSRF token validated on every state-changing submission. API consumers using session cookies do not submit a CSRF token.
- **Method override.** Browser forms only support GET and POST. The admin interface uses a hidden `_METHOD` field with values `DELETE` or `PUT`. Native HTTP clients send the correct method directly.

### CLI Usage

```bash
php cli.php help                  # List all available commands.
php cli.php init                  # Initialize the database and run migrations.
php cli.php <command> --help      # Show help for a specific command.
```

### Running Tests

```bash
cd app && composer test
```

### Running Static Analysis

```bash
cd app && vendor/bin/phpstan analyse
```

## System Documentation
<aside><blockquote>As a system administrator or devops engineer, I am concerned about deploying the application, monitoring it, and ensuring that it runs effectively. Catching failures before they escalate is of concern to me. Detecting the potential causes of failure before the failure happens is preferred.</blockquote></aside>

### Prerequisites

- Docker and Docker Compose for containerized deployment, or PHP 8.4 and Composer for bare-metal deployment.
- A MariaDB server accessible from the application host.

### Initial Deployment

**Step 1: Install dependencies.**

```bash
cd app && composer install --no-dev && cd ..
```

**Step 2: Create the environment file.**

```bash
cp env_example .config/.env
```

Open `.config/.env` and fill in every variable.

| Variable | Required | Description |
|---|---|---|
| `DATABASE_HOST` | Yes | Hostname of the MariaDB server. |
| `DATABASE_PORT` | Yes | Port of the MariaDB server (typically 3306). |
| `DATABASE_NAME` | Yes | Name of the application database. |
| `DATABASE_DBA_USER` | Yes | Administrative database user with CREATE and GRANT privileges. Used only during `init`. |
| `DATABASE_APPLICATION_USER` | Yes | Restricted runtime database user. |
| `DATABASE_APPLICATION_USER_PASSWORD` | Yes | Password for the runtime database user. |
| `DATABASE_USER` | Yes | Set to the same value as `DATABASE_APPLICATION_USER`. |
| `DATABASE_PASSWORD` | No | If omitted, `init` generates a random password automatically. |
| `PASETO_KEY` | Yes | 64-character hexadecimal string (256-bit symmetric key). See below. |
| `APP_DEBUG` | No | Set to `true` to enable verbose error output. Never enable in production. |
| `PROJECT_NAME` | No | Human-readable name of the application, used in titles and display strings. |
| `APP_DOMAIN` | No | Domain name or IP address of the application (for example, `app.example.com`). |
| `APP_PORT` | No | Port the application listens on. Omit or set to `80`/`443` to use the default for the scheme. |
| `USE_HTTPS` | No | Set to `1` to serve the application over HTTPS. Default: `0`. |
| `SUPERADMIN_USERNAME` | Yes | Username for the initial administrator account created during `init`. |
| `ADMIN_PERMISSION_SET_TOKEN` | Yes | Machine-readable token for the built-in administrator permission set. |
| `ADMIN_PERMISSION_TOKEN_PREFIX` | Yes | Prefix applied to all built-in administrator permission tokens. |
| `ADMIN_USER_GROUP_NAME` | Yes | Name of the built-in administrator user group. |
| `ADMIN_PERMISSION_SET_NAME` | Yes | Display name of the built-in administrator permission set. |
| `ADMIN_PERMISSION_SET_DESCRIPTION` | Yes | Description of the built-in administrator permission set. |

**Generating the PASETO key:**

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output and assign it to `PASETO_KEY`. Rotating this key immediately invalidates all active sessions.

**Step 3: Initialize the database.**

```bash
php cli.php init
```

This connects using `DATABASE_DBA_USER`, creates the schema, runs all SQL files from `app/migrations/` in alphabetical order, and seeds the initial administrator account. Each step prints a success or failure message. Correct any reported error and rerun the command.

### Docker Deployment

```bash
docker-compose up -d
docker-compose exec php php cli.php init
```

The MariaDB container runs scripts from `docker/mariadb/init/` on first start.

### Log Files

Application logs are written to `app/logs/`. Set `APP_DEBUG=true` to include debug-level entries. Never enable debug mode in production.

### Sessions

There is no server-side session store. To force all users to re-authenticate immediately, rotate `PASETO_KEY` in `.config/.env` and restart the application.

### Emergency Password Reset

If all administrator accounts are inaccessible, generate a new Argon2id hash and update the database directly.

```bash
php -r "echo password_hash('NewPassword123', PASSWORD_ARGON2ID);"
```

```sql
UPDATE users SET password_hash = '<output from above>' WHERE username = '<username>';
```

### Troubleshooting

**The application fails to start.**
Check `app/logs/` for the error. Common causes are a missing or malformed `.config/.env`, an unreachable database, or an invalid `PASETO_KEY`.

**The `init` command fails.**
Verify that all required environment variables are set and that `DATABASE_DBA_USER` has CREATE, DROP, and GRANT privileges on the target database. Correct the reported error and rerun. The command is safe to rerun.

**An administrator reports being unable to sign in after a deployment.**
If `PASETO_KEY` was rotated or the `.config/.env` file was replaced, all existing sessions have been invalidated. All users must sign in again.

## Application Administator Documentation
<aside><blockquote>As an application administrator, I have regular administrator access. I can modify user accounts, user groups, permissions, and permission sets. My goal is to ensure that regular business users can login and that permissions are accurate and updated in a timely fashion.</blockquote></aside>

### Signing In

1. Go to `/admin/login`.
2. Enter your username and password.
3. Select **Sign In**.

After a successful sign-in, the application redirects you to `/admin/dashboard`. Select **Log Out** in the sidebar when you finish.

> Do not sign in at `/`. That form grants standard user access only, even if your account has administrator rights.

### Admin Panel Navigation

The sidebar is visible on all admin pages.

- **Dashboard** — `/admin/dashboard`, the landing page after sign-in.
- **Users** — `/admin/users`, the user management screen.
- **Log Out** — ends your current session.

### Managing Users

#### Create a User

1. Select **Users** in the sidebar, then select **Create New User**.
2. Complete the required fields: Username (minimum 3 characters, unique), Email Address (unique), Password (minimum 8 characters), and Confirm Password.
3. Optionally enter First Name and Last Name.
4. Select **Create User**.

#### Edit a User

1. Select **Users**, then select **Edit** on the target user's row.
2. Update username, email address, first name, or last name.
3. Select **Save Changes**.

#### Reset a User's Password

This does not require the user's current password.

1. Select **Users**, then select **View** for the target user.
2. Scroll to **Reset Password**, enter and confirm the new password (minimum 8 characters).
3. Select **Reset Password**.

#### Delete a User

Deletion is permanent.

1. Select **Users**, then select **Delete** on the target user's row, or open the detail page and select **Delete** there.
2. Confirm when the browser prompts you.

### The Permission Model

Four building blocks control access.

- A **permission** is a single named right, identified by a machine-readable token such as `report.view`.
- A **permission set** is a named bundle of permissions, analogous to a role. Build it once and grant it to many users or groups.
- A **user group** is a named collection of users. Members inherit access from whatever permission sets are assigned to the group.
- An **access grant** connects a user or user group to a permission set. Grants can be time-limited.

Typical setup flow: define permissions, create a permission set, add the permissions to the set, then grant the set to users or groups.

### Managing Permissions

Create a permission: `POST /api/permissions`

| Field | Required | Description |
|---|---|---|
| `permission_token` | Yes | Machine-readable identifier (e.g., `report.export`) |
| `permission_title` | Yes | Human-readable name |
| `notes` | No | Optional documentation |

List all permissions: `GET /api/permissions`

### Managing Permission Sets

Create: `POST /api/permission-sets` with `permission_set_token` and `title`.

Add a permission to a set: `POST /api/permission-set-permissions` with `permission_set_id` and `permission_id`.

Remove a permission from a set: `DELETE /api/permission-set-permissions/{permissionSetId}/{permissionId}`.

### Managing User Groups

Create: `POST /api/user-groups` with `group_name` and optional `description`.

Add a user to a group: `POST /api/user-group-memberships` with `user_id` and `user_group_id`.

Remove a user from a group: `DELETE /api/user-group-memberships/{userId}/{userGroupId}`.

### Granting Access

**Grant a permission set to a single user:**
`POST /api/permission-set-members` with `user_id` and `permission_set_id`.

**Grant a permission set to a user group:**
`POST /api/permission-set-user-groups` with `user_group_id` and `permission_set_id`.

Both endpoints accept optional fields to control timing:
- `has_access` (boolean) — enables or disables the grant without removing it.
- `access_granted` (ISO 8601 datetime) — grant is inactive before this date.
- `access_expiry` (ISO 8601 datetime) — grant is inactive after this date.

### Revoking Access

To suspend without removing the record: `PUT` to the appropriate endpoint and set `has_access` to `false`.

To remove permanently:

| What to revoke | Endpoint |
|---|---|
| User from a permission set | `DELETE /api/permission-set-members/{permissionSetId}/{userId}` |
| User group from a permission set | `DELETE /api/permission-set-user-groups/{userGroupId}/{permissionSetId}` |
| User from a user group | `DELETE /api/user-group-memberships/{userId}/{userGroupId}` |

### Common Workflows

**Onboarding a new employee:**
1. Create the user account.
2. Add the user to the appropriate groups with `POST /api/user-group-memberships`.

**Offboarding an employee:**
1. Remove all group memberships with `DELETE /api/user-group-memberships/{userId}/{userGroupId}` for each group.
2. Remove any direct permission-set grants with `DELETE /api/permission-set-members/{permissionSetId}/{userId}` for each grant.
3. Optionally delete the account.

**Creating a new role:**
1. Create a user group named for the role.
2. Create a permission set and add the relevant permissions.
3. Grant the permission set to the group with `POST /api/permission-set-user-groups`.
4. Add users to the group.

### Troubleshooting

**A user cannot sign in and you have confirmed their credentials are correct.**
Open the Users screen, find the user, and use Reset Password to set a new temporary password.

**A user cannot access features they should have.**
Check their group memberships and direct permission-set grants. Verify that the relevant access grants have `has_access` set to `true` and that `access_expiry` has not passed.

**You cannot access the admin panel.**
Verify you are signing in at `/admin/login`. If your account appears correct but access is still denied, contact your system administrator to verify the database and application state.

## Regular Business User Documentation
<aside><blockquote>As a regular business user I want to login to my application, or into my business' custom business applications.</blockquote></aside>

### Signing In

1. Open the application URL in your browser.
2. Enter your username and password.
3. Select **Sign In**.

After a successful sign-in, you are redirected to your dashboard.

### Signing Out

Select the **Logout** button on your dashboard. Always sign out when you finish, especially on a shared or public device.

### Sessions Expire Automatically

Your session expires after two hours of inactivity. When it expires, you will be redirected to the sign-in page the next time you try to use the application. Sign in again to continue.

### If You Cannot Sign In

Both the username and password fields are case-sensitive. Verify that you are typing them correctly.

If you have forgotten your password or your account is locked, contact your administrator. Administrators can reset your password without needing your current one.

### If You Cannot Access Certain Features

Your account may not have the permissions required. Contact your administrator and describe what you are trying to do.