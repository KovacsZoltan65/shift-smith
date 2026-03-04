import BaseService from "@/services/BaseService";

class WorkScheduleService extends BaseService {
    constructor() {
        super();
        this.url = "work-schedules";
    }

    getWorkSchedules(params = {}) {
        return this.get(`${this.url}/fetch`, { params });
    }

    getWorkSchedule(id, params = {}) {
        return this.get(route("work_schedules.show", id), { params });
    }

    storeWorkSchedule(payload) {
        return this.post(route("work_schedules.store"), payload);
    }

    updateWorkSchedule(id, payload) {
        return this.put(route("work_schedules.update", id), payload);
    }

    deleteWorkSchedule(id, companyId) {
        return this.delete(route("work_schedules.destroy", id), {
            data: { company_id: companyId },
        });
    }

    deleteWorkSchedules(ids, companyId) {
        return this.delete(route("work_schedules.destroy_bulk"), {
            data: { ids, company_id: companyId },
        });
    }

    getToSelect(params = {}) {
        return this.get(route("work_schedules.selector"), { params });
    }
}

export default new WorkScheduleService();
