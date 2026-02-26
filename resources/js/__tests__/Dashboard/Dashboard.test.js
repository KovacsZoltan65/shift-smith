import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

import Dashboard from "@/Pages/Dashboard.vue";

vi.mock("@inertiajs/vue3", () => ({
    Head: {
        props: ["title"],
        template: "<div data-testid=\"head\" :data-title=\"title\" />",
    },
}));

describe("Dashboard.vue", () => {
    const stubs = {
        AuthenticatedLayout: {
            template: "<div><slot name=\"header\" /><slot /></div>",
        },
    };

    it("kirendereli a statisztikákat a kapott props alapján", () => {
        const wrapper = mount(Dashboard, {
            props: {
                stats: {
                    users: 12,
                    employees: 8,
                    companies: 3,
                    work_shifts: 21,
                },
                recentUsers: [],
            },
            global: { stubs },
        });

        const text = wrapper.text();
        expect(text).toContain("Users");
        expect(text).toContain("12");
        expect(text).toContain("Employees");
        expect(text).toContain("8");
        expect(text).toContain("Companies");
        expect(text).toContain("3");
        expect(text).toContain("Work Shifts");
        expect(text).toContain("21");
        expect(wrapper.find('[data-testid="head"]').attributes("data-title")).toBe("Dashboard");
    });

    it("hiányzó stats esetén 0 értékeket és üres lista üzenetet mutat", () => {
        const wrapper = mount(Dashboard, {
            props: {
                stats: {},
                recentUsers: [],
            },
            global: { stubs },
        });

        const numbers = wrapper.findAll(".text-3xl.font-bold").map((n) => n.text().trim());
        expect(numbers).toEqual(["0", "0", "0", "0"]);
        expect(wrapper.text()).toContain("No recent users found.");
    });

    it("megjeleníti a recent users elemeket névvel, emaillel és dátummal", () => {
        const recentUsers = [
            {
                id: 1,
                name: "Teszt Elek",
                email: "teszt.elek@example.com",
                created_at: "2026-02-20T12:00:00",
            },
            {
                id: 2,
                name: "Minta Anna",
                email: "minta.anna@example.com",
                created_at: "2026-02-21T12:00:00",
            },
        ];

        const wrapper = mount(Dashboard, {
            props: {
                stats: {},
                recentUsers,
            },
            global: { stubs },
        });

        expect(wrapper.text()).toContain("Teszt Elek");
        expect(wrapper.text()).toContain("teszt.elek@example.com");
        expect(wrapper.text()).toContain("Minta Anna");
        expect(wrapper.text()).toContain("minta.anna@example.com");

        const formatted1 = new Date(recentUsers[0].created_at).toLocaleDateString();
        const formatted2 = new Date(recentUsers[1].created_at).toLocaleDateString();
        expect(wrapper.text()).toContain(formatted1);
        expect(wrapper.text()).toContain(formatted2);
        expect(wrapper.text()).not.toContain("No recent users found.");
    });
});
