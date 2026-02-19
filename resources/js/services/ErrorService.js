import { v4 as uuidv4 } from "uuid";
import { apiClient } from "@/services/HttpClient.js";

class ErrorService {
    getLogs(params = {}) {
        return apiClient.get(route(`activity_logs.fetch`), params);
    }

    logClientError(error, additionalData = {}) {
        const payload = {
            message: error.message,
            stack: error.stack,
            component: error.component || "Unknown",
            category: additionalData.category || "unknown_error",
            priority: additionalData.priority || "low",
            data: additionalData.data || null,
            info: error.info || "No additional info",
            additionalInfo: additionalData.additionalInfo || null, // Külön mezőként kerül mentésre
            time: new Date().toISOString(),
            route: window.location.pathname,
            url: window.location.href,
            userAgent: navigator.userAgent,
            uniqueErrorId: uuidv4(), // Egyedi azonosító generálása kliens oldalon
            ...additionalData,
        };

        try {
            if (typeof route !== "function") {
                return Promise.resolve(null);
            }

            const ziggy = route();
            if (typeof ziggy?.has === "function" && !ziggy.has("client-errors.store")) {
                return Promise.resolve(null);
            }

            return apiClient.post(route("client-errors.store"), payload);
        } catch (_) {
            return Promise.resolve(null);
        }
    }
}

export default new ErrorService();
