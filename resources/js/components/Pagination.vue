<script setup>
import Button from '@/components/ui/Button.vue'

const props = defineProps({
    pagination: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['page-change'])

const handlePageChange = (page) => {
    emit('page-change', page)
}
</script>

<template>
    <div v-if="pagination.last_page > 1" class="bg-white rounded-lg shadow-sm border border-neutral-200 px-4 py-3 flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden gap-1.5">
            <Button @click="handlePageChange(pagination.current_page - 1)" :disabled="pagination.current_page === 1" variant="outline"> Previous </Button>
            <Button @click="handlePageChange(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" variant="outline">
                Next
            </Button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-neutral-700">
                    Showing
                    <span class="font-medium">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span>
                    to
                    <span class="font-medium">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span>
                    of
                    <span class="font-medium">{{ pagination.total }}</span>
                    results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex gap-1.5">
                    <Button @click="handlePageChange(pagination.current_page - 1)" :disabled="pagination.current_page === 1" variant="outline" size="sm">
                        Previous
                    </Button>
                    <Button
                        v-for="page in Math.min(5, pagination.last_page)"
                        :key="page"
                        @click="handlePageChange(page)"
                        :variant="page === pagination.current_page ? 'default' : 'outline'"
                        size="sm"
                    >
                        {{ page }}
                    </Button>
                    <Button
                        @click="handlePageChange(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        variant="outline"
                        size="sm"
                    >
                        Next
                    </Button>
                </nav>
            </div>
        </div>
    </div>
</template>
