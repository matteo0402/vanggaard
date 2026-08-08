<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PersonalReleaseMetadataController from '@/actions/App/Http/Controllers/PersonalReleaseMetadataController';
import ReleaseRefreshController from '@/actions/App/Http/Controllers/ReleaseRefreshController';
import DiscogsAttribution from '@/components/DiscogsAttribution.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { collection } from '@/routes';

defineOptions({ layout: AppLayout });

type Value<T> = {
    effective: T | null;
    discogs: T | null;
    is_corrected: boolean;
};

type ReleaseVideo = {
    id: number;
    title: string;
    description: string | null;
    duration: number | null;
    external_url: string | null;
    embed_url: string | null;
};

const props = defineProps<{
    release: {
        id: number;
        is_fresh: boolean;
        values: {
            title: Value<string>;
            year: Value<number> & { is_approximate: boolean };
        };
        discogs: {
            discogs_id: number;
            title: string;
            country: string | null;
            released: string | null;
            released_year: number | null;
            source_url: string;
            image_url: string | null;
            artists: Array<{ name: string; join: string | null }>;
            labels: Array<{ name: string; catalog_number: string | null }>;
            formats: Array<{
                name: string;
                quantity: number | null;
                text: string | null;
                descriptions: string[];
            }>;
            tracks: Array<{
                position: string | null;
                title: string;
                duration: string | null;
            }>;
            videos: ReleaseVideo[];
        } | null;
        personal: {
            notes: string | null;
            rating: number | null;
            is_favourite: boolean;
            is_dj_ready: boolean;
            energy: number | null;
            bpm: number | null;
        };
        physical_copies: Array<{ id: number; locations: string[] }>;
        sync: {
            status: 'idle' | 'queued' | 'refreshing' | 'failed';
            fetched_at: string;
            refresh_attempted_at: string | null;
            refresh_failed_at: string | null;
            error: string | null;
        };
    };
}>();

const selectedVideoId = ref(props.release.discogs?.videos[0]?.id ?? null);
const selectedVideo = computed(
    () =>
        props.release.discogs?.videos.find(
            (video) => video.id === selectedVideoId.value,
        ) ??
        props.release.discogs?.videos[0] ??
        null,
);

function formatTimestamp(timestamp: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(timestamp));
}

function formatDuration(duration: number | null): string | null {
    if (duration === null) {
        return null;
    }

    const minutes = Math.floor(duration / 60);
    const seconds = String(duration % 60).padStart(2, '0');

    return `${minutes}:${seconds}`;
}
</script>

<template>
    <div>
        <Head :title="release.values.title.effective ?? 'Collection release'" />

        <Link
            :href="collection()"
            class="inline-flex min-h-11 items-center rounded-lg text-sm font-semibold text-stone-400 transition hover:text-stone-100 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
        >
            <span aria-hidden="true" class="mr-2">←</span> Back to collection
        </Link>

        <section
            class="mt-4 overflow-hidden rounded-3xl border border-white/10 bg-stone-900"
        >
            <template v-if="release.discogs">
                <div class="grid lg:grid-cols-[minmax(18rem,0.8fr)_1.2fr]">
                    <div class="aspect-square bg-stone-800 lg:aspect-auto">
                        <img
                            v-if="release.discogs.image_url"
                            :src="release.discogs.image_url"
                            :alt="`Cover of ${release.discogs.title}`"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full min-h-80 items-center justify-center text-xs font-semibold tracking-[0.2em] text-stone-600 uppercase"
                        >
                            No cover image
                        </div>
                    </div>
                    <div class="flex flex-col p-6 sm:p-9 lg:p-12">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase"
                        >
                            {{
                                release.discogs.artists
                                    .map((artist) => artist.name)
                                    .join(' ') || 'Unknown artist'
                            }}
                        </p>
                        <h1
                            class="mt-3 text-4xl leading-tight font-semibold tracking-tight text-stone-50 sm:text-5xl"
                        >
                            {{ release.values.title.effective }}
                        </h1>
                        <div
                            class="mt-5 flex flex-wrap gap-2 text-xs text-stone-300"
                        >
                            <span
                                class="rounded-full border border-white/10 px-3 py-1.5"
                                >{{
                                    release.values.year.effective
                                        ? `${release.values.year.is_approximate ? 'c. ' : ''}${release.values.year.effective}`
                                        : 'Year unknown'
                                }}</span
                            >
                            <span
                                v-if="release.discogs.country"
                                class="rounded-full border border-white/10 px-3 py-1.5"
                                >{{ release.discogs.country }}</span
                            >
                            <span
                                v-for="format in release.discogs.formats"
                                :key="`${format.name}-${format.text}`"
                                class="rounded-full border border-white/10 px-3 py-1.5"
                                >{{ format.name
                                }}<template v-if="format.text">
                                    / {{ format.text }}</template
                                ></span
                            >
                        </div>
                        <div
                            v-if="release.values.year.is_corrected"
                            class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/8 px-4 py-3 text-sm text-emerald-200"
                        >
                            Effective year
                            {{ release.values.year.is_approximate ? 'c. ' : ''
                            }}{{ release.values.year.effective }}. Original
                            Discogs year
                            {{ release.values.year.discogs ?? 'not supplied' }}.
                        </div>
                        <p class="mt-auto pt-8 text-sm text-stone-400">
                            <DiscogsAttribution
                                :source-url="release.discogs.source_url"
                            />
                            <span class="px-1 text-stone-700">/</span> Release
                            #{{ release.discogs.discogs_id }}
                        </p>
                    </div>
                </div>
            </template>
            <div v-else class="p-7 sm:p-10">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                >
                    Personal inventory only
                </p>
                <h1
                    class="mt-3 text-3xl font-semibold tracking-tight text-stone-100"
                >
                    Discogs details are temporarily hidden
                </h1>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-stone-400">
                    The local source data is older than the six-hour display
                    limit. Refresh this release to restore current catalog
                    facts.
                </p>
            </div>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
            <div class="flex flex-col gap-6">
                <section
                    aria-labelledby="track-list"
                    class="rounded-2xl border border-white/10 bg-stone-900/70 p-6"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-amber-300 uppercase"
                    >
                        Discogs-sourced
                    </p>
                    <h2
                        id="track-list"
                        class="mt-2 text-2xl font-semibold tracking-tight text-stone-50"
                    >
                        Track list
                    </h2>
                    <ol
                        v-if="release.discogs?.tracks.length"
                        class="mt-5 divide-y divide-white/8"
                    >
                        <li
                            v-for="track in release.discogs.tracks"
                            :key="`${track.position}-${track.title}`"
                            class="grid grid-cols-[3rem_1fr_auto] gap-3 py-3 text-sm"
                        >
                            <span class="font-mono text-stone-500">{{
                                track.position ?? '—'
                            }}</span>
                            <span class="text-stone-200">{{
                                track.title
                            }}</span>
                            <span class="font-mono text-stone-500">{{
                                track.duration ?? ''
                            }}</span>
                        </li>
                    </ol>
                    <p v-else class="mt-5 text-sm leading-6 text-stone-500">
                        No current track listing is available.
                    </p>
                    <p
                        v-if="release.discogs"
                        class="mt-5 border-t border-white/8 pt-4 text-xs text-stone-500"
                    >
                        <DiscogsAttribution
                            :source-url="release.discogs.source_url"
                        />
                    </p>
                </section>

                <section
                    aria-labelledby="release-videos"
                    class="overflow-hidden rounded-2xl border border-white/10 bg-stone-900/70"
                >
                    <div class="p-6">
                        <p
                            class="text-xs font-semibold tracking-[0.18em] text-amber-300 uppercase"
                        >
                            Discogs-sourced
                        </p>
                        <h2
                            id="release-videos"
                            class="mt-2 text-2xl font-semibold tracking-tight text-stone-50"
                        >
                            Release videos
                        </h2>
                    </div>

                    <template v-if="selectedVideo">
                        <iframe
                            v-if="selectedVideo.embed_url"
                            :src="selectedVideo.embed_url"
                            :title="selectedVideo.title"
                            class="aspect-video w-full border-y border-white/10 bg-black"
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            sandbox="allow-scripts allow-same-origin allow-presentation"
                            allow="
                                accelerometer;
                                autoplay;
                                encrypted-media;
                                gyroscope;
                                picture-in-picture;
                                web-share;
                            "
                            allowfullscreen
                        />
                        <div
                            v-else
                            class="flex aspect-video items-center justify-center border-y border-white/10 bg-stone-950 p-8 text-center"
                        >
                            <div>
                                <p class="text-sm font-semibold text-stone-200">
                                    This video cannot be embedded safely.
                                </p>
                                <a
                                    v-if="selectedVideo.external_url"
                                    :href="selectedVideo.external_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-4 inline-flex min-h-11 items-center rounded-full border border-amber-300/30 bg-amber-300/10 px-5 text-sm font-semibold text-amber-200 transition hover:bg-amber-300/15 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                                >
                                    Open video externally
                                </a>
                                <p v-else class="mt-3 text-sm text-stone-500">
                                    Its source link is no longer available.
                                </p>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-stone-100">
                                        {{ selectedVideo.title }}
                                    </h3>
                                    <p
                                        v-if="selectedVideo.description"
                                        class="mt-2 text-sm leading-6 text-stone-400"
                                    >
                                        {{ selectedVideo.description }}
                                    </p>
                                </div>
                                <span
                                    v-if="
                                        formatDuration(selectedVideo.duration)
                                    "
                                    class="font-mono text-xs text-stone-500"
                                >
                                    {{ formatDuration(selectedVideo.duration) }}
                                </span>
                            </div>
                            <a
                                v-if="
                                    selectedVideo.embed_url &&
                                    selectedVideo.external_url
                                "
                                :href="selectedVideo.external_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-amber-300 transition hover:text-amber-200 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                            >
                                Player unavailable? Open on YouTube
                            </a>

                            <div
                                v-if="release.discogs!.videos.length > 1"
                                class="mt-5 border-t border-white/8 pt-5"
                            >
                                <p class="text-xs font-semibold text-stone-500">
                                    Choose a video
                                </p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <button
                                        v-for="video in release.discogs!.videos"
                                        :key="video.id"
                                        type="button"
                                        :aria-pressed="
                                            video.id === selectedVideo.id
                                        "
                                        :class="[
                                            'min-h-11 rounded-xl border px-4 py-3 text-left text-sm transition focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400',
                                            video.id === selectedVideo.id
                                                ? 'border-amber-300/40 bg-amber-300/10 text-amber-100'
                                                : 'border-white/10 bg-stone-950/40 text-stone-300 hover:border-white/20',
                                        ]"
                                        @click="selectedVideoId = video.id"
                                    >
                                        {{ video.title }}
                                    </button>
                                </div>
                            </div>

                            <p
                                class="mt-5 border-t border-white/8 pt-4 text-xs text-stone-500"
                            >
                                <DiscogsAttribution
                                    :source-url="release.discogs!.source_url"
                                />
                            </p>
                        </div>
                    </template>
                    <div v-else class="px-6 pb-6">
                        <p class="text-sm leading-6 text-stone-500">
                            No current release videos are available.
                        </p>
                    </div>
                </section>
            </div>

            <div class="flex flex-col gap-6">
                <section
                    aria-labelledby="physical-copies"
                    class="rounded-2xl border border-emerald-400/20 bg-emerald-400/6 p-6"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase"
                    >
                        Yours
                    </p>
                    <h2
                        id="physical-copies"
                        class="mt-2 text-xl font-semibold text-stone-50"
                    >
                        Physical copies
                    </h2>
                    <ul class="mt-4 flex flex-col gap-3">
                        <li
                            v-for="copy in release.physical_copies"
                            :key="copy.id"
                            class="rounded-xl border border-emerald-300/15 bg-stone-950/40 p-4"
                        >
                            <p class="text-sm font-semibold text-stone-200">
                                Copy #{{ copy.id }}
                            </p>
                            <p class="mt-1 text-xs leading-5 text-stone-400">
                                {{
                                    copy.locations.join(', ') ||
                                    'No physical location assigned'
                                }}
                            </p>
                        </li>
                    </ul>
                </section>

                <section
                    aria-labelledby="personal-metadata"
                    class="rounded-2xl border border-white/10 bg-stone-900/70 p-6"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase"
                    >
                        Yours, not Discogs
                    </p>
                    <h2
                        id="personal-metadata"
                        class="mt-2 text-xl font-semibold text-stone-50"
                    >
                        Personal metadata
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-400">
                        Add the details you use while selecting records. Discogs
                        refreshes never replace these values.
                    </p>

                    <Form
                        :action="PersonalReleaseMetadataController(release.id)"
                        :transform="
                            (data) => ({
                                ...data,
                                is_year_approximate:
                                    data.is_year_approximate === '1',
                                is_favourite: data.is_favourite === '1',
                                is_dj_ready: data.is_dj_ready === '1',
                            })
                        "
                        :options="{ preserveScroll: true }"
                        disable-while-processing
                        #default="{ errors, processing, recentlySuccessful }"
                        class="mt-6 flex flex-col gap-5 inert:opacity-60"
                    >
                        <div>
                            <label
                                for="personal-notes"
                                class="text-sm font-semibold text-stone-200"
                            >
                                Notes
                            </label>
                            <textarea
                                id="personal-notes"
                                name="personal_notes"
                                :value="release.personal.notes ?? ''"
                                rows="4"
                                maxlength="5000"
                                placeholder="Condition, mix notes, memories, or anything useful…"
                                class="mt-2 w-full rounded-xl border border-white/10 bg-stone-950/60 px-4 py-3 text-sm leading-6 text-stone-100 placeholder:text-stone-600 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                            />
                            <p
                                v-if="errors.personal_notes"
                                class="mt-2 text-xs text-red-300"
                            >
                                {{ errors.personal_notes }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    for="corrected-year"
                                    class="text-sm font-semibold text-stone-200"
                                >
                                    Corrected year
                                </label>
                                <input
                                    id="corrected-year"
                                    name="corrected_year"
                                    type="number"
                                    min="1000"
                                    :max="new Date().getFullYear() + 1"
                                    :value="
                                        release.values.year.is_corrected
                                            ? release.values.year.effective
                                            : ''
                                    "
                                    placeholder="e.g. 1975"
                                    class="mt-2 min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-4 text-sm text-stone-100 placeholder:text-stone-600 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                />
                                <p class="mt-2 text-xs text-stone-500">
                                    Discogs year:
                                    {{
                                        release.values.year.discogs ??
                                        'not supplied'
                                    }}
                                </p>
                                <p
                                    v-if="errors.corrected_year"
                                    class="mt-2 text-xs text-red-300"
                                >
                                    {{ errors.corrected_year }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="personal-rating"
                                    class="text-sm font-semibold text-stone-200"
                                >
                                    Personal rating
                                </label>
                                <select
                                    id="personal-rating"
                                    name="rating"
                                    :value="release.personal.rating ?? ''"
                                    class="mt-2 min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-4 text-sm text-stone-100 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                >
                                    <option value="">Not rated</option>
                                    <option
                                        v-for="rating in 5"
                                        :key="rating"
                                        :value="rating"
                                    >
                                        {{ rating }}/5
                                    </option>
                                </select>
                                <p class="mt-2 text-xs text-stone-500">
                                    Your rating, separate from Discogs community
                                    ratings.
                                </p>
                                <p
                                    v-if="errors.rating"
                                    class="mt-2 text-xs text-red-300"
                                >
                                    {{ errors.rating }}
                                </p>
                            </div>
                        </div>

                        <label
                            class="flex min-h-11 items-center gap-3 rounded-xl border border-white/10 bg-stone-950/40 px-4 py-3 text-sm text-stone-300"
                        >
                            <input
                                type="checkbox"
                                name="is_year_approximate"
                                value="1"
                                :checked="release.values.year.is_approximate"
                                class="size-4 rounded border-white/20 bg-stone-900 text-amber-400 focus:ring-amber-300/30"
                            />
                            Corrected year is approximate
                        </label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                class="flex min-h-11 items-center gap-3 rounded-xl border border-white/10 bg-stone-950/40 px-4 py-3 text-sm font-semibold text-stone-200"
                            >
                                <input
                                    type="checkbox"
                                    name="is_favourite"
                                    value="1"
                                    :checked="release.personal.is_favourite"
                                    class="size-4 rounded border-white/20 bg-stone-900 text-amber-400 focus:ring-amber-300/30"
                                />
                                Favourite
                            </label>
                            <label
                                class="flex min-h-11 items-center gap-3 rounded-xl border border-white/10 bg-stone-950/40 px-4 py-3 text-sm font-semibold text-stone-200"
                            >
                                <input
                                    type="checkbox"
                                    name="is_dj_ready"
                                    value="1"
                                    :checked="release.personal.is_dj_ready"
                                    class="size-4 rounded border-white/20 bg-stone-900 text-amber-400 focus:ring-amber-300/30"
                                />
                                DJ-ready
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    for="energy"
                                    class="text-sm font-semibold text-stone-200"
                                >
                                    Energy
                                </label>
                                <select
                                    id="energy"
                                    name="energy"
                                    :value="release.personal.energy ?? ''"
                                    class="mt-2 min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-4 text-sm text-stone-100 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                >
                                    <option value="">Not set</option>
                                    <option
                                        v-for="energy in 5"
                                        :key="energy"
                                        :value="energy"
                                    >
                                        {{ energy }}/5
                                    </option>
                                </select>
                                <p
                                    v-if="errors.energy"
                                    class="mt-2 text-xs text-red-300"
                                >
                                    {{ errors.energy }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="bpm"
                                    class="text-sm font-semibold text-stone-200"
                                >
                                    BPM
                                </label>
                                <input
                                    id="bpm"
                                    name="bpm"
                                    type="number"
                                    min="1"
                                    max="999.99"
                                    step="0.01"
                                    inputmode="decimal"
                                    :value="release.personal.bpm ?? ''"
                                    placeholder="e.g. 78.5"
                                    class="mt-2 min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-4 text-sm text-stone-100 placeholder:text-stone-600 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                />
                                <p
                                    v-if="errors.bpm"
                                    class="mt-2 text-xs text-red-300"
                                >
                                    {{ errors.bpm }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <p
                                aria-live="polite"
                                class="text-sm font-semibold text-emerald-300"
                            >
                                <span v-if="recentlySuccessful">
                                    Personal metadata saved.
                                </span>
                            </p>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="min-h-11 rounded-full bg-amber-300 px-5 text-sm font-semibold text-stone-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ processing ? 'Saving…' : 'Save metadata' }}
                            </button>
                        </div>
                    </Form>
                </section>

                <section
                    aria-labelledby="sync-status"
                    class="rounded-2xl border border-white/10 bg-stone-900/70 p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-stone-500 uppercase"
                            >
                                Synchronization
                            </p>
                            <h2
                                id="sync-status"
                                class="mt-2 text-xl font-semibold text-stone-50 capitalize"
                            >
                                {{ release.sync.status }}
                            </h2>
                        </div>
                        <span
                            :class="[
                                'size-3 rounded-full',
                                release.is_fresh
                                    ? 'bg-emerald-400'
                                    : 'bg-amber-400',
                            ]"
                            :title="release.is_fresh ? 'Fresh' : 'Stale'"
                        />
                    </div>
                    <p class="mt-3 text-xs leading-5 text-stone-500">
                        Last fetched
                        {{ formatTimestamp(release.sync.fetched_at) }}
                    </p>
                    <p
                        v-if="release.sync.error"
                        class="mt-3 rounded-lg border border-red-400/20 bg-red-400/8 p-3 text-xs leading-5 text-red-200"
                    >
                        {{ release.sync.error }}
                    </p>
                    <Form
                        :action="ReleaseRefreshController(release.id)"
                        #default="{ processing }"
                        class="mt-5"
                    >
                        <button
                            type="submit"
                            :disabled="
                                processing ||
                                ['queued', 'refreshing'].includes(
                                    release.sync.status,
                                )
                            "
                            class="min-h-11 w-full rounded-full border border-amber-300/30 bg-amber-300/10 px-4 text-sm font-semibold text-amber-200 transition hover:bg-amber-300/15 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ processing ? 'Queueing…' : 'Refresh release' }}
                        </button>
                    </Form>
                </section>
            </div>
        </div>
    </div>
</template>
