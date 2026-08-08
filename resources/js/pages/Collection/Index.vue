<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import DiscogsAttribution from '@/components/DiscogsAttribution.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { collection } from '@/routes';
import { show } from '@/routes/collection';

defineOptions({ layout: AppLayout });

type CatalogRelease = {
    id: number;
    physical_copies_count: number;
    collection_item_ids: number[];
    is_fresh: boolean;
    refresh_status: 'idle' | 'queued' | 'refreshing' | 'failed';
    effective_year: number | null;
    is_year_corrected: boolean;
    rating: number | null;
    discogs: {
        title: string;
        released_year: number | null;
        artists: string[];
        labels: string[];
        formats: string[];
        image_url: string | null;
        source_url: string;
    } | null;
};

defineProps<{
    releases: {
        data: CatalogRelease[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        q: string | null;
        filter: string | null;
        value: string | null;
    };
}>();
</script>

<template>
    <div>
        <Head title="Collection" />

        <header
            class="flex flex-col gap-6 border-b border-white/10 pb-7 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.24em] text-amber-400 uppercase"
                >
                    Local catalog
                </p>
                <h1
                    class="mt-2 text-4xl font-semibold tracking-tight text-stone-50 sm:text-5xl"
                >
                    Your collection
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-400">
                    {{ releases.total }} releases, grouped by title while
                    keeping every physical copy distinct.
                </p>
            </div>
            <p
                v-if="releases.from"
                class="text-xs font-medium tracking-wide text-stone-500 uppercase"
            >
                Showing {{ releases.from }}–{{ releases.to }}
            </p>
        </header>

        <Form
            v-bind="collection.form()"
            role="search"
            class="mt-6 flex flex-col gap-3 sm:flex-row"
        >
            <label for="catalog-search" class="sr-only"
                >Search releases, artists, labels, or personal notes</label
            >
            <input
                id="catalog-search"
                name="q"
                type="search"
                :value="filters.q ?? ''"
                placeholder="Search releases, artists, labels, or notes"
                class="min-h-12 flex-1 rounded-xl border border-white/10 bg-stone-900 px-4 text-base text-white outline-none placeholder:text-stone-600 focus:border-amber-400 focus:ring-3 focus:ring-amber-400/15"
            />
            <button
                type="submit"
                class="min-h-12 rounded-xl bg-amber-400 px-6 text-sm font-semibold text-stone-950 transition hover:bg-amber-300 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
            >
                Search collection
            </button>
        </Form>

        <div
            v-if="filters.filter && filters.value"
            class="mt-4 flex items-center justify-between gap-4 rounded-xl border border-amber-400/20 bg-amber-400/8 px-4 py-3"
        >
            <p class="text-sm text-amber-100">
                Browsing by
                <span class="font-semibold capitalize">{{
                    filters.filter
                }}</span>
            </p>
            <Link
                :href="collection({ query: { q: filters.q } })"
                class="rounded-md text-sm font-semibold text-amber-300 underline decoration-amber-400/40 underline-offset-4 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
            >
                Clear filter
            </Link>
        </div>

        <div
            v-if="releases.data.length"
            class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="release in releases.data"
                :key="release.id"
                class="group flex min-h-80 flex-col overflow-hidden rounded-2xl border border-white/10 bg-stone-900/75 transition hover:-translate-y-0.5 hover:border-white/20"
            >
                <template v-if="release.discogs">
                    <div
                        class="relative aspect-[16/9] overflow-hidden bg-stone-800"
                    >
                        <img
                            v-if="release.discogs.image_url"
                            :src="release.discogs.image_url"
                            :alt="`Cover of ${release.discogs.title}`"
                            class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center text-xs font-semibold tracking-[0.2em] text-stone-600 uppercase"
                        >
                            No cover image
                        </div>
                        <span
                            class="absolute top-3 right-3 rounded-full border border-black/20 bg-stone-950/85 px-3 py-1 text-xs font-semibold text-stone-200 backdrop-blur"
                        >
                            {{ release.physical_copies_count }}
                            {{
                                release.physical_copies_count === 1
                                    ? 'copy'
                                    : 'copies'
                            }}
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <p
                            class="truncate text-xs font-semibold tracking-[0.16em] text-amber-300 uppercase"
                        >
                            {{
                                release.discogs.artists.join(', ') ||
                                'Unknown artist'
                            }}
                        </p>
                        <h2
                            class="mt-2 text-xl font-semibold tracking-tight text-stone-50"
                        >
                            <Link
                                :href="show(release.id)"
                                class="rounded-sm focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-amber-400"
                            >
                                {{ release.discogs.title }}
                            </Link>
                        </h2>
                        <p class="mt-2 text-sm text-stone-400">
                            {{ release.effective_year ?? 'Year unknown' }}
                            <span
                                v-if="release.is_year_corrected"
                                class="ml-1 text-emerald-300"
                                >corrected</span
                            >
                            <span
                                v-if="release.discogs.labels.length"
                                class="px-1 text-stone-700"
                                >/</span
                            >
                            {{ release.discogs.labels.join(', ') }}
                        </p>
                        <p
                            class="mt-auto pt-5 text-xs leading-5 text-stone-500"
                        >
                            <DiscogsAttribution
                                :source-url="release.discogs.source_url"
                            />
                        </p>
                    </div>
                </template>
                <div v-else class="flex flex-1 flex-col justify-between p-6">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Personal inventory
                        </p>
                        <h2 class="mt-3 text-xl font-semibold text-stone-200">
                            Discogs details hidden
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-stone-400">
                            This release is older than the six-hour display
                            limit. Personal copy information remains available.
                        </p>
                    </div>
                    <div class="mt-8 flex items-end justify-between gap-4">
                        <p class="text-xs text-stone-500">
                            {{ release.physical_copies_count }}
                            {{
                                release.physical_copies_count === 1
                                    ? 'copy'
                                    : 'copies'
                            }}
                            <template v-if="release.effective_year">
                                / {{ release.effective_year }}</template
                            >
                        </p>
                        <Link
                            :href="show(release.id)"
                            class="rounded-full border border-stone-600 px-4 py-2 text-sm font-semibold text-stone-200 transition hover:border-stone-400 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                        >
                            View inventory
                        </Link>
                    </div>
                </div>
            </article>
        </div>

        <div
            v-else
            class="mt-6 rounded-2xl border border-dashed border-stone-700 bg-stone-900/40 px-6 py-16 text-center"
        >
            <p class="text-lg font-semibold text-stone-200">
                No matching releases
            </p>
            <p class="mt-2 text-sm text-stone-500">
                Try another search or wait for your collection import to
                complete.
            </p>
        </div>

        <nav
            v-if="releases.last_page > 1"
            aria-label="Collection pages"
            class="mt-7 flex items-center justify-between gap-4"
        >
            <Link
                :href="
                    collection({
                        query: {
                            page: releases.current_page - 1,
                            q: filters.q,
                            filter: filters.filter,
                            value: filters.value,
                        },
                    })
                "
                :class="[
                    'rounded-full border border-stone-700 px-4 py-2 text-sm font-medium text-stone-300 transition hover:border-stone-500 hover:text-stone-100',
                    !releases.prev_page_url && 'pointer-events-none opacity-40',
                ]"
                preserve-scroll
            >
                Previous
            </Link>
            <span class="text-xs text-stone-500"
                >Page {{ releases.current_page }} of
                {{ releases.last_page }}</span
            >
            <Link
                :href="
                    collection({
                        query: {
                            page: releases.current_page + 1,
                            q: filters.q,
                            filter: filters.filter,
                            value: filters.value,
                        },
                    })
                "
                :class="[
                    'rounded-full border border-stone-700 px-4 py-2 text-sm font-medium text-stone-300 transition hover:border-stone-500 hover:text-stone-100',
                    !releases.next_page_url && 'pointer-events-none opacity-40',
                ]"
                preserve-scroll
            >
                Next
            </Link>
        </nav>
    </div>
</template>
