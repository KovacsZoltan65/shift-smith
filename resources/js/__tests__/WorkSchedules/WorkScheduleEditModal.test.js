import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import EditModal from "@/Pages/Scheduling/WorkSchedules/EditModal.vue";

const getWorkScheduleMock = vi.fn();
const csrfFetchMock = vi.fn();

vi.mock("@/services/WorkScheduleService", () => ({
    default: {
        getWorkSchedule: (...args) => getWorkScheduleMock(...args),
    },
}));

vi.mock("@/lib/csrfFetch", () => ({
    csrfFetch: (...args) => csrfFetchMock(...args),
}));

const stubs = {
    Dialog: {
        props: ["visible"],
        emits: ["update:visible", "hide"],
        template: `
            <div v-if="visible">
                <slot />
                <slot name="footer" />
            </div>
        `,
    },
    Button: {
        props: ["label", "disabled", "loading"],
        emits: ["click"],
        template: `<button :data-label="label" :disabled="disabled" @click="$emit('click', $event)">{{ label }}</button>`,
    },
    InputText: {
        props: ["modelValue", "disabled"],
        emits: ["update:modelValue"],
        template: `<input :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)" />`,
    },
    Select: {
        props: ["modelValue", "options", "optionLabel", "optionValue", "disabled"],
        emits: ["update:modelValue"],
        template: `
            <select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)">
                <option v-for="option in options" :key="option[optionValue]" :value="option[optionValue]">
                    {{ option[optionLabel] }}
                </option>
            </select>
        `,
    },
    DatePicker: {
        props: ["modelValue", "disabled"],
        emits: ["update:modelValue"],
        template: `
            <button
                type="button"
                class="datepicker"
                :disabled="disabled"
                @click="$emit('update:modelValue', new Date('2026-04-15T00:00:00'))"
            >
                {{ modelValue instanceof Date ? modelValue.getFullYear() + '-' + String(modelValue.getMonth() + 1).padStart(2, '0') + '-' + String(modelValue.getDate()).padStart(2, '0') : '' }}
            </button>
        `,
    },
};

function ok(body = {}) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => body,
    });
}

describe("WorkSchedule EditModal", () => {
    beforeEach(() => {
        vi.clearAllMocks();

        getWorkScheduleMock.mockResolvedValue({
            data: {
                data: {
                    id: 12,
                    company_id: 4,
                    name: "Tavaszi beosztás",
                    date_from: "2026-03-01",
                    date_to: "2026-03-31",
                    status: "draft",
                },
            },
        });

        csrfFetchMock.mockResolvedValue(ok());
    });

    it("edit modálban a betöltött dátumok után is menti az új dátumválasztást", async () => {
        const wrapper = mount(EditModal, {
            props: {
                modelValue: false,
                canUpdate: true,
                workSchedule: { id: 12, company_id: 4 },
            },
            global: { stubs },
        });

        await wrapper.setProps({ modelValue: true });
        await flushPromises();

        const pickers = wrapper.findAll(".datepicker");
        expect(pickers).toHaveLength(2);
        expect(pickers[0].text()).toBe("2026-03-01");
        expect(pickers[1].text()).toBe("2026-03-31");

        await pickers[0].trigger("click");
        await pickers[1].trigger("click");
        await flushPromises();

        await wrapper.find('button[data-label="Mentés"]').trigger("click");
        await flushPromises();

        expect(csrfFetchMock).toHaveBeenCalledWith(
            "/work-schedules/12",
            expect.objectContaining({
                method: "PUT",
                body: JSON.stringify({
                    company_id: 4,
                    name: "Tavaszi beosztás",
                    date_from: "2026-04-15",
                    date_to: "2026-04-15",
                    status: "draft",
                }),
            }),
        );
    });
});
