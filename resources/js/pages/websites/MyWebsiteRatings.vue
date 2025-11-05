<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import moment from 'moment'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import api from '@/services/api'
import auth from '@/services/auth'
import { getRatingButtonClasses } from '@/utils/ratingStyles'
import { formatAssets } from '@/composables/useNumberFormat'

const ratings = ref([])
const ratingOptions = ref([])
const selectedFilters = ref([])
const isLoading = ref(false)
const isUpdating = ref(false)
const error = ref(null)
const columns = ref(2)
const isAdmin = ref(auth.isAdmin())
const users = ref([])
const selectedUserId = ref(null)
const isLoadingUsers = ref(false)

const formatDate = (dateString) => {
    if (!dateString) return null
    return moment(dateString).format('MMM D, YYYY')
}

const formatWebsite = (url) => {
    if (!url) return ''
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

const ratingButtonClasses = (option, currentRatingId) => getRatingButtonClasses(option.slug, currentRatingId === option.id)

const getScreenshotUrl = (website) => {
    if (!website) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
    return `${baseUrl}?access_key=${accessKey}&wait_until=network_idle&no_cookie_banners=true&url=${encodeURIComponent(website)}`
}

const loadRatingOptions = async () => {
    try {
        ratingOptions.value = await api.get('/website-rating-options')
    } catch (err) {
        console.error('Failed to load rating options:', err)
        ratingOptions.value = []
    }
}

const loadUsers = async () => {
    if (!isAdmin.value) return

    isLoadingUsers.value = true
    try {
        users.value = await api.get('/users')
    } catch (err) {
        console.error('Failed to load users:', err)
    } finally {
        isLoadingUsers.value = false
    }
}

const loadRatings = async () => {
    isLoading.value = true
    error.value = null

    try {
        const params = {}
        if (selectedFilters.value.length > 0) {
            params.rating_option_ids = selectedFilters.value.join(',')
        }
        if (selectedUserId.value) {
            params.user_id = selectedUserId.value
        }

        ratings.value = await api.get('/website-ratings', { params })
    } catch (err) {
        error.value = err?.message || 'Failed to load rated websites.'
        console.error('Error fetching rated websites:', err)
    } finally {
        isLoading.value = false
    }
}

const toggleFilter = (optionId) => {
    const index = selectedFilters.value.indexOf(optionId)
    if (index > -1) {
        selectedFilters.value.splice(index, 1)
    } else {
        selectedFilters.value.push(optionId)
    }
    loadRatings()
}

const isFilterActive = (optionId) => selectedFilters.value.includes(optionId)

const clearFilters = () => {
    selectedFilters.value = []
    loadRatings()
}

const updateRating = async (rating, newOptionId) => {
    if (isUpdating.value || rating.website_rating_option_id === newOptionId) return

    isUpdating.value = true
    error.value = null

    try {
        await api.post(`/organizations/${rating.organization_id}/website-ratings`, {
            website_rating_option_id: newOptionId
        })

        // Find the new option details
        const newOption = ratingOptions.value.find((opt) => opt.id === newOptionId)

        // Update the rating in place (optimistic update)
        const ratingIndex = ratings.value.findIndex((r) => r.id === rating.id)
        if (ratingIndex !== -1 && newOption) {
            ratings.value[ratingIndex] = {
                ...ratings.value[ratingIndex],
                website_rating_option_id: newOptionId,
                website_rating_option_name: newOption.name,
                website_rating_option_slug: newOption.slug,
                updated_at: new Date().toISOString()
            }
        }
    } catch (err) {
        error.value = err?.message || 'Failed to update rating.'
        console.error('Error updating rating:', err)
    } finally {
        isUpdating.value = false
    }
}

const filteredCount = computed(() => ratings.value.length)

const selectedUserName = computed(() => {
    if (!selectedUserId.value) return null
    const user = users.value.find((u) => u.id === selectedUserId.value)
    return user?.name || null
})

watch(selectedUserId, () => {
    loadRatings()
})

onMounted(async () => {
    await loadRatingOptions()
    if (isAdmin.value) {
        await loadUsers()
    }
    await loadRatings()
})
</script>

<template>
    <DefaultLayout>
        <div class="py-6 sm:py-10">
            <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-3xl font-semibold text-neutral-900">
                        {{ selectedUserName ? `${selectedUserName}'s Rated Websites` : "Websites I've Rated" }}
                    </h1>
                    <p class="mt-2 text-sm text-neutral-500">Review and update website ratings. Filter by rating to find specific websites.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select
                        v-if="isAdmin"
                        v-model="selectedUserId"
                        class="rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 focus:border-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-200"
                        :disabled="isLoadingUsers"
                    >
                        <option :value="null">My Ratings</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                    <router-link
                        :to="{ name: 'websites.ratings' }"
                        class="inline-flex items-center justify-center rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                    >
                        Rate More Websites
                    </router-link>
                </div>
            </div>

            <div v-if="error" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <div v-if="ratingOptions.length" class="mb-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm font-semibold text-neutral-700">Filter by rating</p>
                    <div class="flex items-center gap-3">
                        <button v-if="selectedFilters.length > 0" class="text-xs text-neutral-500 hover:text-neutral-700" @click="clearFilters">
                            Clear filters
                        </button>
                        <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Columns</span>
                        <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white p-1">
                            <button
                                class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline-neutral-400"
                                :class="columns === 1 ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                                @click="columns = 1"
                            >
                                1
                            </button>
                            <button
                                class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline-neutral-400"
                                :class="columns === 2 ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                                @click="columns = 2"
                            >
                                2
                            </button>
                            <button
                                class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline-neutral-400"
                                :class="columns === 3 ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                                @click="columns = 3"
                            >
                                3
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="option in ratingOptions"
                        :key="option.id"
                        size="sm"
                        variant="ghost"
                        class="rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-wide transition hover:opacity-100"
                        :class="[ratingButtonClasses(option, isFilterActive(option.id) ? option.id : null), isFilterActive(option.id) ? '' : 'opacity-30']"
                        @click="toggleFilter(option.id)"
                    >
                        {{ option.name }}
                    </Button>
                </div>
                <p class="mt-3 text-xs text-neutral-500">
                    {{
                        selectedFilters.length > 0
                            ? `Showing ${filteredCount} website${filteredCount !== 1 ? 's' : ''}`
                            : `${filteredCount} total website${filteredCount !== 1 ? 's' : ''} rated`
                    }}
                </p>
            </div>

            <div v-if="isLoading" class="rounded-xl border border-neutral-200 bg-white px-4 py-8 text-center text-sm text-neutral-600">
                Loading your rated websites…
            </div>

            <div v-else-if="ratings.length === 0" class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50 px-4 py-12 text-center">
                <p class="text-lg font-semibold text-neutral-700">
                    {{ selectedFilters.length > 0 ? 'No websites match your filters' : 'No rated websites yet' }}
                </p>
                <p class="mt-1 text-sm text-neutral-500">
                    {{ selectedFilters.length > 0 ? 'Try adjusting your filters to see more results.' : 'Start rating websites to see them here.' }}
                </p>
                <router-link
                    v-if="selectedFilters.length === 0"
                    :to="{ name: 'websites.ratings' }"
                    class="mt-4 inline-flex items-center justify-center rounded-full bg-neutral-900 px-6 py-2 text-sm font-medium text-white transition hover:bg-neutral-800"
                >
                    Rate Websites
                </router-link>
            </div>

            <div
                v-else
                class="grid gap-4"
                :class="{
                    'sm:grid-cols-1': columns === 1,
                    'sm:grid-cols-2': columns === 2,
                    'sm:grid-cols-2 lg:grid-cols-3': columns === 3
                }"
            >
                <div
                    v-for="rating in ratings"
                    :key="rating.id"
                    class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-neutral-300"
                >
                    <div class="flex flex-col gap-4">
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold text-neutral-900">
                                {{ rating.organization_name }}
                            </h3>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-neutral-600">
                                <span v-if="rating.organization_assets"> Assets: {{ formatAssets(rating.organization_assets) }} </span>
                            </div>
                            <a
                                v-if="rating.organization_website"
                                :href="formatWebsite(rating.organization_website)"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 text-sm font-medium text-neutral-600 underline underline-offset-4 hover:text-neutral-900"
                            >
                                {{ formatWebsite(rating.organization_website) }}
                            </a>
                            <div class="pt-1 text-xs text-neutral-500">
                                Rated {{ formatDate(rating.created_at) }}
                                <span v-if="rating.updated_at !== rating.created_at"> • Updated {{ formatDate(rating.updated_at) }} </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="option in ratingOptions"
                                :key="option.id"
                                size="sm"
                                variant="ghost"
                                class="rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-wide transition hover:opacity-100"
                                :class="[
                                    ratingButtonClasses(option, rating.website_rating_option_id),
                                    rating.website_rating_option_id !== option.id ? 'opacity-30' : ''
                                ]"
                                :disabled="isUpdating"
                                @click="updateRating(rating, option.id)"
                            >
                                {{ option.name }}
                            </Button>
                        </div>

                        <div
                            v-if="rating.organization_website"
                            class="relative aspect-[3/2] overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100"
                        >
                            <img
                                v-if="getScreenshotUrl(rating.organization_website)"
                                :src="getScreenshotUrl(rating.organization_website)"
                                :alt="`Screenshot of ${rating.organization_name} website`"
                                class="absolute inset-0 h-full w-full object-contain"
                                @error="(e) => (e.target.style.display = 'none')"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
