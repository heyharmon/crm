<script setup>
import { ref, computed, onMounted, watch, reactive } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import api from '@/services/api'
import { getRatingButtonClasses } from '@/utils/ratingStyles'

const RANDOMIZE_UNRATED_WEBSITES = true

const queue = ref([])
const currentOrg = ref(null)
const ratingOptions = ref([])
const nextPage = ref(1)
const lastPage = ref(null)
const isLoading = ref(false)
const isRating = ref(false)
const error = ref(null)
const finished = ref(false)
const screenshotReady = ref(false)
const screenshotError = ref(null)
const screenshotCache = reactive({})

const hasMorePages = computed(() => {
    if (RANDOMIZE_UNRATED_WEBSITES) {
        return true
    }
    return lastPage.value === null || nextPage.value <= lastPage.value
})
const optionBySlug = computed(() =>
    (ratingOptions.value || []).reduce((map, option) => {
        map[option.slug] = option
        return map
    }, {})
)
const myRatingOptionId = computed(() => currentOrg.value?.my_website_rating_option_id ?? null)
const formatAverage = (value) => {
    if (value === null || value === undefined) return null
    return Number(value).toFixed(2)
}
const ratingButtonClasses = (option) => getRatingButtonClasses(option.slug, myRatingOptionId.value === option.id)

const loadRatingOptions = async () => {
    try {
        ratingOptions.value = await api.get('/website-rating-options')
    } catch (err) {
        console.error('Failed to load rating options:', err)
        ratingOptions.value = []
    }
}

const getScreenshotUrl = (website) => {
    if (!website) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
    const params = new URLSearchParams({
        access_key: accessKey,
        // wait_until: 'page_loaded',
        wait_until: 'network_idle', // wait for no in-flight requests
        // delay: '1', // give sliders/assets time to settle (ms)
        no_cookie_banners: 'true',
        url: website
    })
    return `${baseUrl}?${params.toString()}`
}

const getScreenshotKey = (website) => getScreenshotUrl(website)

const preloadScreenshot = (org) => {
    if (!org || !org.website) return
    const key = getScreenshotKey(org.website)
    if (!key) return
    const entry = screenshotCache[key]
    if (entry && (entry.status === 'loaded' || entry.status === 'loading')) return

    screenshotCache[key] = { status: 'loading' }
    const img = new Image()
    img.onload = () => {
        screenshotCache[key] = { status: 'loaded' }
        const org = currentOrg.value
        if (org && org.website && getScreenshotKey(org.website) === key) {
            screenshotReady.value = true
            screenshotError.value = null
        }
    }
    img.onerror = () => {
        screenshotCache[key] = { status: 'error' }
        const org = currentOrg.value
        if (org && org.website && getScreenshotKey(org.website) === key) {
            screenshotError.value = 'Screenshot unavailable.'
            screenshotReady.value = false
        }
    }
    img.src = key
}

const preloadUpcoming = () => {
    const upcoming = queue.value.length ? queue.value[0] : null
    if (upcoming) {
        preloadScreenshot(upcoming)
    }
}

const formatWebsite = (url) => {
    if (!url) return ''
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

let pendingFetch = null

const fetchNextBatch = async () => {
    if (pendingFetch) return pendingFetch
    if (!hasMorePages.value) return false

    isLoading.value = true
    error.value = null

    pendingFetch = (async () => {
        try {
            const params = {
                website: 'present',
                my_website_rating: 'none',
                website_status: 'up'
            }

            if (RANDOMIZE_UNRATED_WEBSITES) {
                params.random = true
            } else {
                params.page = nextPage.value
            }

            const response = await api.get('/organizations', { params })

            const data = Array.isArray(response.data) ? response.data : []
            const unrated = data.filter((org) => !org.my_website_rating_option_id)

            if (RANDOMIZE_UNRATED_WEBSITES) {
                lastPage.value = null
                nextPage.value = 1
            } else {
                if (typeof response.last_page === 'number') {
                    lastPage.value = response.last_page
                }

                if (typeof response.current_page === 'number') {
                    nextPage.value = response.current_page + 1
                } else {
                    nextPage.value += 1
                }
            }

            if (unrated.length) {
                queue.value.push(...unrated)
                finished.value = false
                preloadUpcoming()
            }

            return unrated.length > 0
        } catch (err) {
            error.value = err?.message || 'Failed to load websites.'
            console.error('Error fetching unrated organizations:', err)
            return false
        } finally {
            isLoading.value = false
            pendingFetch = null
        }
    })()

    return pendingFetch
}

const prefetchIfNeeded = () => {
    if (queue.value.length < 2 && hasMorePages.value && !isLoading.value) {
        fetchNextBatch()
    }
}

const ensureQueueHasItems = async () => {
    if (queue.value.length > 0) return true
    const loaded = await fetchNextBatch()
    return loaded && queue.value.length > 0
}

const loadNextOrganization = async () => {
    currentOrg.value = null
    const hasItems = await ensureQueueHasItems()
    if (!hasItems) {
        finished.value = true
        return false
    }

    finished.value = false
    currentOrg.value = queue.value.shift() || null
    prefetchIfNeeded()
    preloadUpcoming()
    if (currentOrg.value) {
        preloadScreenshot(currentOrg.value)
    }
    return !!currentOrg.value
}

const initialize = async () => {
    await loadRatingOptions()
    if (!ratingOptions.value.length) {
        finished.value = true
        currentOrg.value = null
        queue.value = []
        return
    }
    await loadNextOrganization()
}

const rateWebsite = async (optionId) => {
    if (!currentOrg.value || isRating.value || !optionId) return

    isRating.value = true
    error.value = null

    try {
        await api.post(`/organizations/${currentOrg.value.id}/website-ratings`, { website_rating_option_id: optionId })
        await loadNextOrganization()
    } catch (err) {
        error.value = err?.message || 'Failed to update website rating.'
        console.error('Error updating website rating:', err)
    } finally {
        isRating.value = false
    }
}

const skipWebsite = async () => {
    if (isRating.value) return
    await loadNextOrganization()
}

watch(
    () => currentOrg.value,
    (org) => {
        screenshotReady.value = false
        screenshotError.value = null

        if (!org) {
            prefetchIfNeeded()
            preloadUpcoming()
            return
        }

        if (!org.website) return

        const key = getScreenshotKey(org.website)
        const entry = key ? screenshotCache[key] : null
        if (entry?.status === 'loaded') {
            screenshotReady.value = true
        } else if (entry?.status === 'error') {
            screenshotError.value = 'Screenshot unavailable.'
        } else {
            preloadScreenshot(org)
        }
    },
    { immediate: true }
)

const handleScreenshotLoad = () => {
    screenshotReady.value = true
    const org = currentOrg.value
    if (!org || !org.website) return
    const key = getScreenshotKey(org.website)
    if (key) {
        screenshotCache[key] = { status: 'loaded' }
    }
}

const handleScreenshotError = () => {
    screenshotError.value = 'Screenshot unavailable.'
    screenshotReady.value = false
    const org = currentOrg.value
    if (!org || !org.website) return
    const key = getScreenshotKey(org.website)
    if (key) {
        screenshotCache[key] = { status: 'error' }
    }
}

onMounted(() => {
    initialize()
})
</script>

<template>
    <DefaultLayout>
        <div class="py-6 sm:py-10">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-neutral-900 sm:text-3xl">Website Ratings</h1>
                        <p class="text-sm text-neutral-500 sm:max-w-[560px]">
                            Review each organization's website and choose a rating. Skip any you want to revisit later.
                        </p>
                    </div>
                </div>

                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </div>

                <div v-if="!ratingOptions.length && !isLoading" class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-700">
                    No rating options are configured yet. Add options first so users can submit website ratings.
                </div>

                <div v-if="currentOrg" class="flex flex-col gap-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <h2 class="text-xl font-semibold text-neutral-900 sm:text-2xl">
                                    {{ currentOrg.name }}
                                </h2>
                                <a
                                    v-if="currentOrg.website"
                                    :href="formatWebsite(currentOrg.website)"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-neutral-600 underline underline-offset-4 hover:text-neutral-900"
                                >
                                    {{ formatWebsite(currentOrg.website) }}
                                </a>
                            </div>
                            <div class="flex w-full flex-col gap-4 sm:w-auto sm:items-end">
                                <div class="text-left text-xs text-neutral-500 sm:text-right">
                                    <template v-if="currentOrg.website_rating_summary">
                                        <span class="font-semibold text-neutral-700 sm:block">
                                            Average: {{ optionBySlug[currentOrg.website_rating_summary]?.name || currentOrg.website_rating_summary }}
                                        </span>
                                        <span v-if="currentOrg.website_rating_average !== null">
                                            ({{ formatAverage(currentOrg.website_rating_average) }})
                                        </span>
                                        <span v-if="currentOrg.website_rating_count"> • {{ currentOrg.website_rating_count }} ratings </span>
                                    </template>
                                    <span v-else class="text-neutral-400">No ratings yet</span>
                                </div>
                                <div class="flex flex-wrap gap-2 sm:justify-end sm:gap-3">
                                    <Button
                                        v-for="option in ratingOptions"
                                        :key="option.id"
                                        size="lg"
                                        variant="ghost"
                                        class="flex-1 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-wide transition focus-visible:outline-offset-2 sm:flex-none sm:px-6 sm:text-sm"
                                        :class="ratingButtonClasses(option)"
                                        :disabled="isRating"
                                        @click="rateWebsite(option.id)"
                                    >
                                        {{ option.name }}
                                    </Button>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 sm:justify-end sm:gap-3">
                                    <span v-if="isRating" class="text-xs text-neutral-400">Saving rating…</span>
                                    <button
                                        class="cursor-pointer text-sm text-neutral-500 hover:text-neutral-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="isRating"
                                        @click="skipWebsite"
                                    >
                                        Skip for now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="currentOrg.website"
                        class="relative w-full overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-900 shadow-lg shadow-neutral-900/20 sm:rounded-3xl min-h-[360px] sm:min-h-[480px] lg:min-h-[60vh]"
                    >
                        <div
                            v-if="!screenshotReady && !screenshotError"
                            class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 bg-neutral-900 text-neutral-300"
                        >
                            <span class="text-sm font-medium uppercase tracking-wide">Loading screenshot…</span>
                            <span class="text-xs text-neutral-500">Fetching a fresh view of the site.</span>
                        </div>
                        <div
                            v-else-if="screenshotError"
                            class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 bg-neutral-900 text-neutral-200"
                        >
                            <span class="text-sm font-semibold">Screenshot unavailable</span>
                            <span class="text-xs text-neutral-500">Open the site directly to complete the review.</span>
                        </div>
                        <img
                            v-if="getScreenshotUrl(currentOrg.website) && !screenshotError"
                            :key="currentOrg.id"
                            :src="getScreenshotUrl(currentOrg.website)"
                            :alt="`Screenshot of ${currentOrg.name} website`"
                            class="h-full w-full min-h-[320px] max-h-[70vh] object-contain bg-black sm:min-h-[480px] lg:max-h-none"
                            @load="handleScreenshotLoad"
                            @error="handleScreenshotError"
                        />
                    </div>
                </div>

                <div
                    v-else
                    class="flex min-h-[40vh] flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-neutral-300 bg-neutral-50"
                >
                    <div v-if="finished && !isLoading" class="text-center">
                        <p class="text-lg font-semibold text-neutral-700">All caught up!</p>
                        <p class="text-sm text-neutral-500">There are no more organizations with unrated websites.</p>
                    </div>
                    <div v-else class="text-center">
                        <p class="text-lg font-semibold text-neutral-700">Loading next website…</p>
                        <p class="text-sm text-neutral-500">Please wait while we find the next unrated organization.</p>
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
