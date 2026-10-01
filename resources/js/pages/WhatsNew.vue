<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import NavLink from '@/components/NavLink.vue'
import ShiftNote from '@/components/ShiftNote.vue'
import { formatDate } from '@/utils/date'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // [{ id, date, title, html, new }], newest first. See features/whats-new/.
    entries: { type: Array, default: () => [] },
    selectedId: { type: String, default: null },
})

// Selection stays in the page, so the New labels stay for the whole visit.
const selectedIndex = ref(Math.max(0, props.entries.findIndex((e) => e.id === props.selectedId)))
const selected = computed(() => props.entries[selectedIndex.value] ?? null)

// The list is newest first: Previous goes to the older release, Next to the newer one.
const hasPrevious = computed(() => selectedIndex.value < props.entries.length - 1)
const hasNext = computed(() => selectedIndex.value > 0)
</script>

<template>
    <AppLayout>
        <Head :title="__('whats_new.title')" />

        <Card class="max-w-5xl">
            <template #header>
                <div class="flex h-12 items-center px-6 text-md font-semibold">{{ __('whats_new.title') }}</div>
            </template>

            <p v-if="!entries.length" class="p-6 text-sm text-(--color-text-secondary)">{{ __('whats_new.empty') }}</p>

            <div v-else class="flex flex-col gap-6 p-6 md:flex-row md:items-start">
                <nav class="flex flex-col gap-1 md:w-64 md:shrink-0">
                    <NavLink
                        v-for="(entry, index) in entries"
                        :key="entry.id"
                        :active="index === selectedIndex"
                        data-testid="whats-new-item"
                        @click="selectedIndex = index"
                    >
                        <span class="flex min-w-0 flex-1 flex-col items-start text-left">
                            <span class="w-full truncate text-sm">{{ entry.title }}</span>
                            <span class="text-xs font-normal opacity-75">{{ formatDate(entry.date) }}</span>
                        </span>
                        <span
                            v-if="entry.new"
                            data-testid="whats-new-new"
                            class="rounded-full bg-(--color-btn-primary-bg) px-2 text-xs text-(--color-btn-primary-text)"
                        >
                            {{ __('whats_new.new') }}
                        </span>
                    </NavLink>
                </nav>

                <article v-if="selected" data-testid="whats-new-entry" class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-(--color-text-heading)">{{ selected.title }}</h2>
                    <p class="mb-4 text-xs text-(--color-text-muted)">{{ formatDate(selected.date) }}</p>
                    <ShiftNote :html="selected.html" />

                    <div class="mt-6 flex justify-between gap-3">
                        <ButtonSecondary
                            type="button"
                            icon="chevron-left"
                            :disabled="!hasPrevious"
                            @click="selectedIndex++"
                        >
                            {{ __('whats_new.previous') }}
                        </ButtonSecondary>
                        <ButtonSecondary
                            type="button"
                            icon="chevron-right"
                            :disabled="!hasNext"
                            @click="selectedIndex--"
                        >
                            {{ __('whats_new.next') }}
                        </ButtonSecondary>
                    </div>
                </article>
            </div>
        </Card>
    </AppLayout>
</template>
