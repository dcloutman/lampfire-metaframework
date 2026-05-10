/**
 * Admin panel client-side behaviour.
 * Compiled to /js/admin.js.
 */

type ApiData<T> = {
    data: T;
};

type Permission = {
    permission_id: string;
    permission_token: string;
    permission_title: string;
    notes: string | null;
};

type User = {
    user_id: string;
    username: string;
};

type UserGroup = {
    user_group_id: string;
    group_name: string;
    description: string | null;
};

type PermissionSet = {
    permission_set_id: string;
    permission_set_token: string;
    title: string;
    notes: string | null;
};

type PermissionSetPermission = {
    permission_set_id: string;
    permission_id: string;
    notes: string | null;
};

type PermissionSetMember = {
    permission_set_id: string;
    user_id: string;
    access_granted: string;
    access_expiry: string;
    has_access: boolean;
    notes: string | null;
};

type PermissionSetUserGroup = {
    user_group_id: string;
    permission_set_id: string;
    access_granted: string;
    access_expiry: string;
    has_access: boolean;
    notes: string | null;
};

type AccessControlState = {
    users: User[];
    permissions: Permission[];
    userGroups: UserGroup[];
    permissionSets: PermissionSet[];
    setPermissions: PermissionSetPermission[];
    setMembers: PermissionSetMember[];
    assignments: PermissionSetUserGroup[];
};

type AccessControlElements = {
    root: HTMLElement;
    csrfToken: string;
    activeSection: string;
    adminGroupName: string;
    adminPermissionTokenPrefix: string;
    message: HTMLElement;
    permissionsBody: HTMLTableSectionElement;
    userGroupsBody: HTMLTableSectionElement;
    permissionSetsBody: HTMLTableSectionElement;
    setPermissionsBody: HTMLTableSectionElement;
    setMembersBody: HTMLTableSectionElement;
    assignmentsBody: HTMLTableSectionElement;
    permissionForm: HTMLFormElement;
    userGroupForm: HTMLFormElement;
    permissionSetForm: HTMLFormElement;
    setPermissionForm: HTMLFormElement;
    setMemberForm: HTMLFormElement;
    assignmentForm: HTMLFormElement;
    permissionSubmit: HTMLButtonElement;
    userGroupSubmit: HTMLButtonElement;
    permissionSetSubmit: HTMLButtonElement;
    setPermissionSubmit: HTMLButtonElement;
    setMemberSubmit: HTMLButtonElement;
    assignmentSubmit: HTMLButtonElement;
    permissionCancel: HTMLButtonElement;
    userGroupCancel: HTMLButtonElement;
    permissionSetCancel: HTMLButtonElement;
    setPermissionCancel: HTMLButtonElement;
    setMemberCancel: HTMLButtonElement;
    assignmentCancel: HTMLButtonElement;
    setPermissionPermissionSetSelect: HTMLSelectElement;
    setPermissionPermissionSelect: HTMLSelectElement;
    setMemberPermissionSetSelect: HTMLSelectElement;
    setMemberUserSelect: HTMLSelectElement;
    setMemberAccessGrantedInput: HTMLInputElement;
    setMemberAccessExpiryInput: HTMLInputElement;
    assignmentUserGroupSelect: HTMLSelectElement;
    assignmentPermissionSetSelect: HTMLSelectElement;
    assignmentAccessGrantedInput: HTMLInputElement;
    assignmentAccessExpiryInput: HTMLInputElement;
    initialUserGroupId: string;
    hasAppliedInitialAssignmentContext: boolean;
};

/** Attach confirmation prompts to forms that carry a data-confirm attribute. */
function initConfirmForms(): void {
    const forms: NodeListOf<HTMLFormElement> = document.querySelectorAll("form[data-confirm]");

    forms.forEach((form: HTMLFormElement): void => {
        form.addEventListener("submit", (event: Event): void => {
            const message: string | null = form.getAttribute("data-confirm");
            if (typeof message !== "string" || message.length === 0) {
                return;
            }

            const confirmed: boolean = window.confirm(message);
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
}

/**
 * Initialises API-backed forms on the access-control admin page.
 */
function initAccessControlPage(): void {
    const root: HTMLElement | null = document.querySelector("[data-access-control-root]");
    if (root === null) {
        return;
    }

    const message = document.getElementById("access-control-message");
    const permissionsBody = document.querySelector<HTMLTableSectionElement>("#permissions-table tbody");
    const userGroupsBody = document.querySelector<HTMLTableSectionElement>("#user-groups-table tbody");
    const permissionSetsBody = document.querySelector<HTMLTableSectionElement>("#permission-sets-table tbody");
    const setPermissionsBody = document.querySelector<HTMLTableSectionElement>("#set-permissions-table tbody");
    const setMembersBody = document.querySelector<HTMLTableSectionElement>("#set-members-table tbody");
    const assignmentsBody = document.querySelector<HTMLTableSectionElement>("#assignments-table tbody");

    const permissionForm = document.getElementById("permission-form") as HTMLFormElement | null;
    const userGroupForm = document.getElementById("user-group-form") as HTMLFormElement | null;
    const permissionSetForm = document.getElementById("permission-set-form") as HTMLFormElement | null;
    const setPermissionForm = document.getElementById("set-permission-form") as HTMLFormElement | null;
    const setMemberForm = document.getElementById("set-member-form") as HTMLFormElement | null;
    const assignmentForm = document.getElementById("assignment-form") as HTMLFormElement | null;

    const permissionSubmit = document.getElementById("permission-submit") as HTMLButtonElement | null;
    const userGroupSubmit = document.getElementById("user-group-submit") as HTMLButtonElement | null;
    const permissionSetSubmit = document.getElementById("permission-set-submit") as HTMLButtonElement | null;
    const setPermissionSubmit = document.getElementById("set-permission-submit") as HTMLButtonElement | null;
    const setMemberSubmit = document.getElementById("set-member-submit") as HTMLButtonElement | null;
    const assignmentSubmit = document.getElementById("assignment-submit") as HTMLButtonElement | null;

    const permissionCancel = document.getElementById("permission-cancel") as HTMLButtonElement | null;
    const userGroupCancel = document.getElementById("user-group-cancel") as HTMLButtonElement | null;
    const permissionSetCancel = document.getElementById("permission-set-cancel") as HTMLButtonElement | null;
    const setPermissionCancel = document.getElementById("set-permission-cancel") as HTMLButtonElement | null;
    const setMemberCancel = document.getElementById("set-member-cancel") as HTMLButtonElement | null;
    const assignmentCancel = document.getElementById("assignment-cancel") as HTMLButtonElement | null;

    const setPermissionPermissionSetSelect = document.getElementById("set_permission_permission_set_id") as HTMLSelectElement | null;
    const setPermissionPermissionSelect = document.getElementById("set_permission_permission_id") as HTMLSelectElement | null;
    const setMemberPermissionSetSelect = document.getElementById("set_member_permission_set_id") as HTMLSelectElement | null;
    const setMemberUserSelect = document.getElementById("set_member_user_id") as HTMLSelectElement | null;
    const setMemberAccessGrantedInput = document.getElementById("set_member_access_granted") as HTMLInputElement | null;
    const setMemberAccessExpiryInput = document.getElementById("set_member_access_expiry") as HTMLInputElement | null;

    const assignmentUserGroupSelect = document.getElementById("assignment_user_group_id") as HTMLSelectElement | null;
    const assignmentPermissionSetSelect = document.getElementById("assignment_permission_set_id") as HTMLSelectElement | null;
    const assignmentAccessGrantedInput = document.getElementById("assignment_access_granted") as HTMLInputElement | null;
    const assignmentAccessExpiryInput = document.getElementById("assignment_access_expiry") as HTMLInputElement | null;

    if (
        message === null
        || permissionsBody === null
        || userGroupsBody === null
        || permissionSetsBody === null
        || setPermissionsBody === null
        || setMembersBody === null
        || assignmentsBody === null
        || permissionForm === null
        || userGroupForm === null
        || permissionSetForm === null
        || setPermissionForm === null
        || setMemberForm === null
        || assignmentForm === null
        || permissionSubmit === null
        || userGroupSubmit === null
        || permissionSetSubmit === null
        || setPermissionSubmit === null
        || setMemberSubmit === null
        || assignmentSubmit === null
        || permissionCancel === null
        || userGroupCancel === null
        || permissionSetCancel === null
        || setPermissionCancel === null
        || setMemberCancel === null
        || assignmentCancel === null
        || setPermissionPermissionSetSelect === null
        || setPermissionPermissionSelect === null
        || setMemberPermissionSetSelect === null
        || setMemberUserSelect === null
        || setMemberAccessGrantedInput === null
        || setMemberAccessExpiryInput === null
        || assignmentUserGroupSelect === null
        || assignmentPermissionSetSelect === null
        || assignmentAccessGrantedInput === null
        || assignmentAccessExpiryInput === null
    ) {
        return;
    }

    const elements: AccessControlElements = {
        root,
        csrfToken: root.dataset.csrfToken ?? "",
        activeSection: root.dataset.activeSection ?? "permissions",
        adminGroupName: root.dataset.adminGroupName ?? "",
        adminPermissionTokenPrefix: root.dataset.adminPermissionTokenPrefix ?? "",
        message,
        permissionsBody,
        userGroupsBody,
        permissionSetsBody,
        setPermissionsBody,
        setMembersBody,
        assignmentsBody,
        permissionForm,
        userGroupForm,
        permissionSetForm,
        setPermissionForm,
        setMemberForm,
        assignmentForm,
        permissionSubmit,
        userGroupSubmit,
        permissionSetSubmit,
        setPermissionSubmit,
        setMemberSubmit,
        assignmentSubmit,
        permissionCancel,
        userGroupCancel,
        permissionSetCancel,
        setPermissionCancel,
        setMemberCancel,
        assignmentCancel,
        setPermissionPermissionSetSelect,
        setPermissionPermissionSelect,
        setMemberPermissionSetSelect,
        setMemberUserSelect,
        setMemberAccessGrantedInput,
        setMemberAccessExpiryInput,
        assignmentUserGroupSelect,
        assignmentPermissionSetSelect,
        assignmentAccessGrantedInput,
        assignmentAccessExpiryInput,
        initialUserGroupId: new URLSearchParams(window.location.search).get("user_group_id") ?? "",
        hasAppliedInitialAssignmentContext: false,
    };

    const state: AccessControlState = {
        users: [],
        permissions: [],
        userGroups: [],
        permissionSets: [],
        setPermissions: [],
        setMembers: [],
        assignments: [],
    };

    permissionForm.addEventListener("submit", async (event: Event): Promise<void> => {
        event.preventDefault();
        await submitPermissionForm(elements, state);
    });

    userGroupForm.addEventListener("submit", async (event: Event): Promise<void> => {
        event.preventDefault();
        await submitUserGroupForm(elements, state);
    });

    permissionSetForm.addEventListener("submit", async (event: Event): Promise<void> => {
        event.preventDefault();
        await submitPermissionSetForm(elements, state);
    });

    setPermissionForm.addEventListener("submit", async (event: Event): Promise<void> => {
        event.preventDefault();
        await submitSetPermissionForm(elements, state);
    });

    setMemberForm.addEventListener("submit", async (event: Event): Promise<void> => {
        event.preventDefault();
        await submitSetMemberForm(elements, state);
    });

    assignmentForm.addEventListener("submit", async (event: Event): Promise<void> => {
        event.preventDefault();
        await submitAssignmentForm(elements, state);
    });

    permissionCancel.addEventListener("click", (): void => {
        resetPermissionForm(elements.permissionForm, elements.permissionSubmit, elements.permissionCancel);
    });

    userGroupCancel.addEventListener("click", (): void => {
        resetUserGroupForm(elements.userGroupForm, elements.userGroupSubmit, elements.userGroupCancel);
    });

    permissionSetCancel.addEventListener("click", (): void => {
        resetPermissionSetForm(elements.permissionSetForm, elements.permissionSetSubmit, elements.permissionSetCancel);
    });

    setPermissionCancel.addEventListener("click", (): void => {
        resetSetPermissionForm(elements);
    });

    setMemberCancel.addEventListener("click", (): void => {
        resetSetMemberForm(elements);
    });

    assignmentCancel.addEventListener("click", (): void => {
        resetAssignmentForm(elements);
    });

    elements.permissionsBody.addEventListener("click", async (event: Event): Promise<void> => {
        const target = event.target as HTMLElement;
        const action = target.getAttribute("data-action");
        const permissionId = target.getAttribute("data-id");

        if (action === null || permissionId === null) {
            return;
        }

        if (action === "edit") {
            const permission = state.permissions.find((item: Permission): boolean => item.permission_id === permissionId);
            if (permission !== undefined) {
                if (isImmutableAdministrativePermission(permission, elements.adminPermissionTokenPrefix)) {
                    showError(elements.message, "Framework administrative permissions are immutable and cannot be modified.");
                    return;
                }

                editPermission(elements, permission);
            }
            return;
        }

        if (action === "delete") {
            const permission = state.permissions.find((item: Permission): boolean => item.permission_id === permissionId);
            if (permission !== undefined && isImmutableAdministrativePermission(permission, elements.adminPermissionTokenPrefix)) {
                showError(elements.message, "Framework administrative permissions are immutable and cannot be deleted.");
                return;
            }

            if (window.confirm("Delete this permission?") === false) {
                return;
            }

            await deletePermission(elements, state, permissionId);
        }
    });

    elements.userGroupsBody.addEventListener("click", async (event: Event): Promise<void> => {
        const target = event.target as HTMLElement;
        const action = target.getAttribute("data-action");
        const userGroupId = target.getAttribute("data-id");

        if (action === null || userGroupId === null) {
            return;
        }

        if (action === "edit") {
            const group = state.userGroups.find((item: UserGroup): boolean => item.user_group_id === userGroupId);
            if (group !== undefined) {
                editUserGroup(elements, group);
            }
            return;
        }

        if (action === "delete") {
            const group = state.userGroups.find((item: UserGroup): boolean => item.user_group_id === userGroupId);
            if (group !== undefined && isProtectedAdminUserGroup(group, elements.adminGroupName)) {
                showError(elements.message, "The administrative user group cannot be deleted.");
                return;
            }

            if (window.confirm("Delete this user group?") === false) {
                return;
            }

            await deleteUserGroup(elements, state, userGroupId);
        }
    });

    elements.permissionSetsBody.addEventListener("click", async (event: Event): Promise<void> => {
        const target = event.target as HTMLElement;
        const action = target.getAttribute("data-action");
        const permissionSetId = target.getAttribute("data-id");

        if (action === null || permissionSetId === null) {
            return;
        }

        if (action === "edit") {
            const set = state.permissionSets.find((item: PermissionSet): boolean => item.permission_set_id === permissionSetId);
            if (set !== undefined) {
                editPermissionSet(elements, set);
            }
            return;
        }

        if (action === "delete") {
            if (window.confirm("Delete this permission set?") === false) {
                return;
            }

            await deletePermissionSet(elements, state, permissionSetId);
        }
    });

    elements.setPermissionsBody.addEventListener("click", async (event: Event): Promise<void> => {
        const target = event.target as HTMLElement;
        const action = target.getAttribute("data-action");
        const permissionSetId = target.getAttribute("data-permission-set-id");
        const permissionId = target.getAttribute("data-permission-id");

        if (action === null || permissionSetId === null || permissionId === null) {
            return;
        }

        if (action === "edit") {
            const setPermission = state.setPermissions.find((item: PermissionSetPermission): boolean => {
                return item.permission_set_id === permissionSetId && item.permission_id === permissionId;
            });

            if (setPermission !== undefined) {
                editSetPermission(elements, setPermission);
            }
            return;
        }

        if (action === "delete") {
            if (window.confirm("Remove this permission from the permission set?") === false) {
                return;
            }

            await deleteSetPermission(elements, state, permissionSetId, permissionId);
        }
    });

    elements.setMembersBody.addEventListener("click", async (event: Event): Promise<void> => {
        const target = event.target as HTMLElement;
        const action = target.getAttribute("data-action");
        const permissionSetId = target.getAttribute("data-permission-set-id");
        const userId = target.getAttribute("data-user-id");

        if (action === null || permissionSetId === null || userId === null) {
            return;
        }

        if (action === "edit") {
            const setMember = state.setMembers.find((item: PermissionSetMember): boolean => {
                return item.permission_set_id === permissionSetId && item.user_id === userId;
            });

            if (setMember !== undefined) {
                editSetMember(elements, setMember);
            }
            return;
        }

        if (action === "delete") {
            if (window.confirm("Remove this user from the permission set?") === false) {
                return;
            }

            await deleteSetMember(elements, state, permissionSetId, userId);
        }
    });

    elements.assignmentsBody.addEventListener("click", async (event: Event): Promise<void> => {
        const target = event.target as HTMLElement;
        const action = target.getAttribute("data-action");
        const userGroupId = target.getAttribute("data-user-group-id");
        const permissionSetId = target.getAttribute("data-permission-set-id");

        if (action === null || userGroupId === null || permissionSetId === null) {
            return;
        }

        if (action === "edit") {
            const assignment = state.assignments.find((item: PermissionSetUserGroup): boolean => {
                return item.user_group_id === userGroupId && item.permission_set_id === permissionSetId;
            });

            if (assignment !== undefined) {
                editAssignment(elements, assignment);
            }
            return;
        }

        if (action === "delete") {
            if (window.confirm("Delete this user-group assignment?") === false) {
                return;
            }

            await deleteAssignment(elements, state, userGroupId, permissionSetId);
        }
    });

    const defaults = getDefaultAccessWindow();
    elements.setMemberAccessGrantedInput.value = defaults.accessGranted;
    elements.setMemberAccessExpiryInput.value = defaults.accessExpiry;
    elements.assignmentAccessGrantedInput.value = defaults.accessGranted;
    elements.assignmentAccessExpiryInput.value = defaults.accessExpiry;

    void refreshAccessControlData(elements, state);
}

/**
 * Reloads all access-control resources from the API.
 */
async function refreshAccessControlData(elements: AccessControlElements, state: AccessControlState): Promise<void> {
    try {
        clearMessage(elements.message);

        const [permissionsResponse, userGroupsResponse, permissionSetsResponse] = await Promise.all([
            apiRequest<ApiData<Permission[]>>("/api/permissions", "GET", null, elements.csrfToken),
            apiRequest<ApiData<UserGroup[]>>("/api/user-groups", "GET", null, elements.csrfToken),
            apiRequest<ApiData<PermissionSet[]>>("/api/permission-sets", "GET", null, elements.csrfToken),
        ]);

        state.permissions = permissionsResponse.data;
        state.userGroups = userGroupsResponse.data;
        state.permissionSets = permissionSetsResponse.data;
        state.setPermissions = await fetchSetPermissions(state.permissionSets, elements.csrfToken);

        if (elements.activeSection === "set-members") {
            const usersResponse = await apiRequest<ApiData<User[]>>("/api/users", "GET", null, elements.csrfToken);
            state.users = usersResponse.data;
            state.setMembers = await fetchSetMembers(state.permissionSets, elements.csrfToken);
        } else {
            state.users = [];
            state.setMembers = [];
        }

        state.assignments = await fetchAssignments(state.permissionSets, elements.csrfToken);

        renderPermissions(elements.permissionsBody, state.permissions, elements.adminPermissionTokenPrefix);
        renderUserGroups(elements.userGroupsBody, state.userGroups, elements.adminGroupName);
        renderPermissionSets(elements.permissionSetsBody, state.permissionSets);
        renderSetPermissions(elements.setPermissionsBody, state.setPermissions, state.permissions, state.permissionSets);

        if (elements.activeSection === "set-members") {
            renderSetMembers(elements.setMembersBody, state.setMembers, state.users, state.permissionSets);
            renderSetMemberSelects(elements, state.users, state.permissionSets);
        }

        renderAssignments(elements.assignmentsBody, state.assignments, state.userGroups, state.permissionSets);
        renderSetPermissionSelects(elements, state.permissions, state.permissionSets);
        renderAssignmentSelects(elements, state.userGroups, state.permissionSets);

        if (
            elements.activeSection === "set-group-assignments"
            && elements.hasAppliedInitialAssignmentContext === false
            && elements.initialUserGroupId !== ""
        ) {
            const groupExists = state.userGroups.some((group: UserGroup): boolean => {
                return group.user_group_id === elements.initialUserGroupId;
            });

            if (groupExists) {
                elements.assignmentUserGroupSelect.value = elements.initialUserGroupId;
            } else {
                showError(elements.message, "The selected user group was not found.");
            }

            elements.hasAppliedInitialAssignmentContext = true;
        }
    } catch (error: unknown) {
        showError(elements.message, resolveErrorMessage(error));
    }
}

/**
 * Loads all permission-set user-group assignments by iterating each set.
 */
async function fetchAssignments(permissionSets: PermissionSet[], csrfToken: string): Promise<PermissionSetUserGroup[]> {
    const assignmentMap = new Map<string, PermissionSetUserGroup>();

    for (const permissionSet of permissionSets) {
        const response = await apiRequest<ApiData<PermissionSetUserGroup[]>>(
            `/api/permission-set-user-groups?permission_set_id=${encodeURIComponent(permissionSet.permission_set_id)}`,
            "GET",
            null,
            csrfToken
        );

        for (const assignment of response.data) {
            const key = `${assignment.user_group_id}::${assignment.permission_set_id}`;
            assignmentMap.set(key, assignment);
        }
    }

    return Array.from(assignmentMap.values());
}

/**
 * Loads all permission-set permission associations by iterating each set.
 */
async function fetchSetPermissions(permissionSets: PermissionSet[], csrfToken: string): Promise<PermissionSetPermission[]> {
    const setPermissionMap = new Map<string, PermissionSetPermission>();

    for (const permissionSet of permissionSets) {
        const response = await apiRequest<ApiData<PermissionSetPermission[]>>(
            `/api/permission-set-permissions?permission_set_id=${encodeURIComponent(permissionSet.permission_set_id)}`,
            "GET",
            null,
            csrfToken
        );

        for (const setPermission of response.data) {
            const key = `${setPermission.permission_set_id}::${setPermission.permission_id}`;
            setPermissionMap.set(key, setPermission);
        }
    }

    return Array.from(setPermissionMap.values());
}

/**
 * Loads all permission-set user memberships by iterating each set.
 */
async function fetchSetMembers(permissionSets: PermissionSet[], csrfToken: string): Promise<PermissionSetMember[]> {
    const setMemberMap = new Map<string, PermissionSetMember>();

    for (const permissionSet of permissionSets) {
        const response = await apiRequest<ApiData<PermissionSetMember[]>>(
            `/api/permission-set-members?permission_set_id=${encodeURIComponent(permissionSet.permission_set_id)}`,
            "GET",
            null,
            csrfToken
        );

        for (const setMember of response.data) {
            const key = `${setMember.permission_set_id}::${setMember.user_id}`;
            setMemberMap.set(key, setMember);
        }
    }

    return Array.from(setMemberMap.values());
}

/**
 * Sends a create or update request for a permission.
 */
async function submitPermissionForm(elements: AccessControlElements, state: AccessControlState): Promise<void> {
    const permissionId = valueOfInput(elements.permissionForm, "permission_id");
    const permissionToken = valueOfInput(elements.permissionForm, "permission_token");
    const permissionTitle = valueOfInput(elements.permissionForm, "permission_title");
    const notes = optionalValueOfInput(elements.permissionForm, "notes");

    if (permissionToken.length < 2 || permissionTitle.length < 2) {
        showError(elements.message, "Permission token and title are required.");
        return;
    }

    const payload = {
        permission_token: permissionToken,
        permission_title: permissionTitle,
        notes,
    };

    if (permissionId === "") {
        await apiRequest<ApiData<Permission>>("/api/permissions", "POST", payload, elements.csrfToken);
        showSuccess(elements.message, "Permission created.");
    } else {
        await apiRequest<ApiData<Permission>>(`/api/permissions/${encodeURIComponent(permissionId)}`, "PUT", payload, elements.csrfToken);
        showSuccess(elements.message, "Permission updated.");
    }

    resetPermissionForm(elements.permissionForm, elements.permissionSubmit, elements.permissionCancel);
    await refreshAccessControlData(elements, state);
}

/**
 * Sends a create or update request for a user group.
 */
async function submitUserGroupForm(elements: AccessControlElements, state: AccessControlState): Promise<void> {
    const userGroupId = valueOfInput(elements.userGroupForm, "user_group_id");
    const groupName = valueOfInput(elements.userGroupForm, "group_name");
    const description = optionalValueOfInput(elements.userGroupForm, "description");

    if (groupName.length < 2) {
        showError(elements.message, "Group name is required.");
        return;
    }

    const payload = {
        group_name: groupName,
        description,
    };

    if (userGroupId === "") {
        await apiRequest<ApiData<UserGroup>>("/api/user-groups", "POST", payload, elements.csrfToken);
        showSuccess(elements.message, "User group created.");
    } else {
        await apiRequest<ApiData<UserGroup>>(`/api/user-groups/${encodeURIComponent(userGroupId)}`, "PUT", payload, elements.csrfToken);
        showSuccess(elements.message, "User group updated.");
    }

    resetUserGroupForm(elements.userGroupForm, elements.userGroupSubmit, elements.userGroupCancel);
    await refreshAccessControlData(elements, state);
}

/**
 * Sends a create or update request for a permission set.
 */
async function submitPermissionSetForm(elements: AccessControlElements, state: AccessControlState): Promise<void> {
    const permissionSetId = valueOfInput(elements.permissionSetForm, "permission_set_id");
    const permissionSetToken = valueOfInput(elements.permissionSetForm, "permission_set_token");
    const title = valueOfInput(elements.permissionSetForm, "title");
    const notes = optionalValueOfInput(elements.permissionSetForm, "notes");

    if (permissionSetToken.length < 2 || title.length < 2) {
        showError(elements.message, "Permission set token and title are required.");
        return;
    }

    const payload = {
        permission_set_token: permissionSetToken,
        title,
        notes,
    };

    if (permissionSetId === "") {
        await apiRequest<ApiData<PermissionSet>>("/api/permission-sets", "POST", payload, elements.csrfToken);
        showSuccess(elements.message, "Permission set created.");
    } else {
        await apiRequest<ApiData<PermissionSet>>(`/api/permission-sets/${encodeURIComponent(permissionSetId)}`, "PUT", payload, elements.csrfToken);
        showSuccess(elements.message, "Permission set updated.");
    }

    resetPermissionSetForm(elements.permissionSetForm, elements.permissionSetSubmit, elements.permissionSetCancel);
    await refreshAccessControlData(elements, state);
}

/**
 * Sends a create or update request for a permission-set permission association.
 */
async function submitSetPermissionForm(elements: AccessControlElements, state: AccessControlState): Promise<void> {
    const permissionSetId = valueOfInput(elements.setPermissionForm, "permission_set_id");
    const permissionId = valueOfInput(elements.setPermissionForm, "permission_id");
    const notes = optionalValueOfInput(elements.setPermissionForm, "notes");

    const originalPermissionSetId = valueOfInput(elements.setPermissionForm, "set_permission_permission_set_id_original");
    const originalPermissionId = valueOfInput(elements.setPermissionForm, "set_permission_permission_id_original");

    if (permissionSetId === "" || permissionId === "") {
        showError(elements.message, "Permission set and permission are required.");
        return;
    }

    if (originalPermissionSetId === "" || originalPermissionId === "") {
        await apiRequest<ApiData<PermissionSetPermission>>(
            "/api/permission-set-permissions",
            "POST",
            {
                permission_set_id: permissionSetId,
                permission_id: permissionId,
                notes,
            },
            elements.csrfToken
        );

        showSuccess(elements.message, "Permission added to permission set.");
    } else {
        await apiRequest<ApiData<PermissionSetPermission>>(
            `/api/permission-set-permissions/${encodeURIComponent(originalPermissionSetId)}/${encodeURIComponent(originalPermissionId)}`,
            "PUT",
            {
                notes,
            },
            elements.csrfToken
        );

        showSuccess(elements.message, "Permission set association updated.");
    }

    resetSetPermissionForm(elements);
    await refreshAccessControlData(elements, state);
}

/**
 * Sends a create or update request for a permission-set user member association.
 */
async function submitSetMemberForm(elements: AccessControlElements, state: AccessControlState): Promise<void> {
    const permissionSetId = valueOfInput(elements.setMemberForm, "permission_set_id");
    const userId = valueOfInput(elements.setMemberForm, "user_id");
    const accessGranted = valueOfInput(elements.setMemberForm, "access_granted");
    const accessExpiry = valueOfInput(elements.setMemberForm, "access_expiry");
    const hasAccess = valueOfInput(elements.setMemberForm, "has_access") === "1";
    const notes = optionalValueOfInput(elements.setMemberForm, "notes");

    const originalPermissionSetId = valueOfInput(elements.setMemberForm, "set_member_permission_set_id_original");
    const originalUserId = valueOfInput(elements.setMemberForm, "set_member_user_id_original");

    if (permissionSetId === "" || userId === "" || accessGranted === "" || accessExpiry === "") {
        showError(elements.message, "Permission set member fields are required.");
        return;
    }

    if (originalPermissionSetId === "" || originalUserId === "") {
        await apiRequest<ApiData<PermissionSetMember>>(
            "/api/permission-set-members",
            "POST",
            {
                permission_set_id: permissionSetId,
                user_id: userId,
                access_granted: accessGranted,
                access_expiry: accessExpiry,
                has_access: hasAccess,
                notes,
            },
            elements.csrfToken
        );

        showSuccess(elements.message, "User added to permission set.");
    } else {
        await apiRequest<ApiData<PermissionSetMember>>(
            `/api/permission-set-members/${encodeURIComponent(originalPermissionSetId)}/${encodeURIComponent(originalUserId)}`,
            "PUT",
            {
                access_granted: accessGranted,
                access_expiry: accessExpiry,
                has_access: hasAccess,
                notes,
            },
            elements.csrfToken
        );

        showSuccess(elements.message, "Permission set member updated.");
    }

    resetSetMemberForm(elements);
    await refreshAccessControlData(elements, state);
}

/**
 * Sends a create or update request for a user-group permission-set assignment.
 */
async function submitAssignmentForm(elements: AccessControlElements, state: AccessControlState): Promise<void> {
    const userGroupId = valueOfInput(elements.assignmentForm, "user_group_id");
    const permissionSetId = valueOfInput(elements.assignmentForm, "permission_set_id");
    const accessGranted = valueOfInput(elements.assignmentForm, "access_granted");
    const accessExpiry = valueOfInput(elements.assignmentForm, "access_expiry");
    const hasAccess = valueOfInput(elements.assignmentForm, "has_access") === "1";
    const notes = optionalValueOfInput(elements.assignmentForm, "notes");

    const originalUserGroupId = valueOfInput(elements.assignmentForm, "assignment_user_group_id_original");
    const originalPermissionSetId = valueOfInput(elements.assignmentForm, "assignment_permission_set_id_original");

    if (userGroupId === "" || permissionSetId === "" || accessGranted === "" || accessExpiry === "") {
        showError(elements.message, "Assignment fields are required.");
        return;
    }

    const payload = {
        user_group_id: userGroupId,
        permission_set_id: permissionSetId,
        access_granted: accessGranted,
        access_expiry: accessExpiry,
        has_access: hasAccess,
        notes,
    };

    if (originalUserGroupId === "" || originalPermissionSetId === "") {
        await apiRequest<ApiData<PermissionSetUserGroup>>("/api/permission-set-user-groups", "POST", payload, elements.csrfToken);
        showSuccess(elements.message, "Assignment created.");
    } else {
        await apiRequest<ApiData<PermissionSetUserGroup>>(
            `/api/permission-set-user-groups/${encodeURIComponent(originalUserGroupId)}/${encodeURIComponent(originalPermissionSetId)}`,
            "PUT",
            {
                access_granted: accessGranted,
                access_expiry: accessExpiry,
                has_access: hasAccess,
                notes,
            },
            elements.csrfToken
        );

        showSuccess(elements.message, "Assignment updated.");
    }

    resetAssignmentForm(elements);
    await refreshAccessControlData(elements, state);
}

/**
 * Deletes a permission by identifier.
 */
async function deletePermission(elements: AccessControlElements, state: AccessControlState, permissionId: string): Promise<void> {
    await apiRequest<void>(`/api/permissions/${encodeURIComponent(permissionId)}`, "DELETE", null, elements.csrfToken);
    showSuccess(elements.message, "Permission deleted.");
    await refreshAccessControlData(elements, state);
}

/**
 * Deletes a user group by identifier.
 */
async function deleteUserGroup(elements: AccessControlElements, state: AccessControlState, userGroupId: string): Promise<void> {
    await apiRequest<void>(`/api/user-groups/${encodeURIComponent(userGroupId)}`, "DELETE", null, elements.csrfToken);
    showSuccess(elements.message, "User group deleted.");
    await refreshAccessControlData(elements, state);
}

/**
 * Deletes a permission set by identifier.
 */
async function deletePermissionSet(elements: AccessControlElements, state: AccessControlState, permissionSetId: string): Promise<void> {
    await apiRequest<void>(`/api/permission-sets/${encodeURIComponent(permissionSetId)}`, "DELETE", null, elements.csrfToken);
    showSuccess(elements.message, "Permission set deleted.");
    await refreshAccessControlData(elements, state);
}

/**
 * Deletes a permission-set permission association.
 */
async function deleteSetPermission(
    elements: AccessControlElements,
    state: AccessControlState,
    permissionSetId: string,
    permissionId: string
): Promise<void> {
    await apiRequest<void>(
        `/api/permission-set-permissions/${encodeURIComponent(permissionSetId)}/${encodeURIComponent(permissionId)}`,
        "DELETE",
        null,
        elements.csrfToken
    );

    showSuccess(elements.message, "Permission removed from permission set.");
    await refreshAccessControlData(elements, state);
}

/**
 * Deletes a permission-set user member association.
 */
async function deleteSetMember(
    elements: AccessControlElements,
    state: AccessControlState,
    permissionSetId: string,
    userId: string
): Promise<void> {
    await apiRequest<void>(
        `/api/permission-set-members/${encodeURIComponent(permissionSetId)}/${encodeURIComponent(userId)}`,
        "DELETE",
        null,
        elements.csrfToken
    );

    showSuccess(elements.message, "User removed from permission set.");
    await refreshAccessControlData(elements, state);
}

/**
 * Deletes a user-group permission-set assignment.
 */
async function deleteAssignment(
    elements: AccessControlElements,
    state: AccessControlState,
    userGroupId: string,
    permissionSetId: string
): Promise<void> {
    await apiRequest<void>(
        `/api/permission-set-user-groups/${encodeURIComponent(userGroupId)}/${encodeURIComponent(permissionSetId)}`,
        "DELETE",
        null,
        elements.csrfToken
    );

    showSuccess(elements.message, "Assignment deleted.");
    await refreshAccessControlData(elements, state);
}

/**
 * Fills the permission form for editing.
 */
function editPermission(elements: AccessControlElements, permission: Permission): void {
    setInputValue(elements.permissionForm, "permission_id", permission.permission_id);
    setInputValue(elements.permissionForm, "permission_token", permission.permission_token);
    setInputValue(elements.permissionForm, "permission_title", permission.permission_title);
    setInputValue(elements.permissionForm, "notes", permission.notes ?? "");
    elements.permissionSubmit.textContent = "Save Permission";
    elements.permissionCancel.style.display = "inline-block";
}

/**
 * Fills the user-group form for editing.
 */
function editUserGroup(elements: AccessControlElements, group: UserGroup): void {
    setInputValue(elements.userGroupForm, "user_group_id", group.user_group_id);
    setInputValue(elements.userGroupForm, "group_name", group.group_name);
    setInputValue(elements.userGroupForm, "description", group.description ?? "");
    elements.userGroupSubmit.textContent = "Save Group";
    elements.userGroupCancel.style.display = "inline-block";
}

/**
 * Fills the permission-set form for editing.
 */
function editPermissionSet(elements: AccessControlElements, permissionSet: PermissionSet): void {
    setInputValue(elements.permissionSetForm, "permission_set_id", permissionSet.permission_set_id);
    setInputValue(elements.permissionSetForm, "permission_set_token", permissionSet.permission_set_token);
    setInputValue(elements.permissionSetForm, "title", permissionSet.title);
    setInputValue(elements.permissionSetForm, "notes", permissionSet.notes ?? "");
    elements.permissionSetSubmit.textContent = "Save Permission Set";
    elements.permissionSetCancel.style.display = "inline-block";
}

/**
 * Fills the set-permission form for editing.
 */
function editSetPermission(elements: AccessControlElements, setPermission: PermissionSetPermission): void {
    setInputValue(elements.setPermissionForm, "set_permission_permission_set_id_original", setPermission.permission_set_id);
    setInputValue(elements.setPermissionForm, "set_permission_permission_id_original", setPermission.permission_id);
    setInputValue(elements.setPermissionForm, "permission_set_id", setPermission.permission_set_id);
    setInputValue(elements.setPermissionForm, "permission_id", setPermission.permission_id);
    setInputValue(elements.setPermissionForm, "notes", setPermission.notes ?? "");

    elements.setPermissionPermissionSetSelect.disabled = true;
    elements.setPermissionPermissionSelect.disabled = true;
    elements.setPermissionSubmit.textContent = "Save Association";
    elements.setPermissionCancel.style.display = "inline-block";
}

/**
 * Fills the set-member form for editing.
 */
function editSetMember(elements: AccessControlElements, setMember: PermissionSetMember): void {
    setInputValue(elements.setMemberForm, "set_member_permission_set_id_original", setMember.permission_set_id);
    setInputValue(elements.setMemberForm, "set_member_user_id_original", setMember.user_id);
    setInputValue(elements.setMemberForm, "permission_set_id", setMember.permission_set_id);
    setInputValue(elements.setMemberForm, "user_id", setMember.user_id);
    setInputValue(elements.setMemberForm, "access_granted", setMember.access_granted);
    setInputValue(elements.setMemberForm, "access_expiry", setMember.access_expiry);
    setInputValue(elements.setMemberForm, "has_access", setMember.has_access ? "1" : "0");
    setInputValue(elements.setMemberForm, "notes", setMember.notes ?? "");

    elements.setMemberPermissionSetSelect.disabled = true;
    elements.setMemberUserSelect.disabled = true;
    elements.setMemberSubmit.textContent = "Save Member";
    elements.setMemberCancel.style.display = "inline-block";
}

/**
 * Fills the assignment form for editing.
 */
function editAssignment(elements: AccessControlElements, assignment: PermissionSetUserGroup): void {
    setInputValue(elements.assignmentForm, "assignment_user_group_id_original", assignment.user_group_id);
    setInputValue(elements.assignmentForm, "assignment_permission_set_id_original", assignment.permission_set_id);
    setInputValue(elements.assignmentForm, "user_group_id", assignment.user_group_id);
    setInputValue(elements.assignmentForm, "permission_set_id", assignment.permission_set_id);
    setInputValue(elements.assignmentForm, "access_granted", assignment.access_granted);
    setInputValue(elements.assignmentForm, "access_expiry", assignment.access_expiry);
    setInputValue(elements.assignmentForm, "has_access", assignment.has_access ? "1" : "0");
    setInputValue(elements.assignmentForm, "notes", assignment.notes ?? "");

    elements.assignmentSubmit.textContent = "Save Assignment";
    elements.assignmentCancel.style.display = "inline-block";
}

/**
 * Restores the permission form to create mode.
 */
function resetPermissionForm(form: HTMLFormElement, submit: HTMLButtonElement, cancel: HTMLButtonElement): void {
    form.reset();
    setInputValue(form, "permission_id", "");
    submit.textContent = "Create Permission";
    cancel.style.display = "none";
}

/**
 * Restores the user-group form to create mode.
 */
function resetUserGroupForm(form: HTMLFormElement, submit: HTMLButtonElement, cancel: HTMLButtonElement): void {
    form.reset();
    setInputValue(form, "user_group_id", "");
    submit.textContent = "Create Group";
    cancel.style.display = "none";
}

/**
 * Restores the permission-set form to create mode.
 */
function resetPermissionSetForm(form: HTMLFormElement, submit: HTMLButtonElement, cancel: HTMLButtonElement): void {
    form.reset();
    setInputValue(form, "permission_set_id", "");
    submit.textContent = "Create Permission Set";
    cancel.style.display = "none";
}

/**
 * Restores the set-permission form to create mode.
 */
function resetSetPermissionForm(elements: AccessControlElements): void {
    elements.setPermissionForm.reset();
    setInputValue(elements.setPermissionForm, "set_permission_permission_set_id_original", "");
    setInputValue(elements.setPermissionForm, "set_permission_permission_id_original", "");
    elements.setPermissionPermissionSetSelect.disabled = false;
    elements.setPermissionPermissionSelect.disabled = false;
    elements.setPermissionSubmit.textContent = "Add Permission To Set";
    elements.setPermissionCancel.style.display = "none";
}

/**
 * Restores the set-member form to create mode.
 */
function resetSetMemberForm(elements: AccessControlElements): void {
    elements.setMemberForm.reset();
    setInputValue(elements.setMemberForm, "set_member_permission_set_id_original", "");
    setInputValue(elements.setMemberForm, "set_member_user_id_original", "");
    elements.setMemberPermissionSetSelect.disabled = false;
    elements.setMemberUserSelect.disabled = false;

    const defaults = getDefaultAccessWindow();
    elements.setMemberAccessGrantedInput.value = defaults.accessGranted;
    elements.setMemberAccessExpiryInput.value = defaults.accessExpiry;
    elements.setMemberSubmit.textContent = "Add User To Permission Set";
    elements.setMemberCancel.style.display = "none";
}

/**
 * Restores the assignment form to create mode.
 */
function resetAssignmentForm(elements: AccessControlElements): void {
    elements.assignmentForm.reset();
    setInputValue(elements.assignmentForm, "assignment_user_group_id_original", "");
    setInputValue(elements.assignmentForm, "assignment_permission_set_id_original", "");

    const defaults = getDefaultAccessWindow();
    elements.assignmentAccessGrantedInput.value = defaults.accessGranted;
    elements.assignmentAccessExpiryInput.value = defaults.accessExpiry;
    elements.assignmentSubmit.textContent = "Create Assignment";
    elements.assignmentCancel.style.display = "none";
}

/**
 * Renders permission rows.
 */
function renderPermissions(
    tbody: HTMLTableSectionElement,
    permissions: Permission[],
    adminPermissionTokenPrefix: string
): void {
    if (permissions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4">No permissions found.</td></tr>';
        return;
    }

    tbody.innerHTML = permissions.map((permission: Permission): string => {
        const isImmutable = isImmutableAdministrativePermission(permission, adminPermissionTokenPrefix);
        const actions = isImmutable
            ? '<span title="Framework administrative permissions are immutable.">Immutable</span>'
            : `<button type="button" data-action="edit" data-id="${escapeHtml(permission.permission_id)}">Edit</button>
                <button type="button" data-action="delete" data-id="${escapeHtml(permission.permission_id)}">Delete</button>`;

        return `<tr>
            <td>${escapeHtml(permission.permission_token)}</td>
            <td>${escapeHtml(permission.permission_title)}</td>
            <td>${escapeHtml(permission.notes ?? "")}</td>
            <td class="actions">
                ${actions}
            </td>
        </tr>`;
    }).join("");
}

/**
 * Returns true when a permission token uses the administrative token prefix.
 */
function isImmutableAdministrativePermission(
    permission: Permission,
    adminPermissionTokenPrefix: string
): boolean {
    const prefix = adminPermissionTokenPrefix.trim();
    if (prefix === "") {
        return false;
    }

    return permission.permission_token.startsWith(prefix);
}

/**
 * Renders user-group rows.
 */
function renderUserGroups(tbody: HTMLTableSectionElement, groups: UserGroup[], adminGroupName: string): void {
    if (groups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3">No user groups found.</td></tr>';
        return;
    }

    tbody.innerHTML = groups.map((group: UserGroup): string => {
        const isProtectedGroup = isProtectedAdminUserGroup(group, adminGroupName);
        const deleteAction = isProtectedGroup
            ? '<button type="button" disabled aria-disabled="true" title="Administrative user groups cannot be deleted.">Protected</button>'
            : `<button type="button" data-action="delete" data-id="${escapeHtml(group.user_group_id)}">Delete</button>`;

        return `<tr>
            <td>${escapeHtml(group.group_name)}</td>
            <td>${escapeHtml(group.description ?? "")}</td>
            <td class="actions">
                <button type="button" data-action="edit" data-id="${escapeHtml(group.user_group_id)}">Edit</button>
                <a href="/admin/access-control/user-group-memberships?user_group_id=${encodeURIComponent(group.user_group_id)}">Manage Members</a>
                <a href="/admin/access-control/set-group-assignments?user_group_id=${encodeURIComponent(group.user_group_id)}">Assign Permissions</a>
                ${deleteAction}
            </td>
        </tr>`;
    }).join("");
}

/**
 * Returns true when a user group is the configured administrative group.
 */
function isProtectedAdminUserGroup(group: UserGroup, adminGroupName: string): boolean {
    if (adminGroupName.trim() === "") {
        return false;
    }

    return group.group_name === adminGroupName;
}

/**
 * Renders permission-set rows.
 */
function renderPermissionSets(tbody: HTMLTableSectionElement, permissionSets: PermissionSet[]): void {
    if (permissionSets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4">No permission sets found.</td></tr>';
        return;
    }

    tbody.innerHTML = permissionSets.map((permissionSet: PermissionSet): string => {
        return `<tr>
            <td>${escapeHtml(permissionSet.permission_set_token)}</td>
            <td>${escapeHtml(permissionSet.title)}</td>
            <td>${escapeHtml(permissionSet.notes ?? "")}</td>
            <td class="actions">
                <button type="button" data-action="edit" data-id="${escapeHtml(permissionSet.permission_set_id)}">Edit</button>
                <button type="button" data-action="delete" data-id="${escapeHtml(permissionSet.permission_set_id)}">Delete</button>
            </td>
        </tr>`;
    }).join("");
}

/**
 * Renders permission-set permission association rows.
 */
function renderSetPermissions(
    tbody: HTMLTableSectionElement,
    setPermissions: PermissionSetPermission[],
    permissions: Permission[],
    permissionSets: PermissionSet[]
): void {
    const permissionTitleById = new Map<string, string>();
    for (const permission of permissions) {
        permissionTitleById.set(permission.permission_id, permission.permission_token);
    }

    const permissionSetTitleById = new Map<string, string>();
    for (const permissionSet of permissionSets) {
        permissionSetTitleById.set(permissionSet.permission_set_id, permissionSet.permission_set_token);
    }

    if (setPermissions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4">No permission set permissions found.</td></tr>';
        return;
    }

    tbody.innerHTML = setPermissions.map((setPermission: PermissionSetPermission): string => {
        const permissionName = permissionTitleById.get(setPermission.permission_id) ?? setPermission.permission_id;
        const permissionSetName = permissionSetTitleById.get(setPermission.permission_set_id) ?? setPermission.permission_set_id;

        return `<tr>
            <td>${escapeHtml(permissionSetName)}</td>
            <td>${escapeHtml(permissionName)}</td>
            <td>${escapeHtml(setPermission.notes ?? "")}</td>
            <td class="actions">
                <button
                    type="button"
                    data-action="edit"
                    data-permission-set-id="${escapeHtml(setPermission.permission_set_id)}"
                    data-permission-id="${escapeHtml(setPermission.permission_id)}"
                >Edit</button>
                <button
                    type="button"
                    data-action="delete"
                    data-permission-set-id="${escapeHtml(setPermission.permission_set_id)}"
                    data-permission-id="${escapeHtml(setPermission.permission_id)}"
                >Delete</button>
            </td>
        </tr>`;
    }).join("");
}

/**
 * Renders permission-set member association rows.
 */
function renderSetMembers(
    tbody: HTMLTableSectionElement,
    setMembers: PermissionSetMember[],
    users: User[],
    permissionSets: PermissionSet[]
): void {
    const usernameById = new Map<string, string>();
    for (const user of users) {
        usernameById.set(user.user_id, user.username);
    }

    const permissionSetNameById = new Map<string, string>();
    for (const permissionSet of permissionSets) {
        permissionSetNameById.set(permissionSet.permission_set_id, permissionSet.permission_set_token);
    }

    if (setMembers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">No permission set members found.</td></tr>';
        return;
    }

    tbody.innerHTML = setMembers.map((setMember: PermissionSetMember): string => {
        const username = usernameById.get(setMember.user_id) ?? setMember.user_id;
        const permissionSetName = permissionSetNameById.get(setMember.permission_set_id) ?? setMember.permission_set_id;

        return `<tr>
            <td>${escapeHtml(permissionSetName)}</td>
            <td>${escapeHtml(username)}</td>
            <td>${escapeHtml(setMember.access_granted)}</td>
            <td>${escapeHtml(setMember.access_expiry)}</td>
            <td>${setMember.has_access ? "Active" : "Disabled"}</td>
            <td class="actions">
                <button
                    type="button"
                    data-action="edit"
                    data-permission-set-id="${escapeHtml(setMember.permission_set_id)}"
                    data-user-id="${escapeHtml(setMember.user_id)}"
                >Edit</button>
                <button
                    type="button"
                    data-action="delete"
                    data-permission-set-id="${escapeHtml(setMember.permission_set_id)}"
                    data-user-id="${escapeHtml(setMember.user_id)}"
                >Delete</button>
            </td>
        </tr>`;
    }).join("");
}

/**
 * Renders assignment rows.
 */
function renderAssignments(
    tbody: HTMLTableSectionElement,
    assignments: PermissionSetUserGroup[],
    groups: UserGroup[],
    permissionSets: PermissionSet[]
): void {
    const groupNameById = new Map<string, string>();
    for (const group of groups) {
        groupNameById.set(group.user_group_id, group.group_name);
    }

    const permissionSetNameById = new Map<string, string>();
    for (const permissionSet of permissionSets) {
        permissionSetNameById.set(permissionSet.permission_set_id, permissionSet.permission_set_token);
    }

    if (assignments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">No assignments found.</td></tr>';
        return;
    }

    tbody.innerHTML = assignments.map((assignment: PermissionSetUserGroup): string => {
        const groupName = groupNameById.get(assignment.user_group_id) ?? assignment.user_group_id;
        const setName = permissionSetNameById.get(assignment.permission_set_id) ?? assignment.permission_set_id;

        return `<tr>
            <td>${escapeHtml(groupName)}</td>
            <td>${escapeHtml(setName)}</td>
            <td>${escapeHtml(assignment.access_granted)}</td>
            <td>${escapeHtml(assignment.access_expiry)}</td>
            <td>${assignment.has_access ? "Active" : "Disabled"}</td>
            <td class="actions">
                <button
                    type="button"
                    data-action="edit"
                    data-user-group-id="${escapeHtml(assignment.user_group_id)}"
                    data-permission-set-id="${escapeHtml(assignment.permission_set_id)}"
                >Edit</button>
                <button
                    type="button"
                    data-action="delete"
                    data-user-group-id="${escapeHtml(assignment.user_group_id)}"
                    data-permission-set-id="${escapeHtml(assignment.permission_set_id)}"
                >Delete</button>
            </td>
        </tr>`;
    }).join("");
}

/**
 * Populates assignment select options.
 */
function renderAssignmentSelects(
    elements: AccessControlElements,
    groups: UserGroup[],
    permissionSets: PermissionSet[]
): void {
    elements.assignmentUserGroupSelect.innerHTML =
        '<option value="">Select user group</option>'
        + groups.map((group: UserGroup): string => {
            return `<option value="${escapeHtml(group.user_group_id)}">${escapeHtml(group.group_name)}</option>`;
        }).join("");

    elements.assignmentPermissionSetSelect.innerHTML =
        '<option value="">Select permission set</option>'
        + permissionSets.map((permissionSet: PermissionSet): string => {
            return `<option value="${escapeHtml(permissionSet.permission_set_id)}">${escapeHtml(permissionSet.permission_set_token)}</option>`;
        }).join("");
}

/**
 * Populates set-permission select options.
 */
function renderSetPermissionSelects(
    elements: AccessControlElements,
    permissions: Permission[],
    permissionSets: PermissionSet[]
): void {
    elements.setPermissionPermissionSetSelect.innerHTML =
        '<option value="">Select permission set</option>'
        + permissionSets.map((permissionSet: PermissionSet): string => {
            return `<option value="${escapeHtml(permissionSet.permission_set_id)}">${escapeHtml(permissionSet.permission_set_token)}</option>`;
        }).join("");

    elements.setPermissionPermissionSelect.innerHTML =
        '<option value="">Select permission</option>'
        + permissions.map((permission: Permission): string => {
            return `<option value="${escapeHtml(permission.permission_id)}">${escapeHtml(permission.permission_token)}</option>`;
        }).join("");
}

/**
 * Populates set-member select options.
 */
function renderSetMemberSelects(
    elements: AccessControlElements,
    users: User[],
    permissionSets: PermissionSet[]
): void {
    elements.setMemberPermissionSetSelect.innerHTML =
        '<option value="">Select permission set</option>'
        + permissionSets.map((permissionSet: PermissionSet): string => {
            return `<option value="${escapeHtml(permissionSet.permission_set_id)}">${escapeHtml(permissionSet.permission_set_token)}</option>`;
        }).join("");

    elements.setMemberUserSelect.innerHTML =
        '<option value="">Select user</option>'
        + users.map((user: User): string => {
            return `<option value="${escapeHtml(user.user_id)}">${escapeHtml(user.username)}</option>`;
        }).join("");
}

/**
 * Makes an authenticated JSON API request.
 */
async function apiRequest<T>(
    path: string,
    method: string,
    payload: Record<string, unknown> | null,
    csrfToken: string
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: "application/json",
    };

    if (payload !== null) {
        headers["Content-Type"] = "application/json";
    }

    if (csrfToken !== "") {
        headers["X-CSRF-Token"] = csrfToken;
    }

    const response = await fetch(path, {
        method,
        headers,
        credentials: "same-origin",
        body: payload !== null ? JSON.stringify(payload) : undefined,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const data = await parseJsonResponse(response);

    if (response.ok === false) {
        const message = resolveErrorFromResponseData(data) ?? `Request failed with status ${response.status}.`;
        throw new Error(message);
    }

    return data as T;
}

/**
 * Parses a JSON API response body.
 */
async function parseJsonResponse(response: Response): Promise<unknown> {
    const text = await response.text();

    if (text === "") {
        return {};
    }

    try {
        return JSON.parse(text) as unknown;
    } catch (_error: unknown) {
        throw new Error("The server returned an invalid JSON response.");
    }
}

/**
 * Extracts the most useful error message from a JSON API payload.
 */
function resolveErrorFromResponseData(data: unknown): string | null {
    if (typeof data !== "object" || data === null) {
        return null;
    }

    const payload = data as Record<string, unknown>;
    const message = payload.message;

    if (typeof message === "string" && message.trim() !== "") {
        return message.trim();
    }

    const error = payload.error;
    if (typeof error === "string" && error.trim() !== "") {
        return error.trim();
    }

    return null;
}

/**
 * Returns a stable error message string for unknown error values.
 */
function resolveErrorMessage(error: unknown): string {
    if (error instanceof Error && error.message.trim() !== "") {
        return error.message;
    }

    return "An unexpected error occurred.";
}

/**
 * Reads a form value as a trimmed string.
 */
function valueOfInput(form: HTMLFormElement, fieldName: string): string {
    const field = form.elements.namedItem(fieldName);
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) {
        return "";
    }

    return field.value.trim();
}

/**
 * Reads an optional form value, returning null when empty.
 */
function optionalValueOfInput(form: HTMLFormElement, fieldName: string): string | null {
    const value = valueOfInput(form, fieldName);
    return value !== "" ? value : null;
}

/**
 * Sets a form value on an input or select element.
 */
function setInputValue(form: HTMLFormElement, fieldName: string, value: string): void {
    const field = form.elements.namedItem(fieldName);
    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) {
        field.value = value;
    }
}

/**
 * Escapes HTML special characters in text values.
 */
function escapeHtml(value: string): string {
    return value
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

/**
 * Formats a UTC Date instance using the backend datetime format.
 */
function formatUtcDateTime(date: Date): string {
    const pad = (value: number): string => value.toString().padStart(2, "0");

    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())} ${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:${pad(date.getUTCSeconds())}`;
}

/**
 * Returns default access-granted and access-expiry timestamps.
 */
function getDefaultAccessWindow(): { accessGranted: string; accessExpiry: string } {
    const grantedDate = new Date();
    const expiryDate = new Date(grantedDate.getTime());
    expiryDate.setUTCFullYear(expiryDate.getUTCFullYear() + 2);

    return {
        accessGranted: formatUtcDateTime(grantedDate),
        accessExpiry: formatUtcDateTime(expiryDate),
    };
}

/**
 * Shows an error message.
 */
function showError(messageElement: HTMLElement, message: string): void {
    messageElement.textContent = message;
    messageElement.className = "alert alert-error";
    messageElement.style.display = "block";
}

/**
 * Shows a success message.
 */
function showSuccess(messageElement: HTMLElement, message: string): void {
    messageElement.textContent = message;
    messageElement.className = "alert alert-success";
    messageElement.style.display = "block";
}

/**
 * Clears the page message box.
 */
function clearMessage(messageElement: HTMLElement): void {
    messageElement.textContent = "";
    messageElement.style.display = "none";
}

/** Run all initialisers after the DOM is ready. */
document.addEventListener("DOMContentLoaded", (): void => {
    initConfirmForms();
    initAccessControlPage();
});
