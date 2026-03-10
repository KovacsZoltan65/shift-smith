export const appMenuDefinition = [
    {
        titleKey: "home.title",
        items: [{ titleKey: "dashboard.title", route: "dashboard", key: "dashboard" }],
    },
    {
        titleKey: "administration.title",
        items: [
            { titleKey: "menu.title", route: "menu.index", key: "menu.index" },
            {
                titleKey: "users.title",
                route: "users.index",
                key: "users.index",
                can: "users.viewAny",
            },
            {
                titleKey: "user_assignments.title",
                route: "admin.user_assignments.index",
                key: "admin.user_assignments.index",
                can: "user_assignments.viewAny",
            },
            {
                titleKey: "companies.title",
                route: "companies.index",
                key: "companies.index",
                can: "companies.viewAny",
            },
            {
                titleKey: "hq_companies.title",
                route: "hq.companies.index",
                key: "hq.companies.index",
                can: "hq.companies.view",
            },
            {
                titleKey: "tenant_groups.title",
                route: "hq.tenant_groups.index",
                key: "hq.tenant_groups.index",
                can: "tenant-groups.viewAny",
            },
        ],
    },
    {
        titleKey: "security.title",
        items: [
            {
                titleKey: "permissions.title",
                route: "admin.permissions.index",
                key: "permissions.index",
                can: "permissions.viewAny",
            },
            {
                titleKey: "roles.title",
                route: "admin.roles.index",
                key: "roles.index",
                can: "roles.viewAny",
            },
        ],
    },
    {
        titleKey: "hr.title",
        items: [
            {
                titleKey: "employees.title",
                route: "employees.index",
                key: "employees.index",
                can: "employees.viewAny",
            },
            {
                titleKey: "positions.title",
                route: "positions.index",
                key: "positions.index",
                can: "positions.viewAny",
            },
            {
                titleKey: "position_org_levels.title",
                route: "admin.position_org_levels.index",
                key: "admin.position_org_levels.index",
                can: "org_position_levels.viewAny",
            },
            {
                titleKey: "hierarchy.title",
                route: "org.hierarchy.index",
                key: "org.hierarchy.index",
                can: "org_hierarchy.viewAny",
            },
            {
                titleKey: "work_shifts.title",
                route: "work_shifts.index",
                key: "work_shifts.index",
                can: "work_shifts.view",
            },
            {
                titleKey: "work_patterns.title",
                route: "work_patterns.index",
                key: "work_patterns.index",
                can: "work_patterns.viewAny",
            },
            {
                titleKey: "work_schedules.title",
                route: "work_schedules.index",
                key: "work_schedules.index",
                can: "work_schedules.viewAny",
            },
            {
                titleKey: "calendar.title",
                route: "scheduling.calendar",
                key: "scheduling.calendar",
                can: "work_schedule_assignments.viewAny",
            },
            {
                titleKey: "leave_types.title",
                route: "admin.leave_types.index",
                key: "admin.leave_types.index",
                can: "leave_types.viewAny",
            },
            {
                titleKey: "leave_categories.title",
                route: "admin.leave_categories.index",
                key: "admin.leave_categories.index",
                can: "leave_categories.viewAny",
            },
            {
                titleKey: "sick_leave_categories.title",
                route: "admin.sick_leave_categories.index",
                key: "admin.sick_leave_categories.index",
                can: "sick_leave_categories.viewAny",
            },
        ],
    },
    {
        titleKey: "settings.title",
        items: [
            {
                titleKey: "app_settings.title",
                route: "admin.app_settings.index",
                key: "admin.app_settings.index",
                can: "app_settings.viewAny",
            },
            {
                titleKey: "company_settings.title",
                route: "admin.company_settings.index",
                key: "admin.company_settings.index",
                can: "company_settings.viewAny",
            },
            {
                titleKey: "user_settings.title",
                route: "admin.user_settings.index",
                key: "admin.user_settings.index",
                can: "user_settings.viewAny",
            },
        ],
    },
];
