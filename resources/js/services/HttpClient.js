import axios from "axios";
import { CONFIG } from "@/helpers/config.js";

export const apiClient = axios.create({
    baseURL: CONFIG.BASE_URL,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    withCredentials: true,
});
