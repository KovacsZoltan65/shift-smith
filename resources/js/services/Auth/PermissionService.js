import BaseService from "@/services/BaseService.js";

class PermissionService extends BaseService {
    constructor() {
        super();
        this.url = "permissions";
    }

    getPermissions(params = {}) {
        return this.get(`${this.url}/fetch`, { params });
    }

    storePermission(params) {
        return this.post(route(`${this.url}.store`), params);
    }

    updatePermission(id, params) {
        return this.put(route(`${this.url}.update`, id), params);
    }

    deletePermissions(ids) {
        return this.delete(route(`${this.url}.delete.bulk`), { data: { ids } });
    }

    deletePermission(id) {
        return this.delete(route(`${this.url}.delete`, id));
    }

    restorePermission(id) {
        return this.put(route(`${this.url}.restore`, id));
    }

    forceDeletePermission(id) {
        return this.delete(route(`${this.url}.force-delete`, id));
    }

    getToSelect(params = {}) {
        return this.get(`${this.url}/to_select`, { params });
    }
}

export default new PermissionService();
