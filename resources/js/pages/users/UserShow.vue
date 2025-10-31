<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

const user = ref(null)
const loading = ref(false)
const error = ref(null)

const fetchUser = async () => {
    loading.value = true
    error.value = null
    try {
        user.value = await api.get(`/users/${route.params.id}`)
    } catch (err) {
        error.value = err?.message || 'Failed to load user.'
        user.value = null
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchUser()
})

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const goBack = () => {
    router.push({ name: 'users.index' })
}
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto px-4 py-8">
            <div class="mb-6">
                <Button variant="outline" @click="goBack" class="mb-4"> ← Back to Users </Button>
                <h1 class="text-2xl font-bold text-neutral-900">User Profile</h1>
            </div>

            <div v-if="loading" class="text-center py-12">
                <div class="text-neutral-500">Loading user...</div>
            </div>

            <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                {{ error }}
            </div>

            <div v-else-if="user" class="space-y-6">
                <!-- User Information Card -->
                <div class="rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-neutral-900">User Information</h2>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Name</dt>
                                <dd class="mt-1 text-sm text-neutral-900">{{ user.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Email</dt>
                                <dd class="mt-1 text-sm text-neutral-900">{{ user.email }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Role</dt>
                                <dd class="mt-1">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            user.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                        ]"
                                    >
                                        {{ user.role }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Member Since</dt>
                                <dd class="mt-1 text-sm text-neutral-900">{{ formatDate(user.created_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Website Ratings Card -->
                <div class="rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-200 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-neutral-900">Website Ratings</h2>
                            <span class="text-sm text-neutral-500"> {{ user.website_ratings?.length || 0 }} total </span>
                        </div>
                    </div>

                    <div v-if="!user.website_ratings || user.website_ratings.length === 0" class="px-6 py-8 text-center">
                        <p class="text-sm text-neutral-500">No website ratings yet.</p>
                    </div>

                    <div v-else>
                        <!-- Desktop Table View -->
                        <div class="hidden md:block">
                            <table class="min-w-full divide-y divide-neutral-200">
                                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                    <tr>
                                        <th class="px-6 py-3 text-left">Organization</th>
                                        <th class="px-6 py-3 text-left">Rating</th>
                                        <th class="px-6 py-3 text-left">Score</th>
                                        <th class="px-6 py-3 text-left">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200">
                                    <tr v-for="rating in user.website_ratings" :key="rating.id" class="hover:bg-neutral-50/60">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-neutral-900">
                                                {{ rating.organization?.name || 'Unknown Organization' }}
                                            </div>
                                            <div v-if="rating.organization?.website" class="text-xs text-neutral-500">
                                                {{ rating.organization.website }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-neutral-900">
                                                {{ rating.option?.label || 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-1">
                                                <span class="text-sm font-semibold text-neutral-900">{{ rating.score }}</span>
                                                <span class="text-xs text-neutral-500">/ 5</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-neutral-600">
                                            {{ formatDate(rating.created_at) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card View -->
                        <div class="space-y-4 px-4 py-4 md:hidden">
                            <div
                                v-for="rating in user.website_ratings"
                                :key="rating.id"
                                class="rounded-2xl border border-neutral-200 bg-neutral-50/80 p-4 shadow-sm shadow-neutral-200/40"
                            >
                                <div class="mb-3">
                                    <div class="text-base font-semibold text-neutral-900">
                                        {{ rating.organization?.name || 'Unknown Organization' }}
                                    </div>
                                    <div v-if="rating.organization?.website" class="mt-1 text-xs text-neutral-500">
                                        {{ rating.organization.website }}
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm text-neutral-900">{{ rating.option?.label || 'N/A' }}</div>
                                        <div class="mt-1 flex items-center gap-1">
                                            <span class="text-sm font-semibold text-neutral-900">{{ rating.score }}</span>
                                            <span class="text-xs text-neutral-500">/ 5</span>
                                        </div>
                                    </div>
                                    <div class="text-xs text-neutral-500">
                                        {{ formatDate(rating.created_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
