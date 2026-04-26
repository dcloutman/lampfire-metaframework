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

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Lampfire\Gateways\UserGroupGateway;
use Lampfire\Gateways\UserGroupMembershipGateway;
use Lampfire\Gateways\UserGateway;
use InvalidArgumentException;
use Lampfire\Services\AbstractService;
use Lampfire\Utilities\Enforcers;

class UserGroupService extends AbstractService
{
    private const ACCESS_DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const MAX_MEMBERSHIP_DURATION = 'P2Y';

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
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

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
        Enforcers::enforceMinLength($groupName, 2, 'group_name');

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
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');
        Enforcers::enforceMinLength($groupName, 2, 'group_name');

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
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

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
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

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
        Enforcers::enforceValidUuid($userId, 'user_id');

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
        Enforcers::enforceValidUuid($userId, 'user_id');
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

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
        Enforcers::enforceValidUuid($userId, 'user_id');
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

        if ($this->userGateway->findById($userId) === null) {
            throw new InvalidArgumentException('The specified user does not exist.');
        }

        if ($this->groupGateway->findById($userGroupId) === null) {
            throw new InvalidArgumentException('The specified user group does not exist.');
        }

        if ($hasAccess === false) {
            $accessExpiry = $this->currentUtcTimestampString();
        } else {
            $this->enforceMembershipAccessWindow($accessGranted, $accessExpiry, $hasAccess);
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
        Enforcers::enforceValidUuid($userId, 'user_id');
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

        $existing = $this->membershipGateway->findByKey($userId, $userGroupId);
        if ($existing === null) {
            return null;
        }

        if ($hasAccess === false) {
            $accessExpiry = $this->currentUtcTimestampString();
        } else {
            $this->enforceMembershipAccessWindow($accessGranted, $accessExpiry, $hasAccess);
        }

        $this->membershipGateway->update($userId, $userGroupId, $accessGranted, $accessExpiry, $hasAccess);

        return $this->membershipGateway->findByKey($userId, $userGroupId);
    }

    /**
     * Validates that the membership window is valid and no longer than two years.
     *
     * @param string $accessGranted The date-time access was granted.
     * @param string $accessExpiry  The date-time access expires.
     * @param bool   $hasAccess     Whether access should remain active.
     * @return void
     * @throws InvalidArgumentException When the access window is invalid.
     */
    private function enforceMembershipAccessWindow(string $accessGranted, string $accessExpiry, bool $hasAccess): void
    {
        $grantedAt = $this->parseMembershipDateTime($accessGranted, 'access_granted');
        $expiresAt = $this->parseMembershipDateTime($accessExpiry, 'access_expiry');

        if ($expiresAt <= $grantedAt) {
            throw new InvalidArgumentException('The access_expiry must be later than access_granted.');
        }

        if ($hasAccess === false) {
            return;
        }

        $maxExpiry = $grantedAt->add(new DateInterval(self::MAX_MEMBERSHIP_DURATION));
        if ($expiresAt > $maxExpiry) {
            throw new InvalidArgumentException(
                'User group access cannot expire more than two years after access_granted.'
            );
        }
    }

    /**
     * Parses and validates a UTC date-time string used by group memberships.
     *
     * @param string $value     The date-time string.
     * @param string $fieldName The field name used in error messages.
     * @return DateTimeImmutable The parsed date-time.
     * @throws InvalidArgumentException When the value is not a valid UTC date-time.
     */
    private function parseMembershipDateTime(string $value, string $fieldName): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat(
            self::ACCESS_DATETIME_FORMAT,
            $value,
            new DateTimeZone('UTC')
        );

        $dateTimeErrors = DateTimeImmutable::getLastErrors();
        $hasDateTimeErrors = is_array($dateTimeErrors)
            && (
                (($dateTimeErrors['warning_count'] ?? 0) > 0)
                || (($dateTimeErrors['error_count'] ?? 0) > 0)
            );

        if (
            $parsed === false
            || $hasDateTimeErrors
            || $parsed->format(self::ACCESS_DATETIME_FORMAT) !== $value
        ) {
            throw new InvalidArgumentException(
                sprintf('The %s must be in UTC format Y-m-d H:i:s.', $fieldName)
            );
        }

        return $parsed;
    }

    /**
     * Returns the current UTC timestamp in membership storage format.
     *
     * @return string The current UTC timestamp.
     */
    private function currentUtcTimestampString(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format(self::ACCESS_DATETIME_FORMAT);
    }

    /**
     * Disables a user membership without deleting the record.
     *
     * @param string $userId      The UUID of the user.
     * @param string $userGroupId The UUID of the group.
     * @return bool True when the membership was found and disabled.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function removeMember(string $userId, string $userGroupId): bool
    {
        Enforcers::enforceValidUuid($userId, 'user_id');
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

        $existing = $this->membershipGateway->findByKey($userId, $userGroupId);
        if ($existing === null) {
            return false;
        }

        $accessGranted = is_string($existing['access_granted'] ?? null) ? $existing['access_granted'] : '';
        $accessExpiry = is_string($existing['access_expiry'] ?? null) ? $existing['access_expiry'] : '';

        if ($accessGranted === '' || $accessExpiry === '') {
            throw new InvalidArgumentException('The membership record is missing access timestamps.');
        }

        $accessExpiry = $this->currentUtcTimestampString();

        $this->membershipGateway->update($userId, $userGroupId, $accessGranted, $accessExpiry, false);

        return true;
    }

    /**
     * Returns the configured administrative user-group name.
     *
     * @return string The configured group name, or an empty string when unset.
     */
    public function getAdministrativeGroupName(): string
    {
        $value = getenv('ADMIN_USER_GROUP_NAME');

        if (is_string($value) === false) {
            return '';
        }

        return trim($value);
    }

    /**
     * Returns true when the given group identifier matches the configured
     * administrative user group.
     *
     * @param string $userGroupId The UUID of the group to check.
     * @return bool True when this is the configured administrative group.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function isAdministrativeGroupId(string $userGroupId): bool
    {
        $group = $this->getGroupById($userGroupId);
        if ($group === null) {
            return false;
        }

        $adminGroupName = $this->getAdministrativeGroupName();
        if ($adminGroupName === '') {
            return false;
        }

        $groupName = $group['group_name'] ?? null;

        return is_string($groupName) && $groupName === $adminGroupName;
    }
}
