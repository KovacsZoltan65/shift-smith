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
    type: "fixed_weekly",
    cycle_length_days: null,
    weekly_minutes: null,
    active: true,
    metaText: "",
});

const reset = () => {
    errors.value = {};
    form.value = {
        name: "",
        type: "fixed_weekly",
        cycle_length_days: null,
        weekly_minutes: null,
        active: true,
        metaText: "",
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

const submit = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const companyId = Number(props.companyId ?? 0);
        if (!companyId) {
            errors.value.company_id = "Válassz céget.";
            return;
        }

        const meta = parseMeta();
        if (meta === undefined) return;

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
                type: form.value.type,
                cycle_length_days: form.value.cycle_length_days,
                weekly_minutes: form.value.weekly_minutes,
                active: !!form.value.active,
                meta,
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
