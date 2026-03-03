import BaseService from "@/services/BaseService.js";

class LeaveTypeService extends BaseService {
    constructor() {
        super();
        this.url = "admin/leave-types";
    }

    fetch(params = {}) {
        return this.get(route("admin.leave_types.fetch"), { params });
    }

    show(id) {
        return this.get(route("admin.leave_types.show", id));
    }

    store(payload) {
        return this.post(route("admin.leave_types.store"), payload);
    }

    update(id, payload) {
        return this.put(route("admin.leave_types.update", id), payload);
    }

    destroy(id) {
        return this.delete(route("admin.leave_types.destroy", id));
    }

    selector(params = {}) {
        return this.get(route("admin.leave_types.selector"), { params });
    }
}

export default new LeaveTypeService();
