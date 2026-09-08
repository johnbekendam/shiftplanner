<template>
    <!-- Chrome has no content composition: the frame itself is the preview.
         This line is a specimen of the page text colour. -->
    <p class="text-sm" style="color: var(--color-content-text)">
        {{ __('theme.preview.chrome_note') }}
    </p>
</template>

<script setup>
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()
</script>
