import axios from "axios";
import { CONFIG } from "@/helpers/config.js";

const readMetaCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const readCookie = (name) => {
    const match = document.cookie.match(
        new RegExp(`(?:^|; )${name.replace(/[$()*+./?[\\\]^{|}-]/g, "\\$&")}=([^;]*)`),
    );
    return match ? decodeURIComponent(match[1]) : "";
};

export const apiClient = axios.create({
    baseURL: CONFIG.BASE_URL,
    timeout: CONFIG.TIMEOUT,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
    withCredentials: true,
});

apiClient.interceptors.request.use((config) => {
    config.headers = config.headers ?? {};

    const csrf = readMetaCsrfToken();
    const xsrf = readCookie("XSRF-TOKEN");

    if (csrf) {
        config.headers["X-CSRF-TOKEN"] = csrf;
    }

    if (xsrf) {
        config.headers["X-XSRF-TOKEN"] = xsrf;
    }

    return config;
});
