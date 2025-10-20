<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import OrganizationFilters from '@/components/OrganizationFilters.vue'
import Pagination from '@/components/Pagination.vue'
import api from '@/services/api'
import RightDrawer from '@/components/ui/RightDrawer.vue'
import OrganizationForm from '@/components/OrganizationForm.vue'
import OrganizationDetails from '@/components/OrganizationDetails.vue'

const organizationStore = useOrganizationStore()
const route = useRoute()
const router = useRouter()

// --- Query <-> Filters sync helpers ---
const syncingQuery = ref(false)

const parseFiltersFromQuery = (q) => {
    const toStr = (v) => (typeof v === 'string' ? v : '')
    const toArr = (v) => (Array.isArray(v) ? v : v ? [String(v)] : [])
    return {
        filters: {
            search: toStr(q.search),
            city: toStr(q.city),
            state: toStr(q.state),
            category: toStr(q.category),
            website: toStr(q.website),
            website_rating: toStr(q.website_rating),
            sort: toArr(q.sort)
        },
        page: q.page ? Number(q.page) || 1 : 1
    }
}

const buildQueryFromFilters = (filters, page, base = {}) => {
    const q = { ...base }
    // drop existing filter keys so we can rebuild cleanly
    delete q.search
    delete q.city
    delete q.state
    delete q.category
    delete q.website
    delete q.website_rating
    delete q.sort
    delete q.page

    if (filters.search) q.search = filters.search
    if (filters.city) q.city = filters.city
    if (filters.state) q.state = filters.state
    if (filters.category) q.category = filters.category
    if (filters.website) q.website = filters.website
    if (filters.website_rating) q.website_rating = filters.website_rating
    if (Array.isArray(filters.sort) && filters.sort.length) q.sort = [...filters.sort]
    if (page && page > 1) q.page = String(page)
    return q
}

onMounted(async () => {
    // Hydrate filters and page from the URL on load
    const { filters, page } = parseFiltersFromQuery(route.query)
    // prevent filter watcher from resetting page on initial load
    syncingQuery.value = true
    try {
        if (filters) organizationStore.setFilters(filters)
    } finally {
        syncingQuery.value = false
    }
    await organizationStore.fetchOrganizations(page)
})

// Keep URL query in sync when filters change, and fetch
watch(
    () => organizationStore.filters,
    async (newFilters, oldFilters) => {
        if (syncingQuery.value) return
        syncingQuery.value = true

        // If filters changed, reset page to 1
        const page = 1
        const nextQuery = buildQueryFromFilters(newFilters, page, route.query)
        try {
            await router.replace({ query: nextQuery })
        } finally {
            syncingQuery.value = false
        }
        await organizationStore.fetchOrganizations(page)
    },
    { deep: true }
)

const handleSearch = async () => {
    // Force page reset and fetch using current filters
    const q = buildQueryFromFilters(organizationStore.filters, 1, route.query)
    syncingQuery.value = true
    try {
        await router.replace({ query: q })
    } finally {
        syncingQuery.value = false
    }
    await organizationStore.fetchOrganizations(1)
}

const handlePageChange = async (page) => {
    // Persist page in query and let fetch run here
    const q = buildQueryFromFilters(organizationStore.filters, page, route.query)
    syncingQuery.value = true
    try {
        await router.replace({ query: q })
    } finally {
        syncingQuery.value = false
    }
    await organizationStore.fetchOrganizations(page)
}

const deleteOrganization = async (id) => {
    try {
        await organizationStore.deleteOrganization(id)
    } catch (error) {
        console.error('Error deleting organization:', error)
    }
}

const startWebScraping = async (organization) => {
    if (!organization.website) {
        alert('This organization does not have a website to scrape.')
        return
    }

    try {
        const data = await api.post('/web-scraper/start', {
            organization_id: organization.id,
            max_pages: 50,
            max_depth: 2
        })
    } catch (error) {
        console.error('Error starting web scraping:', error)
        alert('Failed to start web scraping. Please try again.')
    }
}

// Ensure website links include protocol
const formatWebsite = (url) => {
    if (!url) return ''
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

// Unified view toggle (table/grid) synced with ?view=grid
const view = ref(route.query.view === 'grid' ? 'grid' : 'table')
watch(
    () => route.query.view,
    (v) => {
        view.value = v === 'grid' ? 'grid' : 'table'
    }
)
watch(view, (v) => {
    const q = { ...route.query }
    if (v === 'grid') q.view = 'grid'
    else delete q.view
    router.replace({ query: q })
})

// Grid helpers
const columns = ref(3)
const getScreenshotUrl = (website) => {
    if (!website) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
    return `${baseUrl}?access_key=${accessKey}&wait_until=page_loaded&no_cookie_banners=true&url=${encodeURIComponent(website)}`
}
const openWebsite = (website) => {
    if (website) {
        window.open(formatWebsite(website), '_blank')
    }
}
const updateWebsiteRating = async (organizationId, rating) => {
    try {
        await organizationStore.updateOrganization(organizationId, { website_rating: rating })
        const org = organizationStore.organizations.find((o) => o.id === organizationId)
        if (org) org.website_rating = rating
    } catch (error) {
        console.error('Error updating website rating:', error)
    }
}

// Taller screenshot heights for 1–2 column modes
const cardImageHeightClass = computed(() => {
    // Taller screenshots for low column counts
    if (columns.value === 1) return 'h-[36rem] sm:h-[42rem]' // ~576–672px
    if (columns.value === 2) return 'h-96 sm:h-[30rem]' // ~384–480px (unchanged)
    return 'h-40 sm:h-48' // 160–192px for 3–4 columns
})

// Sidebar state synced with route query
const sidebarMode = ref(null) // 'view' | 'edit' | null
const sidebarOrgId = ref(null)
const isDrawerOpen = computed(() => !!sidebarMode.value && !!sidebarOrgId.value)

const syncFromRoute = () => {
    const { org, mode } = route.query
    if (org && (mode === 'view' || mode === 'edit')) {
        sidebarOrgId.value = org
        sidebarMode.value = mode
    } else {
        sidebarOrgId.value = null
        sidebarMode.value = null
    }
}

onMounted(syncFromRoute)
watch(() => route.query, syncFromRoute, { deep: true })

// React to route query changes (e.g., browser nav/manual edits) for filters/page
watch(
    () => route.query,
    async (q, prevQ) => {
        if (syncingQuery.value) return
        const keys = ['search', 'city', 'state', 'category', 'sort', 'page']
        const relevantChanged = keys.some((k) => JSON.stringify(q[k]) !== JSON.stringify(prevQ?.[k]))
        if (!relevantChanged) return

        const { filters, page } = parseFiltersFromQuery(q)
        organizationStore.setFilters(filters)
        await organizationStore.fetchOrganizations(page)
    },
    { deep: true }
)

const openSidebar = async (mode, id) => {
    const q = { ...route.query, org: String(id), mode }
    await router.replace({ query: q })
    // Ensure current organization is loaded for edit/details quickly
    try {
        await organizationStore.fetchOrganization(id)
    } catch (e) {
        // non-fatal; the nested components also handle loading
    }
}

const closeSidebar = async () => {
    const q = { ...route.query }
    delete q.org
    delete q.mode
    await router.replace({ query: q })
}

const selectedOrganization = computed(() => {
    const id = Number(sidebarOrgId.value)
    return organizationStore.organizations.find((o) => o.id === id) || organizationStore.currentOrganization
})

const handleEditSubmit = async (organizationData) => {
    if (!sidebarOrgId.value) return
    try {
        await organizationStore.updateOrganization(Number(sidebarOrgId.value), organizationData)
        await organizationStore.fetchOrganizations(organizationStore.pagination.current_page)
        // After saving, switch to view mode to reflect changes
        openSidebar('view', sidebarOrgId.value)
    } catch (error) {
        console.error('Error updating organization:', error)
    }
}

// Ref for calling submit from the drawer footer
const editFormRef = ref(null)
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 lg:px-6">
            <div class="rounded-3xl border border-neutral-200 bg-white/80 shadow-sm backdrop-blur">
                <div class="flex flex-col gap-4 p-6 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Overview</p>
                        <h1 class="text-3xl font-semibold text-neutral-900">Organizations</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-50 p-1">
                            <button
                                class="rounded-full px-4 py-1.5 text-sm font-medium text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                :class="view === 'table' ? 'bg-white text-neutral-900 shadow-sm' : 'hover:bg-white/80 hover:text-neutral-900'"
                                @click="view = 'table'"
                            >
                                Table
                            </button>
                            <button
                                class="rounded-full px-4 py-1.5 text-sm font-medium text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                :class="view === 'grid' ? 'bg-white text-neutral-900 shadow-sm' : 'hover:bg-white/80 hover:text-neutral-900'"
                                @click="view = 'grid'"
                            >
                                Grid
                            </button>
                        </div>
                        <router-link to="/organizations/import">
                            <Button
                                variant="outline"
                                class="rounded-full border-neutral-200 bg-white/90 px-4 py-2 text-sm font-medium text-neutral-900 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                            >
                                Import from Google Maps
                            </Button>
                        </router-link>
                        <router-link to="/organizations/create">
                            <Button class="rounded-full px-4 py-2 text-sm font-medium shadow-sm">Create Organization</Button>
                        </router-link>
                    </div>
                </div>
            </div>

            <OrganizationFilters
                :filters="organizationStore.filters"
                @update:filters="organizationStore.setFilters"
                @reset-filters="organizationStore.resetFilters"
                @search="handleSearch"
            />

            <div v-if="organizationStore.listLoading" class="flex justify-center rounded-2xl border border-dashed border-neutral-200 bg-white/70 py-16">
                <div class="h-10 w-10 animate-spin rounded-full border-2 border-neutral-200 border-t-neutral-900"></div>
            </div>

            <div v-else-if="organizationStore.error" class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 shadow-sm">
                {{ organizationStore.error }}
            </div>

            <div v-else>
                <!-- Table view -->
                <div v-if="view === 'table'" class="overflow-hidden rounded-3xl border border-neutral-200 bg-white/80 shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-neutral-700">
                            <thead class="bg-neutral-50/80 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                <tr>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Category</th>
                                    <th class="px-6 py-4">Location</th>
                                    <th class="px-6 py-4">Score</th>
                                    <th class="px-6 py-4">Reviews</th>
                                    <th class="px-6 py-4">Website Rating</th>
                                    <th class="px-6 py-4">Pages</th>
                                    <th class="px-6 py-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 bg-white/90">
                                <tr
                                    v-for="organization in organizationStore.organizations"
                                    :key="organization.id"
                                    class="cursor-pointer transition-colors hover:bg-neutral-50/80 focus-within:bg-neutral-50/80"
                                    @click="openSidebar('view', organization.id)"
                                    tabindex="0"
                                    @keydown.enter="openSidebar('view', organization.id)"
                                    @keydown.space.prevent="openSidebar('view', organization.id)"
                                >
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex items-start gap-3">
                                            <img
                                                v-if="organization.banner"
                                                :src="organization.banner"
                                                :alt="organization.name"
                                                class="h-11 w-11 rounded-full border border-neutral-200 object-cover shadow-sm"
                                            />
                                            <div
                                                class="flex h-11 w-11 items-center justify-center rounded-full border border-dashed border-neutral-200 bg-neutral-100 text-sm font-medium text-neutral-500"
                                                v-else
                                            >
                                                <span>{{ organization.name.charAt(0).toUpperCase() }}</span>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-sm font-semibold text-neutral-900">{{ organization.name }}</div>
                                                <div v-if="organization.phone" class="text-xs text-neutral-500">{{ organization.phone }}</div>
                                                <a
                                                    v-if="organization.website"
                                                    :href="formatWebsite(organization.website)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-neutral-700 underline underline-offset-4 hover:text-neutral-900 break-all"
                                                    @click.stop
                                                >
                                                    {{ organization.website }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-700">
                                        {{ organization.category?.name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-700">
                                        <div class="font-medium text-neutral-900">{{ organization.city || '-' }}</div>
                                        <div class="text-xs text-neutral-500">{{ organization.state || '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-700">
                                        <div
                                            v-if="organization.score"
                                            class="flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-50 px-2 py-1 text-xs font-medium text-neutral-700"
                                        >
                                            <span class="text-yellow-500">★</span>
                                            <span>{{ organization.score }}</span>
                                        </div>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-700">
                                        <span v-if="organization.reviews !== null && organization.reviews !== undefined">
                                            {{ organization.reviews }}
                                        </span>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-700">
                                        <span
                                            v-if="organization.website_rating"
                                            :class="{
                                                'border border-green-200 bg-green-100 text-green-700': organization.website_rating === 'good',
                                                'border border-yellow-200 bg-yellow-100 text-yellow-700': organization.website_rating === 'okay',
                                                'border border-red-200 bg-red-100 text-red-600': organization.website_rating === 'bad'
                                            }"
                                            class="rounded-full px-2.5 py-1 text-xs font-medium capitalize shadow-sm"
                                        >
                                            {{ organization.website_rating }}
                                        </span>
                                        <div
                                            v-else-if="!organization.website"
                                            class="inline-flex items-center rounded-full border border-dashed border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-500"
                                        >
                                            No Website
                                        </div>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-700">
                                        {{ organization.website ? organization.pages_count || 0 : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-700">
                                        <div class="flex items-center gap-2">
                                            <button
                                                @click.stop="openSidebar('edit', organization.id)"
                                                class="inline-flex items-center rounded-full border border-neutral-200 px-3 py-1 text-xs font-semibold text-green-700 transition hover:border-green-200 hover:bg-green-50"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="organization.website"
                                                @click.stop="startWebScraping(organization)"
                                                class="inline-flex items-center rounded-full border border-neutral-200 px-3 py-1 text-xs font-semibold text-purple-700 transition hover:border-purple-200 hover:bg-purple-50"
                                            >
                                                Scrape
                                            </button>
                                            <button
                                                @click.stop="deleteOrganization(organization.id)"
                                                class="inline-flex items-center rounded-full border border-neutral-200 px-3 py-1 text-xs font-semibold text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        :pagination="organizationStore.pagination"
                        @page-change="handlePageChange"
                        class="border-t border-neutral-100 bg-neutral-50/70 px-6 py-4"
                    />
                </div>

                <!-- Grid view -->
                <div v-else>
                    <div class="mb-4 flex items-center justify-end">
                        <div class="inline-flex items-center gap-3 rounded-full border border-neutral-200 bg-white/80 px-3 py-1.5 text-xs font-medium text-neutral-600 shadow-sm">
                            <span class="uppercase tracking-wide">Columns</span>
                            <div class="inline-flex items-center gap-1 rounded-full bg-neutral-100 p-1">
                                <button
                                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                    :class="columns === 1 ? 'bg-white text-neutral-900 shadow-sm' : 'hover:bg-white/80 hover:text-neutral-900'"
                                    @click="columns = 1"
                                >
                                    1
                                </button>
                                <button
                                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                    :class="columns === 2 ? 'bg-white text-neutral-900 shadow-sm' : 'hover:bg-white/80 hover:text-neutral-900'"
                                    @click="columns = 2"
                                >
                                    2
                                </button>
                                <button
                                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                    :class="columns === 3 ? 'bg-white text-neutral-900 shadow-sm' : 'hover:bg-white/80 hover:text-neutral-900'"
                                    @click="columns = 3"
                                >
                                    3
                                </button>
                                <button
                                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                    :class="columns === 4 ? 'bg-white text-neutral-900 shadow-sm' : 'hover:bg-white/80 hover:text-neutral-900'"
                                    @click="columns = 4"
                                >
                                    4
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2"
                        :class="{
                            'lg:grid-cols-1': columns === 1,
                            'lg:grid-cols-2': columns === 2,
                            'lg:grid-cols-3': columns === 3,
                            'lg:grid-cols-4': columns === 4
                        }"
                    >
                        <div
                            v-for="organization in organizationStore.organizations"
                            :key="organization.id"
                            class="overflow-hidden rounded-3xl border border-neutral-200 bg-white/80 shadow-sm transition-all hover:shadow-lg"
                        >
                            <div
                                class="relative cursor-pointer bg-neutral-100/80 backdrop-blur-sm"
                                :class="cardImageHeightClass"
                                @click="openWebsite(organization.website)"
                                :title="organization.website ? 'Visit website' : 'No website available'"
                            >
                                <img
                                    v-if="organization.website && getScreenshotUrl(organization.website)"
                                    :src="getScreenshotUrl(organization.website)"
                                    :alt="`Screenshot of ${organization.name} website`"
                                    class="absolute inset-0 h-full w-full object-cover opacity-90 transition-opacity duration-300 hover:opacity-100"
                                    @error="(e) => (e.target.style.display = 'none')"
                                />
                                <div v-else class="absolute inset-0 flex items-center justify-center text-neutral-500">
                                    <div class="text-center">
                                        <div class="mb-2 text-4xl">🌐</div>
                                        <div class="text-sm font-medium">No Website</div>
                                    </div>
                                </div>
                                <div
                                    v-if="organization.website"
                                    class="absolute right-3 top-3 inline-flex items-center rounded-full bg-black/60 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white shadow-sm"
                                >
                                    Visit Site
                                </div>
                            </div>

                            <div class="space-y-4 p-5">
                                <div class="flex items-start justify-between">
                                    <h3 class="text-lg font-semibold leading-tight text-neutral-900">
                                        {{ organization.name }}
                                    </h3>
                                    <div v-if="organization.score" class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-2 py-1 text-xs font-medium text-neutral-600">
                                        <span class="text-yellow-500">★</span>
                                        <span class="ml-1">{{ organization.score }}</span>
                                        <span v-if="organization.reviews !== null && organization.reviews !== undefined" class="ml-2 text-[10px] text-neutral-500">
                                            ({{ organization.reviews }})
                                        </span>
                                    </div>
                                </div>

                                <div v-if="organization.category" class="text-sm font-medium text-neutral-600">
                                    {{ organization.category.name }}
                                </div>

                                <div v-if="organization.city || organization.state" class="text-sm text-neutral-500">
                                    {{ [organization.city, organization.state].filter(Boolean).join(', ') }}
                                </div>

                                <div v-if="organization.phone" class="text-sm text-neutral-600">
                                    {{ organization.phone }}
                                </div>

                                <div v-if="organization.website" class="text-sm">
                                    <a
                                        :href="formatWebsite(organization.website)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-neutral-700 underline underline-offset-4 transition-colors hover:text-neutral-900"
                                    >
                                        {{ organization.website }}
                                    </a>
                                </div>

                                <div v-if="organization.website" class="space-y-2">
                                    <label class="block text-xs font-medium uppercase tracking-wide text-neutral-500">Website Rating</label>
                                    <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-100 p-1">
                                        <button
                                            class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                            :class="
                                                organization.website_rating === 'good'
                                                    ? 'bg-green-600 text-white shadow-sm'
                                                    : 'bg-white text-neutral-700 hover:bg-green-50'
                                            "
                                            @click="updateWebsiteRating(organization.id, 'good')"
                                        >
                                            Good
                                        </button>
                                        <button
                                            class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                            :class="
                                                organization.website_rating === 'okay'
                                                    ? 'bg-yellow-500 text-white shadow-sm'
                                                    : 'bg-white text-neutral-700 hover:bg-yellow-50'
                                            "
                                            @click="updateWebsiteRating(organization.id, 'okay')"
                                        >
                                            Okay
                                        </button>
                                        <button
                                            class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                            :class="
                                                organization.website_rating === 'bad'
                                                    ? 'bg-red-600 text-white shadow-sm'
                                                    : 'bg-white text-neutral-700 hover:bg-red-50'
                                            "
                                            @click="updateWebsiteRating(organization.id, 'bad')"
                                        >
                                            Bad
                                        </button>
                                    </div>
                                    <button
                                        class="text-xs font-medium text-neutral-500 underline underline-offset-4 transition-colors hover:text-neutral-700"
                                        @click="updateWebsiteRating(organization.id, null)"
                                    >
                                        Clear
                                    </button>
                                </div>

                                <div class="flex items-center gap-2 pt-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="flex-1 rounded-full border-neutral-200 text-neutral-700 hover:bg-neutral-100"
                                        @click="openSidebar('view', organization.id)"
                                    >
                                        View Details
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full border-neutral-200 text-neutral-700 hover:bg-neutral-100"
                                        @click="openSidebar('edit', organization.id)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full border-red-200 text-red-600 hover:bg-red-50"
                                        @click="deleteOrganization(organization.id)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Pagination
                        :pagination="organizationStore.pagination"
                        @page-change="handlePageChange"
                        class="mt-6 rounded-full border border-neutral-200 bg-white/70 px-5 py-3 shadow-sm"
                    />
                </div>
            </div>
        </div>

        <!-- Right-side drawer for view/edit -->
        <RightDrawer
            :title="sidebarMode === 'edit' ? 'Edit Organization' : 'Organization Details'"
            :model-value="isDrawerOpen"
            @update:modelValue="
                (v) => {
                    if (!v) closeSidebar()
                }
            "
            @close="closeSidebar"
        >
            <div v-if="isDrawerOpen">
                <div v-if="sidebarMode === 'view'" class="h-full">
                    <OrganizationDetails :organization-id="Number(sidebarOrgId)" />
                    <div class="flex justify-end border-t border-neutral-100 bg-neutral-50/80 px-5 py-4">
                        <Button variant="outline" @click="openSidebar('edit', sidebarOrgId)">Edit</Button>
                    </div>
                </div>
                <div v-else-if="sidebarMode === 'edit'" class="h-full flex flex-col">
                    <div class="flex-1 overflow-y-auto p-4">
                        <OrganizationForm
                            ref="editFormRef"
                            :organization="selectedOrganization || {}"
                            :is-loading="organizationStore.currentLoading"
                            :show-actions="false"
                            @submit="handleEditSubmit"
                        />
                    </div>
                    <div class="flex justify-end gap-2 border-t border-neutral-100 bg-neutral-50/80 px-5 py-4">
                        <Button variant="outline" @click="openSidebar('view', sidebarOrgId)">Cancel</Button>
                        <Button @click="editFormRef?.submitForm?.()">Save</Button>
                    </div>
                </div>
            </div>
        </RightDrawer>
    </DefaultLayout>
</template>
