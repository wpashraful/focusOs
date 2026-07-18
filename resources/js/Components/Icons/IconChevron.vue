<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6" />
    </svg>
</template>

<script setup>
defineProps({ size: { type: Number, default: 14 } });
</script>
