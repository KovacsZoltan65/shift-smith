import BaseService from "@/services/BaseService.js";

class UserSettingsService extends BaseService {
    fetch(params = {}) {
        return this.get(route("admin.user_settings.fetch", params));
    }

    show(id, params = {}) {
        return this.get(route("admin.user_settings.show", id), { params });
    }

    store(payload) {
        return this.post(route("admin.user_settings.store"), payload);
    }

    update(id, payload) {
        return this.put(route("admin.user_settings.update", id), payload);
    }

    destroy(id, params = {}) {
        return this.delete(route("admin.user_settings.destroy", id), { data: params });
    }

    bulkDestroy(ids, userId = null) {
        return this.delete(route("admin.user_settings.destroy_bulk"), {
            data: { ids, ...(userId ? { user_id: userId } : {}) },
        });
    }
}

export default new UserSettingsService();
