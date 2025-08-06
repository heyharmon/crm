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

const showFilters = ref(false)

const updateFilter = (key, value) => {
    emit('update:filters', { [key]: value })
}

const handleSearch = () => {
    emit('search')
}

const resetFilters = () => {
    emit('reset-filters')
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

        <div v-if="showFilters" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
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
    </div>
</template>
