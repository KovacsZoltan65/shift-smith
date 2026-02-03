import BaseService from "./BaseService";

class EmployeeService extends BaseService {
    constructor() {
        super();
        this.url = "employees";
    }

    getEmployees(params = {}) {
        //return this.get(`${this.url}/fetch`, { params });
        return this.get(route(`${this.url}.fetch`), { params });
    }

    storeEmployee(params) {
        return this.post(route(`${this.url}.store`), params);
    }

    updateEmployee(id, params) {
        //console.log( 'id', id, 'params', params );
        return this.put(route(`${this.url}.update`, id), params);
    }

    // BULK DELETE – body a config.data-ban
    deleteEmployees(ids) {
        return this.delete(route(`${this.url}.delete.bulk`), { data: { ids } });
    }

    deleteEmployee(id) {
        return this.delete(route(`${this.url}.delete`, id));
    }

    restoreEmployee(id) {
        return this.put(route(`${this.url}.restore`, id));
    }

    forceDeleteEmployee(id) {
        return this.delete(route(`${this.url}.force-delete`, id));
    }

    getEmployeesToSelect(params = {}) {
        return this.get(`${this.url}/to_select`, { params });
    }
}

export default new EmployeeService();
