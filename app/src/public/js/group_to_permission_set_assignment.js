"use strict";

function initGroupToPermissionSetAssignmentPage() {
    const root = document.querySelector("[data-group-to-permission-set-assignment-root]");
    if (root === null) {
        return;
    }

    const message = document.getElementById("group-assignment-message");
    const form = document.getElementById("group-assignment-form");
    const submitButton = document.getElementById("group-assignment-submit");
    const cancelButton = document.getElementById("group-assignment-cancel");
    const assignmentsBody = document.querySelector("#group-assignments-table tbody");
    const userGroupSelect = document.getElementById("group_assignment_user_group_id");
    const permissionSetSelect = document.getElementById("group_assignment_permission_set_id");
    const accessGrantedInput = document.getElementById("group_assignment_access_granted");
    const accessExpiryInput = document.getElementById("group_assignment_access_expiry");

    if (
        message === null
        || form === null
        || submitButton === null
        || cancelButton === null
        || assignmentsBody === null
        || userGroupSelect === null
        || permissionSetSelect === null
        || accessGrantedInput === null
        || accessExpiryInput === null
    ) {
        return;
    }

    const state = {
        userGroups: [],
        permissionSets: [],
        assignments: [],
    };

    const csrfToken = root.dataset.csrfToken ?? "";
    const initialUserGroupId = new URLSearchParams(window.location.search).get("user_group_id") ?? "";

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        try {
            clearMessage(message);

            const userGroupId = valueOfInput(form, "user_group_id");
            const permissionSetId = valueOfInput(form, "permission_set_id");
            const accessGranted = valueOfInput(form, "access_granted");
            const accessExpiry = valueOfInput(form, "access_expiry");
            const hasAccess = valueOfInput(form, "has_access") === "1";
            const notes = optionalValueOfInput(form, "notes");

            const originalUserGroupId = valueOfInput(form, "group_assignment_user_group_id_original");
            const originalPermissionSetId = valueOfInput(form, "group_assignment_permission_set_id_original");

            if (userGroupId === "" || permissionSetId === "" || accessGranted === "" || accessExpiry === "") {
                showError(message, "Assignment fields are required.");
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
                await apiRequest("/api/permission-set-user-groups", "POST", payload, csrfToken);
                showSuccess(message, "Assignment created.");
            } else {
                await apiRequest(
                    "/api/permission-set-user-groups/"
                        + encodeURIComponent(originalUserGroupId)
                        + "/"
                        + encodeURIComponent(originalPermissionSetId),
                    "PUT",
                    {
                        access_granted: accessGranted,
                        access_expiry: accessExpiry,
                        has_access: hasAccess,
                        notes,
                    },
                    csrfToken
                );

                showSuccess(message, "Assignment updated.");
            }

            resetForm(form, submitButton, cancelButton, accessGrantedInput, accessExpiryInput, initialUserGroupId);
            await refreshData(state, csrfToken, assignmentsBody, userGroupSelect, permissionSetSelect, message, initialUserGroupId);
        } catch (error) {
            showError(message, resolveErrorMessage(error));
        }
    });

    cancelButton.addEventListener("click", function () {
        resetForm(form, submitButton, cancelButton, accessGrantedInput, accessExpiryInput, initialUserGroupId);
    });

    assignmentsBody.addEventListener("click", async function (event) {
        const target = event.target;
        const action = target.getAttribute("data-action");
        const userGroupId = target.getAttribute("data-user-group-id");
        const permissionSetId = target.getAttribute("data-permission-set-id");

        if (action === null || userGroupId === null || permissionSetId === null) {
            return;
        }

        if (action === "edit") {
            const assignment = state.assignments.find(function (item) {
                return item.user_group_id === userGroupId && item.permission_set_id === permissionSetId;
            });

            if (assignment !== undefined) {
                editAssignment(form, submitButton, cancelButton, assignment);
            }

            return;
        }

        if (action === "delete") {
            if (window.confirm("Delete this user-group assignment?") === false) {
                return;
            }

            try {
                await apiRequest(
                    "/api/permission-set-user-groups/"
                        + encodeURIComponent(userGroupId)
                        + "/"
                        + encodeURIComponent(permissionSetId),
                    "DELETE",
                    null,
                    csrfToken
                );

                showSuccess(message, "Assignment deleted.");
                await refreshData(state, csrfToken, assignmentsBody, userGroupSelect, permissionSetSelect, message, initialUserGroupId);
            } catch (error) {
                showError(message, resolveErrorMessage(error));
            }
        }
    });

    const defaults = getDefaultAccessWindow();
    accessGrantedInput.value = defaults.accessGranted;
    accessExpiryInput.value = defaults.accessExpiry;

    void refreshData(state, csrfToken, assignmentsBody, userGroupSelect, permissionSetSelect, message, initialUserGroupId);
}

async function refreshData(
    state,
    csrfToken,
    assignmentsBody,
    userGroupSelect,
    permissionSetSelect,
    message,
    initialUserGroupId
) {
    clearMessage(message);

    const responses = await Promise.all([
        apiRequest("/api/user-groups", "GET", null, csrfToken),
        apiRequest("/api/permission-sets", "GET", null, csrfToken),
    ]);

    state.userGroups = responses[0].data;
    state.permissionSets = responses[1].data;
    state.assignments = await fetchAssignments(state.permissionSets, csrfToken);

    renderSelectOptions(userGroupSelect, state.userGroups, "user_group_id", "group_name", "Select user group");
    renderSelectOptions(permissionSetSelect, state.permissionSets, "permission_set_id", "permission_set_token", "Select permission set");
    renderAssignments(assignmentsBody, state.assignments, state.userGroups, state.permissionSets);

    if (initialUserGroupId !== "") {
        const exists = state.userGroups.some(function (group) {
            return group.user_group_id === initialUserGroupId;
        });

        if (exists) {
            userGroupSelect.value = initialUserGroupId;
        }
    }
}

async function fetchAssignments(permissionSets, csrfToken) {
    const assignmentMap = new Map();

    for (const permissionSet of permissionSets) {
        const response = await apiRequest(
            "/api/permission-set-user-groups?permission_set_id=" + encodeURIComponent(permissionSet.permission_set_id),
            "GET",
            null,
            csrfToken
        );

        for (const assignment of response.data) {
            const key = assignment.user_group_id + "::" + assignment.permission_set_id;
            assignmentMap.set(key, assignment);
        }
    }

    return Array.from(assignmentMap.values());
}

function renderAssignments(tbody, assignments, groups, permissionSets) {
    const groupNameById = new Map();
    for (const group of groups) {
        groupNameById.set(group.user_group_id, group.group_name);
    }

    const setTokenById = new Map();
    for (const permissionSet of permissionSets) {
        setTokenById.set(permissionSet.permission_set_id, permissionSet.permission_set_token);
    }

    if (assignments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">No assignments found.</td></tr>';
        return;
    }

    tbody.innerHTML = assignments.map(function (assignment) {
        const groupName = groupNameById.get(assignment.user_group_id) ?? assignment.user_group_id;
        const setToken = setTokenById.get(assignment.permission_set_id) ?? assignment.permission_set_id;

        return "<tr>"
            + "<td>" + escapeHtml(groupName) + "</td>"
            + "<td>" + escapeHtml(setToken) + "</td>"
            + "<td>" + escapeHtml(assignment.access_granted) + "</td>"
            + "<td>" + escapeHtml(assignment.access_expiry) + "</td>"
            + "<td>" + (assignment.has_access ? "Active" : "Disabled") + "</td>"
            + "<td class=\"actions\">"
            + "<button type=\"button\" data-action=\"edit\" data-user-group-id=\"" + escapeHtml(assignment.user_group_id) + "\" data-permission-set-id=\"" + escapeHtml(assignment.permission_set_id) + "\">Edit</button>"
            + "<button type=\"button\" data-action=\"delete\" data-user-group-id=\"" + escapeHtml(assignment.user_group_id) + "\" data-permission-set-id=\"" + escapeHtml(assignment.permission_set_id) + "\">Delete</button>"
            + "</td>"
            + "</tr>";
    }).join("");
}

function renderSelectOptions(select, items, valueKey, labelKey, placeholder) {
    select.innerHTML = "<option value=\"\">" + escapeHtml(placeholder) + "</option>" + items.map(function (item) {
        return "<option value=\"" + escapeHtml(item[valueKey]) + "\">" + escapeHtml(item[labelKey]) + "</option>";
    }).join("");
}

function editAssignment(form, submitButton, cancelButton, assignment) {
    setInputValue(form, "group_assignment_user_group_id_original", assignment.user_group_id);
    setInputValue(form, "group_assignment_permission_set_id_original", assignment.permission_set_id);
    setInputValue(form, "user_group_id", assignment.user_group_id);
    setInputValue(form, "permission_set_id", assignment.permission_set_id);
    setInputValue(form, "access_granted", assignment.access_granted);
    setInputValue(form, "access_expiry", assignment.access_expiry);
    setInputValue(form, "has_access", assignment.has_access ? "1" : "0");
    setInputValue(form, "notes", assignment.notes ?? "");

    submitButton.textContent = "Save Assignment";
    cancelButton.style.display = "inline-block";
}

function resetForm(form, submitButton, cancelButton, accessGrantedInput, accessExpiryInput, initialUserGroupId) {
    form.reset();
    setInputValue(form, "group_assignment_user_group_id_original", "");
    setInputValue(form, "group_assignment_permission_set_id_original", "");

    const defaults = getDefaultAccessWindow();
    accessGrantedInput.value = defaults.accessGranted;
    accessExpiryInput.value = defaults.accessExpiry;

    if (initialUserGroupId !== "") {
        setInputValue(form, "user_group_id", initialUserGroupId);
    }

    submitButton.textContent = "Create Assignment";
    cancelButton.style.display = "none";
}

function valueOfInput(form, name) {
    const field = form.elements.namedItem(name);
    if (field === null || typeof field.value !== "string") {
        return "";
    }

    return field.value.trim();
}

function optionalValueOfInput(form, name) {
    const value = valueOfInput(form, name);
    return value === "" ? null : value;
}

function setInputValue(form, name, value) {
    const field = form.elements.namedItem(name);
    if (field !== null && typeof field.value === "string") {
        field.value = value;
    }
}

function getDefaultAccessWindow() {
    const now = new Date();
    const expiry = new Date(now.getTime());
    expiry.setFullYear(expiry.getFullYear() + 1);

    return {
        accessGranted: formatUtcDateTime(now),
        accessExpiry: formatUtcDateTime(expiry),
    };
}

function formatUtcDateTime(date) {
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, "0");
    const day = String(date.getUTCDate()).padStart(2, "0");
    const hours = String(date.getUTCHours()).padStart(2, "0");
    const minutes = String(date.getUTCMinutes()).padStart(2, "0");
    const seconds = String(date.getUTCSeconds()).padStart(2, "0");

    return year + "-" + month + "-" + day + " " + hours + ":" + minutes + ":" + seconds;
}

async function apiRequest(path, method, payload, csrfToken) {
    const headers = {
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
        return {};
    }

    const data = await parseJsonResponse(response);

    if (response.ok === false) {
        const message = resolveErrorFromResponseData(data) ?? ("Request failed with status " + response.status + ".");
        throw new Error(message);
    }

    return data;
}

async function parseJsonResponse(response) {
    const text = await response.text();

    if (text === "") {
        return {};
    }

    try {
        return JSON.parse(text);
    } catch {
        throw new Error("The server returned an invalid JSON response.");
    }
}

function resolveErrorFromResponseData(data) {
    if (typeof data !== "object" || data === null) {
        return null;
    }

    if (typeof data.message === "string" && data.message.trim() !== "") {
        return data.message;
    }

    if (typeof data.error === "string" && data.error.trim() !== "") {
        return data.error;
    }

    if (
        typeof data.error === "object"
        && data.error !== null
        && typeof data.error.message === "string"
        && data.error.message.trim() !== ""
    ) {
        return data.error.message;
    }

    return null;
}

function resolveErrorMessage(error) {
    if (error instanceof Error && error.message.trim() !== "") {
        return error.message;
    }

    return "An unexpected error occurred.";
}

function showSuccess(messageElement, text) {
    messageElement.className = "alert alert-success";
    messageElement.textContent = text;
    messageElement.style.display = "block";
}

function showError(messageElement, text) {
    messageElement.className = "alert alert-error";
    messageElement.textContent = text;
    messageElement.style.display = "block";
}

function clearMessage(messageElement) {
    messageElement.textContent = "";
    messageElement.style.display = "none";
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

document.addEventListener("DOMContentLoaded", function () {
    initGroupToPermissionSetAssignmentPage();
});
