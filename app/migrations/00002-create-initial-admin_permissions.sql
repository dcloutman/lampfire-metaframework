USE `lampfire_auth`;

START TRANSACTION;

SET @now := UTC_TIMESTAMP();
SET @access_expiry := DATE_ADD(@now, INTERVAL 100 YEAR);

SET @admin_user_group_name := 'Application Administrators';
SET @admin_permission_set_name := 'Admin Permissions';
SET @admin_permission_set_description := 'Full administrative privileges.';
SET @admin_permission_set_token := 'ADMIN_PERMISSION_SET_ADMIN';

SET @administrators_user_group_id := UUID();
INSERT INTO `UserGroups` (
	`user_group_id`,
	`group_name`,
	`description`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_user_group_id,
	@admin_user_group_name,
	'System administrators with unrestricted access to management features.',
	@now,
	@now
);


SET @administrators_permission_set_id = UUID();
INSERT INTO `PermissionSets` (
	`permission_set_id`,
	`permission_set_token`,
	`title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@admin_permission_set_token,
	@admin_permission_set_name,
	@admin_permission_set_description,
	@now,
	@now
);

SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_CREATE',
	'Create Users',
	'Allows creating new user accounts.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_READ',
	'Read Users',
	'Allows reading user account records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_UPDATE',
	'Update Users',
	'Allows updating user account information.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_DELETE',
	'Delete Users',
	'Allows deleting user accounts.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_PASSWORD_RESET',
	'Reset User Passwords',
	'Allows issuing password resets for user accounts.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_CREATE',
	'Create User Groups',
	'Allows creating new user groups.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_READ',
	'Read User Groups',
	'Allows reading user group records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_UPDATE',
	'Update User Groups',
	'Allows updating user group information.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_DELETE',
	'Delete User Groups',
	'Allows deleting user groups.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_CREATE',
	'Create User Group Memberships',
	'Allows adding users to user groups.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_READ',
	'Read User Group Memberships',
	'Allows reading user group membership records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_UPDATE',
	'Update User Group Memberships',
	'Allows modifying user group membership access status.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_DELETE',
	'Delete User Group Memberships',
	'Allows removing users from user groups.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_CREATE',
	'Create Permissions',
	'Allows creating new permission records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_READ',
	'Read Permissions',
	'Allows reading permission records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_UPDATE',
	'Update Permissions',
	'Allows updating permission records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_DELETE',
	'Delete Permissions',
	'Allows deleting permission records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_CREATE',
	'Create Permission Sets',
	'Allows creating new permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_READ',
	'Read Permission Sets',
	'Allows reading permission set records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_UPDATE',
	'Update Permission Sets',
	'Allows updating permission set records.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_DELETE',
	'Delete Permission Sets',
	'Allows deleting permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_PERMISSION_CREATE',
	'Add Permissions to Permission Sets',
	'Allows associating permissions with permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_PERMISSION_READ',
	'Read Permission Set Permissions',
	'Allows reading permission associations within permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_PERMISSION_DELETE',
	'Remove Permissions from Permission Sets',
	'Allows removing permissions from permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_CREATE',
	'Add Users to Permission Sets',
	'Allows assigning users directly to permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_READ',
	'Read Permission Set Members',
	'Allows reading user assignments within permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_DELETE',
	'Remove Users from Permission Sets',
	'Allows removing users from permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_CREATE',
	'Assign User Groups to Permission Sets',
	'Allows assigning user groups to permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_READ',
	'Read Permission Set User Group Assignments',
	'Allows reading user group assignments within permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);
SET @permission_id = UUID();
INSERT INTO `Permissions` (
	`permission_id`,
	`permission_token`,
	`permission_title`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@permission_id,
	'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_DELETE',
	'Remove User Groups from Permission Sets',
	'Allows removing user groups from permission sets.',
	@now,
	@now
);

INSERT INTO `PermissionSetPermissions` (
	`permission_set_id`,
	`permission_id`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_permission_set_id,
	@permission_id,
	'Automatically granted to the administrators permission set.',
	@now,
	@now
);

INSERT INTO `PermissionSetUserGroups` (
	`user_group_id`,
	`permission_set_id`,
	`access_granted`,
	`access_expiry`,
	`has_access`,
	`notes`,
	`created_at`,
	`updated_at`
) VALUES (
	@administrators_user_group_id,
	@administrators_permission_set_id,
	@now,
	@access_expiry,
	1,
	'Administrators group receives administrative permission set.',
	@now,
	@now
);

COMMIT;

SELECT
	@administrators_user_group_id AS administrators_group_id,
	@administrators_permission_set_id AS administrators_privilege_set_id;

