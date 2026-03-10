import BaseService from "@/services/BaseService.js";
import { csrfFetch } from "@/lib/csrfFetch.js";

/**
 * A naptáras beosztás-hozzárendelés API wrapper rétege.
 *
 * A Scheduling/Calendar modul ezt a service-t használja a feed lekéréséhez,
 * valamint az egyedi és csoportos műveletekhez. A hibákat egységes, axios-szerű
 * formára alakítja, hogy a komponensek azonos szerződésre támaszkodhassanak.
 */
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

class WorkScheduleAssignmentService extends BaseService {
    /**
     * A naptár feed végpontját hívja a kiválasztott schedule és szűrők alapján.
     */
    getCalendarFeed(params = {}) {
        return this.get(route("scheduling.calendar.feed", undefined, false), { params });
    }

    /**
     * Új beosztás-hozzárendelést hoz létre a naptár planner műveletből.
     */
    async createAssignment(payload) {
        const res = await csrfFetch(route("work_schedule_assignments.store", undefined, false), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Mentés sikertelen.");
        }

        return { data: await res.json() };
    }

    /**
     * Meglévő beosztás-hozzárendelést frissít drag&drop vagy modal mentés után.
     */
    async updateAssignment(id, payload) {
        const res = await csrfFetch(route("work_schedule_assignments.update", { id }, false), {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Frissítés sikertelen.");
        }

        return { data: await res.json() };
    }

    /**
     * Egyetlen beosztás-hozzárendelést töröl a naptárból.
     */
    async deleteAssignment(id) {
        const res = await csrfFetch(route("work_schedule_assignments.destroy", { id }, false), {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Törlés sikertelen.");
        }

        return { data: await res.json() };
    }

    /**
     * Csoportos hozzárendelést végez több dolgozóra és dátumra.
     */
    async bulkUpsert(payload) {
        const res = await csrfFetch(route("work_schedule_assignments.bulk_upsert", undefined, false), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "Bulk mentés sikertelen.");
        }

        return { data: await res.json() };
    }
}

export default new WorkScheduleAssignmentService();
