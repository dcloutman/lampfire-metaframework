-- Initial schema for the Lampfire application database.
--
-- This is the source of truth for the database schema.
-- MariaDB Docker container runs files from this migrations directory
-- on first container start when the data volume is empty.

USE `lampfire`;

-- The Users table stores authentication credentials. The user_id
-- is a non-sequential GUID to prevent enumeration.
CREATE TABLE `Users` (
    `user_id` CHAR(40) PRIMARY KEY NOT NULL,
    `username` VARCHAR(512) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT="Associates a login name with an internal unique identifier.";

-- Separates PII from authentication data in a 1:1 relationship.
CREATE TABLE `UserData` (
    `user_id` CHAR(40) PRIMARY KEY NOT NULL,
    `email_address` VARCHAR(1024) UNIQUE NOT NULL,
    `first_name` VARCHAR(1024),
    `last_name` VARCHAR(1024),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `FK1_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `Users`(`user_id`)
) COMMENT="Stores user contact information separately from login credentials.";

-- User groups are collections of users that can be granted permissions.
CREATE TABLE `UserGroups` (
    `user_group_id` CHAR(40) PRIMARY KEY NOT NULL,
    `group_name` VARCHAR(256),
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT="Master records for user groups.";

-- Associates users with user groups in a many-to-many relationship.
CREATE TABLE `UserGroupMemberships` (
    `user_id` CHAR(40),
    `user_group_id` CHAR(40),
    `access_granted` DATETIME NOT NULL,
    `access_expiry` DATETIME NOT NULL,
    `has_access` BOOLEAN NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `user_group_id`),
    CONSTRAINT `FK2_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `Users`(`user_id`),
    CONSTRAINT `FK1_user_group`
        FOREIGN KEY (`user_group_id`)
        REFERENCES `UserGroups`(`user_group_id`)
) COMMENT="Associates users with user groups.";

-- A permission is a programmatic token that controls application behavior.
CREATE TABLE `Permissions` (
    `permission_id` CHAR(40) PRIMARY KEY NOT NULL,
    `permission_token` VARCHAR(256) NOT NULL,
    `permission_title` VARCHAR(256) NOT NULL,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT="Defines permission tokens used to control application behavior.";

-- Permission sets group related permissions for easier assignment.
CREATE TABLE `PermissionSets` (
    `permission_set_id` CHAR(40) PRIMARY KEY NOT NULL,
    `permission_set_token` VARCHAR(256) NOT NULL,
    `title` VARCHAR(256) NOT NULL,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT="Groups related permissions into named sets.";

-- Associates permissions with permission sets.
CREATE TABLE `PermissionSetPermissions` (
    `permission_set_id` CHAR(40) NOT NULL,
    `permission_id` CHAR(40) NOT NULL,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`permission_set_id`, `permission_id`),
    CONSTRAINT `FK1_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `Permissions`(`permission_id`),
    CONSTRAINT `FK3_permission_set`
        FOREIGN KEY (`permission_set_id`) REFERENCES `PermissionSets`(`permission_set_id`)
) COMMENT="Associates individual permissions with permission sets.";

-- Associates individual users with permission sets.
CREATE TABLE `PermissionSetMembers` (
    `permission_set_id` CHAR(40) NOT NULL,
    `user_id` CHAR(40) NOT NULL,
    `access_granted` DATETIME NOT NULL,
    `access_expiry` DATETIME NOT NULL,
    `has_access` BOOLEAN NOT NULL DEFAULT 0,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`permission_set_id`, `user_id`),
    CONSTRAINT `FK3_user`
        FOREIGN KEY (`user_id`) REFERENCES `Users`(`user_id`),
    CONSTRAINT `FK1_permission_set`
        FOREIGN KEY (`permission_set_id`) REFERENCES `PermissionSets`(`permission_set_id`)
) COMMENT="Associates individual users with permission sets.";

-- Associates user groups with permission sets.
CREATE TABLE `PermissionSetGroupMembers` (
    `user_group_id` CHAR(40) NOT NULL,
    `permission_set_id` CHAR(40) NOT NULL,
    `access_granted` DATETIME NOT NULL,
    `access_expiry` DATETIME NOT NULL,
    `has_access` BOOLEAN NOT NULL DEFAULT 0,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_group_id`, `permission_set_id`),
    CONSTRAINT `FK2_user_group`
        FOREIGN KEY (`user_group_id`) REFERENCES `UserGroups` (`user_group_id`),
    CONSTRAINT `FK2_permission_set`
        FOREIGN KEY (`permission_set_id`) REFERENCES `PermissionSets` (`permission_set_id`)
) COMMENT="Associates user groups with permission sets.";
