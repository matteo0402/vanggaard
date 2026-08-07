<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div>
        <Head title="Collection room" />

        <section
            class="relative overflow-hidden rounded-3xl border border-white/10 bg-stone-900 px-6 py-8 shadow-2xl shadow-black/20 sm:px-8 sm:py-10 lg:px-12 lg:py-12"
        >
            <div
                aria-hidden="true"
                class="absolute top-0 right-0 size-72 translate-x-1/3 -translate-y-1/3 rounded-full border-[48px] border-amber-400/10"
            />
            <div class="relative max-w-3xl">
                <p
                    class="mb-4 text-xs font-semibold tracking-[0.28em] text-amber-400 uppercase"
                >
                    Private collection room
                </p>
                <h1
                    class="max-w-2xl text-4xl leading-[0.95] font-semibold tracking-tight text-stone-50 sm:text-5xl lg:text-6xl"
                >
                    Know every record.<br />Find the right one.
                </h1>
                <p class="mt-6 max-w-xl text-base leading-7 text-stone-300">
                    Your local catalog brings source data and your own DJ notes
                    together without confusing who owns what.
                </p>
            </div>
        </section>

        <section aria-labelledby="data-areas" class="mt-6">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.22em] text-stone-500 uppercase"
                    >
                        At a glance
                    </p>
                    <h2
                        id="data-areas"
                        class="mt-1 text-xl font-semibold tracking-tight text-stone-100"
                    >
                        Two sources, one collection
                    </h2>
                </div>
                <span class="hidden text-sm text-stone-500 sm:block">
                    Ready for your first sync
                </span>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <article
                    class="relative overflow-hidden rounded-2xl border border-amber-400/20 bg-amber-400/8 p-6"
                >
                    <div class="flex items-start justify-between gap-5">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase"
                            >
                                Discogs-sourced
                            </p>
                            <h3
                                class="mt-3 text-2xl font-semibold tracking-tight text-stone-50"
                            >
                                Catalog facts
                            </h3>
                        </div>
                        <span
                            aria-hidden="true"
                            class="flex size-12 shrink-0 items-center justify-center rounded-full border border-amber-300/30"
                        >
                            <span
                                class="size-4 rounded-full border-4 border-amber-300"
                            />
                        </span>
                    </div>
                    <p class="mt-4 max-w-md text-sm leading-6 text-stone-300">
                        Release titles, artists, labels, tracks, formats, and
                        credits stay visibly connected to their source.
                    </p>
                </article>

                <article
                    class="relative overflow-hidden rounded-2xl border border-emerald-400/20 bg-emerald-400/8 p-6"
                >
                    <div class="flex items-start justify-between gap-5">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-emerald-300 uppercase"
                            >
                                Yours
                            </p>
                            <h3
                                class="mt-3 text-2xl font-semibold tracking-tight text-stone-50"
                            >
                                Collector context
                            </h3>
                        </div>
                        <span
                            aria-hidden="true"
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-emerald-300/30"
                        >
                            <span class="size-4 rounded-sm bg-emerald-300" />
                        </span>
                    </div>
                    <p class="mt-4 max-w-md text-sm leading-6 text-stone-300">
                        Notes, ratings, tags, riddims, playlists, and storage
                        locations remain personal and independently editable.
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>
