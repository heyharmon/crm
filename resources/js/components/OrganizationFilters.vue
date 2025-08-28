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
    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-4 mb-6">
        <div class="flex flex-wrap gap-4 items-center">
            <div class="flex-1 min-w-64">
                <Input
                    :model-value="filters.search"
                    @update:model-value="updateFilter('search', $event)"
                    placeholder="Search organizations..."
                    @keyup.enter="handleSearch"
                />
            </div>

            <Button @click="showFilters = !showFilters" variant="outline">
                {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
            </Button>

            <Button @click="resetFilters" variant="outline"> Clear Filters </Button>
        </div>

        <div v-if="showFilters" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">City</label>
                    <Input :model-value="filters.city" @update:model-value="updateFilter('city', $event)" placeholder="Filter by city" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">State</label>
                    <Input :model-value="filters.state" @update:model-value="updateFilter('state', $event)" placeholder="Filter by state" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Category</label>
                    <Input :model-value="filters.category" @update:model-value="updateFilter('category', $event)" placeholder="Filter by category" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Sort By</label>
                <div class="flex flex-wrap gap-2">
                    <Button @click="handleSort('name')" :variant="(filters.sort || []).some((s) => s.startsWith('name:')) ? 'default' : 'outline'" size="sm">
                        Name {{ getSortIcon('name') }}
                    </Button>
                    <Button
                        @click="handleSort('category')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('category:')) ? 'default' : 'outline'"
                        size="sm"
                    >
                        Category {{ getSortIcon('category') }}
                    </Button>
                    <Button @click="handleSort('city')" :variant="(filters.sort || []).some((s) => s.startsWith('city:')) ? 'default' : 'outline'" size="sm">
                        Location {{ getSortIcon('city') }}
                    </Button>
                    <Button @click="handleSort('score')" :variant="(filters.sort || []).some((s) => s.startsWith('score:')) ? 'default' : 'outline'" size="sm">
                        Score {{ getSortIcon('score') }}
                    </Button>
                    <Button
                        @click="handleSort('reviews')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('reviews:')) ? 'default' : 'outline'"
                        size="sm"
                    >
                        Reviews {{ getSortIcon('reviews') }}
                    </Button>
                    <Button
                        @click="handleSort('website_rating')"
                        :variant="(filters.sort || []).some((s) => s.startsWith('website_rating:')) ? 'default' : 'outline'"
                        size="sm"
                    >
                        Website Rating {{ getSortIcon('website_rating') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
