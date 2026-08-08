<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/AuthenticatedSessionController';
import {
    assistant,
    boxes,
    browse,
    collection,
    home,
    statistics,
} from '@/routes';

const page = usePage();
const mobileMenu = useTemplateRef<HTMLDetailsElement>('mobile-menu');

const navigation = [
    { label: 'Collection', href: collection(), accent: 'Catalog + yours' },
    { label: 'DJ Assistant', href: assistant(), accent: 'Personal' },
    { label: 'Boxes', href: boxes(), accent: 'Personal' },
    { label: 'Browse', href: browse(), accent: 'Catalog + yours' },
    { label: 'Statistics', href: statistics(), accent: 'Catalog + yours' },
];

function isCurrent(url: string): boolean {
    return page.url.split('?')[0] === url;
}

function closeMobileMenu(): void {
    if (mobileMenu.value) {
        mobileMenu.value.open = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-stone-950 font-sans text-stone-100">
        <a
            href="#main-content"
            class="fixed top-3 left-3 z-50 -translate-y-20 rounded-lg bg-amber-400 px-4 py-3 font-semibold text-stone-950 transition focus:translate-y-0 focus:outline-none"
        >
            Skip to content
        </a>

        <header
            class="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-4 border-b border-white/10 bg-stone-950/95 px-4 backdrop-blur lg:hidden"
        >
            <Link
                :href="home()"
                class="flex min-h-11 items-center gap-3 rounded-lg focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
            >
                <span
                    aria-hidden="true"
                    class="flex size-9 items-center justify-center rounded-full border-3 border-amber-400"
                >
                    <span class="size-2.5 rounded-full bg-amber-400" />
                </span>
                <span class="text-sm font-semibold tracking-[0.16em] uppercase">
                    Vanggaard
                </span>
            </Link>

            <details ref="mobile-menu" class="group relative">
                <summary
                    class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-white/15 px-4 text-sm font-semibold marker:hidden focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400"
                >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="size-5"
                    >
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    Menu
                </summary>
                <nav
                    aria-label="Mobile navigation"
                    class="absolute top-13 right-0 flex w-72 flex-col gap-1 rounded-2xl border border-white/10 bg-stone-900 p-3 shadow-2xl shadow-black/50"
                >
                    <Link
                        v-for="item in navigation"
                        :key="item.label"
                        :href="item.href"
                        @click="closeMobileMenu"
                        class="flex min-h-12 items-center justify-between gap-4 rounded-xl px-4 text-sm font-medium transition hover:bg-white/5 focus-visible:outline-3 focus-visible:outline-offset-1 focus-visible:outline-amber-400"
                        :class="{
                            'bg-amber-400 text-stone-950 hover:bg-amber-300':
                                isCurrent(item.href.url),
                        }"
                    >
                        {{ item.label }}
                        <span
                            class="text-[0.65rem] tracking-wider uppercase opacity-65"
                        >
                            {{ item.accent }}
                        </span>
                    </Link>
                    <Form
                        v-bind="destroy.form()"
                        class="mt-2 border-t border-white/10 pt-2"
                    >
                        <button
                            type="submit"
                            class="flex min-h-12 w-full items-center justify-between rounded-xl px-4 text-sm font-medium text-stone-300 transition hover:bg-white/5 hover:text-white focus-visible:outline-3 focus-visible:outline-offset-1 focus-visible:outline-amber-400"
                        >
                            Sign out
                            <span aria-hidden="true">→</span>
                        </button>
                    </Form>
                </nav>
            </details>
        </header>

        <aside
            class="fixed inset-y-0 left-0 hidden w-72 flex-col border-r border-white/10 bg-stone-900 lg:flex"
        >
            <div class="border-b border-white/10 px-7 py-8">
                <Link
                    :href="home()"
                    class="flex items-center gap-4 rounded-lg focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-amber-400"
                >
                    <span
                        aria-hidden="true"
                        class="flex size-12 items-center justify-center rounded-full border-4 border-amber-400"
                    >
                        <span class="size-3 rounded-full bg-amber-400" />
                    </span>
                    <span>
                        <span
                            class="block text-sm font-semibold tracking-[0.18em] uppercase"
                        >
                            Vanggaard
                        </span>
                        <span class="mt-1 block text-xs text-stone-500">
                            Private record room
                        </span>
                    </span>
                </Link>
            </div>

            <nav
                aria-label="Primary navigation"
                class="flex flex-1 flex-col gap-1 px-4 py-6"
            >
                <p
                    class="mb-2 px-3 text-[0.65rem] font-semibold tracking-[0.22em] text-stone-600 uppercase"
                >
                    Browse
                </p>
                <Link
                    v-for="(item, index) in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="group flex min-h-14 items-center gap-4 rounded-xl px-3 transition hover:bg-white/5 focus-visible:outline-3 focus-visible:outline-offset-1 focus-visible:outline-amber-400"
                    :class="{
                        'bg-amber-400 text-stone-950 hover:bg-amber-300':
                            isCurrent(item.href.url),
                    }"
                >
                    <span
                        class="w-5 text-xs font-semibold tabular-nums opacity-50"
                    >
                        {{ String(index + 1).padStart(2, '0') }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">
                            {{ item.label }}
                        </span>
                        <span
                            class="mt-0.5 block text-[0.65rem] tracking-wider uppercase opacity-55"
                        >
                            {{ item.accent }}
                        </span>
                    </span>
                </Link>
            </nav>

            <div class="border-t border-white/10 p-4">
                <Form v-bind="destroy.form()">
                    <button
                        type="submit"
                        class="flex min-h-12 w-full items-center justify-between rounded-xl px-3 text-sm font-medium text-stone-400 transition hover:bg-white/5 hover:text-white focus-visible:outline-3 focus-visible:outline-offset-1 focus-visible:outline-amber-400"
                    >
                        Sign out
                        <span aria-hidden="true">→</span>
                    </button>
                </Form>
            </div>
        </aside>

        <div class="lg:pl-72">
            <div
                class="border-b border-white/10 bg-stone-950/90 px-4 py-4 backdrop-blur sm:px-6 lg:sticky lg:top-0 lg:z-20 lg:px-8"
            >
                <div class="mx-auto flex max-w-6xl items-center gap-4">
                    <Form
                        v-bind="collection.form()"
                        role="search"
                        class="relative flex-1"
                    >
                        <label for="global-search" class="sr-only">
                            Search your collection
                        </label>
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-stone-500"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-4-4" />
                        </svg>
                        <input
                            id="global-search"
                            name="q"
                            type="search"
                            placeholder="Search artist, release, label or note"
                            class="min-h-12 w-full rounded-xl border border-white/10 bg-stone-900 pr-4 pl-12 text-base text-white outline-none placeholder:text-stone-600 focus:border-amber-400 focus:ring-3 focus:ring-amber-400/15"
                        />
                    </Form>
                    <span
                        class="hidden rounded-full border border-emerald-400/20 bg-emerald-400/8 px-3 py-2 text-xs font-medium text-emerald-300 sm:block"
                    >
                        Private session
                    </span>
                </div>
            </div>

            <main
                id="main-content"
                tabindex="-1"
                class="mx-auto min-h-[calc(100vh-5rem)] max-w-6xl px-4 py-6 outline-none sm:px-6 sm:py-8 lg:px-8 lg:py-10"
            >
                <slot />

                <footer class="mt-12 border-t border-white/10 pt-6">
                    <div
                        class="flex flex-wrap gap-x-6 gap-y-2 text-xs leading-5 text-stone-400"
                    >
                        <span class="inline-flex items-center gap-2">
                            <span class="size-2 rounded-full bg-amber-400" />
                            Discogs-sourced catalog data
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="size-2 rounded-sm bg-emerald-400" />
                            Your notes and organization
                        </span>
                    </div>
                    <p
                        class="mt-4 max-w-3xl rounded-xl border border-white/10 bg-stone-900 px-4 py-3 text-sm leading-6 text-stone-300"
                    >
                        This application uses Discogs’ API but is not affiliated
                        with, sponsored or endorsed by Discogs. ‘Discogs’ is a
                        trademark of Zink Media, LLC.
                    </p>
                </footer>
            </main>
        </div>
    </div>
</template>
