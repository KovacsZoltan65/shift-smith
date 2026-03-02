import BaseService from "@/services/BaseService.js";
import { csrfFetch } from "@/lib/csrfFetch.js";

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

class UserAssignmentsService extends BaseService {
    fetchUsers(params = {}) {
        return this.get(route("admin.user_assignments.users.fetch", undefined, false), { params });
    }

    fetchUser(userId) {
        return this.get(route("admin.user_assignments.fetch", { user: userId }, false));
    }

    async attachCompany(userId, payload) {
        const response = await csrfFetch(route("admin.user_assignments.companies.store", { user: userId }, false), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw await toAxiosLikeError(response, "A cég hozzárendelése sikertelen.");
        }

        return { data: await response.json() };
    }

    async detachCompany(userId, companyId) {
        const response = await csrfFetch(
            route("admin.user_assignments.companies.destroy", { user: userId, company: companyId }, false),
            { method: "DELETE", headers: { "Content-Type": "application/json" } },
        );

        if (!response.ok) {
            throw await toAxiosLikeError(response, "A cég eltávolítása sikertelen.");
        }

        return { data: await response.json() };
    }

    async assignEmployee(userId, companyId, payload) {
        const response = await csrfFetch(
            route("admin.user_assignments.employee.assign", { user: userId, company: companyId }, false),
            {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            },
        );

        if (!response.ok) {
            throw await toAxiosLikeError(response, "A dolgozó hozzárendelése sikertelen.");
        }

        return { data: await response.json() };
    }

    async removeEmployee(userId, companyId) {
        const response = await csrfFetch(
            route("admin.user_assignments.employee.destroy", { user: userId, company: companyId }, false),
            { method: "DELETE", headers: { "Content-Type": "application/json" } },
        );

        if (!response.ok) {
            throw await toAxiosLikeError(response, "A dolgozó hozzárendelés törlése sikertelen.");
        }

        return { data: await response.json() };
    }
}

export default new UserAssignmentsService();
