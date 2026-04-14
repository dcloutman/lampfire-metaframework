<?php
declare(strict_types=1);

namespace Lampfire\Records;

/**
 * UserGroupMembershipRecord represents the membership of a user in a user group.
 *
 */
class UserGroupMembershipRecord extends AbstractDatabaseRecord
{
    protected array $primaryKeys = ['user_id', 'user_group_id'];
    protected array $fields = [
        'user_id',
        'user_group_id',
        'access_granted',
        'access_expiry',
        'has_access',
        'created_at',
        'updated_at',
    ];
    protected array $hiddenFields =[];
    protected array $readOnlyFields = [
        'user_id',
        'user_group_id',
        'created_at',
        'updated_at',
    ];

    use DatabaseRecordConstructorTrait;
}