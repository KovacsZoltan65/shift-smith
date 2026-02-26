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
    error.response = {
        status: res.status,
        data,
    };

    return error;
};

class WorkShiftService extends BaseService {
    getWorkShifts(params = {}) {
        return this.get(route("work_shifts.fetch"), { params });
    }

    getWorkShift(id) {
        return this.get(route("work_shifts.by_id", id));
    }

    async storeWorkShift(params) {
        const res = await csrfFetch(route("work_shifts.store", undefined, false), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(params),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Mentés sikertelen.");
        }

        return { data: await res.json() };
    }

    async updateWorkShift(id, params) {
        const res = await csrfFetch(route("work_shifts.update", { id }, false), {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(params),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Frissítés sikertelen.");
        }

        return { data: await res.json() };
    }

    async deleteWorkShifts(ids) {
        const res = await csrfFetch(route("work_shifts.destroy_bulk", undefined, false), {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ids }),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Bulk törlés sikertelen.");
        }

        return { data: await res.json() };
    }

    async deleteWorkShift(id) {
        const res = await csrfFetch(route("work_shifts.destroy", { id }, false), {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Törlés sikertelen.");
        }

        return { data: await res.json() };
    }

    getToSelect(params = {}) {
        return this.get(route("selectors.work_shifts"), { params });
    }
}

export default new WorkShiftService();
