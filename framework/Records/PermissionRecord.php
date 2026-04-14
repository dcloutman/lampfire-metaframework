<?php
declare(strict_types=1);

namespace Lampfire\Records;

/**
 * PermissionRecord represents a permission entity in the database.
 */
class PermissionRecord extends AbstractDatabaseRecord
{
	use DatabaseRecordConstructorTrait;

	protected ?string $query = null;
	protected array $primaryKeys = ['permission_id'];
	protected array $fields = [
		'permission_id',
		'permission_token',
		'permission_title',
		'notes',
		'created_at',
		'updated_at',
	];

	protected array $hiddenFields = [];
	protected array $readOnlyFields = [
		'created_at',
		'updated_at',
	];
}
