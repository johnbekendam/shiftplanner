<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import OrderedNameList from '@/components/OrderedNameList.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    competences: { type: Array, default: () => [] },
    productGroups: { type: Array, default: () => [] },
})

const tab = ref('competences')
const tabs = computed(() => [
    { value: 'competences', label: __('settings.tab.competences') },
    { value: 'product_groups', label: __('settings.tab.product_groups') },
])
</script>

<template>
    <AppLayout>
        <Head :title="__('settings.title')" />

        <Card class="max-w-2xl">
            <template #header>
                <Tabs v-model="tab" :tabs="tabs" />
            </template>

            <div v-show="tab === 'competences'" data-testid="panel-competences" class="p-6">
                <OrderedNameList
                    :items="competences"
                    endpoint="/settings/competences"
                    i18n-prefix="competences"
                />
            </div>

            <div v-show="tab === 'product_groups'" data-testid="panel-product-groups" class="p-6">
                <OrderedNameList
                    :items="productGroups"
                    endpoint="/settings/product-groups"
                    i18n-prefix="product_groups"
                />
            </div>
        </Card>
    </AppLayout>
</template>
