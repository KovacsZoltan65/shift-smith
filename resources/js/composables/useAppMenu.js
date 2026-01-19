import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

/**
 * Központi menüdefiníció:
 * - title: megjelenített név
 * - route: Ziggy route name
 * - can: permission/policy kulcs (opcionális)
 * - icon: később (PrimeVue/lucide) bővíthető
 */

export function useAppMenu() {
    const page = usePage();

    const menu = computed(() => [
        {
            title: "Adminisztráció",
            items: [
                {
                    title: "Felhasználók",
                    route: "users.index",
                    can: "users.viewAny",
                },
                {
                    title: "Cégek",
                    route: "companies.index",
                    can: "companies.viewAny",
                },
            ],
        },
        {
            title: "Biztonság",
            items: [
                {
                    title: "Jogok",
                    route: "permissions.index",
                    can: "permissions.viewAny",
                },
                {
                    title: "Szerepkörök",
                    route: "roles.index",
                    can: "roles.viewAny",
                },
            ],
        },
        {
            title: "HR",
            items: [
                {
                    title: "Dolgozók",
                    route: "employees.index",
                    can: "employees.viewAny",
                },
                {
                    title: "Beosztások",
                    route: "assignments.index",
                    can: "assignments.viewAny",
                },
                {
                    title: "Műszakok",
                    route: "shifts.index",
                    can: "shifts.viewAny",
                },
                {
                    title: "Tervezés",
                    route: "planning.index",
                    can: "planning.view",
                },
            ],
        },
        {
            title: "Beállítások",
            items: [
                {
                    title: "Applikáció",
                    route: "settings.app",
                    can: "settings.app",
                },
                {
                    title: "Cég",
                    route: "settings.company",
                    can: "settings.company",
                },
                {
                    title: "Személy",
                    route: "settings.user",
                    can: "settings.user",
                },
            ],
        },
    ]);

    const filteredMenu = computed(() => {
        const perms = page.props.auth?.permissions ?? [];
        const roles = page.props.auth?.roles ?? [];

        // Ha superadmin szerep: mindent lásson (UI szinten)
        if (Array.isArray(roles) && roles.includes("superadmin")) {
            return menu.value;
        }

        // Ha nincs joglista: mindent mutatunk (dev fázis)
        if (!Array.isArray(perms) || perms.length === 0) {
            return menu.value;
        }

        console.log("perms", perms);

        console.log("roles", roles);

        console.log("menu.value", menu.value);

        return menu.value;

        /*
        return menu.value
            .map(group => ({
                ...group,
                items: group.items.filter(i => !i.can || perms.includes(i.can)),
            }))
            .filter(group => group.items.length > 0);
        */
    });

    return { menu, filteredMenu };
}
