<?php

declare(strict_types=1);

/**
 * Business logic service for permission set management.
 *
 * Coordinates permission set workflows and delegates persistence
 * operations to record entities.
 */

namespace Lampfire\Services;

use InvalidArgumentException;
use Lampfire\Records\PermissionRecord;
use Lampfire\Records\PermissionSetMemberRecord;
use Lampfire\Records\PermissionSetPermissionRecord;
use Lampfire\Records\PermissionSetRecord;
use Lampfire\Records\PermissionSetUserGroupRecord;
use Lampfire\Records\UserGroupRecord;
use Lampfire\Records\UserRecord;
use Lampfire\Utilities\Enforcers;

class PermissionSetService extends AbstractService
{
    private PermissionSetRecord $permissionSetRecord;
    private PermissionSetPermissionRecord $permissionSetPermissionRecord;
    private PermissionSetMemberRecord $permissionSetMemberRecord;
    private PermissionSetUserGroupRecord $permissionSetUserGroupRecord;
    private PermissionRecord $permissionRecord;
    private UserRecord $userRecord;
    private UserGroupRecord $userGroupRecord;

    public function __construct(
        PermissionSetRecord $permissionSetRecord,
        PermissionSetPermissionRecord $permissionSetPermissionRecord,
        PermissionSetMemberRecord $permissionSetMemberRecord,
        PermissionSetUserGroupRecord $permissionSetUserGroupRecord,
        PermissionRecord $permissionRecord,
        UserRecord $userRecord,
        UserGroupRecord $userGroupRecord
    ) {
        $this->permissionSetRecord = $permissionSetRecord;
        $this->permissionSetPermissionRecord = $permissionSetPermissionRecord;
        $this->permissionSetMemberRecord = $permissionSetMemberRecord;
        $this->permissionSetUserGroupRecord = $permissionSetUserGroupRecord;
        $this->permissionRecord = $permissionRecord;
        $this->userRecord = $userRecord;
        $this->userGroupRecord = $userGroupRecord;
    }

    /**
     * @return array<string, mixed>
     */
    public function createSet(string $permissionSetToken, string $title, ?string $notes = null): array
    {
        Enforcers::enforceMinLength($permissionSetToken, 2, 'permission_set_token');
        Enforcers::enforceMinLength($title, 2, 'title');

        if ($this->permissionSetRecord->tokenExists($permissionSetToken)) {
            throw new InvalidArgumentException('The permission set token is already in use.');
        }

        $created = $this->permissionSetRecord->create([
            'permission_set_id' => $this->generateUuid(),
            'permission_set_token' => $permissionSetToken,
            'title' => $title,
            'notes' => $notes,
        ]);

        return $this->mapPermissionSetRecord($created);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateSet(
        string $permissionSetId,
        string $permissionSetToken,
        string $title,
        ?string $notes = null
    ): ?array {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceMinLength($permissionSetToken, 2, 'permission_set_token');
        Enforcers::enforceMinLength($title, 2, 'title');

        $existing = $this->permissionSetRecord->getByPrimaryKey($permissionSetId);
        if ($existing === null) {
            return null;
        }

        if ($this->permissionSetRecord->tokenExists($permissionSetToken, $permissionSetId)) {
            throw new InvalidArgumentException('The permission set token is already in use.');
        }

        $existing->update([
            'permission_set_id' => $permissionSetId,
            'permission_set_token' => $permissionSetToken,
            'title' => $title,
            'notes' => $notes,
        ]);

        $updated = $this->permissionSetRecord->getByPrimaryKey($permissionSetId);
        if ($updated === null) {
            return null;
        }

        return $this->mapPermissionSetRecord($updated);
    }

    public function deleteSet(string $permissionSetId): bool
    {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        $existing = $this->permissionSetRecord->getByPrimaryKey($permissionSetId);
        if ($existing === null) {
            return false;
        }

        return $existing->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function addPermissionToSet(
        string $permissionSetId,
        string $permissionId,
        ?string $notes = null
    ): array {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($permissionId, 'permission_id');

        if ($this->permissionSetRecord->getByPrimaryKey($permissionSetId) === null) {
            throw new InvalidArgumentException('The specified permission set does not exist.');
        }

        if ($this->permissionRecord->getByPrimaryKey($permissionId) === null) {
            throw new InvalidArgumentException('The specified permission does not exist.');
        }

        $created = $this->permissionSetPermissionRecord->create([
            'permission_set_id' => $permissionSetId,
            'permission_id' => $permissionId,
            'notes' => $notes,
        ]);

        return $this->mapPermissionSetPermissionRecord($created);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateSetPermission(
        string $permissionSetId,
        string $permissionId,
        ?string $notes = null
    ): ?array {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($permissionId, 'permission_id');

        $existing = $this->permissionSetPermissionRecord->getByPrimaryKey($permissionSetId, $permissionId);
        if ($existing === null) {
            return null;
        }

        $existing->update([
            'permission_set_id' => $permissionSetId,
            'permission_id' => $permissionId,
            'notes' => $notes,
        ]);

        $updated = $this->permissionSetPermissionRecord->getByPrimaryKey($permissionSetId, $permissionId);
        if ($updated === null) {
            return null;
        }

        return $this->mapPermissionSetPermissionRecord($updated);
    }

    public function removePermissionFromSet(string $permissionSetId, string $permissionId): bool
    {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($permissionId, 'permission_id');

        $existing = $this->permissionSetPermissionRecord->getByPrimaryKey($permissionSetId, $permissionId);
        if ($existing === null) {
            return false;
        }

        return $existing->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function addMemberToSet(
        string $permissionSetId,
        string $userId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): array {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($userId, 'user_id');

        if ($this->permissionSetRecord->getByPrimaryKey($permissionSetId) === null) {
            throw new InvalidArgumentException('The specified permission set does not exist.');
        }

        if ($this->userRecord->getByPrimaryKey($userId) === null) {
            throw new InvalidArgumentException('The specified user does not exist.');
        }

        $created = $this->permissionSetMemberRecord->create([
            'permission_set_id' => $permissionSetId,
            'user_id' => $userId,
            'access_granted' => $accessGranted,
            'access_expiry' => $accessExpiry,
            'has_access' => $hasAccess ? 1 : 0,
            'notes' => $notes,
        ]);

        return $this->mapPermissionSetMemberRecord($created);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateSetMember(
        string $permissionSetId,
        string $userId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): ?array {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($userId, 'user_id');

        $existing = $this->permissionSetMemberRecord->getByPrimaryKey($permissionSetId, $userId);
        if ($existing === null) {
            return null;
        }

        $existing->update([
            'permission_set_id' => $permissionSetId,
            'user_id' => $userId,
            'access_granted' => $accessGranted,
            'access_expiry' => $accessExpiry,
            'has_access' => $hasAccess ? 1 : 0,
            'notes' => $notes,
        ]);

        $updated = $this->permissionSetMemberRecord->getByPrimaryKey($permissionSetId, $userId);
        if ($updated === null) {
            return null;
        }

        return $this->mapPermissionSetMemberRecord($updated);
    }

    public function removeMemberFromSet(string $permissionSetId, string $userId): bool
    {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($userId, 'user_id');

        $existing = $this->permissionSetMemberRecord->getByPrimaryKey($permissionSetId, $userId);
        if ($existing === null) {
            return false;
        }

        return $existing->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function addGroupToSet(
        string $userGroupId,
        string $permissionSetId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): array {
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        if ($this->userGroupRecord->getByPrimaryKey($userGroupId) === null) {
            throw new InvalidArgumentException('The specified user group does not exist.');
        }

        if ($this->permissionSetRecord->getByPrimaryKey($permissionSetId) === null) {
            throw new InvalidArgumentException('The specified permission set does not exist.');
        }

        $created = $this->permissionSetUserGroupRecord->create([
            'user_group_id' => $userGroupId,
            'permission_set_id' => $permissionSetId,
            'access_granted' => $accessGranted,
            'access_expiry' => $accessExpiry,
            'has_access' => $hasAccess ? 1 : 0,
            'notes' => $notes,
        ]);

        return $this->mapPermissionSetUserGroupRecord($created);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateSetGroupMember(
        string $userGroupId,
        string $permissionSetId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): ?array {
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        $existing = $this->permissionSetUserGroupRecord->getByPrimaryKey($userGroupId, $permissionSetId);
        if ($existing === null) {
            return null;
        }

        $existing->update([
            'user_group_id' => $userGroupId,
            'permission_set_id' => $permissionSetId,
            'access_granted' => $accessGranted,
            'access_expiry' => $accessExpiry,
            'has_access' => $hasAccess ? 1 : 0,
            'notes' => $notes,
        ]);

        $updated = $this->permissionSetUserGroupRecord->getByPrimaryKey($userGroupId, $permissionSetId);
        if ($updated === null) {
            return null;
        }

        return $this->mapPermissionSetUserGroupRecord($updated);
    }

    public function removeGroupFromSet(string $userGroupId, string $permissionSetId): bool
    {
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        $existing = $this->permissionSetUserGroupRecord->getByPrimaryKey($userGroupId, $permissionSetId);
        if ($existing === null) {
            return false;
        }

        return $existing->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPermissionSetRecord(PermissionSetRecord $record): array
    {
        return [
            'permission_set_id' => $record->getPermissionSetId(),
            'permission_set_token' => $record->getPermissionSetToken(),
            'title' => $record->getTitle(),
            'notes' => $record->getNotes(),
            'created_at' => $record->getCreatedAt(),
            'updated_at' => $record->getUpdatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPermissionSetPermissionRecord(PermissionSetPermissionRecord $record): array
    {
        return [
            'permission_set_id' => $record->getPermissionSetId(),
            'permission_id' => $record->getPermissionId(),
            'notes' => $record->getNotes(),
            'created_at' => $record->getCreatedAt(),
            'updated_at' => $record->getUpdatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPermissionSetMemberRecord(PermissionSetMemberRecord $record): array
    {
        return [
            'permission_set_id' => $record->getPermissionSetId(),
            'user_id' => $record->getUserId(),
            'access_granted' => $record->getAccessGranted(),
            'access_expiry' => $record->getAccessExpiry(),
            'has_access' => $record->getHasAccess(),
            'notes' => $record->getNotes(),
            'created_at' => $record->getCreatedAt(),
            'updated_at' => $record->getUpdatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPermissionSetUserGroupRecord(PermissionSetUserGroupRecord $record): array
    {
        return [
            'user_group_id' => $record->getUserGroupId(),
            'permission_set_id' => $record->getPermissionSetId(),
            'access_granted' => $record->getAccessGranted(),
            'access_expiry' => $record->getAccessExpiry(),
            'has_access' => $record->getHasAccess(),
            'notes' => $record->getNotes(),
            'created_at' => $record->getCreatedAt(),
            'updated_at' => $record->getUpdatedAt(),
        ];
    }
}
