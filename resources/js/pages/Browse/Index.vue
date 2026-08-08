<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { browse, collection } from '@/routes';
import { show } from '@/routes/collection';

defineOptions({ layout: AppLayout });

type Dimension = {
    label: string;
    description: string;
};

type BrowseEntry = {
    value: number | string;
    label: string;
    count: number;
    occurred_at?: string;
};

const props = defineProps<{
    dimension: string;
    dimensions: Record<string, Dimension>;
    entries: {
        data: BrowseEntry[];
        current_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const recentDimensions = ['recently-added', 'recently-updated'];

function entryHref(entry: BrowseEntry) {
    if (recentDimensions.includes(props.dimension)) {
        return show(Number(entry.value));
    }

    const filter = {
        labels: 'label',
        artists: 'artist',
        years: 'year',
        videos: 'video',
    }[props.dimension];

    return collection({
        query: { filter, value: String(entry.value) },
    });
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function recentCatalogHref() {
    return collection({
        query: { filter: props.dimension, value: 'all' },
    });
}
</script>

<template>
    <div>
        <Head :title="`Browse ${dimensions[dimension].label}`" />

        <header class="border-b border-white/10 pb-7">
            <p
                class="text-xs font-semibold tracking-[0.24em] text-amber-400 uppercase"
            >
                Collection dimensions
            </p>
            <h1
                class="mt-2 text-4xl font-semibold tracking-tight text-stone-50 sm:text-5xl"
            >
                Browse your records
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-400">
                Move through the collection by the facts and activity that make
                each physical copy useful.
            </p>
        </header>

        <nav
            aria-label="Browse dimensions"
            class="mt-6 flex gap-2 overflow-x-auto pb-2"
        >
            <Link
                v-for="(item, key) in dimensions"
                :key="key"
                :href="browse(key)"
                class="min-h-11 shrink-0 rounded-full border px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                :class="
                    dimension === key
                        ? 'border-amber-400 bg-amber-400 text-stone-950'
                        : 'border-white/10 bg-stone-900 text-stone-300 hover:border-white/25 hover:text-white'
                "
            >
                {{ item.label }}
            </Link>
        </nav>

        <section class="mt-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-stone-100">
                        {{ dimensions[dimension].label }}
                    </h2>
                    <p class="mt-2 text-sm text-stone-500">
                        {{ dimensions[dimension].description }}
                    </p>
                </div>
                <Link
                    v-if="recentDimensions.includes(dimension)"
                    :href="recentCatalogHref()"
                    class="rounded-full border border-amber-400/30 px-4 py-2 text-sm font-semibold text-amber-300 transition hover:border-amber-400 hover:text-amber-200 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                >
                    Open filtered catalog
                </Link>
            </div>

            <div
                v-if="entries.data.length"
                class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <Link
                    v-for="entry in entries.data"
                    :key="entry.value"
                    :href="entryHref(entry)"
                    class="group flex min-h-28 items-center justify-between gap-4 rounded-2xl border border-white/10 bg-stone-900/75 p-5 transition hover:-translate-y-0.5 hover:border-amber-400/35 hover:bg-stone-900 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                >
                    <span class="min-w-0">
                        <span
                            class="block truncate text-lg font-semibold text-stone-100 group-hover:text-amber-200"
                        >
                            {{ entry.label }}
                        </span>
                        <span
                            v-if="entry.occurred_at"
                            class="mt-1 block text-xs text-stone-500"
                        >
                            {{ formatDate(entry.occurred_at) }}
                        </span>
                    </span>
                    <span
                        class="shrink-0 rounded-full border border-white/10 bg-stone-950 px-3 py-1.5 text-xs font-semibold text-stone-300"
                    >
                        {{ entry.count }}
                        {{ entry.count === 1 ? 'copy' : 'copies' }}
                    </span>
                </Link>
            </div>

            <div
                v-else
                class="mt-5 rounded-2xl border border-dashed border-stone-700 bg-stone-900/40 px-6 py-16 text-center"
            >
                <p class="text-lg font-semibold text-stone-200">
                    Nothing to browse yet
                </p>
                <p class="mt-2 text-sm text-stone-500">
                    This view will fill as active records gain
                    {{ dimensions[dimension].label.toLowerCase() }} data.
                </p>
            </div>

            <nav
                v-if="entries.prev_page_url || entries.next_page_url"
                aria-label="Browse result pages"
                class="mt-7 flex items-center justify-between gap-4"
            >
                <Link
                    :href="entries.prev_page_url ?? browse(dimension)"
                    :class="[
                        'rounded-full border border-stone-700 px-4 py-2 text-sm font-medium text-stone-300 transition hover:border-stone-500 hover:text-stone-100',
                        !entries.prev_page_url &&
                            'pointer-events-none opacity-40',
                    ]"
                    preserve-scroll
                >
                    Previous
                </Link>
                <span class="text-xs text-stone-500">
                    Page {{ entries.current_page }}
                </span>
                <Link
                    :href="entries.next_page_url ?? browse(dimension)"
                    :class="[
                        'rounded-full border border-stone-700 px-4 py-2 text-sm font-medium text-stone-300 transition hover:border-stone-500 hover:text-stone-100',
                        !entries.next_page_url &&
                            'pointer-events-none opacity-40',
                    ]"
                    preserve-scroll
                >
                    Next
                </Link>
            </nav>
        </section>
    </div>
</template>
