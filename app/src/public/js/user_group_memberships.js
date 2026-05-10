"use strict";

function initUserGroupMembershipsPage() {
    const root = document.querySelector("[data-user-group-memberships-root]");
    if (root === null) {
        return;
    }

    const message = document.getElementById("user-group-memberships-message");
    const form = document.getElementById("user-group-membership-form");
    const membershipsBody = document.querySelector("#user-group-memberships-table tbody");
    const submitButton = document.getElementById("membership-submit");
    const cancelButton = document.getElementById("membership-cancel");
    const userSelect = document.getElementById("membership_user_id");
    const userGroupSelect = document.getElementById("membership_user_group_id");
    const accessGrantedInput = document.getElementById("membership_access_granted");
    const accessExpiryInput = document.getElementById("membership_access_expiry");

    if (
        message === null
        || form === null
        || membershipsBody === null
        || submitButton === null
        || cancelButton === null
        || userSelect === null
        || userGroupSelect === null
        || accessGrantedInput === null
        || accessExpiryInput === null
    ) {
        return;
    }

    const csrfToken = root.dataset.csrfToken ?? "";
    const initialUserGroupId = new URLSearchParams(window.location.search).get("user_group_id") ?? "";

    const state = {
        users: [],
        userGroups: [],
        memberships: [],
    };

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        try {
            clearMessage(message);

            const userId = valueOfInput(form, "user_id");
            const userGroupId = valueOfInput(form, "user_group_id");
            const accessGranted = valueOfInput(form, "access_granted");
            const accessExpiry = valueOfInput(form, "access_expiry");
            const hasAccess = valueOfInput(form, "has_access") === "1";

            const originalUserId = valueOfInput(form, "membership_user_id_original");
            const originalUserGroupId = valueOfInput(form, "membership_user_group_id_original");

            if (userId === "" || userGroupId === "" || accessGranted === "" || accessExpiry === "") {
                showError(message, "Membership fields are required.");
                return;
            }

            const payload = {
                user_id: userId,
                user_group_id: userGroupId,
                access_granted: accessGranted,
                access_expiry: accessExpiry,
                has_access: hasAccess,
            };

            if (originalUserId === "" || originalUserGroupId === "") {
                await apiRequest("/api/user-group-memberships", "POST", payload, csrfToken);
                showSuccess(message, "Membership created.");
            } else {
                await apiRequest(
                    "/api/user-group-memberships/"
                        + encodeURIComponent(originalUserId)
                        + "/"
                        + encodeURIComponent(originalUserGroupId),
                    "PUT",
                    {
                        access_granted: accessGranted,
                        access_expiry: accessExpiry,
                        has_access: hasAccess,
                    },
                    csrfToken
                );

                showSuccess(message, "Membership updated.");
            }

            resetMembershipForm(form, submitButton, cancelButton, accessGrantedInput, accessExpiryInput, initialUserGroupId);
            await refreshMembershipData(state, message, membershipsBody, userSelect, userGroupSelect, csrfToken, initialUserGroupId);
        } catch (error) {
            showError(message, resolveErrorMessage(error));
        }
    });

    cancelButton.addEventListener("click", function () {
        resetMembershipForm(form, submitButton, cancelButton, accessGrantedInput, accessExpiryInput, initialUserGroupId);
    });

    membershipsBody.addEventListener("click", async function (event) {
        const target = event.target;
        const action = target.getAttribute("data-action");
        const userId = target.getAttribute("data-user-id");
        const userGroupId = target.getAttribute("data-user-group-id");

        if (action === null || userId === null || userGroupId === null) {
            return;
        }

        if (action === "edit") {
            const membership = state.memberships.find(function (item) {
                return item.user_id === userId && item.user_group_id === userGroupId;
            });

            if (membership !== undefined) {
                editMembership(form, submitButton, cancelButton, membership);
            }

            return;
        }

        if (action === "delete") {
            if (window.confirm("Disable this membership?") === false) {
                return;
            }

            try {
                await apiRequest(
                    "/api/user-group-memberships/"
                        + encodeURIComponent(userId)
                        + "/"
                        + encodeURIComponent(userGroupId),
                    "DELETE",
                    null,
                    csrfToken
                );

                showSuccess(message, "Membership disabled.");
                await refreshMembershipData(state, message, membershipsBody, userSelect, userGroupSelect, csrfToken, initialUserGroupId);
            } catch (error) {
                showError(message, resolveErrorMessage(error));
            }
        }
    });

    resetMembershipForm(form, submitButton, cancelButton, accessGrantedInput, accessExpiryInput, initialUserGroupId);
    void refreshMembershipData(state, message, membershipsBody, userSelect, userGroupSelect, csrfToken, initialUserGroupId);
}

async function refreshMembershipData(state, message, tbody, userSelect, userGroupSelect, csrfToken, initialUserGroupId) {
    clearMessage(message);

    const [usersResponse, groupsResponse] = await Promise.all([
        apiRequest("/api/users", "GET", null, csrfToken),
        apiRequest("/api/user-groups", "GET", null, csrfToken),
    ]);

    state.users = usersResponse.data;
    state.userGroups = groupsResponse.data;
    state.memberships = await fetchAllMemberships(state.userGroups, csrfToken);

    renderSelectOptions(userSelect, state.users, "user_id", "username", "Select user");
    renderSelectOptions(userGroupSelect, state.userGroups, "user_group_id", "group_name", "Select user group");
    renderMemberships(tbody, state.memberships, state.users, state.userGroups);

    if (initialUserGroupId !== "") {
        const groupExists = state.userGroups.some(function (group) {
            return group.user_group_id === initialUserGroupId;
        });

        if (groupExists) {
            userGroupSelect.value = initialUserGroupId;
        }
    }
}

async function fetchAllMemberships(userGroups, csrfToken) {
    const membershipMap = new Map();

    for (const userGroup of userGroups) {
        const response = await apiRequest(
            "/api/user-group-memberships?user_group_id=" + encodeURIComponent(userGroup.user_group_id),
            "GET",
            null,
            csrfToken
        );

        for (const membership of response.data) {
            const key = membership.user_id + "::" + membership.user_group_id;
            membershipMap.set(key, membership);
        }
    }

    return Array.from(membershipMap.values());
}

function renderMemberships(tbody, memberships, users, userGroups) {
    const usernameById = new Map();
    for (const user of users) {
        usernameById.set(user.user_id, user.username);
    }

    const groupNameById = new Map();
    for (const group of userGroups) {
        groupNameById.set(group.user_group_id, group.group_name);
    }

    if (memberships.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">No user group memberships found.</td></tr>';
        return;
    }

    tbody.innerHTML = memberships.map(function (membership) {
        const username = usernameById.get(membership.user_id) ?? membership.user_id;
        const groupName = groupNameById.get(membership.user_group_id) ?? membership.user_group_id;

        return "<tr>"
            + "<td>" + escapeHtml(username) + "</td>"
            + "<td>" + escapeHtml(groupName) + "</td>"
            + "<td>" + escapeHtml(membership.access_granted) + "</td>"
            + "<td>" + escapeHtml(membership.access_expiry) + "</td>"
            + "<td>" + (membership.has_access ? "Active" : "Disabled") + "</td>"
            + "<td class=\"actions\">"
            + "<button type=\"button\" data-action=\"edit\" data-user-id=\"" + escapeHtml(membership.user_id) + "\" data-user-group-id=\"" + escapeHtml(membership.user_group_id) + "\">Edit</button>"
            + "<button type=\"button\" data-action=\"delete\" data-user-id=\"" + escapeHtml(membership.user_id) + "\" data-user-group-id=\"" + escapeHtml(membership.user_group_id) + "\">Disable</button>"
            + "</td>"
            + "</tr>";
    }).join("");
}

function renderSelectOptions(select, items, valueKey, labelKey, placeholder) {
    select.innerHTML = "<option value=\"\">" + escapeHtml(placeholder) + "</option>" + items.map(function (item) {
        return "<option value=\"" + escapeHtml(item[valueKey]) + "\">" + escapeHtml(item[labelKey]) + "</option>";
    }).join("");
}

function editMembership(form, submitButton, cancelButton, membership) {
    setInputValue(form, "membership_user_id_original", membership.user_id);
    setInputValue(form, "membership_user_group_id_original", membership.user_group_id);
    setInputValue(form, "user_id", membership.user_id);
    setInputValue(form, "user_group_id", membership.user_group_id);
    setInputValue(form, "access_granted", membership.access_granted);
    setInputValue(form, "access_expiry", membership.access_expiry);
    setInputValue(form, "has_access", membership.has_access ? "1" : "0");

    submitButton.textContent = "Save Membership";
    cancelButton.style.display = "inline-block";
}

function resetMembershipForm(form, submitButton, cancelButton, accessGrantedInput, accessExpiryInput, initialUserGroupId) {
    form.reset();
    setInputValue(form, "membership_user_id_original", "");
    setInputValue(form, "membership_user_group_id_original", "");

    const defaults = getDefaultAccessWindow();
    accessGrantedInput.value = defaults.accessGranted;
    accessExpiryInput.value = defaults.accessExpiry;

    if (initialUserGroupId !== "") {
        setInputValue(form, "user_group_id", initialUserGroupId);
    }

    submitButton.textContent = "Create Membership";
    cancelButton.style.display = "none";
}

function valueOfInput(form, name) {
    const field = form.elements.namedItem(name);
    if (field === null || typeof field.value !== "string") {
        return "";
    }

    return field.value.trim();
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
    initUserGroupMembershipsPage();
});
