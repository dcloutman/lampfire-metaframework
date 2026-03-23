# Command Line Client
This code provides a command line for the project skeleton.

The root entry point is `cli.php`, which bootstraps a Symfony Console application and dispatches subcommands from classes under `cli`.

## Current Subcommands
- `init` initializes the application database and runs migrations.

## Help
- `php cli.php --help` displays top-level usage.
- `php cli.php init --help` displays command-specific usage.

