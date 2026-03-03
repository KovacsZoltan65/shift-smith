import BaseService from "@/services/BaseService.js";

class LeaveCategoryService extends BaseService {
    fetch(params = {}) {
        return this.get(route("admin.leave_categories.fetch"), { params });
    }

    show(id) {
        return this.get(route("admin.leave_categories.show", id));
    }

    store(payload) {
        return this.post(route("admin.leave_categories.store"), payload);
    }

    update(id, payload) {
        return this.put(route("admin.leave_categories.update", id), payload);
    }

    destroy(id) {
        return this.delete(route("admin.leave_categories.destroy", id));
    }

    selector(params = {}) {
        return this.get(route("admin.leave_categories.selector"), { params });
    }
}

export default new LeaveCategoryService();
