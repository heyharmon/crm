<script setup>
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const props = defineProps({
    filters: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['update:filters', 'reset-filters', 'search'])

const showFilters = ref(true)

const updateFilter = (key, value) => {
    emit('update:filters', { [key]: value })
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

const handleSearch = () => {
    emit('search')
}

const resetFilters = () => {
    emit('reset-filters')
}

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
        <div class="space-y-3">
            <label class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">Search</label>
            <Input
                :model-value="filters.search"
                @update:model-value="updateFilter('search', $event)"
                placeholder="Search organizations..."
                @keyup.enter="handleSearch"
            />
            <div class="flex flex-wrap gap-2 pt-2">
                <Button @click="showFilters = !showFilters" variant="outline" class="rounded-full border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100">
                    {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                </Button>
                <Button @click="resetFilters" variant="outline" class="rounded-full border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100">
                    Clear Filters
                </Button>
            </div>
        </div>

        <div v-if="showFilters" class="space-y-6">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-500">City</label>
                    <Input :model-value="filters.city" @update:model-value="updateFilter('city', $event)" placeholder="Filter by city" />
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-500">State</label>
                    <Input :model-value="filters.state" @update:model-value="updateFilter('state', $event)" placeholder="Filter by state" />
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Category</label>
                    <Input :model-value="filters.category" @update:model-value="updateFilter('category', $event)" placeholder="Filter by category" />
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Website</label>
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" :variant="!filters.website ? 'default' : 'outline'" class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold" @click="updateFilter('website', '')">
                            Any
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website === 'present' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                            @click="setWebsiteFilter('present')"
                        >
                            Has Website
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website === 'missing' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                            @click="setWebsiteFilter('missing')"
                        >
                            No Website
                        </Button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Website Rating</label>
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" :variant="!filters.website_rating ? 'default' : 'outline'" class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold" @click="updateFilter('website_rating', '')">
                            Any
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website_rating === 'good' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                            @click="setWebsiteRatingFilter('good')"
                        >
                            Good
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website_rating === 'okay' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                            @click="setWebsiteRatingFilter('okay')"
                        >
                            Okay
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website_rating === 'bad' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                            @click="setWebsiteRatingFilter('bad')"
                        >
                            Bad
                        </Button>
                        <Button
                            size="sm"
                            :variant="filters.website_rating === 'none' ? 'default' : 'outline'"
                            class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                            @click="setWebsiteRatingFilter('none')"
                        >
                            Not Rated
                        </Button>
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-3 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Sort By</label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        @click="handleSort('name')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('name:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                    >
                        Name {{ getSortIcon('name') }}
                    </Button>
                    <Button
                        @click="handleSort('category')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('category:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                    >
                        Category {{ getSortIcon('category') }}
                    </Button>
                    <Button
                        @click="handleSort('city')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('city:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                    >
                        Location {{ getSortIcon('city') }}
                    </Button>
                    <Button
                        @click="handleSort('score')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('score:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                    >
                        Score {{ getSortIcon('score') }}
                    </Button>
                    <Button
                        @click="handleSort('reviews')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('reviews:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                    >
                        Reviews {{ getSortIcon('reviews') }}
                    </Button>
                    <Button
                        @click="handleSort('website_rating')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('website_rating:')) ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full border-neutral-200 px-3 py-1 text-xs font-semibold"
                    >
                        Website Rating {{ getSortIcon('website_rating') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
