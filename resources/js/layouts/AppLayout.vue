<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import Layout from '@/layouts/Layout.vue'
import AppLogo from '@/components/AppLogo.vue'
import NavLink from '@/components/NavLink.vue'
import FlashMessage from '@/components/FlashMessage.vue'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'
import { useBreadcrumb } from '@/composables/useBreadcrumb'
import { useDarkMode } from '@/composables/useDarkMode'
import { useAuth } from '@/composables/useAuth'

const page = usePage()
const __ = useI18n()
const { segments } = useBreadcrumb()
const { isDark, toggle: toggleDarkMode } = useDarkMode()
const { user } = useAuth()

const sidebarOpen = ref(false)
const userMenuOpen = ref(false)
const userMenuRef = ref(null)

function handleOutsideClick(event) {
    if (userMenuOpen.value && !userMenuRef.value?.contains(event.target)) {
        userMenuOpen.value = false
    }
}

onMounted(() => document.addEventListener('click', handleOutsideClick))
onUnmounted(() => document.removeEventListener('click', handleOutsideClick))

// A context/domain extension would add its own context-scoped section here,
// following the same NavLink pattern.
const isAdmin = computed(() => user.value?.role === 'admin')

const navItems = computed(() => {
    const items = [
        { label: __('nav.dashboard'), href: '/dashboard', icon: 'chart-bar' },
        { label: __('nav.employees'), href: '/employees', icon: 'users' },
    ]

    if (user.value?.employee_id) {
        items.push({
            label: __('nav.my_details'),
            href: `/employees/${user.value.employee_id}/edit`,
            icon: 'user',
        })
    }

    if (isAdmin.value) {
        items.push(
            { label: __('nav.users'), href: '/users', icon: 'user-plus' },
            { label: __('nav.settings'), href: '/settings', icon: 'cog' },
        )
        // Theme builder stays reachable at /theme-builder but is not in the nav.
    }

    return items
})

function logout() {
    router.post('/logout')
}

const breadcrumbs = computed(() => segments.value)

function isActive(href) {
    const url = page.url ?? ''
    return url === href || url.startsWith(href + '/')
}
</script>

<template>
    <Layout :sidebar-open="sidebarOpen" @close-sidebar="sidebarOpen = false">

        <!-- Logo area (desktop left column) -->
        <template #logo>
            <AppLogo />
        </template>

        <!-- TopBar -->
        <template #topbar>
            <div class="flex items-center justify-between w-full h-full">

                <!-- Left side: hamburger + logo (mobile only) + breadcrumb -->
                <div class="flex items-center gap-3">
                    <button
                        class="lg:hidden p-1 rounded text-(--color-header-text) hover:opacity-80"
                        @click="sidebarOpen = !sidebarOpen"
                        :aria-label="__('nav.toggle')"
                    >
                        <Icon v-if="sidebarOpen" name="x-mark" class="w-6 h-6" />
                        <Icon v-else name="bars" class="w-6 h-6" />
                    </button>
                    <div class="lg:hidden">
                        <AppLogo />
                    </div>
                    <div class="hidden lg:flex items-center gap-1.5 text-xl font-medium text-(--color-header-text)">
                        <template v-for="(crumb, index) in breadcrumbs" :key="index">
                            <Icon v-if="index > 0" name="chevron-right" class="w-5 h-5 opacity-40 flex-shrink-0" />
                            <span :class="index === breadcrumbs.length - 1 ? '' : 'opacity-60'">{{ crumb }}</span>
                        </template>
                    </div>
                </div>

                <!-- Right side: dark mode toggle + user menu -->
                <div class="flex items-center gap-x-2">
                    <button
                        class="p-1.5 rounded text-(--color-header-text) hover:opacity-80"
                        @click="toggleDarkMode"
                        :aria-label="isDark ? __('nav.switch_to_light') : __('nav.switch_to_dark')"
                    >
                        <Icon v-if="isDark" name="sun" class="w-5 h-5" />
                        <Icon v-else name="moon" class="w-5 h-5" />
                    </button>

                    <div v-if="user" ref="userMenuRef" class="relative">
                        <button
                            class="flex items-center gap-1.5 px-2 py-1.5 rounded text-(--color-header-text) hover:opacity-80"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <span class="hidden sm:inline text-sm">{{ user.name }}</span>
                            <Icon name="chevron-down" class="w-4 h-4" />
                        </button>

                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 mt-1 w-40 rounded-md border shadow-lg overflow-hidden bg-[var(--color-dropdown-panel-bg)] border-[var(--color-dropdown-panel-border)]"
                        >
                            <Link
                                href="/account"
                                class="block w-full px-3 py-2 text-left text-sm text-[var(--color-dropdown-option-text)] hover:bg-[var(--color-dropdown-option-hover-bg)] hover:text-[var(--color-dropdown-option-hover-text)]"
                                @click="userMenuOpen = false"
                            >
                                {{ __('nav.account') }}
                            </Link>
                            <button
                                class="w-full px-3 py-2 text-left text-sm text-[var(--color-dropdown-option-text)] hover:bg-[var(--color-dropdown-option-hover-bg)] hover:text-[var(--color-dropdown-option-hover-text)]"
                                @click="logout"
                            >
                                {{ __('nav.logout') }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </template>

        <!-- Sidebar nav -->
        <template #sidebar>
            <nav class="px-3 py-4 flex flex-col gap-1">
                <NavLink
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :active="isActive(item.href)"
                    @click="sidebarOpen = false"
                >
                    <Icon :name="item.icon" class="w-5 h-5 flex-shrink-0" />
                    <span>{{ item.label }}</span>
                </NavLink>
            </nav>
        </template>

        <!-- Page content -->
        <FlashMessage />
        <div class="p-6">
            <slot />
        </div>

    </Layout>
</template>
