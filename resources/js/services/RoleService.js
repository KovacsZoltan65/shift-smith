import apiClient from "@/services/HttpClient.js";

export default {
    fetch(params = {}) {
        return apiClient.get(route("admin.roles.fetch"), { params });
    },

    store(payload) {
        return apiClient.post(route("admin.roles.store"), payload);
    },

    update(id, payload) {
        return apiClient.put(route("admin.roles.update", { id }), payload);
    },

    destroy(id) {
        return apiClient.delete(route("admin.roles.destroy", { id }));
    },

    destroyBulk(ids) {
        return apiClient.delete(route("admin.roles.destroy_bulk"), {
            data: { ids },
        });
    },

    getToSelect(params = {}) {
        return apiClient.get(route("selectors.roles"), { params });
    },

    getPermissionsToSelect(params = {}) {
        return apiClient.get(route("selectors.permissions"), { params });
    },
};
