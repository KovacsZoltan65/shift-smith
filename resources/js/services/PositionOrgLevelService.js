import BaseService from "@/services/BaseService.js";

class PositionOrgLevelService extends BaseService {
    getMappings(params = {}) {
        return this.get(route("admin.position_org_levels.fetch"), { params });
    }

    storeMapping(payload) {
        return this.post(route("admin.position_org_levels.store"), payload);
    }

    updateMapping(id, payload) {
        return this.put(route("admin.position_org_levels.update", id), payload);
    }

    deleteMapping(id) {
        return this.delete(route("admin.position_org_levels.destroy", id));
    }
}

export default new PositionOrgLevelService();

