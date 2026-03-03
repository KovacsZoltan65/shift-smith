import { describe, expect, it, vi, beforeEach } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import SickLeaveCategorySelector from "@/Components/Selectors/SickLeaveCategorySelector.vue";

const selectorMock = vi.fn();

vi.mock("@/services/SickLeaveCategoryService.js", () => ({
    default: {
        selector: (...args) => selectorMock(...args),
    },
}));

describe("SickLeaveCategorySelector", () => {
    beforeEach(() => {
        selectorMock.mockReset();
    });

    it("betolti a selector opciokat mount utan", async () => {
        selectorMock.mockResolvedValue({
            data: {
                data: [
                    { id: 1, name: "Sajat betegseg", code: "slc_own", active: true },
                    { id: 2, name: "Gyermek apolasa", code: "slc_child", active: true },
                ],
            },
        });

        const wrapper = mount(SickLeaveCategorySelector, {
            props: { modelValue: null },
            global: {
                stubs: {
                    Select: {
                        props: ["options", "modelValue"],
                        template: `
                            <div>
                                <span v-for="option in options" :key="option.id" class="option">{{ option.name }}</span>
                            </div>
                        `,
                    },
                },
            },
        });

        await flushPromises();

        expect(selectorMock).toHaveBeenCalledWith({ only_active: 1 });
        expect(wrapper.text()).toContain("Sajat betegseg");
        expect(wrapper.text()).toContain("Gyermek apolasa");
    });
});
