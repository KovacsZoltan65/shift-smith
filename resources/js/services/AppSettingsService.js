import BaseService from "@/services/BaseService.js";

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

    store(payload) {
        return this.post(route("admin.app_settings.store"), payload);
    }

    update(id, payload) {
        return this.put(route("admin.app_settings.update", id), payload);
    }

    destroy(id) {
        return this.delete(route("admin.app_settings.destroy", id));
    }

    bulkDestroy(ids) {
        return this.delete(route("admin.app_settings.destroy_bulk"), {
            data: { ids },
        });
    }
}

export default new AppSettingsService();
