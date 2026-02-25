<script setup>
import CompaniesIndex from "@/Pages/Companies/Index.vue";

defineProps({
    title: String,
    filter: Object,
});
</script>

<template>
    <CompaniesIndex
        :title="title || 'HQ / Összes cég'"
        :filter="filter"
        endpoint-base="/hq/companies"
        permission-prefix="hq.companies"
        hq-badge="LANDLORD"
        fetch-route-name="hq.companies.fetch"
        detail-route-name="hq.companies.by_id"
        forbidden-redirect-route-name="dashboard"
    />
</template>
