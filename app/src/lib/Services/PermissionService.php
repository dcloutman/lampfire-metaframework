<?php

declare(strict_types=1);

/**
 * Business logic service for permission management.
 *
 * Handles CRUD operations on individual Permissions with input
 * validation, uniqueness enforcement for programmatic tokens, and
 * UUID generation.
 */

namespace App\Services;

use Lampfire\Gateways\PermissionGateway;
use InvalidArgumentException;
use Lampfire\Services\AbstractService;

class PermissionService extends AbstractService
{
    /**
     * @var PermissionGateway Gateway for the Permissions table.
     */
    private PermissionGateway $permissionGateway;

    /**
     * Creates the permission service.
     *
     * @param PermissionGateway $permissionGateway Gateway for Permissions.
     */
    public function __construct(PermissionGateway $permissionGateway)
    {
        $this->permissionGateway = $permissionGateway;
    }

    /**
     * Returns all permissions.
     *
     * @return array<int, array<string, mixed>> The list of permissions.
     */
    public function getAllPermissions(): array
    {
        return $this->permissionGateway->findAll();
    }

    /**
     * Returns a single permission by identifier.
     *
     * @param string $permissionId The UUID of the permission.
     * @return array<string, mixed>|null The permission or null when not found.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getPermissionById(string $permissionId): ?array
    {
        $this->requireValidUuid($permissionId, 'permission_id');

        return $this->permissionGateway->findById($permissionId);
    }

    /**
     * Creates a new permission.
     *
     * Validates inputs and enforces uniqueness of the programmatic token
     * before inserting.
     *
     * @param string      $permissionToken The programmatic token string.
     * @param string      $permissionTitle The human-readable title.
     * @param string|null $notes           Optional notes.
     * @return array<string, mixed> The newly created permission record.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function createPermission(
        string $permissionToken,
        string $permissionTitle,
        ?string $notes = null
    ): array {
        $this->requireMinLength($permissionToken, 2, 'permission_token');
        $this->requireMinLength($permissionTitle, 2, 'permission_title');

        if ($this->permissionGateway->tokenExists($permissionToken)) {
            throw new InvalidArgumentException('The permission token is already in use.');
        }

        $permissionId = $this->generateUuid();
        $this->permissionGateway->insert($permissionId, $permissionToken, $permissionTitle, $notes);

        return $this->permissionGateway->findById($permissionId);
    }

    /**
     * Updates an existing permission.
     *
     * Validates inputs and enforces uniqueness of the programmatic token
     * (excluding the current record) before updating.
     *
     * @param string      $permissionId    The UUID of the permission.
     * @param string      $permissionToken The new token string.
     * @param string      $permissionTitle The new title.
     * @param string|null $notes           The new notes.
     * @return array<string, mixed>|null The updated record or null when not found.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function updatePermission(
        string $permissionId,
        string $permissionToken,
        string $permissionTitle,
        ?string $notes = null
    ): ?array {
        $this->requireValidUuid($permissionId, 'permission_id');
        $this->requireMinLength($permissionToken, 2, 'permission_token');
        $this->requireMinLength($permissionTitle, 2, 'permission_title');

        $existing = $this->permissionGateway->findById($permissionId);
        if ($existing === null) {
            return null;
        }

        if ($this->permissionGateway->tokenExists($permissionToken, $permissionId)) {
            throw new InvalidArgumentException('The permission token is already in use.');
        }

        $this->permissionGateway->update($permissionId, $permissionToken, $permissionTitle, $notes);

        return $this->permissionGateway->findById($permissionId);
    }

    /**
     * Deletes a permission.
     *
     * @param string $permissionId The UUID of the permission.
     * @return bool True when the permission was deleted.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function deletePermission(string $permissionId): bool
    {
        $this->requireValidUuid($permissionId, 'permission_id');

        $existing = $this->permissionGateway->findById($permissionId);
        if ($existing === null) {
            return false;
        }

        return $this->permissionGateway->delete($permissionId);
    }
}
