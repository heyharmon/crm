<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useOrganizationSelection } from '@/composables/useOrganizationSelection'
import { useQueryFilters } from '@/composables/useQueryFilters'
import { useSidebar } from '@/composables/useSidebar'
import { useOrganizationActions } from '@/composables/useOrganizationActions'
import { useMobileUI } from '@/composables/useMobileUI'
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
const categories = ref([])

// Selection composable
const { selectedIds, selectedCount, allVisibleSelected, isIndeterminate, toggleRow, toggleAllVisible, clearSelection } = useOrganizationSelection(
    computed(() => organizationStore.organizations)
)

// Query/Filters composable
const { initializeFilters, handleSearch, handlePageChange, handlePerPageChange } = useQueryFilters(organizationStore, {
    onClearSelection: clearSelection
})

// Sidebar composable
const { sidebarMode, sidebarOrgId, isDrawerOpen, selectedOrganization, openSidebar, closeSidebar, handleEditSubmit } = useSidebar(organizationStore)

// Organization actions composable
const {
    deleteOrganization,
    startWebScraping,
    detectWebsiteRedesign,
    detectWebsiteCms,
    checkWebsiteStatus,
    submitWebsiteRating,
    clearWebsiteRating,
    formatWebsite
} = useOrganizationActions(organizationStore)

// Mobile UI composable
const { mobileFiltersOpen, mobileActionsOpen } = useMobileUI()

// Batch actions
const batchActionLoading = ref(null)
const batchCategoryId = ref(null)

const runBatchAction = async (actionKey, payload = {}) => {
    if (!selectedIds.value.length || batchActionLoading.value) return
    batchActionLoading.value = actionKey
    try {
        const response = await organizationStore.runBatchOrganizationAction(actionKey, selectedIds.value, payload)
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
        if (actionKey === 'set_category') {
            batchCategoryId.value = null
        }
    } catch (error) {
        const errorMessage = error?.message || 'Failed to run batch action. Please try again.'
        alert(errorMessage)
    } finally {
        batchActionLoading.value = null
    }
}

const handleBatchCategoryUpdate = () => {
    if (batchCategoryId.value === null) {
        alert('Please select a category first.')
        return
    }
    runBatchAction('set_category', { category_id: batchCategoryId.value })
}

const handleInlineCategoryUpdate = async ({ organizationId, categoryId }) => {
    try {
        const response = await organizationStore.updateOrganization(organizationId, {
            organization_category_id: categoryId
        })

        // Update the organization in the local list to avoid full reload
        const orgIndex = organizationStore.organizations.findIndex((org) => org.id === organizationId)
        if (orgIndex !== -1) {
            organizationStore.organizations[orgIndex] = {
                ...organizationStore.organizations[orgIndex],
                organization_category_id: categoryId,
                category: categoryId ? categories.value.find((cat) => cat.id === categoryId) : null
            }
        }
    } catch (error) {
        alert(error?.message || 'Failed to update category.')
    }
}

// View toggle (table/grid)
const view = ref(route.query.view === 'grid' ? 'grid' : 'table')
const columns = ref(3)

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

// Computed
const filteredTotalLabel = computed(() => {
    const total = organizationStore.pagination?.total
    if (total === null || total === undefined) return null
    return Number.isFinite(total) ? total.toLocaleString() : String(total)
})

// Event handlers
const handleRowSelection = ({ organization, checked, shiftKey }) => {
    if (!organization?.id) return
    toggleRow(organization.id, checked, { shiftKey })
}

const handleSelectAllRows = (checked) => {
    toggleAllVisible(checked)
}

const fetchRatingOptions = async () => {
    try {
        ratingOptions.value = await api.get('/website-rating-options')
    } catch (error) {
        console.error('Failed to load website rating options:', error)
        ratingOptions.value = []
    }
}

const fetchCategories = async () => {
    try {
        categories.value = await api.get('/organization-categories')
    } catch (error) {
        console.error('Failed to load categories:', error)
        categories.value = []
    }
}

// Ref for calling submit from the drawer footer
const editFormRef = ref(null)

onMounted(async () => {
    await initializeFilters()
    await fetchRatingOptions()
    await fetchCategories()
})
</script>

<template>
    <TwoColumnLayout>
        <template #sidebar>
            <OrganizationFilters
                :filters="organizationStore.filters"
                :rating-options="ratingOptions"
                @update:filters="organizationStore.setFilters"
                @reset-filters="organizationStore.resetFilters"
                @search="handleSearch(() => (mobileFiltersOpen = false))"
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
                            @search="handleSearch(() => (mobileFiltersOpen = false))"
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
                        selectable
                        :organizations="organizationStore.organizations"
                        :pagination="organizationStore.pagination"
                        :format-website="formatWebsite"
                        :selected-ids="selectedIds"
                        :select-all-checked="allVisibleSelected"
                        :select-all-indeterminate="isIndeterminate"
                        :categories="categories"
                        @open-sidebar="({ mode, id }) => openSidebar(mode, id)"
                        @start-web-scraping="startWebScraping"
                        @detect-redesign="detectWebsiteRedesign"
                        @detect-cms="detectWebsiteCms"
                        @check-website-status="checkWebsiteStatus"
                        @delete-organization="deleteOrganization"
                        @toggle-row-selection="handleRowSelection"
                        @toggle-select-all="handleSelectAllRows"
                        @update-category="handleInlineCategoryUpdate"
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
                    <div class="rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-4 shadow-xl sm:px-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-1 text-white">
                                <p class="text-sm font-semibold">{{ selectedCount }} selected</p>
                                <button
                                    type="button"
                                    class="text-xs font-medium text-neutral-400 underline decoration-neutral-600 underline-offset-4 transition hover:text-neutral-200 hover:decoration-neutral-400"
                                    @click="clearSelection"
                                >
                                    Clear selection
                                </button>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="batchCategoryId"
                                        class="rounded-full border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-none focus:border-neutral-300 focus:outline-none focus:ring-1 focus:ring-neutral-300"
                                    >
                                        <option :value="null">Select category...</option>
                                        <option :value="null">None</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                    <Button
                                        variant="outline"
                                        class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-none"
                                        :disabled="batchActionLoading === 'set_category'"
                                        @click="handleBatchCategoryUpdate"
                                    >
                                        <span v-if="batchActionLoading === 'set_category'">Setting...</span>
                                        <span v-else>Set Category</span>
                                    </Button>
                                </div>
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
                    <OrganizationDetails :organization-id="Number(sidebarOrgId)" @edit="openSidebar('edit', sidebarOrgId)" />
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
