import BaseService from "@/services/BaseService.js";
import { csrfFetch } from "@/lib/csrfFetch.js";

class WorkShiftService extends BaseService {
    async mutate(url, options = {}) {
        const response = await csrfFetch(url, {
            ...options,
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
                ...(options.headers ?? {}),
            },
        });

        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(
                body?.message || `Request failed (HTTP ${response.status})`,
            );
            error.response = {
                status: response.status,
                data: body,
            };
            error.normalizedErrors = body?.errors ?? null;
            throw error;
        }

        return {
            status: response.status,
            data: body,
        };
    }

    getWorkShifts(params = {}) {
        return this.get(route("work_shifts.fetch"), { params });
    }

    getWorkShift(id) {
        return this.get(route("work_shifts.by_id", id));
    }

    storeWorkShift(params) {
        return this.mutate(route("work_shifts.store"), {
            method: "POST",
            body: JSON.stringify(params),
        });
    }

    updateWorkShift(id, params) {
        return this.mutate(route("work_shifts.update", id), {
            method: "PUT",
            body: JSON.stringify(params),
        });
    }

    deleteWorkShifts(ids) {
        return this.mutate(route("work_shifts.destroy_bulk"), {
            method: "DELETE",
            body: JSON.stringify({ ids }),
        });
    }

    deleteWorkShift(id) {
        return this.mutate(route("work_shifts.destroy", id), {
            method: "DELETE",
        });
    }

    getToSelect(params = {}) {
        return this.get(route("selectors.work_shifts"), { params });
    }
}

export default new WorkShiftService();
