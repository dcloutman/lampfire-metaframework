<?php

declare(strict_types=1);

namespace Lampfire\Records;

/**
 * UserRecord represents a user entity in the Users table.
 */
class UserRecord extends AbstractDatabaseRecord
{
    use DatabaseRecordConstructorTrait;

    protected ?string $query = null;
    protected array $primaryKeys = ['user_id'];
    protected array $fields = [
        'user_id',
        'username',
        'password_hash',
        'enabled',
        'created_at',
        'updated_at',
    ];
    protected array $hiddenFields = ['password_hash'];
    protected array $readOnlyFields = [
        'created_at',
        'updated_at',
    ];
}
