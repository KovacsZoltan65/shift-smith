import BaseService from "@/services/BaseService.js";

class WorkShiftService extends BaseService {
    getWorkShifts(params = {}) {
        return this.get(route("work_shifts.fetch"), { params });
    }

    getWorkShift(id) {
        return this.get(route("work_shifts.by_id", id));
    }

    storeWorkShift(params) {
        return this.post(route("work_shifts.store"), params);
    }

    updateWorkShift(id, params) {
        return this.put(route("work_shifts.update", id), params);
    }

    deleteWorkShifts(ids) {
        return this.delete(route("work_shifts.destroy_bulk"), {
            data: { ids },
        });
    }

    deleteWorkShift(id) {
        return this.delete(route("work_shifts.destroy", id));
    }

    getToSelect(params = {}) {
        return this.get(route("selectors.work_shifts"), { params });
    }
}

export default new WorkShiftService();
