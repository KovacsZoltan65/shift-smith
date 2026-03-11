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

    getEmployee(id) {
        return this.get(route(`${this.url}.by_id`, id));
    }

    assignSupervisor(employeeId, payload) {
        return this.post(route(`${this.url}.supervisor.assign`, employeeId), payload);
    }

    getDeletePreview(id, params = {}) {
        return this.get(route(`${this.url}.delete_preview`, id), { params });
    }

    // BULK DELETE – body a config.data-ban
    deleteEmployees(ids) {
        return this.delete(route(`${this.url}.destroy_bulk`), {
            data: { ids },
        });
    }

    deleteEmployee(id, params = {}) {
        return this.delete(route(`${this.url}.destroy`, id), {
            data: params,
        });
    }

    //restoreEmployee(id) {
    //    return this.put(route(`${this.url}.restore`, id));
    // }

    //forceDeleteEmployee(id) {
    //    return this.delete(route(`${this.url}.force-delete`, id));
    //}

    getToSelect(params = {}) {
        return this.get(route("selectors.employees"), { params });
    }

    getEligibleForAutoPlan(params = {}) {
        return this.get(route("employees.selector"), {
            params: {
                eligible_for_autoplan: 1,
                required_daily_minutes: 480,
                ...params,
            },
        });
    }

    exportEmployees(format, params = {}) {
        return this.apiClient.get(route(`${this.url}.export`, format), {
            params,
            responseType: "blob",
        });
    }

    downloadEmployeeTemplate(format) {
        return this.apiClient.get(route(`${this.url}.template`, format), {
            responseType: "blob",
        });
    }

    importEmployees(file, format) {
        const formData = new FormData();
        formData.append("file", file);

        return this.post(route(`${this.url}.import`, format), formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });
    }

    saveDownload(response, fallbackFileName) {
        const blob = response?.data;
        const disposition = response?.headers?.["content-disposition"] ?? "";
        const fileNameMatch = disposition.match(/filename="?([^"]+)"?/i);
        const fileName = fileNameMatch?.[1] ?? fallbackFileName;
        const downloadUrl = window.URL.createObjectURL(blob);
        const anchor = document.createElement("a");

        anchor.href = downloadUrl;
        anchor.download = fileName;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.URL.revokeObjectURL(downloadUrl);
    }
}

export default new EmployeeService();
