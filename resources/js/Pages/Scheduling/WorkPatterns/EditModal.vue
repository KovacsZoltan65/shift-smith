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
    type: "fixed_weekly",
    cycle_length_days: null,
    weekly_minutes: null,
    active: true,
    metaText: "",
});

const reset = () => {
    errors.value = {};
    loading.value = false;
    saving.value = false;
    form.value = {
        company_id: null,
        name: "",
        type: "fixed_weekly",
        cycle_length_days: null,
        weekly_minutes: null,
        active: true,
        metaText: "",
    };
};

const close = () => {
    open.value = false;
};

const parseMeta = () => {
    const raw = String(form.value.metaText ?? "").trim();
    if (!raw) return null;
    try {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) return parsed;
        errors.value.meta = "A meta csak objektum JSON lehet.";
        return undefined;
    } catch (_) {
        errors.value.meta = "Hibás JSON formátum.";
        return undefined;
    }
};

const load = async (id) => {
    loading.value = true;
    errors.value = {};
    try {
        const res = await fetch(`/work-patterns/${id}`, {
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });

        if (!res.ok) throw new Error(`Betöltés sikertelen (HTTP ${res.status})`);

        const body = await res.json();
        const row = body?.data ?? {};

        form.value = {
            company_id: row.company_id ?? props.workPattern?.company_id ?? null,
            name: row.name ?? "",
            type: row.type ?? "fixed_weekly",
            cycle_length_days: row.cycle_length_days ?? null,
            weekly_minutes: row.weekly_minutes ?? null,
            active: !!row.active,
            metaText: row.meta ? JSON.stringify(row.meta, null, 2) : "",
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
        const meta = parseMeta();
        if (meta === undefined) return;

        const payload = {
            company_id: Number(form.value.company_id ?? 0),
            name: String(form.value.name ?? "").trim(),
            type: form.value.type,
            cycle_length_days: form.value.cycle_length_days,
            weekly_minutes: form.value.weekly_minutes,
            active: !!form.value.active,
            meta,
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
