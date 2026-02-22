import BaseService from "@/services/BaseService.js";

class PositionService extends BaseService {
    constructor() {
        super();
        this.url = "positions";
    }

    getPositions(params = {}) {
        return this.get(`${this.url}/fetch`, { params });
    }

    storePosition(params) {
        return this.post(route(`${this.url}.store`), params);
    }

    updatePosition(id, params) {
        return this.put(route(`${this.url}.update`, id), params);
    }

    deletePositions(ids, companyId) {
        return this.delete(route(`${this.url}.destroy_bulk`), {
            data: { ids, company_id: companyId },
        });
    }

    deletePosition(id, companyId) {
        return this.delete(route(`${this.url}.destroy`, id), {
            data: { company_id: companyId },
        });
    }

    getToSelect(params = {}) {
        return this.get(route("selectors.positions"), { params });
    }
}

export default new PositionService();
