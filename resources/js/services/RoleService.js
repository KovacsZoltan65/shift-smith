import BaseService from "@/services/BaseService.js";

class RoleService extends BaseService {
    constructor() {
        super();
        this.url = "roles";
    }

    getRoles(params = {}) {
        return this.get(`${this.url}/fetch`, { params });
    }

    storeRole(params) {
        return this.post(route(`${this.url}.store`), params);
    }

    updateRole(id, params) {
        return this.put(route(`${this.url}.update`, id), params);
    }

    deleteRoles(ids) {
        return this.delete(route(`${this.url}.delete.bulk`), { data: { ids } });
    }

    deleteRole(id) {
        return this.delete(route(`${this.url}.delete`, id));
    }

    restoreRole(id) {
        return this.put(route(`${this.url}.restore`, id));
    }

    forceDeleteRole(id) {
        return this.delete(route(`${this.url}.force-delete`, id));
    }

    getToSelect(params = {}) {
        return this.get(`${this.url}/to_select`, { params });
    }
}

export default new RoleService();
