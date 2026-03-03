import BaseService from "@/services/BaseService.js";

class SickLeaveCategoryService extends BaseService {
    selector(params = {}) {
        return this.get(route("admin.sick_leave_categories.selector"), { params });
    }
}

export default new SickLeaveCategoryService();
