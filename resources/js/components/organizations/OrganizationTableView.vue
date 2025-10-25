<script setup>
import Pagination from '@/components/Pagination.vue'

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
    }
})

const emit = defineEmits(['open-sidebar', 'start-web-scraping', 'delete-organization', 'page-change'])

const formatRatingSummary = (slug) => {
    if (!slug) return null
    return slug
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
}

const formatAverage = (value) => {
    if (value === null || value === undefined) return null
    return Number(value).toFixed(2)
}
</script>

<template>
    <div class="flex flex-1 flex-col min-h-0">
        <div class="flex-1 overflow-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                <thead class="bg-neutral-50 text-xs tracking-wide uppercase text-neutral-500">
                    <tr>
                        <th class="border-b border-neutral-200 px-4 py-3">Name</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Category</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Location</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Score</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Reviews</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Website Rating</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Pages</th>
                        <th class="border-b border-neutral-200 px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="organization in props.organizations"
                        :key="organization.id"
                        class="cursor-pointer border-b border-neutral-200 transition-colors hover:bg-neutral-50 focus-within:bg-neutral-50"
                        @click="emit('open-sidebar', { mode: 'view', id: organization.id })"
                        tabindex="0"
                        @keydown.enter="emit('open-sidebar', { mode: 'view', id: organization.id })"
                        @keydown.space.prevent="emit('open-sidebar', { mode: 'view', id: organization.id })"
                    >
                        <td class="px-4 py-3 align-top">
                            <div class="flex items-start gap-3">
                                <img
                                    v-if="organization.banner"
                                    :src="organization.banner"
                                    :alt="organization.name"
                                    class="h-10 w-10 rounded-full border border-neutral-200 object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-dashed border-neutral-200 bg-neutral-100 text-sm font-medium text-neutral-500"
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
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-neutral-700">
                            {{ organization.category?.name || '-' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <div class="font-medium text-neutral-700">{{ organization.state || '-' }}</div>
                            <div class="text-xs text-neutral-500">{{ organization.city || '-' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <div v-if="organization.score" class="flex items-center gap-1 text-xs font-medium text-neutral-700">
                                <span class="text-yellow-500">★</span>
                                <span>{{ organization.score }}</span>
                            </div>
                            <span v-else>-</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <span v-if="organization.reviews !== null && organization.reviews !== undefined">
                                {{ organization.reviews }}
                            </span>
                            <span v-else>-</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            <div
                                v-if="!organization.website"
                                class="inline-flex items-center rounded-full border border-dashed border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-500"
                            >
                                No Website
                            </div>
                            <div v-else class="flex flex-col gap-2">
                                <div class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                                    <span v-if="organization.website_rating_summary" class="font-medium text-neutral-700">
                                        {{ formatRatingSummary(organization.website_rating_summary) }}
                                    </span>
                                    <span v-else class="text-neutral-400">No ratings yet</span>
                                    <div class="flex flex-wrap items-center gap-1 text-xs text-neutral-500">
                                        <span v-if="organization.website_rating_average !== null">
                                            ({{ formatAverage(organization.website_rating_average) }})
                                        </span>
                                        <span v-if="organization.website_rating_count"> {{ organization.website_rating_count }} ratings </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-700">
                            {{ organization.website ? organization.pages_count || 0 : '-' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-neutral-700">
                            <div class="flex items-center gap-2">
                                <button
                                    class="inline-flex items-center rounded-full border border-neutral-200 px-3 py-1 text-xs font-semibold text-green-700 transition hover:border-green-200 hover:bg-green-50"
                                    @click.stop="emit('open-sidebar', { mode: 'edit', id: organization.id })"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="organization.website"
                                    class="inline-flex items-center rounded-full border border-neutral-200 px-3 py-1 text-xs font-semibold text-purple-700 transition hover:border-purple-200 hover:bg-purple-50"
                                    @click.stop="emit('start-web-scraping', organization)"
                                >
                                    Scrape
                                </button>
                                <button
                                    class="inline-flex items-center rounded-full border border-neutral-200 px-3 py-1 text-xs font-semibold text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                    @click.stop="emit('delete-organization', organization.id)"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 bg-white px-4 py-3">
            <Pagination :pagination="props.pagination" @page-change="emit('page-change', $event)" />
        </div>
    </div>
</template>
