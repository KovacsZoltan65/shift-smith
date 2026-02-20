<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import WorkPatternFields from "@/Pages/Scheduling/WorkPatterns/Partials/WorkPatternFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    companyId: { type: [Number, String, null], default: null },
    canCreate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const saving = ref(false);
const errors = ref({});

const form = ref({
    name: "",
    daily_work_minutes: 480,
    break_minutes: 30,
    core_start_time: "",
    core_end_time: "",
    active: true,
});

const reset = () => {
    errors.value = {};
    form.value = {
        name: "",
        daily_work_minutes: 480,
        break_minutes: 30,
        core_start_time: "",
        core_end_time: "",
        active: true,
    };
};

watch(
    () => open.value,
    (v) => {
        if (v) reset();
    }
);

const close = () => {
    open.value = false;
};

const submit = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const companyId = Number(props.companyId ?? 0);
        if (!companyId) {
            errors.value.company_id = "Válassz céget.";
            return;
        }

        const res = await csrfFetch("/work-patterns", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                company_id: companyId,
                name: String(form.value.name ?? "").trim(),
                daily_work_minutes: form.value.daily_work_minutes,
                break_minutes: form.value.break_minutes,
                core_start_time: String(form.value.core_start_time ?? "").trim() || null,
                core_end_time: String(form.value.core_end_time ?? "").trim() || null,
                active: !!form.value.active,
            }),
        });

        if (!res.ok) {
            if (res.status === 422) {
                const body = await res.json();
                const bag = body?.errors ?? {};
                const flat = {};
                Object.keys(bag).forEach((k) => (flat[k] = bag[k]?.[0] ?? String(bag[k])));
                errors.value = flat;
                return;
            }
            throw new Error(`Mentés sikertelen (HTTP ${res.status})`);
        }

        emit("saved", "Munkarend létrehozva.");
        close();
    } catch (e) {
        errors.value._global = e?.message || "Ismeretlen hiba.";
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="open"
        modal
        header="Új munkarend"
        :style="{ width: '52rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <WorkPatternFields
            v-model="form"
            :companyId="companyId"
            :errors="errors"
            :disabled="saving"
        />
        <div v-if="errors?._global" class="mt-2 text-sm text-red-600">
            {{ errors._global }}
        </div>
        <div v-if="errors?.company_id" class="mt-2 text-sm text-red-600">
            {{ errors.company_id }}
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Mégse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !props.canCreate"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
