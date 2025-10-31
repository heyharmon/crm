<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import Pagination from '@/components/ui/Pagination.vue'
import { getRatingLabel, getRatingPillClasses } from '@/utils/ratingStyles'
import { formatDisplayDate } from '@/utils/date'
import { formatWebsiteStatus, getWebsiteStatusClasses } from '@/utils/websiteStatus'

const props = defineProps({
    organizations: {
        type: Array,
        default: () => []
    },
    pagination: {
        type: Object,
        required: true
    },
    formatWebsite: {
        type: Function,
        required: true
    },
    selectable: {
        type: Boolean,
        default: false
    },
    selectedIds: {
        type: Array,
        default: () => []
    },
    selectAllChecked: {
        type: Boolean,
        default: false
    },
    selectAllIndeterminate: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits([
    'open-sidebar',
    'start-web-scraping',
    'delete-organization',
    'check-website-status',
    'detect-redesign',
    'detect-cms',
    'page-change',
    'toggle-row-selection',
    'toggle-select-all'
])
const openMenuId = ref(null)
const toggleMenu = (organizationId) => {
    openMenuId.value = openMenuId.value === organizationId ? null : organizationId
}
const closeMenu = () => {
    openMenuId.value = null
}
const handleDocumentClick = () => {
    closeMenu()
}
const handleEdit = (organizationId) => {
    emit('open-sidebar', { mode: 'edit', id: organizationId })
    closeMenu()
}
const handleScrape = (organization) => {
    emit('start-web-scraping', organization)
    closeMenu()
}
const handleArchive = (organizationId) => {
    emit('delete-organization', organizationId)
    closeMenu()
}
const handleDetectRedesign = (organization) => {
    emit('detect-redesign', organization)
    closeMenu()
}
const handleDetectCms = (organization) => {
    emit('detect-cms', organization)
    closeMenu()
}
const handleCheckWebsiteStatus = (organization) => {
    emit('check-website-status', organization)
    closeMenu()
}

const headerCheckbox = ref(null)

watch(
    () => props.selectAllIndeterminate,
    (value) => {
        if (headerCheckbox.value) {
            headerCheckbox.value.indeterminate = !!value
        }
    },
    { immediate: true }
)

watch(
    () => headerCheckbox.value,
    (checkbox) => {
        if (checkbox) {
            checkbox.indeterminate = !!props.selectAllIndeterminate
        }
    }
)

const rowIsSelected = (organizationId) => {
    const normalized = Number(organizationId)
    if (!Number.isFinite(normalized)) return false
    return props.selectedIds.includes(normalized)
}
onMounted(() => {
    if (typeof document !== 'undefined') {
        document.addEventListener('click', handleDocumentClick)
    }
})
onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('click', handleDocumentClick)
    }
})

const formatRatingSummary = (slug) => getRatingLabel(slug)
const ratingSummaryClasses = (slug) => getRatingPillClasses(slug)

const formatAverage = (value) => {
    if (value === null || value === undefined) return null
    return Number(value).toFixed(2)
}

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '—'
    const numeric = Number(value)
    if (!Number.isFinite(numeric)) return '—'
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(numeric)
}

const formatPercent = (value) => {
    if (value === null || value === undefined) return '—'
    const numeric = Number(value)
    if (!Number.isFinite(numeric)) return '—'
    return `${numeric.toFixed(2)}%`
}

const formatPagesCount = (organization) => {
    if (!organization?.website) return '-'
    const count = organization.pages_count
    if (count === null || count === undefined) return '—'
    const numericCount = Number(count)
    if (!Number.isFinite(numericCount)) return '—'
    return numericCount === 0 ? '—' : count
}

const REDESIGN_STATUS_META = {
    wayback_failed: {
        label: 'Request failed',
        classes: 'border-red-200 bg-red-50 text-red-700'
    },
    no_wayback_data: {
        label: 'No snapshots',
        classes: 'border-amber-200 bg-amber-50 text-amber-800'
    },
    no_major_events: {
        label: "Can't predict",
        classes: 'border-blue-200 bg-blue-50 text-blue-700'
    }
}

const shouldShowRedesignStatus = (organization) => {
    const status = organization?.website_redesign_status
    if (!status) return false
    return status !== 'success'
}

const redesignStatusLabel = (organization) => {
    if (!organization) return ''
    const status = organization.website_redesign_status
    if (!status) return ''
    return REDESIGN_STATUS_META[status]?.label || 'Wayback issue'
}

const redesignStatusTooltip = (organization) => organization?.website_redesign_status_message || ''

const redesignStatusClasses = (status) => REDESIGN_STATUS_META[status]?.classes ?? 'border-neutral-200 bg-neutral-50 text-neutral-600'

const websiteStatusClasses = (status) => getWebsiteStatusClasses(status)
</script>

<template>
    <div class="flex flex-1 flex-col min-h-0">
        <div class="flex-1 overflow-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                <thead class="bg-neutral-50 text-xs tracking-wide uppercase text-neutral-500">
                    <tr>
                        <th v-if="props.selectable" class="border-b border-neutral-200 px-4 py-3">
                            <input
                                ref="headerCheckbox"
                                type="checkbox"
                                class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-500"
                                :checked="props.selectAllChecked"
                                @change.stop="emit('toggle-select-all', $event.target.checked)"
                            />
                        </th>
                        <th class="border-b border-neutral-200 px-4 py-3">Name</th>
                        <!-- <th class="border-b border-neutral-200 px-4 py-3">Category</th> -->
                        <th class="border-b border-neutral-200 px-4 py-3">Location</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Assets</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Asset Growth</th>
                        <!-- <th class="border-b border-neutral-200 px-4 py-3">Score</th> -->
                        <!-- <th class="border-b border-neutral-200 px-4 py-3">Reviews</th> -->
                        <th class="border-b border-neutral-200 px-4 py-3">Last Redesign</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Website Rating</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Website Status</th>
                        <th class="border-b border-neutral-200 px-4 py-3">CMS</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Pages</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="organization in props.organizations"
                        :key="organization.id"
                        class="cursor-pointer border-b border-neutral-200 transition-colors hover:bg-neutral-50 focus-within:bg-neutral-50"
                        :class="props.selectable && rowIsSelected(organization.id) ? 'bg-neutral-50/80' : ''"
                        @click="emit('open-sidebar', { mode: 'view', id: organization.id })"
                        tabindex="0"
                        @keydown.enter="emit('open-sidebar', { mode: 'view', id: organization.id })"
                        @keydown.space.prevent="emit('open-sidebar', { mode: 'view', id: organization.id })"
                    >
                        <td v-if="props.selectable" class="px-4 py-3 align-top" @click.stop>
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-500"
                                :checked="rowIsSelected(organization.id)"
                                @click.stop="
                                    emit('toggle-row-selection', {
                                        organization,
                                        checked: $event.target.checked,
                                        shiftKey: $event.shiftKey
                                    })
                                "
                            />
                        </td>
                        <td class="px-4 py-3 align-top">
                            <div class="flex items-start gap-3">
                                <img
                                    v-if="organization.banner"
                                    :src="organization.banner"
                                    :alt="organization.name"
                                    class="h-10 w-10 shrink-0 rounded-full border border-neutral-200 object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-dashed border-neutral-200 bg-neutral-100 text-sm font-medium text-neutral-500"
                                >
                                    <span>{{ organization.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-sm font-semibold text-neutral-900">{{ organization.name }}</div>
                                    <a
                                        v-if="organization.website"
                                        :href="props.formatWebsite(organization.website)"
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
                        <!-- <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-neutral-700">
                            {{ organization.category?.name || '-' }}
                        </td> -->
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <div class="font-medium text-neutral-700">{{ organization.state || '-' }}</div>
                            <div class="text-xs text-neutral-500">{{ organization.country || '-' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            {{ formatCurrency(organization.assets) }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            {{ formatPercent(organization.asset_growth) }}
                        </td>
                        <!-- <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <div v-if="organization.score" class="flex items-center gap-1 text-xs font-medium text-neutral-700">
                                <span class="text-yellow-500">★</span>
                                <span>{{ organization.score }}</span>
                            </div>
                            <span v-else>-</span>
                        </td> -->
                        <!-- <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <span v-if="organization.reviews !== null && organization.reviews !== undefined">
                                {{ organization.reviews }}
                            </span>
                            <span v-else>-</span>
                        </td> -->
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <div v-if="shouldShowRedesignStatus(organization)">
                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :title="redesignStatusTooltip(organization) || null"
                                    :class="redesignStatusClasses(organization.website_redesign_status)"
                                >
                                    {{ redesignStatusLabel(organization) }}
                                </span>
                            </div>
                            <span v-else-if="organization.last_major_redesign_at">
                                {{ formatDisplayDate(organization.last_major_redesign_at) }}
                            </span>
                            <span v-else class="text-neutral-400">—</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <div
                                v-if="!organization.website"
                                class="inline-flex items-center rounded-full border border-dashed border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-500"
                            >
                                No Website
                            </div>
                            <div v-else class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="organization.website_rating_summary"
                                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :class="ratingSummaryClasses(organization.website_rating_summary)"
                                    >
                                        {{ formatRatingSummary(organization.website_rating_summary) }}
                                    </span>
                                    <span v-else class="text-xs font-medium text-neutral-400">No ratings yet</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-1 text-xs text-neutral-500">
                                    <span v-if="organization.website_rating_average !== null">
                                        ({{ formatAverage(organization.website_rating_average) }})
                                    </span>
                                    <span v-if="organization.website_rating_count"> {{ organization.website_rating_count }} ratings </span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                                :class="websiteStatusClasses(organization.website_status)"
                            >
                                {{ formatWebsiteStatus(organization.website_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            {{ organization.cms || '—' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            {{ formatPagesCount(organization) }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-neutral-700">
                            <div class="relative flex justify-end" @keydown.escape.stop="closeMenu">
                                <button
                                    class="inline-flex items-center justify-center rounded-full border border-neutral-200 p-2 text-neutral-600 transition hover:border-neutral-300 hover:bg-neutral-50 hover:text-neutral-900 focus-visible:outline-neutral-400"
                                    type="button"
                                    :aria-expanded="openMenuId === organization.id"
                                    @click.stop="toggleMenu(organization.id)"
                                >
                                    <span class="sr-only">Open actions</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                        <circle cx="4" cy="10" r="1.5" />
                                        <circle cx="10" cy="10" r="1.5" />
                                        <circle cx="16" cy="10" r="1.5" />
                                    </svg>
                                </button>
                                <div
                                    v-if="openMenuId === organization.id"
                                    class="absolute right-0 top-10 z-20 w-40 rounded-lg border border-neutral-200 bg-white py-1 shadow-lg"
                                    @click.stop
                                >
                                    <button
                                        class="flex w-full items-center px-3 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50"
                                        type="button"
                                        @click.stop="handleEdit(organization.id)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="organization.website"
                                        class="flex w-full items-center px-3 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50"
                                        type="button"
                                        @click.stop="handleScrape(organization)"
                                    >
                                        Count pages
                                    </button>
                                    <button
                                        v-if="organization.website"
                                        class="flex w-full items-center px-3 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50"
                                        type="button"
                                        @click.stop="handleDetectRedesign(organization)"
                                    >
                                        Detect redesign
                                    </button>
                                    <button
                                        v-if="organization.website"
                                        class="flex w-full items-center px-3 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50"
                                        type="button"
                                        @click.stop="handleDetectCms(organization)"
                                    >
                                        Detect CMS
                                    </button>
                                    <button
                                        v-if="organization.website"
                                        class="flex w-full items-center px-3 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50"
                                        type="button"
                                        @click.stop="handleCheckWebsiteStatus(organization)"
                                    >
                                        Check website status
                                    </button>
                                    <button
                                        class="flex w-full items-center px-3 py-2 text-sm text-red-600 transition hover:bg-red-50"
                                        type="button"
                                        @click.stop="handleArchive(organization.id)"
                                    >
                                        Archive
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 bg-white px-4 py-3">
            <Pagination :pagination="props.pagination" @page-change="emit('page-change', $event)" @per-page-change="emit('per-page-change', $event)" />
        </div>
    </div>
</template>
