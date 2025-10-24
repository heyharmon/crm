<script setup>
import { computed } from 'vue'
import Button from '@/components/ui/Button.vue'
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
    columns: {
        type: Number,
        default: 3
    },
    ratingOptions: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits([
    'update:columns',
    'open-sidebar',
    'delete-organization',
    'update-website-rating',
    'clear-website-rating',
    'page-change'
])

const optionsBySlug = computed(() => {
    return (props.ratingOptions || []).reduce((map, option) => {
        map[option.slug] = option
        return map
    }, {})
})

const getOptionLabelFromSlug = (slug) => optionsBySlug.value?.[slug]?.name || slug || '-'

const formatAverage = (value) => {
    if (value === null || value === undefined) return null
    return Number(value).toFixed(2)
}

const getScreenshotUrl = (website) => {
    if (!website) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
    return `${baseUrl}?access_key=${accessKey}&wait_until=network_idle&no_cookie_banners=true&url=${encodeURIComponent(website)}`
}
</script>

<template>
    <div class="flex flex-1 flex-col min-h-0">
        <div class="flex items-center justify-end gap-3 border-b border-neutral-200 bg-white px-4 py-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Columns</span>
            <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white p-1">
                <button
                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                    :class="props.columns === 1 ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                    @click="emit('update:columns', 1)"
                >
                    1
                </button>
                <button
                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                    :class="props.columns === 2 ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                    @click="emit('update:columns', 2)"
                >
                    2
                </button>
                <button
                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                    :class="props.columns === 3 ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                    @click="emit('update:columns', 3)"
                >
                    3
                </button>
                <button
                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                    :class="props.columns === 4 ? 'bg-neutral-900 text-white shadow-sm' : 'hover:bg-neutral-100 hover:text-neutral-900'"
                    @click="emit('update:columns', 4)"
                >
                    4
                </button>
            </div>
        </div>

        <div class="flex-1 px-4 py-4">
            <div
                class="grid gap-4 sm:grid-cols-2"
                :class="{
                    'lg:grid-cols-1': props.columns === 1,
                    'lg:grid-cols-2': props.columns === 2,
                    'lg:grid-cols-3': props.columns === 3,
                    'lg:grid-cols-4': props.columns === 4
                }"
            >
                <div
                    v-for="organization in props.organizations"
                    :key="organization.id"
                    class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold leading-tight text-neutral-900">
                                {{ organization.name }}
                            </h3>
                            <div v-if="organization.category" class="text-sm font-medium text-neutral-600">
                                {{ organization.category.name }}
                            </div>
                            <div v-if="organization.city || organization.state" class="text-sm text-neutral-500">
                                {{ [organization.city, organization.state].filter(Boolean).join(', ') }}
                            </div>
                        </div>
                        <div
                            v-if="organization.score"
                            class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-2 py-1 text-xs font-medium text-neutral-600"
                        >
                            <span class="text-yellow-500">★</span>
                            <span class="ml-1">{{ organization.score }}</span>
                        </div>
                    </div>

                    <div v-if="organization.website" class="relative h-40 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100">
                        <img
                            v-if="getScreenshotUrl(organization.website)"
                            :src="getScreenshotUrl(organization.website)"
                            :alt="`Screenshot of ${organization.name} website`"
                            class="absolute inset-0 h-full w-full object-cover"
                            @error="(e) => (e.target.style.display = 'none')"
                        />
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-3 py-2 text-xs font-medium text-white">
                            {{ organization.website }}
                        </div>
                    </div>

                    <div v-if="organization.phone" class="text-sm text-neutral-600">
                        {{ organization.phone }}
                    </div>

                    <div v-if="organization.website" class="space-y-2">
                        <label class="block text-xs font-medium uppercase tracking-wide text-neutral-500">Website Ratings</label>
                        <div class="flex flex-col gap-2">
                            <div class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white p-1">
                                <button
                                    v-for="option in ratingOptions"
                                    :key="option.id"
                                    class="rounded-full px-3 py-1 text-xs font-semibold text-neutral-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-neutral-400"
                                    :class="
                                        organization.my_website_rating_option_id === option.id
                                            ? 'bg-neutral-900 text-white shadow-sm'
                                            : 'hover:bg-neutral-100 hover:text-neutral-900'
                                    "
                                    @click="emit('update-website-rating', { id: organization.id, optionId: option.id })"
                                >
                                    {{ option.name }}
                                </button>
                            </div>
                            <div class="flex items-center justify-between text-xs text-neutral-500">
                                <div class="flex flex-col items-end gap-1 text-right">
                                    <span v-if="organization.website_rating_summary" class="inline-flex items-center gap-2">
                                        <span class="font-medium text-neutral-700">
                                            Average: {{ getOptionLabelFromSlug(organization.website_rating_summary) }}
                                        </span>
                                        <span v-if="organization.website_rating_average !== null">
                                            ({{ formatAverage(organization.website_rating_average) }})
                                        </span>
                                        <span v-if="organization.website_rating_count">
                                            • {{ organization.website_rating_count }} ratings
                                        </span>
                                    </span>
                                    <span v-else class="text-neutral-400">No ratings yet</span>
                                    <span v-if="organization.my_website_rating_option_name" class="text-neutral-500">
                                        Your rating: {{ organization.my_website_rating_option_name }}
                                    </span>
                                </div>
                                <button
                                    class="text-xs font-medium text-neutral-500 underline underline-offset-4 transition-colors hover:text-neutral-700"
                                    :disabled="!organization.my_website_rating_option_id"
                                    @click="emit('clear-website-rating', organization.id)"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="rounded-full border-neutral-200 text-neutral-700 hover:bg-neutral-100"
                            @click="emit('open-sidebar', { mode: 'view', id: organization.id })"
                        >
                            View
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="rounded-full border-neutral-200 text-neutral-700 hover:bg-neutral-100"
                            @click="emit('open-sidebar', { mode: 'edit', id: organization.id })"
                        >
                            Edit
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="rounded-full border-red-200 text-red-600 hover:bg-red-50"
                            @click="emit('delete-organization', organization.id)"
                        >
                            Delete
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-neutral-200 bg-white px-4 py-3">
            <Pagination :pagination="props.pagination" @page-change="emit('page-change', $event)" />
        </div>
    </div>
</template>
