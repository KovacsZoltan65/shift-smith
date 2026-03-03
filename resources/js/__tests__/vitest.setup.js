import { vi } from "vitest";

// modul-scope (látszik a mock factory-ból is)
const allow = new Set([
    // Users
    "users.view",
    "users.viewAny",
    "users.create",
    "users.update",
    "users.assignRoles",
    "users.delete",

    // Employees
    "employees.view",
    "employees.viewAny",
    "employees.create",
    "employees.update",
    "employees.delete",

    // Companies
    "companies.view",
    "companies.viewAny",
    "companies.create",
    "companies.update",
    "companies.delete",

    // Roles
    "roles.view",
    "roles.viewAny",
    "roles.create",
    "roles.update",
    "roles.delete",

    // Permissions
    "permissions.view",
    "permissions.viewAny",
    "permissions.create",
    "permissions.update",
    "permissions.delete",

    // WorkShifts
    "work_shifts.view",
    "work_shifts.view",
    "work_shifts.create",
    "work_shifts.update",
    "work_shifts.delete",

    // Assignments
    "assignments.view",
    "assignments.viewAny",
    "assignments.create",
    "assignments.update",
    "assignments.delete",

    // Shifts
    "shifts.view",
    "shifts.viewAny",
    "shifts.create",
    "shifts.update",
    "shifts.delete",

    // WorkPatterns
    "work_patterns.view",
    "work_patterns.viewAny",
    "work_patterns.create",
    "work_patterns.update",
    "work_patterns.delete",
    "work_patterns.deleteAny",

    // LeaveTypes
    "leave_types.view",
    "leave_types.viewAny",
    "leave_types.create",
    "leave_types.update",
    "leave_types.delete",
    "sick_leave_categories.viewAny",
    "sick_leave_categories.create",
    "sick_leave_categories.update",
    "sick_leave_categories.delete",

    // EmployeeWorkPatterns
    "employee_work_patterns.view",
    "employee_work_patterns.assign",
    "employee_work_patterns.unassign",

    // WorkScheduleAssignments
    "work_schedule_assignments.view",
    "work_schedule_assignments.viewAny",
    "work_schedule_assignments.create",
    "work_schedule_assignments.update",
    "work_schedule_assignments.delete",

]);

vi.mock("@/composables/usePermissions", () => ({
    usePermissions: () => ({
        has: (perm) => allow.has(perm),
        __allow: allow, // opcionális: tesztben bővíthető
    }),
}));
