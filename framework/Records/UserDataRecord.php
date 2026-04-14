<?php

declare(strict_types=1);

namespace Lampfire\Records;

/**
 * UserDataRecord represents a user profile entity in the UserData table.
 */
class UserDataRecord extends AbstractDatabaseRecord
{
    use DatabaseRecordConstructorTrait;

    protected ?string $query = null;
    protected array $primaryKeys = ['user_id'];
    protected array $fields = [
        'user_id',
        'first_name',
        'last_name',
        'email_address',
        'created_at',
        'updated_at',
    ];
    protected array $hiddenFields = [];
    protected array $readOnlyFields = [
        'created_at',
        'updated_at',
    ];

    protected function resolveTableName(): string
    {
        return 'UserData';
    }
}
