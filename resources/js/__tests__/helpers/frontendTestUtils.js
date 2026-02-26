import { mount, flushPromises } from "@vue/test-utils";
import { nextTick } from "vue";
import { vi } from "vitest";

export function mountPrimeVue(component, options = {}) {
    return mount(component, options);
}

export function mockInertiaPageProps(props = {}) {
    return {
        props,
    };
}

export function mockRoute(map = {}) {
    globalThis.route = vi.fn((name, ...args) => {
        if (typeof map[name] === "function") {
            return map[name](...args);
        }

        return `/${name}`;
    });

    return globalThis.route;
}

export async function flushUi() {
    await flushPromises();
    await nextTick();
}

export function mockFetchOk(json, status = 200) {
    return Promise.resolve({
        ok: status >= 200 && status < 300,
        status,
        json: async () => json,
    });
}

export function resolveConfirmAccept(accept) {
    if (typeof accept === "function") {
        return accept;
    }

    if (accept && typeof accept === "object") {
        for (const key of ["callback", "command", "action", "handler"]) {
            if (typeof accept[key] === "function") {
                return accept[key];
            }
        }
    }

    return null;
}

export function createPrimeCrudStubs(extra = {}) {
    return {
        AuthenticatedLayout: { template: "<div><slot /></div>" },
        Toast: { template: "<div />" },
        ConfirmDialog: { template: "<div />" },
        Button: {
            inheritAttrs: true,
            props: [
                "label",
                "icon",
                "size",
                "severity",
                "loading",
                "disabled",
                "text",
                "rounded",
            ],
            template: `<button v-bind="$attrs" :disabled="disabled" @click="$emit('click', $event)">{{ label }}<slot /></button>`,
        },
        InputText: {
            inheritAttrs: true,
            props: ["modelValue"],
            emits: ["update:modelValue", "input"],
            template: `<input v-bind="$attrs" :value="modelValue" @input="$emit('update:modelValue', $event.target.value); $emit('input', $event)" />`,
        },
        DataTable: {
            props: ["value"],
            template: `
                <div data-testid="datatable">
                    <div v-for="row in (value ?? [])" :key="row.id" class="row">
                        <span class="row-name">{{ row.name ?? row.title ?? row.id }}</span>
                    </div>
                    <slot />
                </div>
            `,
        },
        Column: { template: "<div><slot /></div>" },
        Menu: { template: "<div />" },
        Select: {
            props: ["modelValue", "options", "optionLabel", "optionValue"],
            emits: ["update:modelValue"],
            template: `<div data-testid="select"><slot /></div>`,
        },
        DatePicker: {
            props: ["modelValue"],
            emits: ["update:modelValue"],
            template: `<div data-testid="datepicker"><slot /></div>`,
        },
        ...extra,
    };
}
