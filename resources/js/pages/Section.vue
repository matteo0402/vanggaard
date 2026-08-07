<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

type DataKind = 'discogs' | 'personal' | 'mixed';

const props = defineProps<{
    title: string;
    description: string;
    dataKind: DataKind;
}>();

defineOptions({ layout: AppLayout });

const sourceLabels: Record<DataKind, string> = {
    discogs: 'Discogs-sourced catalog',
    personal: 'Your personal data',
    mixed: 'Catalog + personal data',
};
</script>

<template>
    <div>
        <Head :title="props.title" />

        <section
            class="relative min-h-[32rem] overflow-hidden rounded-3xl border border-white/10 bg-stone-900 p-6 sm:p-8 lg:p-12"
        >
            <div
                aria-hidden="true"
                class="absolute right-0 bottom-0 size-80 translate-x-1/3 translate-y-1/3 rounded-full border-[52px] border-white/3"
            />
            <div class="relative flex h-full max-w-2xl flex-col">
                <span
                    class="w-fit rounded-full border px-3 py-1.5 text-xs font-semibold tracking-wider uppercase"
                    :class="{
                        'border-amber-400/30 bg-amber-400/10 text-amber-300':
                            props.dataKind === 'discogs',
                        'border-emerald-400/30 bg-emerald-400/10 text-emerald-300':
                            props.dataKind === 'personal',
                        'border-white/15 bg-white/5 text-stone-300':
                            props.dataKind === 'mixed',
                    }"
                >
                    {{ sourceLabels[props.dataKind] }}
                </span>
                <h1
                    class="mt-6 text-4xl font-semibold tracking-tight text-stone-50 sm:text-5xl"
                >
                    {{ props.title }}
                </h1>
                <p class="mt-4 max-w-xl text-base leading-7 text-stone-300">
                    {{ props.description }}
                </p>

                <div
                    class="mt-12 rounded-2xl border border-dashed border-white/15 bg-stone-950/35 p-6 sm:p-8"
                >
                    <p class="text-sm font-semibold text-stone-200">
                        This room is ready
                    </p>
                    <p class="mt-2 text-sm leading-6 text-stone-500">
                        Collection features will arrive here as the catalog and
                        synchronization milestones are completed.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
