<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { RouteDefinition } from '@/wayfinder';

type Vocabulary = {
    id: number;
    name: string;
};

defineProps<{
    label: string;
    singular: string;
    items: Vocabulary[];
    storeAction: RouteDefinition<'post'>;
    updateAction: (id: number) => RouteDefinition<'patch'>;
    destroyAction: (id: number) => RouteDefinition<'delete'>;
}>();

function confirmDelete(event: SubmitEvent, name: string): void {
    if (!window.confirm(`Remove “${name}” from your vocabulary?`)) {
        event.preventDefault();
    }
}
</script>

<template>
    <div>
        <h3 class="text-sm font-semibold text-stone-200">
            {{ label }}
        </h3>
        <Form
            :action="storeAction"
            :error-bag="`create-${singular}`"
            :options="{ preserveScroll: true }"
            reset-on-success
            #default="{ errors, processing }"
            class="mt-3 flex gap-2"
        >
            <div class="grow">
                <label :for="`new-${singular}`" class="sr-only">
                    New {{ singular }} name
                </label>
                <input
                    :id="`new-${singular}`"
                    name="name"
                    required
                    maxlength="255"
                    :placeholder="`New ${singular}`"
                    class="min-h-11 w-full rounded-xl border border-white/10 bg-stone-950/60 px-3 text-sm text-stone-100 placeholder:text-stone-600 focus:border-amber-300/60 focus:ring-2 focus:ring-amber-300/20 focus:outline-none"
                />
                <p v-if="errors.name" class="mt-1 text-xs text-red-300">
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
                v-for="item in items"
                :key="item.id"
                class="flex items-start gap-2"
            >
                <Form
                    :action="updateAction(item.id)"
                    :error-bag="`${singular}-${item.id}`"
                    :options="{ preserveScroll: true }"
                    #default="{ errors, processing }"
                    class="grow"
                >
                    <div class="flex gap-2">
                        <label :for="`${singular}-${item.id}`" class="sr-only">
                            Rename {{ item.name }}
                        </label>
                        <input
                            :id="`${singular}-${item.id}`"
                            name="name"
                            required
                            maxlength="255"
                            :value="item.name"
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
                    <p v-if="errors.name" class="mt-1 text-xs text-red-300">
                        {{ errors.name }}
                    </p>
                </Form>
                <Form
                    :action="destroyAction(item.id)"
                    :options="{ preserveScroll: true }"
                    #default="{ processing }"
                    @submit="confirmDelete($event, item.name)"
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
</template>
