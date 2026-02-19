import BaseService from "@/services/BaseService.js";

class WorkShiftAssignmentService extends BaseService {
    list(workShiftId) {
        return this.get(`/work_shifts/${workShiftId}/assignments`);
    }

    assign(workShiftId, params) {
        return this.post(`/work_shifts/${workShiftId}/assignments`, params);
    }

    unassign(workShiftId, id) {
        return this.delete(`/work_shifts/${workShiftId}/assignments/${id}`);
    }
}

export default new WorkShiftAssignmentService();
