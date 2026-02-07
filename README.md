# Slim + Twig project skeleton.
This repository contains a simple project skeleton for PHP applications that provides robust security and little else.

## Configurations
You should create a file at the project root called `.config` and create a file in that directory called `.env`. Use the `env_example` file to populate the `.env` file and then add your own settings.

## Migrations
Migrations are done through SQL.

## Docker
There should be a Docker-based development environment with a MySQL container and a PHP 8.4 container. Debian-based containers are prefered.

## Superusers
Initially, the application will need at least one superuser. Only other superusers can add and remove superuser status from a user. The primary special ability of superusers is to add and remove application admins and to manage the privilege sets of application admins. There should be a cli command to add and remove superuser status from a user.

## Application Admins
Application admins are less powerful that superusers, but they can assign any user to a user group or grant them privileges from a privilege set. They can also create, update, and delete permissions, permission sets, or user groups. Admins can also disable a user. Admins should not be able to modify application admin-specific privileges. 

## Logging
Any changes to the records in the tables defined by `000001-initial-schema.sql` should be logged for auditing purposes.

## LLM Instructions
[AGENTS.md](AGENTS.md)
