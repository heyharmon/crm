<script setup>
import { onMounted, ref, watch } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import { getRatingLabel, getRatingPillClasses } from '@/utils/ratingStyles'
import { formatDisplayDate } from '@/utils/date'

const props = defineProps({
    organizationId: { type: [String, Number], required: true }
})

const organizationStore = useOrganizationStore()
const APIFLASH_ACCESS_KEY = '3725d3868ee3426e82b2a3b9eebde219'
const isLoadingLocal = ref(false)
const error = ref(null)
const screenshotStatus = ref('idle') // idle | loading | ready | error | empty
const screenshotError = ref(null)
const screenshotSrc = ref(null)
const activeScreenshotKey = ref(null)

const isDetectingRedesign = ref(false)
const redesignActionError = ref(null)
const redesignPreviewStates = ref({})
const REDESIGN_VIEWS = ['after', 'before']

const load = async () => {
    try {
        isLoadingLocal.value = true
        error.value = null
        await organizationStore.fetchOrganization(props.organizationId)
    } catch (e) {
        error.value = e?.message || 'Failed to load organization'
    } finally {
        isLoadingLocal.value = false
    }
}

onMounted(load)
const org = () => organizationStore.currentOrganization

const formatRatingLabel = (slug) => getRatingLabel(slug)
const ratingSummaryClasses = (slug) => getRatingPillClasses(slug)

const normalizeWebsite = (url) => {
    if (!url) return ''
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

function buildApiflashUrl(targetUrl) {
    if (!targetUrl) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const params = new URLSearchParams({
        access_key: APIFLASH_ACCESS_KEY,
        wait_until: 'network_idle',
        no_cookie_banners: 'true',
        url: targetUrl
    })
    return `${baseUrl}?${params.toString()}`
}

function getScreenshotUrl(website) {
    if (!website) return null
    return buildApiflashUrl(normalizeWebsite(website))
}

function resetScreenshot(status = 'idle') {
    screenshotStatus.value = status
    screenshotSrc.value = null
    screenshotError.value = null
    activeScreenshotKey.value = null
}

function loadScreenshot(organization) {
    if (!organization) {
        resetScreenshot('idle')
        return
    }

    if (!organization.website) {
        resetScreenshot('empty')
        return
    }

    const screenshotUrl = getScreenshotUrl(organization.website)
    if (!screenshotUrl) {
        screenshotError.value = 'Screenshot unavailable.'
        screenshotStatus.value = 'error'
        screenshotSrc.value = null
        return
    }

    const key = screenshotUrl
    activeScreenshotKey.value = key
    screenshotStatus.value = 'loading'
    screenshotError.value = null
    screenshotSrc.value = null

    const img = new Image()
    img.onload = () => {
        if (activeScreenshotKey.value !== key) return
        screenshotSrc.value = screenshotUrl
        screenshotStatus.value = 'ready'
    }
    img.onerror = () => {
        if (activeScreenshotKey.value !== key) return
        screenshotStatus.value = 'error'
        screenshotError.value = 'Screenshot unavailable.'
        screenshotSrc.value = null
    }
    img.src = screenshotUrl
}

const buildArchivedUrlForTimestamp = (timestamp) => {
    if (!timestamp) return null
    const baseWebsite = org()?.website
    if (!baseWebsite) return null
    const encodedOriginal = normalizeWebsite(baseWebsite)
    return `https://web.archive.org/web/${timestamp}/${encodedOriginal}`
}

const redesignViewTimestamp = (event, view = 'after') => {
    if (!event) return null
    if (view === 'before') {
        return event.before_wayback_timestamp || null
    }
    if (view === 'after') {
        return event.after_wayback_timestamp || null
    }
    return null
}

const redesignViewCapturedAt = (event, view = 'after') => {
    if (!event) return null
    if (view === 'before') {
        return event.before_captured_at || null
    }
    if (view === 'after') {
        return event.after_captured_at || null
    }
    return null
}

const buildRedesignArchivedUrl = (event, view = 'after') => {
    const timestamp = redesignViewTimestamp(event, view)
    if (!timestamp) return null
    return buildArchivedUrlForTimestamp(timestamp)
}

const getRedesignScreenshotUrl = (event, view = 'after') => {
    const archivedUrl = buildRedesignArchivedUrl(event, view)
    if (!archivedUrl) return null
    return buildApiflashUrl(archivedUrl)
}

const redesignEventKey = (event) => {
    if (!event) return null
    return event.id ?? event.after_wayback_timestamp ?? event.before_wayback_timestamp ?? null
}

const redesignPreviewKey = (event, view = 'after') => {
    const key = redesignEventKey(event)
    if (!key) return null
    return `${key}:${view}`
}

const setRedesignPreviewState = (event, state, view = 'after') => {
    const key = redesignPreviewKey(event, view)
    if (!key) return

    redesignPreviewStates.value = {
        ...redesignPreviewStates.value,
        [key]: state
    }
}

const getRedesignPreviewState = (event, view = 'after') => {
    const key = redesignPreviewKey(event, view)
    return key ? redesignPreviewStates.value[key] : null
}

const isRedesignPreviewLoading = (event, view = 'after') => getRedesignPreviewState(event, view) === 'loading'
const hasRedesignPreviewError = (event, view = 'after') => getRedesignPreviewState(event, view) === 'error'

const describeNavChange = (event) => {
    if (!event || typeof event.nav_similarity !== 'number') return null
    const difference = Math.max(0, Math.min(1, 1 - event.nav_similarity))
    return `Navigation changed ≈ ${Math.round(difference * 100)}%`
}

const navClassCountLabel = (event, view = 'after') => {
    if (!event) return null
    const count = view === 'before' ? event.before_nav_class_count : event.after_nav_class_count
    if (typeof count !== 'number' || count <= 0) return null
    return `${count} CSS class${count === 1 ? '' : 'es'}`
}

const summarizeNavClasses = (event, view = 'after') => {
    if (!event) return null
    const classList = view === 'before' ? event.before_nav_classes : event.after_nav_classes
    if (!Array.isArray(classList)) return null

    const cleaned = classList
        .map((value) => (typeof value === 'string' ? value.trim() : ''))
        .filter(Boolean)

    if (!cleaned.length) return null

    const maxItems = 6
    const subset = cleaned.slice(0, maxItems).join(', ')
    const ellipsis = cleaned.length > maxItems ? '…' : ''

    return `${subset}${ellipsis}`
}

const REDESIGN_STATUS_META = {
    wayback_failed: {
        label: 'Wayback request failed. Please try again later.',
        banner: 'border-red-200 bg-red-50 text-red-700'
    },
    no_wayback_data: {
        label: 'Wayback Machine does not have snapshots for this website yet.',
        banner: 'border-amber-200 bg-amber-50 text-amber-800'
    },
    no_major_events: {
        label: 'Navigation analysis did not detect any major redesigns.',
        banner: 'border-blue-200 bg-blue-50 text-blue-700'
    }
}

const shouldShowRedesignStatus = (organization) => {
    const status = organization?.website_redesign_status
    if (!status) return false
    return status !== 'success'
}

const redesignStatusMessage = (organization) => {
    if (!organization) return ''
    const status = organization.website_redesign_status
    const meta = REDESIGN_STATUS_META[status]
    return organization.website_redesign_status_message || meta?.label || 'Unable to retrieve Wayback data.'
}

const redesignStatusBannerClasses = (status) => REDESIGN_STATUS_META[status]?.banner ?? 'border-neutral-200 bg-neutral-50 text-neutral-700'

const redesignFallbackDescription = (organization) => {
    if (shouldShowRedesignStatus(organization)) {
        return 'Try running detection again once the Wayback issue above is resolved.'
    }
    return "We haven't confirmed any major redesigns yet."
}

const syncRedesignPreviewStates = (organization) => {
    if (!organization?.website_redesigns?.length) {
        redesignPreviewStates.value = {}
        return
    }

    const nextStates = {}

    organization.website_redesigns.forEach((event) => {
        REDESIGN_VIEWS.forEach((view) => {
            const key = redesignPreviewKey(event, view)
            if (!key) {
                return
            }

            const screenshotUrl = getRedesignScreenshotUrl(event, view)
            if (!screenshotUrl) {
                nextStates[key] = 'error'
                return
            }

            const currentState = redesignPreviewStates.value[key]
            nextStates[key] = currentState === 'ready' ? 'ready' : 'loading'
        })
    })

    redesignPreviewStates.value = nextStates
}

const handleRedesignPreviewLoad = (event, view = 'after') => {
    setRedesignPreviewState(event, 'ready', view)
}

const handleRedesignPreviewError = (event, view = 'after') => {
    setRedesignPreviewState(event, 'error', view)
}

const detectWebsiteRedesign = async () => {
    const organization = org()
    if (!organization?.id || isDetectingRedesign.value) {
        return
    }

    try {
        redesignActionError.value = null
        isDetectingRedesign.value = true
        organizationStore.resetOrganizationRedesignData(organization.id)
        syncRedesignPreviewStates(organizationStore.currentOrganization)
        await organizationStore.detectWebsiteRedesign(organization.id)
        await organizationStore.fetchOrganization(organization.id)
    } catch (e) {
        redesignActionError.value = e?.message || 'Failed to detect redesign.'
    } finally {
        isDetectingRedesign.value = false
    }
}

watch(() => props.organizationId, load)
watch(
    () => organizationStore.currentOrganization,
    (organization) => {
        loadScreenshot(organization)
        syncRedesignPreviewStates(organization)
        redesignActionError.value = null
    },
    { immediate: true }
)
</script>

<template>
    <div class="h-full flex flex-col">
        <div v-if="isLoadingLocal" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
        </div>

        <div v-else-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded m-4">
            {{ error }}
        </div>

        <div v-else-if="org()" class="space-y-6 p-4">
            <div class="space-y-2">
                <h2 class="text-xl font-bold text-neutral-900">{{ org().name }}</h2>
                <p v-if="org().category" class="text-neutral-600">{{ org().category.name }}</p>
            </div>

            <div v-if="org().website" class="rounded-lg overflow-hidden border border-neutral-200">
                <img v-if="screenshotStatus === 'ready' && screenshotSrc" :src="screenshotSrc" :alt="org().name" class="w-full max-h-56 object-cover" />
                <div v-else class="flex h-56 w-full items-center justify-center bg-neutral-50 text-sm font-medium text-neutral-500">
                    <span v-if="screenshotStatus === 'loading'">Loading website preview…</span>
                    <span v-else-if="screenshotStatus === 'error'">{{ screenshotError || 'Screenshot unavailable.' }}</span>
                    <span v-else>Website preview unavailable.</span>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-neutral-200 p-4">
                <h3 class="font-semibold mb-3">Contact</h3>
                <div class="grid grid-cols-1 gap-2 text-sm">
                    <div v-if="org().phone">
                        <span class="font-medium text-neutral-700">Phone:</span>
                        <a :href="`tel:${org().phone}`" class="ml-2 text-blue-600 hover:text-blue-800">{{ org().phone }}</a>
                    </div>
                    <div v-if="org().website">
                        <span class="font-medium text-neutral-700">Website:</span>
                        <a
                            :href="org().formatted_website || org().website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ml-2 text-blue-600 hover:text-blue-800"
                            >{{ org().website }}</a
                        >
                    </div>
                </div>
            </div>

            <div v-if="org().full_address" class="bg-white rounded-lg border border-neutral-200 p-4">
                <h3 class="font-semibold mb-3">Address</h3>
                <p class="text-sm">{{ org().full_address }}</p>
                <div v-if="org().map_url" class="mt-2">
                    <a :href="org().map_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 text-sm"
                        >View on Google Maps →</a
                    >
                </div>
            </div>

            <div class="bg-white rounded-lg border border-neutral-200 p-4">
                <h3 class="font-semibold mb-3">Website Ratings</h3>
                <div class="space-y-2 text-sm text-neutral-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-neutral-900">Average:</span>
                        <template v-if="org().website_rating_summary">
                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                                :class="ratingSummaryClasses(org().website_rating_summary)"
                            >
                                {{ formatRatingLabel(org().website_rating_summary) }}
                            </span>
                            <span v-if="org().website_rating_average !== null" class="text-neutral-500 text-sm">
                                ({{ Number(org().website_rating_average).toFixed(2) }})
                            </span>
                            <span v-if="org().website_rating_count" class="text-neutral-500 text-sm"> • {{ org().website_rating_count }} ratings </span>
                        </template>
                        <span v-else class="text-neutral-400 text-sm">No ratings yet</span>
                    </div>
                    <div>
                        <span class="font-medium text-neutral-900">Weighted:</span>
                        <span v-if="org().website_rating_weighted !== null">
                            {{ Number(org().website_rating_weighted).toFixed(2) }}
                        </span>
                        <span v-else class="text-neutral-400">No data</span>
                    </div>
                    <div>
                        <span class="font-medium text-neutral-900">Your rating:</span>
                        <span v-if="org().my_website_rating_option_name">
                            {{ org().my_website_rating_option_name }}
                        </span>
                        <span v-else class="text-neutral-400">Not set</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-neutral-200 p-4">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="font-semibold">Website History</h3>
                    <button
                        v-if="org().website"
                        type="button"
                        class="inline-flex items-center justify-center rounded-full border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isDetectingRedesign"
                        @click="detectWebsiteRedesign"
                    >
                        <span v-if="isDetectingRedesign" class="flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 animate-spin text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                            </svg>
                            Detecting…
                        </span>
                        <span v-else>
                            {{ org().last_major_redesign_at ? 'Redetect redesign' : 'Detect redesign' }}
                        </span>
                    </button>
                </div>
                <p
                    v-if="shouldShowRedesignStatus(org())"
                    class="mb-3 rounded-lg border px-3 py-2 text-xs font-medium"
                    :class="redesignStatusBannerClasses(org().website_redesign_status)"
                >
                    {{ redesignStatusMessage(org()) }}
                </p>
                <p v-if="redesignActionError" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ redesignActionError }}
                </p>
                <div class="space-y-3 text-sm text-neutral-700">
                    <div
                        class="flex flex-col gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
                        v-if="org().last_major_redesign_at"
                    >
                        <span class="font-semibold text-emerald-900">Last major redesign:</span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                            {{ formatDisplayDate(org().last_major_redesign_at) }}
                        </span>
                    </div>
                    <div v-else class="flex items-center justify-between">
                        <span class="font-medium text-neutral-900">Last major redesign:</span>
                        <span class="text-neutral-400"> Not detected </span>
                    </div>
                    <div v-if="org().website_redesigns && org().website_redesigns.length" class="space-y-3">
                        <div
                            v-for="event in org().website_redesigns"
                            :key="event.id || event.after_wayback_timestamp"
                            class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden"
                        >
                            <div class="px-4 py-3 space-y-3 text-sm text-neutral-700">
                                <div class="flex flex-wrap items-start justify-between gap-2 text-sm font-semibold text-neutral-900">
                                    <div class="flex flex-col">
                                        <span>{{ formatDisplayDate(event.after_captured_at) }}</span>
                                        <span v-if="event.before_captured_at" class="text-xs font-medium text-neutral-500">
                                            Compared to {{ formatDisplayDate(event.before_captured_at) }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span v-if="describeNavChange(event)" class="block text-xs font-medium text-emerald-600">
                                            {{ describeNavChange(event) }}
                                        </span>
                                        <span v-if="navClassCountLabel(event, 'after')" class="block text-xs text-neutral-500">
                                            After nav: {{ navClassCountLabel(event, 'after') }}
                                        </span>
                                        <span v-if="navClassCountLabel(event, 'before')" class="block text-xs text-neutral-400">
                                            Before nav: {{ navClassCountLabel(event, 'before') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                            <span>After</span>
                                            <span v-if="redesignViewCapturedAt(event, 'after')" class="text-[11px] font-medium text-neutral-500">
                                                {{ formatDisplayDate(redesignViewCapturedAt(event, 'after')) }}
                                            </span>
                                        </div>
                                        <div class="relative h-40 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100">
                                            <img
                                                v-if="getRedesignScreenshotUrl(event, 'after')"
                                                :src="getRedesignScreenshotUrl(event, 'after')"
                                                :alt="`Archived screenshot after redesign (${formatDisplayDate(redesignViewCapturedAt(event, 'after')) || 'Wayback'})`"
                                                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-200"
                                                :class="isRedesignPreviewLoading(event, 'after') ? 'opacity-0' : 'opacity-100'"
                                                loading="lazy"
                                                @load="handleRedesignPreviewLoad(event, 'after')"
                                                @error="handleRedesignPreviewError(event, 'after')"
                                            />
                                            <div
                                                v-if="isRedesignPreviewLoading(event, 'after')"
                                                class="absolute inset-0 flex items-center justify-center gap-2 bg-white/75 text-xs font-medium text-neutral-600"
                                            >
                                                <svg class="h-4 w-4 animate-spin text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                                                </svg>
                                                Loading screenshot…
                                            </div>
                                            <div
                                                v-else-if="hasRedesignPreviewError(event, 'after') || !getRedesignScreenshotUrl(event, 'after')"
                                                class="absolute inset-0 flex items-center justify-center text-xs font-medium text-neutral-500"
                                            >
                                                Preview unavailable
                                            </div>
                                        </div>
                                        <a
                                            v-if="buildRedesignArchivedUrl(event, 'after')"
                                            :href="buildRedesignArchivedUrl(event, 'after')"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800"
                                        >
                                            View after snapshot →
                                        </a>
                                        <p v-if="summarizeNavClasses(event, 'after')" class="text-[11px] text-neutral-500">
                                            {{ summarizeNavClasses(event, 'after') }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-neutral-600">
                                            <span>Before</span>
                                            <span v-if="redesignViewCapturedAt(event, 'before')" class="text-[11px] font-medium text-neutral-500">
                                                {{ formatDisplayDate(redesignViewCapturedAt(event, 'before')) }}
                                            </span>
                                        </div>
                                        <div class="relative h-40 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100">
                                            <img
                                                v-if="getRedesignScreenshotUrl(event, 'before')"
                                                :src="getRedesignScreenshotUrl(event, 'before')"
                                                :alt="`Archived screenshot before redesign (${formatDisplayDate(redesignViewCapturedAt(event, 'before')) || 'Wayback'})`"
                                                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-200"
                                                :class="isRedesignPreviewLoading(event, 'before') ? 'opacity-0' : 'opacity-100'"
                                                loading="lazy"
                                                @load="handleRedesignPreviewLoad(event, 'before')"
                                                @error="handleRedesignPreviewError(event, 'before')"
                                            />
                                            <div
                                                v-if="isRedesignPreviewLoading(event, 'before')"
                                                class="absolute inset-0 flex items-center justify-center gap-2 bg-white/75 text-xs font-medium text-neutral-600"
                                            >
                                                <svg class="h-4 w-4 animate-spin text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                                                </svg>
                                                Loading screenshot…
                                            </div>
                                            <div
                                                v-else-if="hasRedesignPreviewError(event, 'before') || !getRedesignScreenshotUrl(event, 'before')"
                                                class="absolute inset-0 flex items-center justify-center text-xs font-medium text-neutral-500"
                                            >
                                                Preview unavailable
                                            </div>
                                        </div>
                                        <a
                                            v-if="buildRedesignArchivedUrl(event, 'before')"
                                            :href="buildRedesignArchivedUrl(event, 'before')"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800"
                                        >
                                            View before snapshot →
                                        </a>
                                        <p v-if="summarizeNavClasses(event, 'before')" class="text-[11px] text-neutral-500">
                                            {{ summarizeNavClasses(event, 'before') }}
                                        </p>
                                    </div>
                                </div>
                                <span class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">Wayback Machine</span>
                            </div>
                        </div>
                        <p class="text-xs text-neutral-400">Data from the Internet Archive Wayback Machine</p>
                    </div>
                    <div v-else class="text-sm text-neutral-500">
                        {{ redesignFallbackDescription(org()) }}
                    </div>
                </div>
            </div>

            <div v-if="org().notes" class="bg-white rounded-lg border border-neutral-200 p-4">
                <h3 class="font-semibold mb-3">Notes</h3>
                <p class="text-sm whitespace-pre-wrap">{{ org().notes }}</p>
            </div>
        </div>
    </div>
</template>
