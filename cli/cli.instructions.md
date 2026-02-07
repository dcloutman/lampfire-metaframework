# Command Line Interface (CLI) Instructions
---
description: This file describes the coding of the command line interface.
applyTo: **/cli/*.php
---
Structure my PHP cli code using a similar approach to Python's Click library. 
- `cli.php` should have subcommands, similar to Click's group commands.
- Each subcommand is a separate class and the CLI entry point simply dispatches to the appropriate command class.
- Each subcommand should have a `--help` option that displays usage information.
- Follow PSR-12 coding standards.
- Make use on the web app's gateways and services for data access and business logic.
- Ensure proper error handling and user feedback for CLI commands.
