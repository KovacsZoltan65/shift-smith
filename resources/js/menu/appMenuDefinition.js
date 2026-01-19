// resources/js/menu/appMenuDefinition.js

export const appMenuDefinition = [
    {
        title: "Főoldal",
        items: [{ title: "Dashboard", route: "dashboard", key: "dashboard" }],
    },
    {
        title: "Adminisztráció",
        items: [
            { title: "Menü", route: "menu.index", key: "menu.index" },
            {
                title: "Felhasználók",
                route: "users.index",
                key: "users.index",
                can: "users.viewAny",
            },
            {
                title: "Cégek",
                route: "companies.index",
                key: "companies.index",
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
                key: "permissions.index",
                can: "permissions.viewAny",
            },
            {
                title: "Szerepkörök",
                route: "roles.index",
                key: "roles.index",
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
                key: "employees.index",
                can: "employees.viewAny",
            },
            {
                title: "Beosztások",
                route: "assignments.index",
                key: "assignments.index",
                can: "assignments.viewAny",
            },
            {
                title: "Műszakok",
                route: "shifts.index",
                key: "shifts.index",
                can: "shifts.viewAny",
            },
            {
                title: "Tervezés",
                route: "planning.index",
                key: "planning.index",
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
                key: "settings.app",
                can: "settings.app",
            },
            {
                title: "Cég",
                route: "settings.company",
                key: "settings.company",
                can: "settings.company",
            },
            {
                title: "Személy",
                route: "settings.user",
                key: "settings.user",
                can: "settings.user",
            },
        ],
    },
];
