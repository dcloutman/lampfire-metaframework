---
description: This file describes the coding of the command line interface.
applyTo: **/cli/*.php
---
# Command Line Interface (CLI) Instructions
`cli.php` is a utility for developers to manage the framework and applicaiton from the command line. Treat it as framework code but use the `cli` directory and the `Cli\` namespace for all code specific to the CLI.
- Structure my PHP cli code using a similar approach to Python's Click library. 
- `cli.php` should have subcommands, similar to Click's group commands.
- Each subcommand is a separate class and the CLI entry point simply dispatches to the appropriate command class.
- Each subcommand should have a `--help` option that displays usage information.
- Follow PSR-12 coding standards.
- Make use on the web app's gateways and services for data access and business logic.
- Ensure proper error handling and user feedback for CLI commands.
