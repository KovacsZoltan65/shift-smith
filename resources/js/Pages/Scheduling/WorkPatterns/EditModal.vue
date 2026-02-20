<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import WorkPatternFields from "@/Pages/Scheduling/WorkPatterns/Partials/WorkPatternFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workPattern: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const loading = ref(false);
const saving = ref(false);
const errors = ref({});

const form = ref({
    company_id: null,
    name: "",
    daily_work_minutes: 480,
    break_minutes: 30,
    core_start_time: "",
    core_end_time: "",
    active: true,
});

const reset = () => {
    errors.value = {};
    loading.value = false;
    saving.value = false;
    form.value = {
        company_id: null,
        name: "",
        daily_work_minutes: 480,
        break_minutes: 30,
        core_start_time: "",
        core_end_time: "",
        active: true,
    };
};

const close = () => {
    open.value = false;
};

const load = async (id) => {
    loading.value = true;
    errors.value = {};
    try {
        const companyId = Number(props.workPattern?.company_id ?? 0);
        const query = new URLSearchParams({ company_id: String(companyId) }).toString();
        const res = await fetch(`/work-patterns/${id}?${query}`, {
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });

        if (!res.ok) throw new Error(`Betöltés sikertelen (HTTP ${res.status})`);

        const body = await res.json();
        const row = body?.data ?? {};

        form.value = {
            company_id: row.company_id ?? props.workPattern?.company_id ?? null,
            name: row.name ?? "",
            daily_work_minutes: row.daily_work_minutes ?? 480,
            break_minutes: row.break_minutes ?? 30,
            core_start_time: row.core_start_time ? String(row.core_start_time).slice(0, 5) : "",
            core_end_time: row.core_end_time ? String(row.core_end_time).slice(0, 5) : "",
            active: !!row.active,
        };
    } catch (e) {
        errors.value._global = e?.message || "Betöltési hiba.";
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.modelValue,
    async (isOpen) => {
        if (!isOpen) {
            reset();
            return;
        }

        const id = Number(props.workPattern?.id ?? 0);
        if (!id) {
            errors.value._global = "Nincs kiválasztott munkarend.";
            return;
        }

        await load(id);
    }
);

const submit = async () => {
    const id = Number(props.workPattern?.id ?? 0);
    if (!id) {
        errors.value._global = "Nincs kiválasztott munkarend.";
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const payload = {
            company_id: Number(form.value.company_id ?? 0),
            name: String(form.value.name ?? "").trim(),
            daily_work_minutes: form.value.daily_work_minutes,
            break_minutes: form.value.break_minutes,
            core_start_time: String(form.value.core_start_time ?? "").trim() || null,
            core_end_time: String(form.value.core_end_time ?? "").trim() || null,
            active: !!form.value.active,
        };

        const res = await csrfFetch(`/work-patterns/${id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(payload),
        });

        if (res.status === 422) {
            const body = await res.json().catch(() => ({}));
            const bag = body?.errors ?? {};
            const flat = {};
            Object.keys(bag).forEach((k) => (flat[k] = bag[k]?.[0] ?? String(bag[k])));
            errors.value = flat;
            return;
        }

        if (!res.ok) throw new Error(`Mentés sikertelen (HTTP ${res.status})`);

        emit("saved", "Munkarend frissítve.");
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
        header="Munkarend szerkesztése"
        :style="{ width: '52rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="loading" class="text-sm text-gray-500">Betöltés...</div>

        <template v-else>
            <WorkPatternFields
                v-model="form"
                :companyId="form.company_id"
                :errors="errors"
                :disabled="saving"
            />
            <div v-if="errors?._global" class="mt-2 text-sm text-red-600">
                {{ errors._global }}
            </div>
        </template>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Mégse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !props.canUpdate || loading"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
