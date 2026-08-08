<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PersonalReleaseMetadataController from '@/actions/App/Http/Controllers/PersonalReleaseMetadataController';
import ReleaseRefreshController from '@/actions/App/Http/Controllers/ReleaseRefreshController';
import { update as updateReleaseVocabulary } from '@/actions/App/Http/Controllers/ReleaseVocabularyController';
import {
    destroy as destroyRiddim,
    store as storeRiddim,
    update as updateRiddim,
} from '@/actions/App/Http/Controllers/RiddimController';
import {
    destroy as destroyTag,
    store as storeTag,
    update as updateTag,
} from '@/actions/App/Http/Controllers/TagController';
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

type Vocabulary = {
    id: number;
    name: string;
};

type TrackRiddimOverride = {
    sequence: number;
    riddim_id: number;
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
                sequence: number;
                position: string | null;
                title: string;
                duration: string | null;
                effective_riddim_id: number | null;
                is_riddim_override: boolean;
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
            tags: number[];
            release_riddim_id: number | null;
            track_riddim_overrides: TrackRiddimOverride[];
        };
        vocabularies: {
            tags: Vocabulary[];
            riddims: Vocabulary[];
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
const vocabularyForm = useForm({
    tag_ids: [...props.release.personal.tags],
    release_riddim_id: props.release.personal.release_riddim_id,
    track_riddim_overrides: [...props.release.personal.track_riddim_overrides],
});
const selectedVideo = computed(
    () =>
        props.release.discogs?.videos.find(
            (video) => video.id === selectedVideoId.value,
        ) ??
        props.release.discogs?.videos[0] ??
        null,
);
const vocabularyError = computed(
    () => Object.values(vocabularyForm.errors)[0] ?? null,
);

watch(
    () => props.release.personal,
    (personal) => {
        vocabularyForm.tag_ids = [...personal.tags];
        vocabularyForm.release_riddim_id = personal.release_riddim_id;
        vocabularyForm.track_riddim_overrides = [
            ...personal.track_riddim_overrides,
        ];
    },
);

function toggleTag(tagId: number): void {
    vocabularyForm.tag_ids = vocabularyForm.tag_ids.includes(tagId)
        ? vocabularyForm.tag_ids.filter((id) => id !== tagId)
        : [...vocabularyForm.tag_ids, tagId];
}

function setReleaseRiddim(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    vocabularyForm.release_riddim_id = value === '' ? null : Number(value);
}

function trackOverride(sequence: number): number | null {
    return (
        vocabularyForm.track_riddim_overrides.find(
            (override) => override.sequence === sequence,
        )?.riddim_id ?? null
    );
}

function setTrackOverride(sequence: number, event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const otherOverrides = vocabularyForm.track_riddim_overrides.filter(
        (override) => override.sequence !== sequence,
    );

    vocabularyForm.track_riddim_overrides =
        value === ''
            ? otherOverrides
            : [...otherOverrides, { sequence, riddim_id: Number(value) }].sort(
                  (first, second) => first.sequence - second.sequence,
              );
}

function riddimName(riddimId: number | null): string | null {
    return (
        props.release.vocabularies.riddims.find(
            (riddim) => riddim.id === riddimId,
        )?.name ?? null
    );
}

function saveVocabularies(): void {
    vocabularyForm.submit(updateReleaseVocabulary(props.release.id), {
        preserveScroll: true,
    });
}

function confirmVocabularyDelete(event: SubmitEvent, name: string): void {
    if (!window.confirm(`Remove “${name}” from your vocabulary?`)) {
        event.preventDefault();
    }
}

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
                            :key="track.sequence"
                            class="grid grid-cols-[3rem_1fr] items-center gap-x-3 gap-y-2 py-3 text-sm sm:grid-cols-[3rem_1fr_auto]"
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
                            <div class="col-start-2 sm:col-span-2">
                                <label
                                    :for="`track-riddim-${track.sequence}`"
                                    class="sr-only"
                                >
                                    Riddim override for {{ track.title }}
                                </label>
                                <select
                                    :id="`track-riddim-${track.sequence}`"
                                    :value="trackOverride(track.sequence) ?? ''"
                                    class="min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-3 text-xs text-stone-200 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                    @change="
                                        setTrackOverride(track.sequence, $event)
                                    "
                                >
                                    <option value="">
                                        Inherit release riddim ({{
                                            riddimName(
                                                vocabularyForm.release_riddim_id,
                                            ) ?? 'none set'
                                        }})
                                    </option>
                                    <option
                                        v-for="riddim in release.vocabularies
                                            .riddims"
                                        :key="riddim.id"
                                        :value="riddim.id"
                                    >
                                        Override: {{ riddim.name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-stone-500">
                                    Effective:
                                    {{
                                        riddimName(
                                            trackOverride(track.sequence) ??
                                                vocabularyForm.release_riddim_id,
                                        ) ?? 'No riddim'
                                    }}
                                </p>
                            </div>
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
                    aria-labelledby="release-vocabulary"
                    class="rounded-2xl border border-white/10 bg-stone-900/70 p-6"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase"
                    >
                        Yours, not Discogs
                    </p>
                    <h2
                        id="release-vocabulary"
                        class="mt-2 text-xl font-semibold text-stone-50"
                    >
                        Tags & riddims
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-400">
                        Tags describe this release. Its riddim is inherited by
                        every track unless you choose an override in the track
                        list.
                    </p>

                    <form
                        class="mt-6 flex flex-col gap-5"
                        @submit.prevent="saveVocabularies"
                    >
                        <fieldset>
                            <legend
                                class="text-sm font-semibold text-stone-200"
                            >
                                Release tags
                            </legend>
                            <div
                                v-if="release.vocabularies.tags.length"
                                class="mt-3 flex flex-wrap gap-2"
                            >
                                <label
                                    v-for="tag in release.vocabularies.tags"
                                    :key="tag.id"
                                    :class="[
                                        'flex min-h-11 cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm transition',
                                        vocabularyForm.tag_ids.includes(tag.id)
                                            ? 'border-emerald-300/40 bg-emerald-300/10 text-emerald-100'
                                            : 'border-white/10 bg-stone-950/40 text-stone-300 hover:border-white/20',
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            vocabularyForm.tag_ids.includes(
                                                tag.id,
                                            )
                                        "
                                        class="size-4 rounded border-white/20 bg-stone-900 text-emerald-400 focus:ring-emerald-300/30"
                                        @change="toggleTag(tag.id)"
                                    />
                                    {{ tag.name }}
                                </label>
                            </div>
                            <p v-else class="mt-2 text-xs text-stone-500">
                                Create your first tag below.
                            </p>
                        </fieldset>

                        <div>
                            <label
                                for="release-riddim"
                                class="text-sm font-semibold text-stone-200"
                            >
                                Release riddim
                            </label>
                            <select
                                id="release-riddim"
                                :value="vocabularyForm.release_riddim_id ?? ''"
                                class="mt-2 min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-4 text-sm text-stone-100 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                @change="setReleaseRiddim"
                            >
                                <option value="">No release riddim</option>
                                <option
                                    v-for="riddim in release.vocabularies
                                        .riddims"
                                    :key="riddim.id"
                                    :value="riddim.id"
                                >
                                    {{ riddim.name }}
                                </option>
                            </select>
                        </div>

                        <p v-if="vocabularyError" class="text-xs text-red-300">
                            {{ vocabularyError }}
                        </p>

                        <div class="flex items-center justify-between gap-4">
                            <p
                                aria-live="polite"
                                class="text-sm font-semibold text-emerald-300"
                            >
                                <span v-if="vocabularyForm.recentlySuccessful">
                                    Tags and riddims saved.
                                </span>
                            </p>
                            <button
                                type="submit"
                                :disabled="vocabularyForm.processing"
                                class="min-h-11 rounded-full bg-amber-300 px-5 text-sm font-semibold text-stone-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    vocabularyForm.processing
                                        ? 'Saving…'
                                        : 'Save organization'
                                }}
                            </button>
                        </div>
                    </form>

                    <details class="mt-6 border-t border-white/8 pt-5">
                        <summary
                            class="cursor-pointer text-sm font-semibold text-amber-300 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                        >
                            Manage vocabularies
                        </summary>

                        <div class="mt-5 flex flex-col gap-6">
                            <div>
                                <h3
                                    class="text-sm font-semibold text-stone-200"
                                >
                                    Tags
                                </h3>
                                <Form
                                    :action="storeTag()"
                                    error-bag="createTag"
                                    :options="{ preserveScroll: true }"
                                    reset-on-success
                                    #default="{ errors, processing }"
                                    class="mt-3 flex gap-2"
                                >
                                    <div class="grow">
                                        <label for="new-tag" class="sr-only">
                                            New tag name
                                        </label>
                                        <input
                                            id="new-tag"
                                            name="name"
                                            required
                                            maxlength="255"
                                            placeholder="New tag"
                                            class="min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-3 text-sm text-stone-100 placeholder:text-stone-600 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                        />
                                        <p
                                            v-if="errors.name"
                                            class="mt-1 text-xs text-red-300"
                                        >
                                            {{ errors.name }}
                                        </p>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="processing"
                                        class="min-h-11 rounded-xl border border-white/10 px-4 text-sm font-semibold text-stone-200 hover:border-white/20 disabled:opacity-50"
                                    >
                                        Add
                                    </button>
                                </Form>

                                <div class="mt-3 flex flex-col gap-2">
                                    <div
                                        v-for="tag in release.vocabularies.tags"
                                        :key="tag.id"
                                        class="flex items-start gap-2"
                                    >
                                        <Form
                                            :action="updateTag(tag.id)"
                                            :error-bag="`tag-${tag.id}`"
                                            :options="{ preserveScroll: true }"
                                            #default="{ errors, processing }"
                                            class="grow"
                                        >
                                            <div class="flex gap-2">
                                                <label
                                                    :for="`tag-${tag.id}`"
                                                    class="sr-only"
                                                >
                                                    Rename {{ tag.name }}
                                                </label>
                                                <input
                                                    :id="`tag-${tag.id}`"
                                                    name="name"
                                                    required
                                                    maxlength="255"
                                                    :value="tag.name"
                                                    class="min-h-11 min-w-0 grow rounded-xl border border-white/10 bg-stone-950/60 px-3 text-sm text-stone-100 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                                />
                                                <button
                                                    type="submit"
                                                    :disabled="processing"
                                                    class="min-h-11 rounded-xl border border-white/10 px-3 text-xs font-semibold text-stone-300 hover:border-white/20 disabled:opacity-50"
                                                >
                                                    Rename
                                                </button>
                                            </div>
                                            <p
                                                v-if="errors.name"
                                                class="mt-1 text-xs text-red-300"
                                            >
                                                {{ errors.name }}
                                            </p>
                                        </Form>
                                        <Form
                                            :action="destroyTag(tag.id)"
                                            :options="{ preserveScroll: true }"
                                            #default="{ processing }"
                                            @submit="
                                                confirmVocabularyDelete(
                                                    $event,
                                                    tag.name,
                                                )
                                            "
                                        >
                                            <button
                                                type="submit"
                                                :disabled="processing"
                                                class="min-h-11 rounded-xl px-3 text-xs font-semibold text-red-300 hover:bg-red-400/10 disabled:opacity-50"
                                            >
                                                Remove
                                            </button>
                                        </Form>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3
                                    class="text-sm font-semibold text-stone-200"
                                >
                                    Riddims
                                </h3>
                                <Form
                                    :action="storeRiddim()"
                                    error-bag="createRiddim"
                                    :options="{ preserveScroll: true }"
                                    reset-on-success
                                    #default="{ errors, processing }"
                                    class="mt-3 flex gap-2"
                                >
                                    <div class="grow">
                                        <label for="new-riddim" class="sr-only">
                                            New riddim name
                                        </label>
                                        <input
                                            id="new-riddim"
                                            name="name"
                                            required
                                            maxlength="255"
                                            placeholder="New riddim"
                                            class="min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-3 text-sm text-stone-100 placeholder:text-stone-600 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                        />
                                        <p
                                            v-if="errors.name"
                                            class="mt-1 text-xs text-red-300"
                                        >
                                            {{ errors.name }}
                                        </p>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="processing"
                                        class="min-h-11 rounded-xl border border-white/10 px-4 text-sm font-semibold text-stone-200 hover:border-white/20 disabled:opacity-50"
                                    >
                                        Add
                                    </button>
                                </Form>

                                <div class="mt-3 flex flex-col gap-2">
                                    <div
                                        v-for="riddim in release.vocabularies
                                            .riddims"
                                        :key="riddim.id"
                                        class="flex items-start gap-2"
                                    >
                                        <Form
                                            :action="updateRiddim(riddim.id)"
                                            :error-bag="`riddim-${riddim.id}`"
                                            :options="{ preserveScroll: true }"
                                            #default="{ errors, processing }"
                                            class="grow"
                                        >
                                            <div class="flex gap-2">
                                                <label
                                                    :for="`riddim-${riddim.id}`"
                                                    class="sr-only"
                                                >
                                                    Rename {{ riddim.name }}
                                                </label>
                                                <input
                                                    :id="`riddim-${riddim.id}`"
                                                    name="name"
                                                    required
                                                    maxlength="255"
                                                    :value="riddim.name"
                                                    class="min-h-11 min-w-0 grow rounded-xl border border-white/10 bg-stone-950/60 px-3 text-sm text-stone-100 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                                                />
                                                <button
                                                    type="submit"
                                                    :disabled="processing"
                                                    class="min-h-11 rounded-xl border border-white/10 px-3 text-xs font-semibold text-stone-300 hover:border-white/20 disabled:opacity-50"
                                                >
                                                    Rename
                                                </button>
                                            </div>
                                            <p
                                                v-if="errors.name"
                                                class="mt-1 text-xs text-red-300"
                                            >
                                                {{ errors.name }}
                                            </p>
                                        </Form>
                                        <Form
                                            :action="destroyRiddim(riddim.id)"
                                            :options="{ preserveScroll: true }"
                                            #default="{ processing }"
                                            @submit="
                                                confirmVocabularyDelete(
                                                    $event,
                                                    riddim.name,
                                                )
                                            "
                                        >
                                            <button
                                                type="submit"
                                                :disabled="processing"
                                                class="min-h-11 rounded-xl px-3 text-xs font-semibold text-red-300 hover:bg-red-400/10 disabled:opacity-50"
                                            >
                                                Remove
                                            </button>
                                        </Form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
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
