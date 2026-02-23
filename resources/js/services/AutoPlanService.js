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

class AutoPlanService extends BaseService {
    getDefaults() {
        return this.get(route("scheduling.work_schedules.autoplan.defaults", undefined, false));
    }

    async generate(payload) {
        const res = await csrfFetch(route("scheduling.work_schedules.autoplan.generate", undefined, false), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "AutoPlan generálás sikertelen.");
        }

        return { data: await res.json() };
    }
}

export default new AutoPlanService();
