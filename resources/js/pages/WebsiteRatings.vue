<script setup>
import { ref, computed, onMounted, watch, reactive } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import api from '@/services/api'

const queue = ref([])
const currentOrg = ref(null)
const nextPage = ref(1)
const lastPage = ref(null)
const isLoading = ref(false)
const isRating = ref(false)
const error = ref(null)
const finished = ref(false)
const screenshotReady = ref(false)
const screenshotError = ref(null)
const screenshotCache = reactive({})

const hasMorePages = computed(() => lastPage.value === null || nextPage.value <= lastPage.value)

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
            const response = await api.get('/organizations', {
                params: {
                    page: nextPage.value,
                    website: 'present',
                    website_rating: 'none'
                }
            })

            const data = Array.isArray(response.data) ? response.data : []

            if (typeof response.last_page === 'number') {
                lastPage.value = response.last_page
            }

            if (typeof response.current_page === 'number') {
                nextPage.value = response.current_page + 1
            } else {
                nextPage.value += 1
            }

            if (data.length) {
                queue.value.push(...data)
                finished.value = false
                preloadUpcoming()
            }

            return data.length > 0
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
    await loadNextOrganization()
}

const rateWebsite = async (rating) => {
    if (!currentOrg.value || isRating.value) return

    isRating.value = true
    error.value = null

    try {
        await api.put(`/organizations/${currentOrg.value.id}`, { website_rating: rating })
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
        <div class="py-10">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 class="text-3xl font-semibold text-neutral-900">Website Ratings</h1>
                        <p class="text-sm text-neutral-500">Review each organization's website and choose a rating. Skip any you want to revisit later.</p>
                    </div>
                </div>

                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </div>

                <div v-if="currentOrg" class="flex flex-col gap-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-2xl font-semibold text-neutral-900">
                                    {{ currentOrg.name }}
                                </h2>
                                <a
                                    v-if="currentOrg.website"
                                    :href="formatWebsite(currentOrg.website)"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-sm text-neutral-600 underline underline-offset-4 hover:text-neutral-900"
                                >
                                    {{ formatWebsite(currentOrg.website) }}
                                </a>
                            </div>
                            <div class="flex flex-col items-end gap-3">
                                <div class="flex flex-wrap justify-end gap-3">
                                    <Button
                                        size="lg"
                                        variant="ghost"
                                        class="rounded-full bg-green-600 px-6 py-2 text-white hover:bg-green-700"
                                        :disabled="isRating"
                                        @click="rateWebsite('good')"
                                    >
                                        Good
                                    </Button>
                                    <Button
                                        size="lg"
                                        variant="ghost"
                                        class="rounded-full bg-yellow-500 px-6 py-2 text-neutral-900 hover:bg-yellow-600"
                                        :disabled="isRating"
                                        @click="rateWebsite('okay')"
                                    >
                                        Okay
                                    </Button>
                                    <Button
                                        size="lg"
                                        variant="ghost"
                                        class="rounded-full bg-red-600 px-6 py-2 text-white hover:bg-red-700"
                                        :disabled="isRating"
                                        @click="rateWebsite('bad')"
                                    >
                                        Bad
                                    </Button>
                                </div>
                                <div class="flex flex-wrap items-center justify-end gap-3">
                                    <span v-if="isRating" class="text-xs text-neutral-400">Saving rating…</span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full border-neutral-200 px-4 py-1.5 text-xs font-semibold text-neutral-600 hover:bg-neutral-100"
                                        :disabled="isRating"
                                        @click="skipWebsite"
                                    >
                                        Skip
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="currentOrg.website"
                        class="relative w-full overflow-hidden rounded-3xl border border-neutral-200 bg-neutral-900"
                        style="min-height: 60vh"
                    >
                        <div
                            v-if="!screenshotReady && !screenshotError"
                            class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-neutral-900 text-neutral-300"
                        >
                            <span class="text-sm font-medium uppercase tracking-wide">Loading screenshot…</span>
                            <span class="text-xs text-neutral-500">Fetching a fresh view of the site.</span>
                        </div>
                        <div
                            v-else-if="screenshotError"
                            class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-neutral-900 text-neutral-200"
                        >
                            <span class="text-sm font-semibold">Screenshot unavailable</span>
                            <span class="text-xs text-neutral-500">Open the site directly to complete the review.</span>
                        </div>
                        <img
                            v-if="getScreenshotUrl(currentOrg.website) && !screenshotError"
                            :key="currentOrg.id"
                            :src="getScreenshotUrl(currentOrg.website)"
                            :alt="`Screenshot of ${currentOrg.name} website`"
                            class="h-full w-full object-contain bg-black"
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
