<?php

declare(strict_types=1);

/**
 * Business logic service for user group management.
 *
 * Coordinates operations on UserGroups and their UserGroupMemberships.
 * Validates inputs and enforces referential integrity through existence
 * checks before delegating data access to the gateway layer.
 */

namespace App\Services;

use Lampfire\Gateways\UserGroupGateway;
use Lampfire\Gateways\UserGroupMembershipGateway;
use Lampfire\Gateways\UserGateway;
use InvalidArgumentException;
use Lampfire\Services\AbstractService;

class UserGroupService extends AbstractService
{
    /**
     * @var UserGroupGateway Gateway for the UserGroups table.
     */
    private UserGroupGateway $groupGateway;

    /**
     * @var UserGroupMembershipGateway Gateway for the UserGroupMemberships table.
     */
    private UserGroupMembershipGateway $membershipGateway;

    /**
     * @var UserGateway Gateway for the Users table, used for existence checks.
     */
    private UserGateway $userGateway;

    /**
     * Creates the user group service.
     *
     * @param UserGroupGateway           $groupGateway      Gateway for UserGroups.
     * @param UserGroupMembershipGateway $membershipGateway Gateway for UserGroupMemberships.
     * @param UserGateway                $userGateway       Gateway for Users.
     */
    public function __construct(
        UserGroupGateway $groupGateway,
        UserGroupMembershipGateway $membershipGateway,
        UserGateway $userGateway
    ) {
        $this->groupGateway = $groupGateway;
        $this->membershipGateway = $membershipGateway;
        $this->userGateway = $userGateway;
    }

    // ----------------------------------------------------------------
    // UserGroups CRUD
    // ----------------------------------------------------------------

    /**
     * Returns all user groups.
     *
     * @return array<int, array<string, mixed>> The list of groups.
     */
    public function getAllGroups(): array
    {
        return $this->groupGateway->findAll();
    }

    /**
     * Returns a single user group by identifier.
     *
     * @param string $userGroupId The UUID of the group.
     * @return array<string, mixed>|null The group or null when not found.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getGroupById(string $userGroupId): ?array
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');

        return $this->groupGateway->findById($userGroupId);
    }

    /**
     * Creates a new user group.
     *
     * @param string      $groupName   The display name for the group.
     * @param string|null $description An optional description.
     * @return array<string, mixed> The newly created group record.
     * @throws InvalidArgumentException When the group name is too short.
     */
    public function createGroup(string $groupName, ?string $description = null): array
    {
        $this->requireMinLength($groupName, 2, 'group_name');

        $userGroupId = $this->generateUuid();
        $this->groupGateway->insert($userGroupId, $groupName, $description);

        return $this->groupGateway->findById($userGroupId);
    }

    /**
     * Updates an existing user group.
     *
     * @param string      $userGroupId The UUID of the group.
     * @param string      $groupName   The new display name.
     * @param string|null $description The new description.
     * @return array<string, mixed>|null The updated record or null when not found.
     * @throws InvalidArgumentException When validation fails.
     */
    public function updateGroup(string $userGroupId, string $groupName, ?string $description = null): ?array
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');
        $this->requireMinLength($groupName, 2, 'group_name');

        $existing = $this->groupGateway->findById($userGroupId);
        if ($existing === null) {
            return null;
        }

        $this->groupGateway->update($userGroupId, $groupName, $description);

        return $this->groupGateway->findById($userGroupId);
    }

    /**
     * Deletes a user group.
     *
     * @param string $userGroupId The UUID of the group.
     * @return bool True when the group was deleted.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function deleteGroup(string $userGroupId): bool
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $existing = $this->groupGateway->findById($userGroupId);
        if ($existing === null) {
            return false;
        }

        return $this->groupGateway->delete($userGroupId);
    }

    // ----------------------------------------------------------------
    // UserGroupMemberships
    // ----------------------------------------------------------------

    /**
     * Returns all memberships for a given group.
     *
     * @param string $userGroupId The UUID of the group.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getMembersByGroupId(string $userGroupId): array
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');

        return $this->membershipGateway->findByGroupId($userGroupId);
    }

    /**
     * Returns all group memberships for a given user.
     *
     * @param string $userId The UUID of the user.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getMembershipsByUserId(string $userId): array
    {
        $this->requireValidUuid($userId, 'user_id');

        return $this->membershipGateway->findByUserId($userId);
    }

    /**
     * Returns a single membership by its composite key.
     *
     * @param string $userId      The UUID of the user.
     * @param string $userGroupId The UUID of the group.
     * @return array<string, mixed>|null The membership or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function getMembership(string $userId, string $userGroupId): ?array
    {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireValidUuid($userGroupId, 'user_group_id');

        return $this->membershipGateway->findByKey($userId, $userGroupId);
    }

    /**
     * Adds a user to a group.
     *
     * Validates that both the user and the group exist before inserting.
     *
     * @param string $userId        The UUID of the user.
     * @param string $userGroupId   The UUID of the group.
     * @param string $accessGranted The date-time access was granted.
     * @param string $accessExpiry  The date-time access expires.
     * @param bool   $hasAccess     Whether access is currently active.
     * @return array<string, mixed> The newly created membership record.
     * @throws InvalidArgumentException When validation or existence checks fail.
     */
    public function addMember(
        string $userId,
        string $userGroupId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess
    ): array {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireValidUuid($userGroupId, 'user_group_id');

        if ($this->userGateway->findById($userId) === null) {
            throw new InvalidArgumentException('The specified user does not exist.');
        }

        if ($this->groupGateway->findById($userGroupId) === null) {
            throw new InvalidArgumentException('The specified user group does not exist.');
        }

        $this->membershipGateway->insert($userId, $userGroupId, $accessGranted, $accessExpiry, $hasAccess);

        return $this->membershipGateway->findByKey($userId, $userGroupId);
    }

    /**
     * Updates an existing group membership.
     *
     * @param string $userId        The UUID of the user.
     * @param string $userGroupId   The UUID of the group.
     * @param string $accessGranted The new grant date-time.
     * @param string $accessExpiry  The new expiry date-time.
     * @param bool   $hasAccess     Whether access is currently active.
     * @return array<string, mixed>|null The updated record or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function updateMembership(
        string $userId,
        string $userGroupId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess
    ): ?array {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $existing = $this->membershipGateway->findByKey($userId, $userGroupId);
        if ($existing === null) {
            return null;
        }

        $this->membershipGateway->update($userId, $userGroupId, $accessGranted, $accessExpiry, $hasAccess);

        return $this->membershipGateway->findByKey($userId, $userGroupId);
    }

    /**
     * Removes a user from a group.
     *
     * @param string $userId      The UUID of the user.
     * @param string $userGroupId The UUID of the group.
     * @return bool True when the membership was deleted.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function removeMember(string $userId, string $userGroupId): bool
    {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireValidUuid($userGroupId, 'user_group_id');

        return $this->membershipGateway->deleteByKey($userId, $userGroupId);
    }
}
