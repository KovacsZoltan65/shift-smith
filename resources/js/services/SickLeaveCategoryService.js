import BaseService from "@/services/BaseService.js";

class SickLeaveCategoryService extends BaseService {
    fetch(params = {}) {
        return this.get(route("admin.sick_leave_categories.fetch"), { params });
    }

    show(id) {
        return this.get(route("admin.sick_leave_categories.show", id));
    }

    store(payload) {
        return this.post(route("admin.sick_leave_categories.store"), payload);
    }

    update(id, payload) {
        return this.put(route("admin.sick_leave_categories.update", id), payload);
    }

    destroy(id) {
        return this.delete(route("admin.sick_leave_categories.destroy", id));
    }

    selector(params = {}) {
        return this.get(route("admin.sick_leave_categories.selector"), { params });
    }
}

export default new SickLeaveCategoryService();
