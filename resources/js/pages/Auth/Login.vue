<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/AuthenticatedSessionController';
</script>

<template>
    <div
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-stone-950 px-5 py-12 text-stone-100 sm:px-8"
    >
        <Head title="Sign in" />

        <div
            aria-hidden="true"
            class="absolute inset-0 [background-image:radial-gradient(circle_at_18%_20%,rgba(217,119,6,0.22),transparent_28%),radial-gradient(circle_at_82%_80%,rgba(22,163,74,0.16),transparent_30%)] opacity-60"
        />
        <div
            aria-hidden="true"
            class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.025)_1px,transparent_1px)] bg-[size:42px_42px]"
        />

        <main
            class="relative grid w-full max-w-4xl overflow-hidden rounded-3xl border border-white/10 bg-stone-900/90 shadow-2xl shadow-black/40 backdrop-blur md:grid-cols-[1.05fr_1fr]"
        >
            <section
                class="flex min-h-56 flex-col justify-between bg-amber-500 p-8 text-stone-950 sm:p-10 md:min-h-[34rem]"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-11 items-center justify-center rounded-full border-4 border-stone-950"
                    >
                        <span class="size-3 rounded-full bg-stone-950" />
                    </span>
                    <p
                        class="text-sm font-semibold tracking-[0.22em] uppercase"
                    >
                        Vanggaard Selects
                    </p>
                </div>

                <div class="max-w-sm">
                    <p
                        class="mb-4 text-xs font-semibold tracking-[0.3em] uppercase"
                    >
                        Private collection
                    </p>
                    <h1
                        class="text-4xl leading-[0.95] font-semibold tracking-tight sm:text-5xl"
                    >
                        Your records.<br />Your room.<br />Your session.
                    </h1>
                </div>
            </section>

            <section class="flex flex-col justify-center p-8 sm:p-10 md:p-12">
                <div class="mb-8">
                    <p
                        class="mb-3 text-xs font-semibold tracking-[0.24em] text-amber-400 uppercase"
                    >
                        Owner access
                    </p>
                    <h2 class="text-3xl font-semibold tracking-tight">
                        Sign in
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-400">
                        This catalog is private. Use the owner account to
                        continue.
                    </p>
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-error="['password']"
                    class="flex flex-col gap-6"
                    #default="{ errors, processing }"
                >
                    <div class="flex flex-col gap-2">
                        <label for="email" class="text-sm font-medium">
                            Email address
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="username"
                            autofocus
                            required
                            :aria-invalid="Boolean(errors.email)"
                            :aria-describedby="
                                errors.email ? 'email-error' : undefined
                            "
                            class="min-h-12 rounded-xl border border-white/15 bg-stone-950/70 px-4 text-base text-white transition outline-none placeholder:text-stone-600 focus:border-amber-400 focus:ring-3 focus:ring-amber-400/20"
                            placeholder="owner@example.com"
                        />
                        <p
                            v-if="errors.email"
                            id="email-error"
                            role="alert"
                            class="text-sm text-red-300"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="text-sm font-medium">
                            Password
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            :aria-invalid="Boolean(errors.password)"
                            :aria-describedby="
                                errors.password ? 'password-error' : undefined
                            "
                            class="min-h-12 rounded-xl border border-white/15 bg-stone-950/70 px-4 text-base text-white transition outline-none focus:border-amber-400 focus:ring-3 focus:ring-amber-400/20"
                        />
                        <p
                            v-if="errors.password"
                            id="password-error"
                            role="alert"
                            class="text-sm text-red-300"
                        >
                            {{ errors.password }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="min-h-12 rounded-xl bg-amber-500 px-5 font-semibold text-stone-950 transition hover:bg-amber-400 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-amber-400 disabled:cursor-wait disabled:opacity-60"
                    >
                        {{ processing ? 'Signing in...' : 'Enter collection' }}
                    </button>
                </Form>
            </section>
        </main>
    </div>
</template>
