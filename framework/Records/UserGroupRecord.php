<?php
declare(strict_types=1);

namespace Lampfire\Records;

use Lampfire\Database\DatabaseConnection;

/**
 * UserGroupRecord represents a user group entity in the database.
 *
 */
class UserGroupRecord extends AbstractDatabaseRecord
{
    protected array $primaryKeys = ['user_group_id'];
    protected array $fields = [
        'user_group_id',
        'group_name',
        'enabled',
        'description',
        'created_at',
        'updated_at',
    ];
    protected array $hiddenFields =[];
    protected array $readOnlyFields = [
        'user_group_id',
        'created_at',
        'updated_at',
    ];

    use DatabaseRecordConstructorTrait;
}
