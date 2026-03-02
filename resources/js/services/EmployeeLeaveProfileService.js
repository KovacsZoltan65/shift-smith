import BaseService from "./BaseService";

class EmployeeLeaveProfileService extends BaseService {
    getProfile(employeeId) {
        return this.get(route("employees.leave_profile.show", { id: employeeId }));
    }

    updateProfile(employeeId, payload) {
        return this.put(route("employees.leave_profile.update", { id: employeeId }), payload);
    }

    getEntitlement(employeeId) {
        return this.get(route("employees.leave_entitlement", { id: employeeId }));
    }
}

export default new EmployeeLeaveProfileService();
