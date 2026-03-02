import BaseService from "@/services/BaseService.js";

class LeaveTypeService extends BaseService {
    fetch(params = {}) {
        return this.get(route("admin.leave_types.fetch", params));
    }
}

export default new LeaveTypeService();
