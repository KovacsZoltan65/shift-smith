import BaseService from "@/services/BaseService.js";

class CompanyService extends BaseService {
    constructor() {
        super();
        this.url = "companies";
    }

    getCompanies(params = {}) {
        return this.get(`${this.url}/fetch`, { params });
    }

    storeCompany(params) {
        return this.post(route(`${this.url}.store`), params);
    }

    updateCompany(id, params) {
        return this.put(route(`${this.url}.update`, id), params);
    }

    deleteCompanies(ids) {
        return this.delete(route(`${this.url}.delete.bulk`), { data: { ids } });
    }

    deleteCompany(id) {
        return this.delete(route(`${this.url}.delete`, id));
    }

    restoreCompany(id) {
        return this.put(route(`${this.url}.restore`, id));
    }

    forceDeleteCompany(id) {
        return this.delete(route(`${this.url}.force-delete`, id));
    }

    getToSelect(params = {}) {
        return this.get(`${this.url}/to_select`, { params });
    }
}

export default new CompanyService();
