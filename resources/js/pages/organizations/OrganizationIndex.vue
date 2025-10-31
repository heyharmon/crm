<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import TwoColumnLayout from '@/layouts/TwoColumnLayout.vue'
import Button from '@/components/ui/Button.vue'
import OrganizationFilters from '@/components/organizations/OrganizationFilters.vue'
import api from '@/services/api'
import RightDrawer from '@/components/ui/RightDrawer.vue'
import OrganizationForm from '@/components/organizations/OrganizationForm.vue'
import OrganizationDetails from '@/components/organizations/OrganizationDetails.vue'
import OrganizationTableView from '@/components/organizations/OrganizationTableView.vue'
import OrganizationGridView from '@/components/organizations/OrganizationGridView.vue'
import { useOrganizationSelection } from '@/composables/useOrganizationSelection'

const organizationStore = useOrganizationStore()
const route = useRoute()
const router = useRouter()
const ratingOptions = ref([])
const { selectedIds, selectedCount, allVisibleSelected, isIndeterminate, toggleRow, toggleAllVisible, clearSelection } = useOrganizationSelection(
    computed(() => organizationStore.organizations)
)
const batchActionLoading = ref(null)

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
            country: toStr(q.country),
            category: toStr(q.category),
            cms: toStr(q.cms),
            website: toStr(q.website),
            last_redesign: toStr(q.last_redesign),
            website_rating: toStr(q.website_rating),
            website_status: toArr(q.website_status),
            sort: toArr(q.sort),
            assets_min: toStr(q.assets_min),
            assets_max: toStr(q.assets_max),
            asset_growth_min: toStr(q.asset_growth_min),
            asset_growth_max: toStr(q.asset_growth_max)
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
    delete q.country
    delete q.category
    delete q.cms
    delete q.website
    delete q.last_redesign
    delete q.website_rating
    delete q.website_status
    delete q.sort
    delete q.page
    delete q.assets_min
    delete q.assets_max
    delete q.asset_growth_min
    delete q.asset_growth_max

    if (filters.search) q.search = filters.search
    if (filters.city) q.city = filters.city
    if (filters.state) q.state = filters.state
    if (filters.country) q.country = filters.country
    if (filters.category) q.category = filters.category
    if (filters.cms) q.cms = filters.cms
    if (filters.website) q.website = filters.website
    if (filters.last_redesign) q.last_redesign = filters.last_redesign
    if (filters.website_rating) q.website_rating = filters.website_rating
    if (Array.isArray(filters.website_status) && filters.website_status.length) q.website_status = [...filters.website_status]
    if (Array.isArray(filters.sort) && filters.sort.length) q.sort = [...filters.sort]
    if (filters.assets_min) q.assets_min = filters.assets_min
    if (filters.assets_max) q.assets_max = filters.assets_max
    if (filters.asset_growth_min) q.asset_growth_min = filters.asset_growth_min
    if (filters.asset_growth_max) q.asset_growth_max = filters.asset_growth_max
    if (page && page > 1) q.page = String(page)
    return q
}

const fetchRatingOptions = async () => {
    try {
        ratingOptions.value = await api.get('/website-rating-options')
    } catch (error) {
        console.error('Failed to load website rating options:', error)
        ratingOptions.value = []
    }
}

onMounted(async () => {
    // Check if there are any query params
    const hasQueryParams = Object.keys(route.query).length > 0

    // Hydrate filters and page from the URL on load
    const { filters, page } = parseFiltersFromQuery(route.query)

    // If no query params exist, set default filters
    if (!hasQueryParams) {
        filters.website_status = ['up']
    }

    // prevent filter watcher from resetting page on initial load
    syncingQuery.value = true
    try {
        if (filters) organizationStore.setFilters(filters)

        // If we applied default filters, update the URL to reflect them
        if (!hasQueryParams) {
            const nextQuery = buildQueryFromFilters(filters, page)
            await router.replace({ query: nextQuery })
        }
    } finally {
        syncingQuery.value = false
    }
    await fetchRatingOptions()
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
        clearSelection()
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
    clearSelection()
    await organizationStore.fetchOrganizations(1)
    mobileFiltersOpen.value = false
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
    clearSelection()
    await organizationStore.fetchOrganizations(page)
}

const handlePerPageChange = async (perPage) => {
    // Reset to page 1 when changing per_page
    const q = buildQueryFromFilters(organizationStore.filters, 1, route.query)
    syncingQuery.value = true
    try {
        await router.replace({ query: q })
    } finally {
        syncingQuery.value = false
    }
    clearSelection()
    await organizationStore.fetchOrganizations(1, perPage)
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
        await api.post('/web-scraper/start', {
            organization_id: organization.id
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
const mobileFiltersOpen = ref(false)
const mobileActionsOpen = ref(false)
watch(
    () => route.query.view,
    (v) => {
        view.value = v === 'grid' ? 'grid' : 'table'
    }
)
watch(view, async (v) => {
    mobileActionsOpen.value = false
    const q = { ...route.query }
    if (v === 'grid') q.view = 'grid'
    else delete q.view
    await router.replace({ query: q })
    if (v !== 'table') {
        clearSelection()
    }
})

watch(mobileActionsOpen, (open) => {
    if (open) {
        mobileFiltersOpen.value = false
    }
})

watch(mobileFiltersOpen, (open) => {
    if (open) {
        mobileActionsOpen.value = false
    }
})

// Grid helpers
const columns = ref(3)
const submitWebsiteRating = async (organizationId, optionId) => {
    try {
        await organizationStore.setWebsiteRating(organizationId, optionId)
    } catch (error) {
        console.error('Error submitting website rating:', error)
    }
}

const clearWebsiteRating = async (organizationId) => {
    try {
        await organizationStore.clearWebsiteRating(organizationId)
    } catch (error) {
        console.error('Error clearing website rating:', error)
    }
}

const detectWebsiteRedesign = async (organization) => {
    if (!organization?.id) return
    try {
        await organizationStore.detectWebsiteRedesign(organization.id)
    } catch (error) {
        console.error('Error queuing redesign detection:', error)
    }
}

const detectWebsiteCms = async (organization) => {
    if (!organization?.id) return

    if (!organization.website) {
        alert('This organization does not have a website to analyze.')
        return
    }

    try {
        const response = await organizationStore.detectOrganizationCms(organization.id)
        const message = response?.message || 'CMS detection queued.'
        alert(message)
    } catch (error) {
        console.error('Error queuing CMS detection:', error)
        const errorMessage = error?.message || 'Failed to queue CMS detection. Please try again.'
        alert(errorMessage)
    }
}

const checkWebsiteStatus = async (organization) => {
    if (!organization?.id) return

    if (!organization.website) {
        alert('This organization does not have a website to check.')
        return
    }

    try {
        const response = await organizationStore.checkWebsiteStatus(organization.id)
        const message = response?.message || 'Website status check queued.'
        alert(message)
    } catch (error) {
        console.error('Error queuing website status check:', error)
        const errorMessage = error?.message || 'Failed to queue website status check. Please try again.'
        alert(errorMessage)
    }
}

// Taller screenshot heights for 1–2 column modes
// Sidebar state synced with route query
const sidebarMode = ref(null) // 'view' | 'edit' | null
const sidebarOrgId = ref(null)
const isDrawerOpen = computed(() => !!sidebarMode.value && !!sidebarOrgId.value)
const filteredTotalLabel = computed(() => {
    const total = organizationStore.pagination?.total
    if (total === null || total === undefined) return null
    return Number.isFinite(total) ? total.toLocaleString() : String(total)
})

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
        const keys = ['search', 'city', 'state', 'category', 'cms', 'sort', 'page']
        const relevantChanged = keys.some((k) => JSON.stringify(q[k]) !== JSON.stringify(prevQ?.[k]))
        if (!relevantChanged) return

        const { filters, page } = parseFiltersFromQuery(q)
        organizationStore.setFilters(filters)
        clearSelection()
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

const handleRowSelection = ({ organization, checked, shiftKey }) => {
    if (!organization?.id) return
    toggleRow(organization.id, checked, { shiftKey })
}

const handleSelectAllRows = (checked) => {
    toggleAllVisible(checked)
}

const runBatchAction = async (actionKey) => {
    if (!selectedIds.value.length || batchActionLoading.value) return
    batchActionLoading.value = actionKey
    try {
        const response = await organizationStore.runBatchOrganizationAction(actionKey, selectedIds.value)
        if (actionKey === 'archive') {
            await organizationStore.fetchOrganizations(organizationStore.pagination.current_page)
        }
        const queued = response?.queued ?? 0
        const skipped = Array.isArray(response?.skipped) ? response.skipped.length : 0
        const fallbackMessages = {
            count_pages: 'Count pages jobs queued.',
            detect_redesign: 'Website redesign detection queued.',
            detect_cms: 'CMS detection queued.',
            check_website_status: 'Website status checks queued.',
            archive: 'Organizations archived.'
        }
        const message = response?.message || fallbackMessages[actionKey] || 'Batch action completed.'
        const details = []
        if (queued) details.push(`${queued} queued`)
        if (skipped) details.push(`${skipped} skipped`)
        alert(details.length ? `${message} (${details.join(', ')})` : message)
        clearSelection()
    } catch (error) {
        const errorMessage = error?.message || 'Failed to run batch action. Please try again.'
        alert(errorMessage)
    } finally {
        batchActionLoading.value = null
    }
}

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
    <TwoColumnLayout>
        <template #sidebar>
            <OrganizationFilters
                :filters="organizationStore.filters"
                :rating-options="ratingOptions"
                @update:filters="organizationStore.setFilters"
                @reset-filters="organizationStore.resetFilters"
                @search="handleSearch"
            />
        </template>

        <div class="flex h-full flex-col min-h-0">
            <div class="border-b border-neutral-200 bg-white px-4 py-3 lg:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h1 class="text-lg font-semibold text-neutral-900 sm:text-xl">
                                Organizations
                                <span v-if="filteredTotalLabel !== null" class="text-xs font-medium text-neutral-500 sm:text-sm">
                                    ({{ filteredTotalLabel }})
                                </span>
                            </h1>
                        </div>
                        <div class="flex items-center gap-2 sm:hidden">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100 focus-visible:outline-offset-2 focus-visible:outline-neutral-400"
                                :aria-expanded="mobileFiltersOpen"
                                @click="mobileFiltersOpen = !mobileFiltersOpen"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.8"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 4l4 6v6l4 2v-8l4-6" />
                                </svg>
                                Filters
                            </button>
                            <div class="relative">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border border-neutral-900 bg-neutral-900 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-neutral-800 focus-visible:outline-offset-2 focus-visible:outline-neutral-500"
                                    :aria-expanded="mobileActionsOpen"
                                    @click="mobileActionsOpen = !mobileActionsOpen"
                                >
                                    Actions
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.084l3.71-3.854a.75.75 0 011.08 1.04l-4.25 4.417a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                                <transition name="scale-fade">
                                    <div
                                        v-if="mobileActionsOpen"
                                        class="absolute right-0 top-11 z-20 min-w-[180px] rounded-xl border border-neutral-200 bg-white p-2 text-sm shadow-xl"
                                    >
                                        <router-link
                                            to="/organizations/import"
                                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-neutral-700 transition hover:bg-neutral-50"
                                            @click="mobileActionsOpen = false"
                                        >
                                            Import
                                            <span class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400">CSV</span>
                                        </router-link>
                                        <router-link
                                            to="/organizations/create"
                                            class="mt-1 flex w-full items-center justify-between rounded-lg bg-neutral-900 px-3 py-2 text-white transition hover:bg-neutral-800"
                                            @click="mobileActionsOpen = false"
                                        >
                                            Add Organization
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </router-link>
                                    </div>
                                </transition>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white p-1">
                            <button
                                class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline-neutral-400 sm:text-sm"
                                :class="view === 'table' ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                                @click="view = 'table'"
                            >
                                Table
                            </button>
                            <button
                                class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline-neutral-400 sm:text-sm"
                                :class="view === 'grid' ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                                @click="view = 'grid'"
                            >
                                Grid
                            </button>
                        </div>
                        <div class="hidden sm:flex sm:items-center sm:gap-3">
                            <router-link to="/organizations/import">
                                <Button
                                    variant="outline"
                                    class="rounded-full border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                                >
                                    Import
                                </Button>
                            </router-link>
                            <router-link to="/organizations/create">
                                <Button class="rounded-full px-4 py-2 text-sm font-medium">Add Organization</Button>
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-b border-neutral-200 bg-white px-4 lg:hidden">
                <transition name="collapse">
                    <div v-if="mobileFiltersOpen" class="py-4">
                        <OrganizationFilters
                            :filters="organizationStore.filters"
                            :rating-options="ratingOptions"
                            @update:filters="organizationStore.setFilters"
                            @reset-filters="
                                () => {
                                    organizationStore.resetFilters()
                                    mobileFiltersOpen = false
                                }
                            "
                            @search="handleSearch"
                        />
                    </div>
                </transition>
            </div>

            <div class="flex flex-1 flex-col bg-white min-h-0">
                <div v-if="organizationStore.listLoading" class="flex flex-1 items-center justify-center">
                    <div class="h-10 w-10 animate-spin rounded-full border-2 border-neutral-200 border-t-neutral-900"></div>
                </div>

                <div v-else-if="organizationStore.error" class="border-l-4 border-red-400 bg-red-50 px-6 py-4 text-sm font-medium text-red-700">
                    {{ organizationStore.error }}
                </div>

                <div v-else class="flex flex-1 flex-col min-h-0">
                    <OrganizationTableView
                        v-if="view === 'table'"
                        :organizations="organizationStore.organizations"
                        :pagination="organizationStore.pagination"
                        :format-website="formatWebsite"
                        selectable
                        :selected-ids="selectedIds"
                        :select-all-checked="allVisibleSelected"
                        :select-all-indeterminate="isIndeterminate"
                        @open-sidebar="({ mode, id }) => openSidebar(mode, id)"
                        @start-web-scraping="startWebScraping"
                        @detect-redesign="detectWebsiteRedesign"
                        @detect-cms="detectWebsiteCms"
                        @check-website-status="checkWebsiteStatus"
                        @delete-organization="deleteOrganization"
                        @toggle-row-selection="handleRowSelection"
                        @toggle-select-all="handleSelectAllRows"
                        @page-change="handlePageChange"
                        @per-page-change="handlePerPageChange"
                    />

                    <OrganizationGridView
                        v-else
                        :organizations="organizationStore.organizations"
                        :pagination="organizationStore.pagination"
                        :columns="columns"
                        :rating-options="ratingOptions"
                        @update:columns="(value) => (columns = value)"
                        @open-sidebar="({ mode, id }) => openSidebar(mode, id)"
                        @delete-organization="deleteOrganization"
                        @update-website-rating="({ id, optionId }) => submitWebsiteRating(id, optionId)"
                        @clear-website-rating="(id) => clearWebsiteRating(id)"
                        @page-change="handlePageChange"
                        @per-page-change="handlePerPageChange"
                    />
                </div>
            </div>
        </div>

        <Teleport to="body">
            <transition name="selection-actions">
                <div v-if="view === 'table' && selectedCount" class="fixed bottom-6 left-4 right-4 z-40 sm:left-auto sm:right-6 sm:w-auto">
                    <div class="rounded-2xl border border-neutral-200 bg-white px-4 py-4 shadow-xl sm:px-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-1 text-neutral-900">
                                <p class="text-sm font-semibold">{{ selectedCount }} selected</p>
                                <button
                                    type="button"
                                    class="text-xs font-medium text-neutral-500 underline decoration-neutral-300 underline-offset-4 transition hover:text-neutral-900 hover:decoration-neutral-400"
                                    @click="clearSelection"
                                >
                                    Clear selection
                                </button>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-none"
                                    :disabled="batchActionLoading === 'count_pages'"
                                    @click="runBatchAction('count_pages')"
                                >
                                    <span v-if="batchActionLoading === 'count_pages'">Counting...</span>
                                    <span v-else>Count pages</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-none"
                                    :disabled="batchActionLoading === 'detect_redesign'"
                                    @click="runBatchAction('detect_redesign')"
                                >
                                    <span v-if="batchActionLoading === 'detect_redesign'">Queuing...</span>
                                    <span v-else>Detect redesign</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-none"
                                    :disabled="batchActionLoading === 'detect_cms'"
                                    @click="runBatchAction('detect_cms')"
                                >
                                    <span v-if="batchActionLoading === 'detect_cms'">Queuing...</span>
                                    <span v-else>Detect CMS</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-none"
                                    :disabled="batchActionLoading === 'check_website_status'"
                                    @click="runBatchAction('check_website_status')"
                                >
                                    <span v-if="batchActionLoading === 'check_website_status'">Queuing...</span>
                                    <span v-else>Check website status</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-none hover:bg-red-600 hover:text-white"
                                    :disabled="batchActionLoading === 'archive'"
                                    @click="runBatchAction('archive')"
                                >
                                    <span v-if="batchActionLoading === 'archive'">Archiving...</span>
                                    <span v-else>Archive</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

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
    </TwoColumnLayout>
</template>

<style scoped>
.collapse-enter-active,
.collapse-leave-active {
    transition: max-height 0.25s ease, opacity 0.2s ease;
}
.collapse-enter-from,
.collapse-leave-to {
    max-height: 0;
    opacity: 0;
}
.collapse-enter-to,
.collapse-leave-from {
    max-height: 2000px;
    opacity: 1;
}
.scale-fade-enter-active,
.scale-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.2s ease;
    transform-origin: top right;
}
.scale-fade-enter-from,
.scale-fade-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

.selection-actions-enter-active,
.selection-actions-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.selection-actions-enter-from,
.selection-actions-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
.selection-actions-enter-to,
.selection-actions-leave-from {
    opacity: 1;
    transform: translateY(0);
}
</style>
