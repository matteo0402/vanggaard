<script setup lang="ts">
import { Form, Head, Link, usePoll } from '@inertiajs/vue3';
import ReleaseRefreshController from '@/actions/App/Http/Controllers/ReleaseRefreshController';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';

defineOptions({ layout: AppLayout });

type SyncRun = {
    status: 'pending' | 'running' | 'failed' | 'completed';
    items_seen: number;
    items_created: number;
    items_updated: number;
    items_removed: number;
    last_completed_page: number;
    total_pages: number | null;
    total_items: number | null;
    completed_at: string | null;
    error: string | null;
};

defineProps<{
    synchronization: {
        account_configured: boolean;
        current: SyncRun | null;
        last_success: SyncRun | null;
        release_refreshes: {
            active: number;
            failed: number;
            latest_error: string | null;
        };
    };
    refreshable_releases: {
        data: Array<{
            id: number;
            collection_item_id: number;
            title: string | null;
            discogs_id: number | null;
            source_url: string | null;
            is_fresh: boolean;
            refresh_status: 'idle' | 'queued' | 'refreshing' | 'failed';
        }>;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

usePoll(
    5000,
    { only: ['synchronization', 'refreshable_releases'] },
    { mode: 'rest' },
);

function formatTimestamp(timestamp: string | null): string {
    if (timestamp === null) {
        return 'Not yet completed';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(timestamp));
}
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
                    {{
                        synchronization.current
                            ? synchronization.current.status === 'failed'
                                ? 'Synchronization needs attention'
                                : 'Synchronization active'
                            : synchronization.last_success
                              ? 'Catalog synchronized'
                              : 'Ready for your first sync'
                    }}
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

        <section aria-labelledby="synchronization-status" class="mt-6">
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.22em] text-stone-500 uppercase"
                >
                    Discogs operations
                </p>
                <h2
                    id="synchronization-status"
                    class="mt-1 text-xl font-semibold tracking-tight text-stone-100"
                >
                    Synchronization status
                </h2>
            </div>

            <div
                v-if="!synchronization.account_configured"
                class="mt-4 rounded-2xl border border-stone-700 bg-stone-900/70 p-6 text-sm leading-6 text-stone-300"
            >
                Connect a Discogs account to begin synchronizing your
                collection.
            </div>

            <div v-else class="mt-4 grid gap-4 lg:grid-cols-3">
                <article
                    class="rounded-2xl border border-stone-700 bg-stone-900/70 p-6 lg:col-span-2"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-amber-300 uppercase"
                            >
                                Current run
                            </p>
                            <h3
                                class="mt-2 text-lg font-semibold tracking-tight text-stone-50 capitalize"
                            >
                                {{
                                    synchronization.current?.status ??
                                    'Waiting for schedule'
                                }}
                            </h3>
                        </div>
                        <span
                            v-if="synchronization.current"
                            class="rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-xs font-medium text-amber-200"
                        >
                            {{ synchronization.current.items_seen }}
                            <template
                                v-if="
                                    synchronization.current.total_items !== null
                                "
                            >
                                / {{ synchronization.current.total_items }}
                            </template>
                            records
                        </span>
                    </div>

                    <template v-if="synchronization.current">
                        <progress
                            v-if="synchronization.current.total_items"
                            class="mt-5 h-2 w-full overflow-hidden rounded-full accent-amber-400"
                            :max="synchronization.current.total_items"
                            :value="synchronization.current.items_seen"
                        />
                        <p
                            v-if="synchronization.current.error"
                            class="mt-4 rounded-xl border border-red-400/20 bg-red-400/8 px-4 py-3 text-sm text-red-200"
                        >
                            {{ synchronization.current.error }}
                        </p>
                        <p v-else class="mt-4 text-sm text-stone-400">
                            Page
                            {{ synchronization.current.last_completed_page }}
                            <template
                                v-if="
                                    synchronization.current.total_pages !== null
                                "
                            >
                                of {{ synchronization.current.total_pages }}
                            </template>
                            processed.
                        </p>
                    </template>
                    <p v-else class="mt-4 text-sm leading-6 text-stone-400">
                        The next collection reconciliation starts automatically.
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-stone-700 bg-stone-900/70 p-6"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase"
                    >
                        Last success
                    </p>
                    <p
                        v-if="synchronization.last_success"
                        class="mt-3 text-3xl font-semibold tracking-tight text-stone-50"
                    >
                        {{ synchronization.last_success.items_seen }}
                    </p>
                    <p v-else class="mt-3 text-lg font-semibold text-stone-200">
                        Not yet completed
                    </p>
                    <p class="mt-2 text-sm leading-6 text-stone-400">
                        {{
                            synchronization.last_success
                                ? 'records confirmed in the latest completed run.'
                                : 'Progress will appear here after the first completed run.'
                        }}
                    </p>
                    <p
                        v-if="synchronization.last_success"
                        class="mt-2 text-xs text-stone-500"
                    >
                        {{
                            formatTimestamp(
                                synchronization.last_success.completed_at,
                            )
                        }}
                    </p>
                    <div
                        class="mt-5 grid grid-cols-2 gap-3 border-t border-stone-700 pt-4 text-sm"
                    >
                        <div>
                            <p class="text-stone-500">Refreshing</p>
                            <p class="mt-1 font-semibold text-stone-200">
                                {{ synchronization.release_refreshes.active }}
                            </p>
                        </div>
                        <div>
                            <p class="text-stone-500">Retry later</p>
                            <p class="mt-1 font-semibold text-stone-200">
                                {{ synchronization.release_refreshes.failed }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="synchronization.release_refreshes.latest_error"
                        class="mt-4 text-xs leading-5 text-red-200"
                    >
                        {{ synchronization.release_refreshes.latest_error }}
                    </p>
                </article>
            </div>
        </section>

        <section
            v-if="synchronization.account_configured"
            aria-labelledby="manual-refresh"
            class="mt-6"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.22em] text-stone-500 uppercase"
                >
                    Manual operation
                </p>
                <h2
                    id="manual-refresh"
                    class="mt-1 text-xl font-semibold tracking-tight text-stone-100"
                >
                    Refresh an individual release
                </h2>
                <p class="mt-2 max-w-3xl text-xs leading-5 text-stone-500">
                    This application uses Discogs’ API but is not affiliated
                    with, sponsored or endorsed by Discogs. ‘Discogs’ is a
                    trademark of Zink Media, LLC.
                </p>
            </div>

            <div
                v-if="refreshable_releases.data.length"
                class="mt-4 divide-y divide-stone-700 overflow-hidden rounded-2xl border border-stone-700 bg-stone-900/70"
            >
                <div
                    v-for="release in refreshable_releases.data"
                    :key="release.id"
                    class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <template v-if="release.is_fresh">
                            <p class="truncate font-medium text-stone-100">
                                {{ release.title }}
                            </p>
                            <p class="mt-1 text-xs text-stone-500">
                                <a
                                    :href="release.source_url ?? undefined"
                                    target="_blank"
                                    rel="noopener"
                                    class="underline decoration-stone-700 underline-offset-2 hover:text-stone-300"
                                >
                                    Data provided by Discogs.
                                </a>
                                <span class="px-1 text-stone-700">/</span>
                                Release #{{ release.discogs_id }}
                                <span class="px-1 text-stone-700">/</span>
                                {{ release.refresh_status }}
                            </p>
                        </template>
                        <template v-else>
                            <p class="font-medium text-stone-300">
                                Stale release data hidden
                            </p>
                            <p class="mt-1 text-xs text-stone-500">
                                Collection item #{{
                                    release.collection_item_id
                                }}
                                <span class="px-1 text-stone-700">/</span>
                                Refresh to restore current details
                            </p>
                        </template>
                    </div>
                    <Form
                        :action="ReleaseRefreshController(release.id)"
                        #default="{ processing }"
                    >
                        <button
                            type="submit"
                            class="w-full rounded-full border border-amber-300/30 bg-amber-300/10 px-4 py-2 text-sm font-semibold text-amber-200 transition hover:border-amber-300/50 hover:bg-amber-300/15 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            :disabled="
                                processing ||
                                ['queued', 'refreshing'].includes(
                                    release.refresh_status,
                                )
                            "
                        >
                            {{ processing ? 'Queueing...' : 'Refresh now' }}
                        </button>
                    </Form>
                </div>
            </div>
            <p
                v-else
                class="mt-4 rounded-2xl border border-stone-700 bg-stone-900/70 p-6 text-sm text-stone-400"
            >
                Releases will appear here after the collection import begins.
            </p>

            <nav
                v-if="refreshable_releases.last_page > 1"
                aria-label="Release refresh pages"
                class="mt-4 flex items-center justify-between gap-4"
            >
                <Link
                    :href="
                        home({
                            query: {
                                refresh_page:
                                    refreshable_releases.current_page - 1,
                            },
                        })
                    "
                    :class="[
                        'rounded-full border border-stone-700 px-4 py-2 text-sm font-medium text-stone-300 transition hover:border-stone-500 hover:text-stone-100',
                        !refreshable_releases.prev_page_url &&
                            'pointer-events-none opacity-40',
                    ]"
                    preserve-scroll
                >
                    Previous
                </Link>
                <span class="text-xs text-stone-500">
                    Page {{ refreshable_releases.current_page }} of
                    {{ refreshable_releases.last_page }}
                </span>
                <Link
                    :href="
                        home({
                            query: {
                                refresh_page:
                                    refreshable_releases.current_page + 1,
                            },
                        })
                    "
                    :class="[
                        'rounded-full border border-stone-700 px-4 py-2 text-sm font-medium text-stone-300 transition hover:border-stone-500 hover:text-stone-100',
                        !refreshable_releases.next_page_url &&
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
