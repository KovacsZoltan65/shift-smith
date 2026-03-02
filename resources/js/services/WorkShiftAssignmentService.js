import BaseService from "@/services/BaseService.js";
import { csrfFetch } from "@/lib/csrfFetch.js";

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

class WorkShiftAssignmentService extends BaseService {
    list(workShiftId) {
        return this.get(`/work_shifts/${workShiftId}/assignments`);
    }

    listSchedules(workShiftId) {
        return this.get(`/work_shifts/${workShiftId}/assignments/schedules`);
    }

    async assign(workShiftId, params) {
        const res = await csrfFetch(`/work_shifts/${workShiftId}/assignments`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(params),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Dolgozó hozzárendelése sikertelen.");
        }

        return { data: await res.json() };
    }

    async unassign(workShiftId, id) {
        const res = await csrfFetch(`/work_shifts/${workShiftId}/assignments/${id}`, {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Hozzárendelés törlése sikertelen.");
        }

        return { data: await res.json() };
    }
}

export default new WorkShiftAssignmentService();
