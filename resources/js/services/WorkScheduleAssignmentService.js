import BaseService from "@/services/BaseService.js";

class WorkScheduleAssignmentService extends BaseService {
    fetch(scheduleId, params = {}) {
        return this.get(`/work-schedules/${scheduleId}/assignments/fetch`, { params });
    }

    store(scheduleId, payload) {
        return this.post(`/work-schedules/${scheduleId}/assignments`, payload);
    }

    update(scheduleId, id, payload) {
        return this.put(`/work-schedules/${scheduleId}/assignments/${id}`, payload);
    }

    destroy(scheduleId, id) {
        return this.delete(`/work-schedules/${scheduleId}/assignments/${id}`);
    }

    bulkDestroy(scheduleId, ids) {
        return this.delete(`/work-schedules/${scheduleId}/assignments/destroy_bulk`, {
            data: { ids },
        });
    }
}

export default new WorkScheduleAssignmentService();
