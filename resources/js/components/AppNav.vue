<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import auth from '@/services/auth'

const router = useRouter()
const isAuthenticated = computed(() => auth.isAuthenticated())
const user = computed(() => auth.getUser())

const logout = async () => {
    await auth.logout()
    router.push('/login')
}
</script>

<template>
    <nav class="border-b border-neutral-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 lg:px-6">
            <div class="flex items-center gap-6">
                <router-link to="/" class="text-base font-semibold tracking-tight text-neutral-900">
                    CRM
                </router-link>

                <div v-if="isAuthenticated" class="hidden items-center gap-4 text-sm font-medium text-neutral-500 md:flex">
                    <router-link to="/" class="rounded-full px-3 py-1 transition hover:bg-neutral-100 hover:text-neutral-900">
                        Dashboard
                    </router-link>
                    <router-link to="/organizations" class="rounded-full px-3 py-1 transition hover:bg-neutral-100 hover:text-neutral-900">
                        Organizations
                    </router-link>
                    <router-link to="/teams" class="rounded-full px-3 py-1 transition hover:bg-neutral-100 hover:text-neutral-900">
                        Teams
                    </router-link>
                    <router-link to="/organization-categories" class="rounded-full px-3 py-1 transition hover:bg-neutral-100 hover:text-neutral-900">
                        Categories
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
