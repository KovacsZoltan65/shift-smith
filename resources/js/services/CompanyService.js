import BaseService from "@/services/BaseService.js";

/**
 * Company API wrapper a Companies index és a selector komponensek számára.
 *
 * A service a tenant-scoped CRUD és selector végpontokat fogja össze,
 * hogy a komponenseknek ne kelljen route- és payload-részleteket ismerniük.
 */
class CompanyService extends BaseService {
    constructor() {
        super();
        this.url = "companies";
    }

    /**
     * Lapozott céglistát kér le a Companies/Index.vue DataTable számára.
     */
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
        return this.delete(route(`${this.url}.destroy_bulk`), {
            data: { ids },
        });
    }

    deleteCompany(id) {
        return this.delete(route(`${this.url}.destroy`, id));
    }

    //restoreCompany(id) {
    //    return this.put(route(`${this.url}.restore`, id));
    //}

    //forceDeleteCompany(id) {
    //    return this.delete(route(`${this.url}.force-delete`, id));
    //}

    /**
     * Cég selector adatot kér le dropdown és form mezők számára.
     */
    getToSelect(params = {}) {
        return this.get(route("selectors.companies"), { params });
    }
}

export default new CompanyService();
