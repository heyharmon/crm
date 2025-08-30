<script setup>
import { onMounted, watch, ref } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import OrganizationFilters from '@/components/OrganizationFilters.vue'
import Pagination from '@/components/Pagination.vue'
import Button from '@/components/ui/Button.vue'

const organizationStore = useOrganizationStore()

onMounted(async () => {
    await organizationStore.fetchOrganizations()
})

watch(
    () => organizationStore.filters,
    () => {
        organizationStore.fetchOrganizations(1)
    },
    { deep: true }
)

const handleSearch = () => {
    organizationStore.fetchOrganizations(1)
}

const handlePageChange = (page) => {
    organizationStore.fetchOrganizations(page)
}

// Column toggle for card grid
const columns = ref(3)

// Ensure website links include protocol
const formatWebsite = (url) => {
    if (!url) return ''
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

const getScreenshotUrl = (website) => {
    if (!website) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
    return `${baseUrl}?access_key=${accessKey}&wait_until=page_loaded&no_cookie_banners=true&url=${encodeURIComponent(website)}`
}

// const getScreenshotUrl = (website) => {
//     if (!website) return null
//     return `https://api.screenshotone.com/take?
// 	access_key=sPbeJ5nS_-OXgA
// 	&url=${encodeURIComponent(website)}
// 	&format=jpg
// 	&block_ads=true
// 	&block_cookie_banners=true
// 	&block_banners_by_heuristics=false
// 	&block_trackers=true
// 	&delay=0
// 	&timeout=60
//     &wait_until=load
// 	&response_type=by_format
//     &ignore_host_errors=true
// 	&image_quality=80`
// }

const openWebsite = (website) => {
    if (website) {
        window.open(formatWebsite(website), '_blank')
    }
}

const updateWebsiteRating = async (organizationId, rating) => {
    try {
        await organizationStore.updateOrganization(organizationId, { website_rating: rating })
        const org = organizationStore.organizations.find((o) => o.id === organizationId)
        if (org) org.website_rating = rating
    } catch (error) {
        console.error('Error updating website rating:', error)
    }
}
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto py-8 px-4">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-2xl font-bold">Browse Organizations</h1>
                <div class="flex space-x-2">
                    <router-link to="/organizations">
                        <Button variant="outline">List View</Button>
                    </router-link>
                    <router-link to="/organizations/import">
                        <Button class="bg-green-600 hover:bg-green-700 text-white">Import from Google Maps</Button>
                    </router-link>
                    <router-link to="/organizations/create">
                        <Button>Create Organization</Button>
                    </router-link>
                </div>
            </div>

            <OrganizationFilters
                :filters="organizationStore.filters"
                @update:filters="organizationStore.setFilters"
                @reset-filters="organizationStore.resetFilters"
                @search="handleSearch"
            />

            <div v-if="organizationStore.isLoading" class="flex justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
            </div>

            <div v-else-if="organizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ organizationStore.error }}
            </div>

            <div v-else>
                <!-- Columns toggle -->
                <div class="flex items-center justify-end mb-4 space-x-2">
                    <span class="text-sm text-neutral-600">Columns:</span>
                    <div class="inline-flex rounded-md overflow-hidden border border-neutral-300">
                        <button
                            class="px-3 py-1 text-sm focus:outline-none"
                            :class="columns === 1 ? 'bg-neutral-900 text-white' : 'bg-white hover:bg-neutral-50'"
                            @click="columns = 1"
                        >
                            1
                        </button>
                        <button
                            class="px-3 py-1 text-sm border-l border-neutral-300 focus:outline-none"
                            :class="columns === 2 ? 'bg-neutral-900 text-white' : 'bg-white hover:bg-neutral-50'"
                            @click="columns = 2"
                        >
                            2
                        </button>
                        <button
                            class="px-3 py-1 text-sm border-l border-neutral-300 focus:outline-none"
                            :class="columns === 3 ? 'bg-neutral-900 text-white' : 'bg-white hover:bg-neutral-50'"
                            @click="columns = 3"
                        >
                            3
                        </button>
                    </div>
                </div>

                <div class="grid gap-6 mb-8" :class="{ 'grid-cols-1': columns === 1, 'grid-cols-2': columns === 2, 'grid-cols-3': columns === 3 }">
                    <div
                        v-for="organization in organizationStore.organizations"
                        :key="organization.id"
                        class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden hover:shadow-md transition-shadow duration-200"
                    >
                        <div class="aspect-video bg-neutral-100 relative overflow-hidden">
                            <img
                                v-if="organization.website && getScreenshotUrl(organization.website)"
                                :src="getScreenshotUrl(organization.website)"
                                :alt="`Screenshot of ${organization.name}`"
                                class="w-full h-full object-cover cursor-pointer hover:scale-105 transition-transform duration-200"
                                @click="openWebsite(organization.website)"
                                @error="$event.target.style.display = 'none'"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center bg-neutral-200 text-neutral-500">
                                <div class="text-center">
                                    <div class="text-4xl mb-2">🌐</div>
                                    <div class="text-sm">No Website</div>
                                </div>
                            </div>
                            <div v-if="organization.website" class="absolute top-2 right-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
                                Click to visit
                            </div>
                        </div>

                        <div class="p-4">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="font-semibold text-neutral-900 text-lg leading-tight">
                                    {{ organization.name }}
                                </h3>
                                <div v-if="organization.score" class="flex items-center text-sm">
                                    <span class="text-yellow-400">★</span>
                                    <span class="ml-1 text-neutral-600">{{ organization.score }}</span>
                                    <span
                                        v-if="organization.reviews !== null && organization.reviews !== undefined"
                                        class="ml-2 text-neutral-500"
                                    >
                                        ({{ organization.reviews }})
                                    </span>
                                </div>
                            </div>

                            <div v-if="organization.category" class="text-sm text-neutral-600 mb-2">
                                {{ organization.category.name }}
                            </div>

                            <div v-if="organization.city || organization.state" class="text-sm text-neutral-500 mb-3">
                                {{ [organization.city, organization.state].filter(Boolean).join(', ') }}
                            </div>

                            <div v-if="organization.phone" class="text-sm text-neutral-600 mb-3">
                                {{ organization.phone }}
                            </div>

                            <div v-if="organization.website" class="text-sm mb-3">
                                <a
                                    :href="formatWebsite(organization.website)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-blue-600 hover:text-blue-800 cursor-pointer break-all"
                                >
                                    {{ organization.website }}
                                </a>
                            </div>

                            <div v-if="organization.website" class="mb-3">
                                <label class="block text-xs font-medium text-neutral-700 mb-1">Website Rating</label>
                                <div class="inline-flex rounded-md overflow-hidden border border-neutral-300">
                                    <button
                                        class="px-3 py-1 text-xs focus:outline-none"
                                        :class="organization.website_rating === 'good' ? 'bg-green-600 text-white' : 'bg-white hover:bg-green-50 text-neutral-700'"
                                        @click="updateWebsiteRating(organization.id, 'good')"
                                    >
                                        Good
                                    </button>
                                    <button
                                        class="px-3 py-1 text-xs border-l border-neutral-300 focus:outline-none"
                                        :class="organization.website_rating === 'okay' ? 'bg-yellow-500 text-white' : 'bg-white hover:bg-yellow-50 text-neutral-700'"
                                        @click="updateWebsiteRating(organization.id, 'okay')"
                                    >
                                        Okay
                                    </button>
                                    <button
                                        class="px-3 py-1 text-xs border-l border-neutral-300 focus:outline-none"
                                        :class="organization.website_rating === 'bad' ? 'bg-red-600 text-white' : 'bg-white hover:bg-red-50 text-neutral-700'"
                                        @click="updateWebsiteRating(organization.id, 'bad')"
                                    >
                                        Bad
                                    </button>
                                </div>
                                <button
                                    class="ml-2 text-xs text-neutral-500 hover:text-neutral-700 underline"
                                    @click="updateWebsiteRating(organization.id, null)"
                                >
                                    Clear
                                </button>
                            </div>

                            <div class="flex space-x-2">
                                <router-link :to="{ name: 'organizations.show', params: { id: organization.id } }" class="flex-1">
                                    <Button variant="outline" size="sm" class="w-full">View Details</Button>
                                </router-link>
                                <router-link :to="{ name: 'organizations.edit', params: { id: organization.id } }">
                                    <Button variant="outline" size="sm">Edit</Button>
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>

                <Pagination :pagination="organizationStore.pagination" @page-change="handlePageChange" />
            </div>
        </div>
    </DefaultLayout>
</template>
