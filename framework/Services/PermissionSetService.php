<?php

declare(strict_types=1);

/**
 * Business logic service for permission set management.
 *
 * Coordinates permission set workflows and delegates persistence
 * operations to record entities.
 */

namespace Lampfire\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Lampfire\Records\PermissionRecord;
use Lampfire\Records\PermissionSetMemberRecord;
use Lampfire\Records\PermissionSetPermissionRecord;
use Lampfire\Records\PermissionSetRecord;
use Lampfire\Records\PermissionSetUserGroupRecord;
use Lampfire\Records\UserGroupMembershipRecord;
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
    private UserGroupMembershipRecord $userGroupMembershipRecord;

    public function __construct(
        PermissionSetRecord $permissionSetRecord,
        PermissionSetPermissionRecord $permissionSetPermissionRecord,
        PermissionSetMemberRecord $permissionSetMemberRecord,
        PermissionSetUserGroupRecord $permissionSetUserGroupRecord,
        PermissionRecord $permissionRecord,
        UserRecord $userRecord,
        UserGroupRecord $userGroupRecord,
        UserGroupMembershipRecord $userGroupMembershipRecord
    ) {
        $this->permissionSetRecord = $permissionSetRecord;
        $this->permissionSetPermissionRecord = $permissionSetPermissionRecord;
        $this->permissionSetMemberRecord = $permissionSetMemberRecord;
        $this->permissionSetUserGroupRecord = $permissionSetUserGroupRecord;
        $this->permissionRecord = $permissionRecord;
        $this->userRecord = $userRecord;
        $this->userGroupRecord = $userGroupRecord;
        $this->userGroupMembershipRecord = $userGroupMembershipRecord;
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

        if ($this->permissionSetPermissionRecord->getByPrimaryKey($permissionSetId, $permissionId) !== null) {
            throw new InvalidArgumentException('Association already exists.');
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

        if ($this->permissionSetMemberRecord->getByPrimaryKey($permissionSetId, $userId) !== null) {
            throw new InvalidArgumentException('Association already exists.');
        }

        $this->enforceNoDirectGrantOverlapWithGroupAssignments(
            $userId,
            $permissionSetId,
            $hasAccess,
            $accessExpiry
        );

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

        $this->enforceNoDirectGrantOverlapWithGroupAssignments(
            $userId,
            $permissionSetId,
            $hasAccess,
            $accessExpiry
        );

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

        if ($this->permissionSetUserGroupRecord->getByPrimaryKey($userGroupId, $permissionSetId) !== null) {
            throw new InvalidArgumentException('Association already exists.');
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

    /**
     * Blocks direct set memberships that would grant permissions already
     * granted to the user through active group-to-set associations.
     */
    private function enforceNoDirectGrantOverlapWithGroupAssignments(
        string $userId,
        string $permissionSetId,
        bool $hasAccess,
        string $accessExpiry
    ): void {
        if ($hasAccess === false) {
            return;
        }

        if ($this->isExpiryInFutureUtc($accessExpiry) === false) {
            return;
        }

        $directPermissionIds = $this->getPermissionIdsForSet($permissionSetId);
        if (count($directPermissionIds) === 0) {
            return;
        }

        $groupPermissionIds = $this->getActiveGroupGrantedPermissionIdsForUser($userId);
        foreach ($directPermissionIds as $permissionId => $_unused) {
            if (array_key_exists($permissionId, $groupPermissionIds)) {
                throw new InvalidArgumentException('Association already exists.');
            }
        }
    }

    /**
     * @return array<string, true>
     */
    private function getPermissionIdsForSet(string $permissionSetId): array
    {
        $setPermissions = $this->permissionSetPermissionRecord->findBySetId($permissionSetId);
        $permissionIds = [];

        foreach ($setPermissions as $setPermission) {
            $permissionId = $setPermission['permission_id'] ?? null;
            if (is_string($permissionId) && $permissionId !== '') {
                $permissionIds[$permissionId] = true;
            }
        }

        return $permissionIds;
    }

    /**
     * @return array<string, true>
     */
    private function getActiveGroupGrantedPermissionIdsForUser(string $userId): array
    {
        $memberships = $this->userGroupMembershipRecord->findByUserId($userId);
        $permissionIds = [];

        foreach ($memberships as $membership) {
            if ($this->isActiveAssociation($membership) === false) {
                continue;
            }

            $userGroupId = $membership['user_group_id'] ?? null;
            if (is_string($userGroupId) === false || $userGroupId === '') {
                continue;
            }

            $group = $this->userGroupRecord->getByPrimaryKey($userGroupId);
            if ($group === null) {
                continue;
            }

            $groupEnabled = $group->get('enabled');
            if ((int) $groupEnabled !== 1) {
                continue;
            }

            $groupAssignments = $this->permissionSetUserGroupRecord->findByGroupId($userGroupId);
            foreach ($groupAssignments as $groupAssignment) {
                if ($this->isActiveAssociation($groupAssignment) === false) {
                    continue;
                }

                $groupPermissionSetId = $groupAssignment['permission_set_id'] ?? null;
                if (is_string($groupPermissionSetId) === false || $groupPermissionSetId === '') {
                    continue;
                }

                $setPermissions = $this->permissionSetPermissionRecord->findBySetId($groupPermissionSetId);
                foreach ($setPermissions as $setPermission) {
                    $permissionId = $setPermission['permission_id'] ?? null;
                    if (is_string($permissionId) && $permissionId !== '') {
                        $permissionIds[$permissionId] = true;
                    }
                }
            }
        }

        return $permissionIds;
    }

    /**
     * @param array<string, mixed> $association
     */
    private function isActiveAssociation(array $association): bool
    {
        $hasAccess = $association['has_access'] ?? 0;
        if ((int) $hasAccess !== 1) {
            return false;
        }

        $accessExpiry = $association['access_expiry'] ?? null;
        if (is_string($accessExpiry) === false || $accessExpiry === '') {
            return false;
        }

        return $this->isExpiryInFutureUtc($accessExpiry);
    }

    private function isExpiryInFutureUtc(string $accessExpiry): bool
    {
        $expiry = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $accessExpiry, new DateTimeZone('UTC'));
        if ($expiry === false) {
            return false;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $expiry > $now;
    }
}
