import BaseService from "@/services/BaseService.js";

// Landlord-only API wrapper a TenantGroup CRUD végpontokhoz.
// A domain entitások továbbra is company-scope alatt maradnak, ez a service csak HQ route-okat hív.
class TenantGroupService extends BaseService {
    constructor() {
        super();
        this.routeBase = "hq.tenant_groups";
    }

    // Az aszinkron datatable payloadot kéri le; a backend query stringben várja a szűrési és rendezési paramétereket.
    fetch(params = {}) {
        return this.get(route(`${this.routeBase}.fetch`), { params });
    }

    show(id) {
        return this.get(route(`${this.routeBase}.show`, id));
    }

    store(payload) {
        return this.post(route(`${this.routeBase}.store`), payload);
    }

    update(id, payload) {
        return this.put(route(`${this.routeBase}.update`, id), payload);
    }

    destroy(id) {
        return this.delete(route(`${this.routeBase}.destroy`, id));
    }
}

export default new TenantGroupService();
