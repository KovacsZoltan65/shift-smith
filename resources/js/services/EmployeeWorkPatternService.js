import BaseService from "@/services/BaseService";
import { csrfFetch } from "@/lib/csrfFetch.js";
import { trans } from "laravel-vue-i18n";

const toAxiosLikeError = async (res, fallbackMessage) => {
    let data = null;
    try {
        data = await res.json();
    } catch (_) {
        data = { message: fallbackMessage };
    }

    const error = new Error(data?.message || fallbackMessage);
    error.response = { status: res.status, data };
    return error;
};

class EmployeeWorkPatternService extends BaseService {
    getList(employeeId) {
        return this.get(`/employees/${employeeId}/work-patterns`);
    }

    async assign(employeeId, params) {
        const res = await csrfFetch(`/employees/${employeeId}/work-patterns/assign`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(params),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, trans("employees.messages.work_pattern_assign_failed"));
        }

        return { data: await res.json() };
    }

    async update(employeeId, id, params) {
        const res = await csrfFetch(`/employees/${employeeId}/work-patterns/${id}`, {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(params),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, trans("employees.messages.work_pattern_update_failed"));
        }

        return { data: await res.json() };
    }

    async unassign(employeeId, id) {
        const res = await csrfFetch(`/employees/${employeeId}/work-patterns/${id}`, {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, trans("employees.messages.work_pattern_delete_failed"));
        }

        return { data: await res.json() };
    }
}

export default new EmployeeWorkPatternService();
