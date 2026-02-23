import BaseService from "@/services/BaseService.js";

class WorkShiftService extends BaseService {
    constructor() {
        super();
        this.url = "work_shifts";
    }

    getWorkShifts(params = {}) {
        return this.get(`${this.url}/fetch`, { params });
    }

    storeWorkShift(params) {
        return this.post(route(`${this.url}.store`), params);
    }

    updateWorkShift(id, params) {
        return this.put(route(`${this.url}.update`, id), params);
    }

    deleteWorkShifts(ids) {
        return this.delete(route(`${this.url}.destroy_bulk`), {
            data: { ids },
        });
    }

    deleteWorkShift(id) {
        return this.delete(route(`${this.url}.destroy`, id));
    }

    getToSelect(params = {}) {
        return this.get(route("selectors.work_shifts"), { params });
    }
}

export default new WorkShiftService();
