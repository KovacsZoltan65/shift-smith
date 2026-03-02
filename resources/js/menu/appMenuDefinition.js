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
                title: "Felhasználó hozzárendelések",
                route: "admin.user_assignments.index",
                key: "admin.user_assignments.index",
                can: "user_assignments.viewAny",
            },
            {
                title: "Cégek",
                route: "companies.index",
                key: "companies.index",
                can: "companies.viewAny",
            },
            {
                title: "HQ Cégek",
                route: "hq.companies.index",
                key: "hq.companies.index",
                can: "hq.companies.view",
            },
        ],
    },
    {
        title: "Biztonság",
        items: [
            {
                title: "Szabályok",
                route: "admin.permissions.index",
                key: "permissions.index",
                can: "permissions.viewAny",
            },
            {
                title: "Szerepkörök",
                route: "admin.roles.index",
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
                title: "Positions",
                route: "positions.index",
                key: "positions.index",
                can: "positions.viewAny",
            },
            {
                title: "Műszakok",
                route: "work_shifts.index",
                key: "work_shifts.index",
                can: "work_shifts.view",
            },
            {
                title: "Munkarendek",
                route: "work_patterns.index",
                key: "work_patterns.index",
                can: "work_patterns.viewAny",
            },
            {
                title: "Naptár",
                route: "scheduling.calendar",
                key: "scheduling.calendar",
                can: "work_schedule_assignments.viewAny",
            },
        ],
    },
    {
        title: "Beállítások",
        items: [
            {
                title: "App Settings",
                route: "admin.app_settings.index",
                key: "admin.app_settings.index",
                can: "app_settings.viewAny",
            },
            {
                title: "Company Settings",
                route: "admin.company_settings.index",
                key: "admin.company_settings.index",
                can: "company_settings.viewAny",
            },
            {
                title: "User Settings",
                route: "admin.user_settings.index",
                key: "admin.user_settings.index",
                can: "user_settings.viewAny",
            },
        ],
    },
];
