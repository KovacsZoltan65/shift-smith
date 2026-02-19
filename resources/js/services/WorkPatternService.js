import BaseService from "@/services/BaseService";

class WorkPatternService extends BaseService {
    constructor() {
        super();
        this.url = "work-patterns";
    }

    getWorkPatterns(params = {}) {
        return this.get(`${this.url}/fetch`, { params });
    }

    storeWorkPattern(params) {
        return this.post(route("work_patterns.store"), params);
    }

    updateWorkPattern(id, params) {
        return this.put(route("work_patterns.update", id), params);
    }

    deleteWorkPattern(id) {
        return this.delete(route("work_patterns.destroy", id));
    }

    deleteWorkPatterns(ids) {
        return this.delete(route("work_patterns.destroy_bulk"), { data: { ids } });
    }

    getToSelect(params = {}) {
        return this.get("selectors/work-patterns", { params });
    }
}

export default new WorkPatternService();
