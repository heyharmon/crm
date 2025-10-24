<script setup>
import { ref, computed, onMounted } from 'vue'
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

const hasMorePages = computed(() => lastPage.value === null || nextPage.value <= lastPage.value)

const getScreenshotUrl = (website) => {
    if (!website) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
    return `${baseUrl}?access_key=${accessKey}&wait_until=page_loaded&no_cookie_banners=true&url=${encodeURIComponent(website)}`
}

const formatWebsite = (url) => {
    if (!url) return ''
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

const fetchNextBatch = async () => {
    if (isLoading.value) return false
    if (!hasMorePages.value) return false

    isLoading.value = true
    error.value = null

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
        }

        return data.length > 0
    } catch (err) {
        error.value = err?.message || 'Failed to load websites.'
        console.error('Error fetching unrated organizations:', err)
        return false
    } finally {
        isLoading.value = false
    }
}

const prefetchIfNeeded = () => {
    if (queue.value.length < 3 && hasMorePages.value && !isLoading.value) {
        fetchNextBatch()
    }
}

const ensureCurrent = async () => {
    if (currentOrg.value) return

    if (queue.value.length === 0) {
        const loaded = await fetchNextBatch()
        if (!loaded) {
            if (!hasMorePages.value) {
                finished.value = true
            }
            return
        }
    }

    currentOrg.value = queue.value.shift() || null
    if (currentOrg.value) {
        prefetchIfNeeded()
    }
}

const advanceToNext = async () => {
    currentOrg.value = null
    await ensureCurrent()
}

const rateWebsite = async (rating) => {
    if (!currentOrg.value || isRating.value) return

    isRating.value = true
    error.value = null

    try {
        await api.put(`/organizations/${currentOrg.value.id}`, { website_rating: rating })
        await advanceToNext()
    } catch (err) {
        error.value = err?.message || 'Failed to update website rating.'
        console.error('Error updating website rating:', err)
    } finally {
        isRating.value = false
    }
}

onMounted(() => {
    ensureCurrent()
})
</script>

<template>
    <DefaultLayout>
        <div class="py-10">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 class="text-3xl font-semibold text-neutral-900">Website Ratings</h1>
                        <p class="text-sm text-neutral-500">
                            Review each organization's website and choose a rating. The next unrated organization will load automatically.
                        </p>
                    </div>
                    <div v-if="currentOrg" class="text-right text-sm text-neutral-500">
                        <div class="font-semibold text-neutral-600">Up next</div>
                        <div>{{ queue.length }} more loaded</div>
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
                                <div v-if="currentOrg.category" class="text-sm font-medium text-neutral-600">
                                    {{ currentOrg.category.name }}
                                </div>
                                <div v-if="currentOrg.city || currentOrg.state" class="text-sm text-neutral-500">
                                    {{ [currentOrg.city, currentOrg.state].filter(Boolean).join(', ') }}
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-neutral-500">
                                <a
                                    v-if="currentOrg.website"
                                    :href="formatWebsite(currentOrg.website)"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-semibold text-neutral-700 underline underline-offset-4 hover:text-neutral-900"
                                >
                                    Open Website
                                </a>
                            </div>
                        </div>
                        <div v-if="currentOrg.phone" class="text-sm text-neutral-500">
                            {{ currentOrg.phone }}
                        </div>
                    </div>

                    <div
                        v-if="currentOrg.website"
                        class="relative w-full overflow-hidden rounded-3xl border border-neutral-200 bg-neutral-900"
                        style="min-height: 60vh"
                    >
                        <img
                            v-if="getScreenshotUrl(currentOrg.website)"
                            :src="getScreenshotUrl(currentOrg.website)"
                            :alt="`Screenshot of ${currentOrg.name} website`"
                            class="h-full w-full object-contain bg-black"
                            @error="(e) => (e.target.style.display = 'none')"
                        />
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-5 py-4 text-sm font-medium text-white">
                            {{ currentOrg.website }}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
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
                        <div v-if="isRating" class="text-sm text-neutral-500">Saving rating…</div>
                    </div>
                </div>

                <div v-else class="flex min-h-[40vh] flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-neutral-300 bg-neutral-50">
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
