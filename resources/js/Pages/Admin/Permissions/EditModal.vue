<script setup>
import { computed, ref, watch } from "vue";

import Dialog from "primevue/dialog";
import Button from "primevue/button";

import PermissionFields from "@/Pages/Admin/Permissions/Partials/PermissionFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    permission: { type: Object, default: null }, // {id,name,guard_name}
    defaultGuard: { type: String, default: "web" },
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
});

const hydrateFromPermission = () => {
    errors.value = {};

    const p = props.permission ?? {};

    form.value = {
        id: p.id ?? null,
        name: p.name ?? "",
        guard_name: p.guard_name ?? props.defaultGuard,
    };
};

watch(
    () => open.value,
    (v) => {
        if (v) hydrateFromPermission();
    }
);

watch(
    () => props.permission,
    () => {
        if (open.value) hydrateFromPermission();
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
        const res = await csrfFetch(`/admin/permissions/${form.value.id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                name: String(form.value.name ?? "").trim(),
                guard_name: form.value.guard_name || props.defaultGuard,
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

        emit("saved", "Permission frissítve.");
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
        header="Permission szerkesztése"
        :style="{ width: '48rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <div v-if="!permission" class="text-sm text-gray-600">
            Nincs kiválasztott permission.
        </div>

        <PermissionFields
            v-else
            v-model="form"
            :defaultGuard="defaultGuard"
            :errors="errors"
            :disabled="saving"
        />

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    label="Mégse"
                    severity="secondary"
                    :disabled="saving"
                    @click="close"
                />
                <Button
                    label="Mentés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="!permission"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
