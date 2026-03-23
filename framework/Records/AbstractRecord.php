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
     * Returns an array representation of the record.
     *
     * Subclasses should override this method to include all relevant properties
     * in the returned array.
     */
    abstract public function toArray(): array;
}