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

class RoleService extends BaseService {
    constructor() {
        super();
        this.url = "/admin/roles";
    }

    getRoles(params = {}) {
        return this.get(resolveRoute("admin.roles.fetch", undefined, `${this.url}/fetch`), { params });
    }

    storeRole(params) {
        return this.post(resolveRoute("admin.roles.store", undefined, this.url), params);
    }

    updateRole(id, params) {
        return this.put(resolveRoute("admin.roles.update", { id }, `${this.url}/${id}`), params);
    }

    getRole(id) {
        return this.get(resolveRoute("admin.roles.by_id", { id }, `${this.url}/${id}`));
    }

    getToSelect(params = {}) {
        return this.get(resolveRoute("admin.selectors.roles", undefined, "/admin/selectors/roles"), { params });
    }

    async syncRoleUsers(roleId, userIds) {
        const response = await csrfFetch(
            resolveRoute("admin.roles.users.update", { role: roleId }, `${this.url}/${roleId}/users`),
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ user_ids: userIds }),
            },
        );

        if (!response.ok) {
            throw await toAxiosLikeError(response, "A role felhasználóinak mentése sikertelen.");
        }

        return { data: await response.json() };
    }

    deleteRoles(ids) {
        return this.delete(resolveRoute("admin.roles.destroy_bulk", undefined, `${this.url}/destroy_bulk`), {
            data: { ids },
        });
    }

    deleteRole(id) {
        return this.delete(resolveRoute("admin.roles.destroy", { id }, `${this.url}/${id}`));
    }

    restoreRole(id) {
        return this.put(route(`${this.url}.restore`, id));
    }

    forceDeleteRole(id) {
        return this.delete(route(`${this.url}.force-delete`, id));
    }

}

export default new RoleService();
