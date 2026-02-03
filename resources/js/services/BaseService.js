import ErrorService from "@/services/ErrorService.js";
import { apiClient } from "@/services/HttpClient.js";

class BaseService {
    static _interceptorInstalled = false;

    constructor() {
        this.apiClient = apiClient;

        if (!BaseService._interceptorInstalled) {
            this.apiClient.interceptors.response.use(
                (response) => response,
                (error) => {
                    // Enrich error, de NE változtasd az alakját
                    const status = error?.response?.status;
                    const data = error?.response?.data;

                    if (data?.errors && status === 422) {
                        // adjunk “normalizedErrors”-t, hogy a komponens egyszerűen olvassa
                        error.normalizedErrors = data.errors;
                    }

                    // Kliens oldali log
                    if (error.response) {
                        ErrorService.logClientError(error, {
                            category: "api_error",
                            data: {
                                method: error.config?.method,
                                url: error.config?.url,
                                params: error.config?.params,
                                data: error.config?.data,
                                status,
                            },
                        });
                    }

                    return Promise.reject(error); // <- mindig az eredeti error megy tovább
                },
            );
            BaseService._interceptorInstalled = true;
        }
    }

    extractErrors(error) {
        return error?.normalizedErrors || error?.response?.data?.errors || null;
    }

    get(url, config = {}) {
        return this.apiClient.get(url, config);
    }
    post(url, data, config = {}) {
        return this.apiClient.post(url, data, config);
    }
    put(url, data, config = {}) {
        return this.apiClient.put(url, data, config);
    }

    delete(url, config = {}) {
        return this.apiClient.delete(url, {
            ...config,
            data: config.data || {},
        });
    }
}

export default BaseService;
