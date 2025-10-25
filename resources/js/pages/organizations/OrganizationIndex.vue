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

const organizationStore = useOrganizationStore()
const route = useRoute()
const router = useRouter()
const ratingOptions = ref([])

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

const fetchRatingOptions = async () => {
    try {
        ratingOptions.value = await api.get('/website-rating-options')
    } catch (error) {
        console.error('Failed to load website rating options:', error)
        ratingOptions.value = []
    }
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
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <h1 class="text-xl font-semibold text-neutral-900">
                        Organizations
                        <span v-if="filteredTotalLabel !== null" class="text-sm font-normal text-neutral-500">({{ filteredTotalLabel }})</span>
                    </h1>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white p-1">
                            <button
                                class="rounded-full px-3 py-1 text-sm font-medium text-neutral-600 transition-colors focus-visible:outline-neutral-400"
                                :class="view === 'table' ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                                @click="view = 'table'"
                            >
                                Table
                            </button>
                            <button
                                class="rounded-full px-3 py-1 text-sm font-medium text-neutral-600 transition-colors focus-visible:outline-neutral-400"
                                :class="view === 'grid' ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                                @click="view = 'grid'"
                            >
                                Grid
                            </button>
                        </div>
                        <router-link to="/organizations/import">
                            <Button
                                variant="outline"
                                class="rounded-full border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                            >
                                Import from Google Maps
                            </Button>
                        </router-link>
                        <router-link to="/organizations/create">
                            <Button class="rounded-full px-4 py-2 text-sm font-medium">Add Organization</Button>
                        </router-link>
                    </div>
                </div>
            </div>

            <div class="border-b border-neutral-200 bg-white px-4 py-4 lg:hidden">
                <OrganizationFilters
                    :filters="organizationStore.filters"
                    :rating-options="ratingOptions"
                    @update:filters="organizationStore.setFilters"
                    @reset-filters="organizationStore.resetFilters"
                    @search="handleSearch"
                />
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
                        @open-sidebar="({ mode, id }) => openSidebar(mode, id)"
                        @start-web-scraping="startWebScraping"
                        @detect-redesign="detectWebsiteRedesign"
                        @delete-organization="deleteOrganization"
                        @page-change="handlePageChange"
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
    </TwoColumnLayout>
</template>
