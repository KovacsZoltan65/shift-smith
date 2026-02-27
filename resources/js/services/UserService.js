import BaseService from "@/services/BaseService.js";
import { csrfFetch } from "@/lib/csrfFetch.js";

const resolveRoute = (name, params, fallback) => {
    try {
        if (typeof route === "function") {
            return route(name, params, false);
        }
    } catch (_) {}

    return fallback;
};

const toAxiosLikeError = async (response, fallbackMessage) => {
    let data = null;

    try {
        data = await response.json();
    } catch (_) {
        data = { message: fallbackMessage };
    }

    const error = new Error(data?.message || fallbackMessage);
    error.response = {
        status: response.status,
        data,
    };
    error.normalizedErrors = data?.errors || null;

    return error;
};

class UserService extends BaseService {
    fetchUsers(params = {}) {
        return this.get(resolveRoute("users.fetch", undefined, "/users/fetch"), { params });
    }

    fetchUsersToSelect(params = {}) {
        return this.get(resolveRoute("admin.selectors.users", undefined, "/admin/selectors/users"), { params });
    }

    async updatePrimaryRole(userId, roleId) {
        const response = await csrfFetch(
            resolveRoute("admin.users.role.update", { user: userId }, `/admin/users/${userId}/role`),
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ role_id: roleId }),
            },
        );

        if (!response.ok) {
            throw await toAxiosLikeError(response, "A felhasználó szerepkörének mentése sikertelen.");
        }

        return { data: await response.json() };
    }
}

export default new UserService();
