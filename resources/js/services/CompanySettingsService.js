import BaseService from "@/services/BaseService.js";

class CompanySettingsService extends BaseService {
    fetch(params = {}) {
        return this.get(route("admin.company_settings.fetch", params));
    }

    show(id) {
        return this.get(route("admin.company_settings.show", id));
    }

    store(payload) {
        return this.post(route("admin.company_settings.store"), payload);
    }

    update(id, payload) {
        return this.put(route("admin.company_settings.update", id), payload);
    }

    destroy(id) {
        return this.delete(route("admin.company_settings.destroy", id));
    }

    bulkDestroy(ids) {
        return this.delete(route("admin.company_settings.destroy_bulk"), {
            data: { ids },
        });
    }

    effective(params = {}) {
        return this.get(route("admin.company_settings.effective", params));
    }
}

export default new CompanySettingsService();
