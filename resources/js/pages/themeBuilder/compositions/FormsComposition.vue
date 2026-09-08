<template>
    <!-- A realistic labelled form. Real Input/* components where they exist;
         toggle, radio and range are mockup markup (no component yet). The text
         field is shown in its resting, invalid and disabled states together;
         focus, disabled and invalid are captioned, not an exhaustive matrix. -->
    <div class="flex flex-1 flex-col gap-3 overflow-auto p-1 text-[10px]">
        <p class="text-sm font-bold" style="color: var(--color-text-heading)">Edit profile</p>

        <!-- Text input: the same control, three labelled states grouped -->
        <div class="space-y-2">
            <LabeledInput label="Display name">
                <TextInput v-model="name" placeholder="Click to focus" class="w-full" />
                <p class="mt-0.5" style="color: var(--color-text-muted)">Resting · focus shows on click.</p>
            </LabeledInput>

            <LabeledInput label="Email" error="Enter a valid email address">
                <div class="rounded-lg outline outline-2 -outline-offset-1" style="outline-color: var(--color-input-invalid-border)">
                    <TextInput v-model="email" class="w-full" />
                </div>
                <p class="mt-0.5" style="color: var(--color-input-invalid-text)">Invalid state.</p>
            </LabeledInput>

            <LabeledInput label="Account ID">
                <TextInput model-value="acct_10482" disabled class="w-full" />
                <p class="mt-0.5" style="color: var(--color-text-muted)">Disabled · not editable.</p>
            </LabeledInput>
        </div>

        <LabeledInput label="Bio">
            <MultilineInput v-model="bio" rows="2" class="w-full" />
        </LabeledInput>

        <LabeledInput label="Role">
            <SelectInput v-model="role" :options="['Owner', 'Editor', 'Viewer']" />
        </LabeledInput>

        <div class="space-y-1.5">
            <CheckboxInput v-model="updates">Send me product updates</CheckboxInput>
            <!-- Radio — no component yet, mockup markup -->
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5">
                    <span
                        class="grid size-3 place-items-center rounded-full border"
                        style="border-color: var(--color-brand-bg)"
                    >
                        <span class="block size-1.5 rounded-full" style="background-color: var(--color-brand-bg)"></span>
                    </span>
                    <span style="color: var(--color-input-text)">Monthly</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="block size-3 rounded-full border" style="border-color: var(--color-input-border)"></span>
                    <span style="color: var(--color-input-text)">Yearly</span>
                </span>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <span style="color: var(--color-input-text)">Public profile</span>
            <!-- Toggle — no component yet -->
            <span
                class="flex h-4 w-7 items-center rounded-full p-0.5"
                style="background-color: var(--color-brand-bg)"
            >
                <span class="ml-auto block size-3 rounded-full bg-white"></span>
            </span>
        </div>

        <div class="space-y-1">
            <span style="color: var(--color-input-text)">Volume</span>
            <!-- Range — no component yet -->
            <div class="relative h-1 rounded-full" style="background-color: var(--color-input-border)">
                <div class="absolute inset-y-0 left-0 w-1/2 rounded-full" style="background-color: var(--color-brand-bg)"></div>
                <div
                    class="absolute top-1/2 left-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border"
                    style="background-color: var(--color-input-bg); border-color: var(--color-brand-bg)"
                ></div>
            </div>
        </div>

        <LabeledInput label="Avatar">
            <FileInput>
                <template #default="{ trigger }">
                    <ButtonSecondary class="!px-2 !py-1 text-[10px]" @click="trigger">Choose file…</ButtonSecondary>
                </template>
            </FileInput>
        </LabeledInput>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { TextInput, MultilineInput, SelectInput, CheckboxInput, FileInput } from '@/components/ui/Input'

const name = ref('')
const bio = ref('Product designer, coffee enthusiast.')
const role = ref('Editor')
const updates = ref(true)
const email = ref('not-an-email')
</script>
