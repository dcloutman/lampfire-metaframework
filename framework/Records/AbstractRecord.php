<?php

declare(strict_types=1);

namespace Lampfire\Records;

/**
 * AbstractRecord serves as a base class for all record objects in the application.
 *
 * This class provides common functionality for data records, including property
 * management and serialization capabilities. Subclasses should define their own
 * properties and implement domain-specific logic.
 */
abstract class AbstractRecord
{
    /**
     * Get the previous record from the result set.
     *
     * @return self|null
     */
    abstract public function previous(): ?self;

    /**
     * Get the next record from the result set.
     *
     * @return self|null
     */
    abstract public function next(): ?self;

    /**
     * Rewind the result set to the first record.
     *
     * @return self|null
     */
    abstract public function rewind(): ?self;

    /**
     * Get the count of records in the result set.
     *
     * @return int
     */
    abstract public function count(): int;

    /**
     * Create a new record with the given data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    abstract public function create(array $data): self;

    /**
    * Update the current record with the given data.
    *
    * @param array<string, mixed> $data
    * @return self
    */
    abstract public function update(array $data): self;

    /**
     * Delete the current record.
     *
     * @return bool
     */
    abstract public function delete(): bool;
}
