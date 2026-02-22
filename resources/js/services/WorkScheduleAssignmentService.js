import BaseService from "@/services/BaseService.js";

class WorkScheduleAssignmentService extends BaseService {
    getCalendarFeed(params = {}) {
        return this.get(route("scheduling.calendar.feed"), { params });
    }

    createAssignment(payload) {
        return this.post(route("work_schedule_assignments.store"), payload);
    }

    updateAssignment(id, payload) {
        return this.put(route("work_schedule_assignments.update", id), payload);
    }

    deleteAssignment(id) {
        return this.delete(route("work_schedule_assignments.destroy", id));
    }

    bulkUpsert(payload) {
        return this.post(route("work_schedule_assignments.bulk_upsert"), payload);
    }
}

export default new WorkScheduleAssignmentService();
