import BaseService from "@/services/BaseService";

class EmployeeWorkPatternService extends BaseService {
    getList(employeeId) {
        return this.get(`/employees/${employeeId}/work-patterns`);
    }

    assign(employeeId, params) {
        return this.post(`/employees/${employeeId}/work-patterns/assign`, params);
    }

    update(employeeId, id, params) {
        return this.put(`/employees/${employeeId}/work-patterns/${id}`, params);
    }

    unassign(employeeId, id) {
        return this.delete(`/employees/${employeeId}/work-patterns/${id}`);
    }
}

export default new EmployeeWorkPatternService();
