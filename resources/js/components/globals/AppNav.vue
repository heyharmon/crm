<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import auth from '@/services/auth'

const router = useRouter()
const route = useRoute()
const isAuthenticated = computed(() => auth.isAuthenticated())
const user = computed(() => auth.getUser())
const navLinkClasses = 'rounded-full px-3 py-1 transition hover:bg-neutral-100 hover:text-neutral-900'
const activeNavClasses = 'bg-neutral-900 text-white hover:bg-neutral-900 hover:text-white'

const logout = async () => {
    await auth.logout()
    router.push('/login')
}

const isRouteActive = (target) => {
    if (!target) {
        return false
    }

    if (typeof target === 'string') {
        if (target.startsWith('/')) {
            return route.path === target
        }

        return route.name === target
    }

    if (typeof target === 'object') {
        if (target.name) {
            return route.name === target.name
        }

        if (target.path) {
            return route.path === target.path
        }
    }

    return false
}
</script>

<template>
    <nav class="border-b border-neutral-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex items-center justify-between px-4 py-4 lg:px-8">
            <div class="flex items-center gap-6">
                <router-link to="/" class="text-base font-bold text-neutral-900"> CRM </router-link>

                <div v-if="isAuthenticated" class="hidden items-center gap-1 text-sm font-medium text-neutral-500 md:flex">
                    <router-link :to="{ name: 'dashboard' }" :class="[navLinkClasses, { [activeNavClasses]: isRouteActive('dashboard') }]">
                        Dashboard
                    </router-link>
                    <router-link :to="{ name: 'organizations.index' }" :class="[navLinkClasses, { [activeNavClasses]: isRouteActive('organizations.index') }]">
                        Organizations
                    </router-link>
                    <router-link to="/organization-categories" :class="[navLinkClasses, { [activeNavClasses]: isRouteActive('/organization-categories') }]">
                        Categories
                    </router-link>
                    <router-link :to="{ name: 'websites.options' }" :class="[navLinkClasses, { [activeNavClasses]: isRouteActive('websites.options') }]">
                        Rating Options
                    </router-link>
                    <router-link :to="{ name: 'websites.ratings' }" :class="[navLinkClasses, { [activeNavClasses]: isRouteActive('websites.ratings') }]">
                        Rate Websites
                    </router-link>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <template v-if="isAuthenticated">
                    <span class="text-sm font-medium text-neutral-600">{{ user?.name }}</span>
                    <button
                        @click="logout"
                        class="inline-flex items-center rounded-full border border-neutral-200 bg-white px-4 py-1.5 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                    >
                        Logout
                    </button>
                </template>
                <template v-else>
                    <router-link
                        to="/login"
                        class="inline-flex items-center rounded-full border border-neutral-200 bg-white px-4 py-1.5 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                    >
                        Login
                    </router-link>
                    <router-link
                        to="/register"
                        class="inline-flex items-center rounded-full border border-neutral-900 bg-neutral-900 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                    >
                        Register
                    </router-link>
                </template>
            </div>
        </div>
    </nav>
</template>
