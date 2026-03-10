import BaseService from "@/services/BaseService.js";
import { csrfFetch } from "@/lib/csrfFetch.js";

class AppSettingsService extends BaseService {
    constructor() {
        super();
        this.url = "admin/app-settings";
    }

    fetch(params = {}) {
        return this.get(route("admin.app_settings.fetch", params));
    }

    show(id) {
        return this.get(route("admin.app_settings.show", id));
    }

    async store(payload) {
        return this.csrfJson(route("admin.app_settings.store"), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });
    }

    async update(id, payload) {
        return this.csrfJson(route("admin.app_settings.update", id), {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });
    }

    async destroy(id) {
        return this.csrfJson(route("admin.app_settings.destroy", id), {
            method: "DELETE",
        });
    }

    async bulkDestroy(ids) {
        return this.csrfJson(route("admin.app_settings.destroy_bulk"), {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ids }),
        });
    }

    async csrfJson(url, options = {}) {
        const response = await csrfFetch(url, options);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw {
                response: {
                    status: response.status,
                    data,
                },
            };
        }

        return { data };
    }
}

export default new AppSettingsService();
