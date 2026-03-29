<?php
/**
 * This is the entry point for the command line interface (`cli`) tool. Use
 * the `/cli` directory to store code specific to the `cli` tool.
 */

declare(strict_types=1);

namespace Cli;

// We need to autoload both the `cli` and `app` dependencies.
require_once __DIR__ . '/cli/vendor/autoload.php';
require_once __DIR__ . '/app/vendor/autoload.php';

use Throwable;

try {
    $application = ApplicationFactory::create();
    exit($application->run($argv));
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf("CLI bootstrap error: %s\n", $throwable->getMessage()));
    exit(1);
}

