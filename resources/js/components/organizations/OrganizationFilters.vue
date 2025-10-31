<script setup>
import { computed } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { useNumberFormat } from '@/composables/useNumberFormat'

const props = defineProps({
    filters: {
        type: Object,
        required: true
    },
    ratingOptions: {
        type: Array,
        default: () => []
    }
})

const WEBSITE_STATUS_OPTIONS = [
    { value: 'up', label: 'Up' },
    { value: 'down', label: 'Down' },
    { value: 'redirected', label: 'Redirected' },
    { value: 'unknown', label: 'Unknown' }
]

const LAST_REDESIGN_OPTIONS = [
    { value: 'has_date', label: 'Has date' },
    { value: 'cant_predict', label: "Can't predict" },
    { value: 'no_snapshots', label: 'No snapshots' },
    { value: 'request_failed', label: 'Request failed' }
]

const emit = defineEmits(['update:filters', 'reset-filters', 'search'])

const updateFilter = (key, value) => {
    emit('update:filters', { [key]: value })
}

// Use number formatting for assets fields
const assetsMin = useNumberFormat(props, updateFilter, 'assets_min')
const assetsMax = useNumberFormat(props, updateFilter, 'assets_max')

const setSweetSpot = () => {
    assetsMin.handleInput('400000000')
    assetsMax.handleInput('2000000000')
}

const setWebsiteFilter = (value) => {
    // Clicking the active option should revert to "any"
    const next = props.filters.website === value ? '' : value
    emit('update:filters', { website: next })
}

const setWebsiteRatingFilter = (value) => {
    const next = props.filters.website_rating === value ? '' : value
    emit('update:filters', { website_rating: next })
}

const setLastRedesignFilter = (value) => {
    const next = props.filters.last_redesign === value ? '' : value
    emit('update:filters', { last_redesign: next })
}

const toggleWebsiteStatus = (value) => {
    const current = Array.isArray(props.filters.website_status) ? [...props.filters.website_status] : []
    const index = current.indexOf(value)
    if (index >= 0) {
        current.splice(index, 1)
    } else {
        current.push(value)
    }
    emit('update:filters', { website_status: current })
}

const hasWebsiteStatus = (value) => Array.isArray(props.filters.website_status) && props.filters.website_status.includes(value)

const handleSearch = () => {
    emit('search')
}

const resetFilters = () => {
    emit('reset-filters')
}

const hasActiveFilters = computed(() => {
    const filterEntries = Object.entries(props.filters || {})
    return filterEntries.some(([key, value]) => {
        if (['sort', 'website_status'].includes(key)) return Array.isArray(value) && value.length > 0
        return Boolean(value)
    })
})

// Multi-sort: maintain an ordered list of "field:direction" in filters.sort
const parseSort = (entry) => {
    if (!entry || typeof entry !== 'string') return { field: '', direction: 'desc' }
    const [field, dir] = entry.split(':')
    return { field, direction: dir === 'asc' ? 'asc' : 'desc' }
}

const getSortList = () => (Array.isArray(props.filters.sort) ? [...props.filters.sort] : [])

const handleSort = (column) => {
    const list = getSortList()
    const idx = list.findIndex((s) => parseSort(s).field === column)

    if (idx >= 0) {
        // Cycle: desc -> asc -> off
        const { direction } = parseSort(list[idx])
        if (direction === 'desc') {
            list[idx] = `${column}:asc`
        } else {
            // remove to disable sorting on third click
            list.splice(idx, 1)
        }
    } else {
        // First click should add with descending by default
        list.push(`${column}:desc`)
    }

    emit('update:filters', { sort: list })
}

const getSortIcon = (column) => {
    const list = getSortList()
    const item = list.find((s) => parseSort(s).field === column)
    if (!item) return ''
    return parseSort(item).direction === 'asc' ? '↑' : '↓'
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Filters</p>
            <Button v-if="hasActiveFilters" @click="resetFilters" size="sm" variant="link"> Clear Filters </Button>
        </div>

        <div class="space-y-3">
            <label class="block text-xs font-medium uppercase tracking-wide text-neutral-500">Search</label>
            <Input
                :model-value="filters.search"
                @update:model-value="updateFilter('search', $event)"
                placeholder="Search organizations..."
                @keyup.enter="handleSearch"
            />
        </div>

        <div class="space-y-6">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">City</label>
                    <Input :model-value="filters.city" @update:model-value="updateFilter('city', $event)" placeholder="Filter by city" />
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">State</label>
                    <Input :model-value="filters.state" @update:model-value="updateFilter('state', $event)" placeholder="Filter by state" />
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">Country</label>
                    <Input :model-value="filters.country" @update:model-value="updateFilter('country', $event)" placeholder="Filter by country" />
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">Category</label>
                    <Input :model-value="filters.category" @update:model-value="updateFilter('category', $event)" placeholder="Filter by category" />
                </div>
                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">CMS</label>
                    <Input :model-value="filters.cms" @update:model-value="updateFilter('cms', $event)" placeholder="Filter by CMS" />
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-xs font-medium uppercase tracking-wide text-neutral-500">Assets</label>
                        <button
                            type="button"
                            @click="setSweetSpot"
                            class="rounded bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600 transition hover:bg-neutral-200 hover:text-neutral-800"
                        >
                            Preset: Sweet Spot
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <Input :model-value="assetsMin.displayValue.value" @update:model-value="assetsMin.handleInput" type="text" placeholder="Min" />
                        <Input :model-value="assetsMax.displayValue.value" @update:model-value="assetsMax.handleInput" type="text" placeholder="Max" />
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">Asset Growth (%)</label>
                    <div class="grid grid-cols-2 gap-2">
                        <Input
                            :model-value="filters.asset_growth_min"
                            @update:model-value="updateFilter('asset_growth_min', $event)"
                            type="number"
                            step="0.01"
                            placeholder="Min"
                        />
                        <Input
                            :model-value="filters.asset_growth_max"
                            @update:model-value="updateFilter('asset_growth_max', $event)"
                            type="number"
                            step="0.01"
                            placeholder="Max"
                        />
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">Website</label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :variant="!filters.website ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="updateFilter('website', '')"
                        >
                            Any
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website === 'present' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="setWebsiteFilter('present')"
                        >
                            Has Website
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website === 'missing' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="setWebsiteFilter('missing')"
                        >
                            No Website
                        </Button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">Last Redesign</label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :variant="!filters.last_redesign ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="updateFilter('last_redesign', '')"
                        >
                            Any
                        </Button>
                        <Button
                            v-for="option in LAST_REDESIGN_OPTIONS"
                            :key="option.value"
                            size="sm"
                            :variant="filters.last_redesign === option.value ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="setLastRedesignFilter(option.value)"
                        >
                            {{ option.label }}
                        </Button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">Website Rating</label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :variant="!filters.website_rating ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="updateFilter('website_rating', '')"
                        >
                            Any
                        </Button>
                        <Button
                            v-for="option in ratingOptions"
                            :key="option.id"
                            size="sm"
                            :variant="filters.website_rating === option.slug ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="setWebsiteRatingFilter(option.slug)"
                        >
                            {{ option.name }}
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website_rating === 'none' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="setWebsiteRatingFilter('none')"
                        >
                            Not Rated
                        </Button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-neutral-500">Website Status</label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :variant="!Array.isArray(filters.website_status) || !filters.website_status.length ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="updateFilter('website_status', [])"
                        >
                            Any
                        </Button>
                        <Button
                            v-for="option in WEBSITE_STATUS_OPTIONS"
                            :key="option.value"
                            size="sm"
                            :variant="hasWebsiteStatus(option.value) ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                            @click="toggleWebsiteStatus(option.value)"
                        >
                            {{ option.label }}
                        </Button>
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-3 block text-xs font-medium uppercase tracking-wide text-neutral-500">Sort By</label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        @click="handleSort('name')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('name:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Name {{ getSortIcon('name') }}
                    </Button>
                    <Button
                        @click="handleSort('category')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('category:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Category {{ getSortIcon('category') }}
                    </Button>
                    <Button
                        @click="handleSort('cms')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('cms:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        CMS {{ getSortIcon('cms') }}
                    </Button>
                    <Button
                        @click="handleSort('city')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('city:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Location {{ getSortIcon('city') }}
                    </Button>
                    <Button
                        @click="handleSort('score')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('score:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Score {{ getSortIcon('score') }}
                    </Button>
                    <Button
                        @click="handleSort('reviews')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('reviews:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Reviews {{ getSortIcon('reviews') }}
                    </Button>
                    <Button
                        @click="handleSort('assets')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('assets:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Assets {{ getSortIcon('assets') }}
                    </Button>
                    <Button
                        @click="handleSort('asset_growth')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('asset_growth:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Asset Growth {{ getSortIcon('asset_growth') }}
                    </Button>
                    <Button
                        @click="handleSort('website_rating')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('website_rating:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Website Rating {{ getSortIcon('website_rating') }}
                    </Button>
                    <Button
                        @click="handleSort('website_rating_weighted')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('website_rating_weighted:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Weighted Rating {{ getSortIcon('website_rating_weighted') }}
                    </Button>
                    <Button
                        @click="handleSort('website_status')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('website_status:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs"
                    >
                        Website Status {{ getSortIcon('website_status') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
