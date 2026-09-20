<script setup>
defineProps({
    workcenter: { type: Object, required: true },
});
</script>

<template>
    <h1 class="text-text-primary">{{ workcenter.name }}</h1>
</template>
