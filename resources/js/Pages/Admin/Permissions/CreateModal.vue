<script setup>
import { computed, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";


import PermissionFields from "@/Pages/Admin/Permissions/Partials/PermissionFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    defaultGuard: { type: String, default: "web" },
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
    guard_name: props.defaultGuard,
});

const reset = () => {
    errors.value = {};
    form.value = {
        name: "",
        guard_name: props.defaultGuard,
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
        const res = await csrfFetch(`/admin/permissions`, {
            method: "POST",
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
                // Laravel: errors mezők általában { errors: {field:[..]} }
                const bag = body?.errors ?? {};
                const flat = {};
                Object.keys(bag).forEach(
                    (k) => (flat[k] = bag[k]?.[0] ?? String(bag[k]))
                );
                errors.value = flat;

                throw new Error(body?.message || trans("validation.invalid"));
            }

            let msg = trans("permissions.messages.save_failed_http", {
                status: res.status,
            });
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        emit("saved", trans("permissions.messages.created_success"));
        close();
    } catch (e) {
        // a toast-ot az Index oldalon intézed az @saved alapján
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
        :header="trans('permissions.dialogs.create_title')"
        :style="{ width: '48rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <PermissionFields
            v-model="form"
            :defaultGuard="defaultGuard"
            :errors="errors"
            :disabled="saving"
        />

        <template #footer>
            <div class="flex justify-end gap-2">
                <!-- CANCEL -->
                <Button
                    :label="trans('common.cancel')"
                    severity="secondary"
                    :disabled="saving"
                    @click="close"
                />

                <!-- MENTÉS -->
                <Button
                    :label="trans('common.save')"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !props.canCreate"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
