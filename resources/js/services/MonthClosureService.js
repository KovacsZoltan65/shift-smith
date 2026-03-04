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

class MonthClosureService {
    async close(payload) {
        const res = await csrfFetch(route("scheduling.month_closures.store", undefined, false), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "A hónap lezárása sikertelen.");
        }

        return { data: await res.json() };
    }

    async reopen(id) {
        const res = await csrfFetch(route("scheduling.month_closures.destroy", { id }, false), {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
        });

        if (!res.ok) {
            throw await toAxiosLikeError(res, "A hónap újranyitása sikertelen.");
        }

        return { data: await res.json() };
    }
}

export default new MonthClosureService();
