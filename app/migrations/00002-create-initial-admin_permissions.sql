USE `lampfire_auth`;

START TRANSACTION;

SET @now := UTC_TIMESTAMP();
SET @access_expiry := DATE_ADD(@now, INTERVAL 100 YEAR);

SET @admin_user_group_name := 'Application Administrators';
SET @admin_permission_set_name := 'Admin Permissions';
SET @admin_permission_set_description := 'Full administrative privileges.';
SET @admin_permission_set_token := 'ADMIN_PERMISSION_SET_ADMIN';
-- The administrative user groups, permissions, and permissions sets will be defined in this file.
-- Run `php cli.php init` to initialize the database with this schema.
INSERT INTO `UserGroups` (

	`user_group_id`,
