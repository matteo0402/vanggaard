<script setup lang="ts">
defineProps<{
    sourceUrl: string;
}>();
</script>

<template>
    <a
        :href="sourceUrl"
        target="_blank"
        rel="noopener"
        class="font-medium text-amber-300 underline decoration-amber-300/35 underline-offset-4 transition hover:text-amber-200 focus-visible:rounded-sm focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
    >
        Data provided by Discogs.
    </a>
</template>
