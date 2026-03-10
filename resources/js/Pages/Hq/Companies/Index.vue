<script setup>
import { computed } from "vue";
import { trans } from "laravel-vue-i18n";
import CompaniesIndex from "@/Pages/Companies/Index.vue";

const props = defineProps({
    title: String,
    filter: Object,
});

const title = computed(() => props.title || trans("companies.hq.title"));
const hqBadge = computed(() => trans("companies.hq_badge"));
</script>

<template>
    <CompaniesIndex
        :title="title"
        :filter="props.filter"
        endpoint-base="/hq/companies"
        permission-prefix="hq.companies"
        :hq-badge="hqBadge"
        fetch-route-name="hq.companies.fetch"
        detail-route-name="hq.companies.by_id"
        forbidden-redirect-route-name="dashboard"
    />
</template>
