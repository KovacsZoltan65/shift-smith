<script setup>
import { computed, ref, watch } from "vue";

import Dialog from "primevue/dialog";
import Button from "primevue/button";

import RoleFields from "@/Pages/Admin/Roles/Partials/RoleFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    role: { type: Object, default: null }, // {id,name,guard_name,permission_ids?}
    defaultGuard: { type: String, default: "web" },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const saving = ref(false);
const errors = ref({});

const form = ref({
    id: null,
    name: "",
    guard_name: props.defaultGuard,
    permission_ids: [],
});

const hydrateFromRole = () => {
    errors.value = {};

    const r = props.role ?? {};

    form.value = {
        id: r.id ?? null,
        name: r.name ?? "",
        guard_name: r.guard_name ?? props.defaultGuard,
        permission_ids: Array.isArray(r.permission_ids) ? r.permission_ids : [],
    };
};

watch(
    () => open.value,
    (v) => {
        if (v) hydrateFromRole();
    }
);

watch(
    () => props.role,
    () => {
        if (open.value) hydrateFromRole();
    }
);

const close = () => {
    open.value = false;
};

const submit = async () => {
    if (!form.value.id) return;

    saving.value = true;
    errors.value = {};

    try {
        const res = await csrfFetch(`/admin/roles/${form.value.id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                name: String(form.value.name ?? "").trim(),
                guard_name: form.value.guard_name || props.defaultGuard,
                permission_ids: form.value.permission_ids ?? [],
            }),
        });

        if (!res.ok) {
            if (res.status === 422) {
                const body = await res.json();
                const bag = body?.errors ?? {};
                const flat = {};
                Object.keys(bag).forEach(
                    (k) => (flat[k] = bag[k]?.[0] ?? String(bag[k]))
                );
                errors.value = flat;

                throw new Error(body?.message || "Validációs hiba.");
            }

            let msg = `Mentés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        emit("saved", "Role frissítve.");
        close();
    } catch (e) {
        console.error(e);
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="open"
        modal
        header="Role szerkesztése"
        :style="{ width: '48rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <div v-if="!role" class="text-sm text-gray-600">Nincs kiválasztott role.</div>

        <RoleFields
            v-else
            v-model="form"
            :defaultGuard="defaultGuard"
            :errors="errors"
            :disabled="saving"
        />

        <template #footer>
            <div class="flex justify-end gap-2">
                <!-- CANCEL -->
                <Button
                    label="Mégse"
                    severity="secondary"
                    :disabled="saving"
                    @click="close"
                />

                <!-- MENTÉS -->
                <Button
                    label="Mentés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="!role || !props.canUpdate"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
