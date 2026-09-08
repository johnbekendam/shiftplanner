<template>
    <!-- Every variant in every state, all at one size and rounding. -->
    <div class="flex flex-1 flex-col gap-4 overflow-auto p-1">
        <div v-for="v in variants" :key="v.label" class="space-y-1">
            <p class="text-xs" style="color: var(--color-text-muted)">{{ v.label }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <component :is="v.comp" state="normal" class="text-xs">Normal</component>
                <component :is="v.comp" state="hover" class="text-xs">Hover</component>
                <component :is="v.comp" state="disabled" class="text-xs">Disabled</component>
            </div>
        </div>
    </div>
</template>

<script setup>
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'

const variants = [
    { label: 'Primary', comp: ButtonPrimary },
    { label: 'Secondary', comp: ButtonSecondary },
    { label: 'Danger', comp: ButtonDanger },
]
</script>
