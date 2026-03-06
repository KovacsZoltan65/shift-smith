<script setup>
import { computed } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Message from "primevue/message";

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:visible", "confirm"]);

const formattedDeletedAt = computed(() => {
    if (!props.employee?.deleted_at) {
        return "-";
    }

    const date = new Date(props.employee.deleted_at);

    return Number.isNaN(date.getTime())
        ? props.employee.deleted_at
        : date.toLocaleString("hu-HU");
});
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :draggable="false"
        :style="{ width: '34rem', maxWidth: '96vw' }"
        header="Korábban törölt dolgozó található"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-800">
                    {{ employee?.last_name || "" }} {{ employee?.first_name || "" }}
                </div>
                <div class="mt-1 text-sm text-slate-600">{{ employee?.email || "-" }}</div>
                <div class="mt-2 text-xs text-slate-500">
                    Törölve: {{ formattedDeletedAt }}
                </div>
            </div>

            <Message severity="info" :closable="false">
                Szeretnéd visszaállítani az eredeti rekordot?
            </Message>

            <Message severity="warn" :closable="false">
                A hierarchia hozzárendelés nem áll vissza automatikusan, azt külön kell megadni.
            </Message>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <Button
                    label="Mégse"
                    severity="secondary"
                    text
                    :disabled="loading"
                    @click="emit('update:visible', false)"
                />
                <Button
                    label="Visszaállítás"
                    icon="pi pi-history"
                    :loading="loading"
                    :disabled="loading"
                    @click="emit('confirm')"
                />
            </div>
        </template>
    </Dialog>
</template>
