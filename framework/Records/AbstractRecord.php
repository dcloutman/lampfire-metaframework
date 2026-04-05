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
    abstract public function previous(): ?self;

    abstract public function next(): ?self;

    abstract public function rewind(): ?self;

    abstract public function count(): ?self;
}