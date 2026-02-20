<script setup>
import PositionsIndex from "@/Pages/Positions/Index.vue";

defineProps({
    title: { type: String, default: "Pozíciók" },
    filter: { type: Object, default: () => ({}) },
});
</script>

<template>
    <PositionsIndex :title="title" :filter="filter" />
</template>
