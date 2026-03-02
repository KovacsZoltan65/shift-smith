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

class AbsenceService extends BaseService {
    fetch(params = {}) {
        return this.get(route("admin.absences.fetch", params));
    }

    async store(payload) {
        const res = await csrfFetch(route("admin.absences.store", undefined, false), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Távollét mentése sikertelen.");
        }

        return { data: await res.json() };
    }

    async update(id, payload) {
        const res = await csrfFetch(route("admin.absences.update", { id }, false), {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Távollét frissítése sikertelen.");
        }

        return { data: await res.json() };
    }

    async destroy(id) {
        const res = await csrfFetch(route("admin.absences.destroy", { id }, false), {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Távollét törlése sikertelen.");
        }

        return { data: await res.json() };
    }
}

export default new AbsenceService();
