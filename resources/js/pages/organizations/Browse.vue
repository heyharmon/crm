<script setup>
import { onMounted, watch } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import OrganizationFilters from '@/components/OrganizationFilters.vue'
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
        window.open(website, '_blank')
    }
}

const updateWebsiteRating = async (organizationId, rating) => {
    try {
        await organizationStore.updateOrganization(organizationId, { website_rating: rating })
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
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
                                </div>
                            </div>

                            <div v-if="organization.category" class="text-sm text-neutral-600 mb-2">
                                {{ organization.category }}
                            </div>

                            <div v-if="organization.city || organization.state" class="text-sm text-neutral-500 mb-3">
                                {{ [organization.city, organization.state].filter(Boolean).join(', ') }}
                            </div>

                            <div v-if="organization.phone" class="text-sm text-neutral-600 mb-3">
                                {{ organization.phone }}
                            </div>

                            <div class="mb-3">
                                <label class="block text-xs font-medium text-neutral-700 mb-1">Website Rating</label>
                                <select
                                    :value="organization.website_rating || ''"
                                    @change="updateWebsiteRating(organization.id, $event.target.value || null)"
                                    class="w-full text-xs border border-neutral-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Not rated</option>
                                    <option value="good">Good</option>
                                    <option value="okay">Okay</option>
                                    <option value="bad">Bad</option>
                                </select>
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

                <div
                    v-if="organizationStore.pagination.last_page > 1"
                    class="bg-white rounded-lg shadow-sm border border-neutral-200 px-4 py-3 flex items-center justify-between"
                >
                    <div class="flex-1 flex justify-between sm:hidden">
                        <Button
                            @click="handlePageChange(organizationStore.pagination.current_page - 1)"
                            :disabled="organizationStore.pagination.current_page === 1"
                            variant="outline"
                        >
                            Previous
                        </Button>
                        <Button
                            @click="handlePageChange(organizationStore.pagination.current_page + 1)"
                            :disabled="organizationStore.pagination.current_page === organizationStore.pagination.last_page"
                            variant="outline"
                        >
                            Next
                        </Button>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-neutral-700">
                                Showing
                                <span class="font-medium">{{
                                    (organizationStore.pagination.current_page - 1) * organizationStore.pagination.per_page + 1
                                }}</span>
                                to
                                <span class="font-medium">{{
                                    Math.min(
                                        organizationStore.pagination.current_page * organizationStore.pagination.per_page,
                                        organizationStore.pagination.total
                                    )
                                }}</span>
                                of
                                <span class="font-medium">{{ organizationStore.pagination.total }}</span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <Button
                                    @click="handlePageChange(organizationStore.pagination.current_page - 1)"
                                    :disabled="organizationStore.pagination.current_page === 1"
                                    variant="outline"
                                    size="sm"
                                >
                                    Previous
                                </Button>
                                <Button
                                    v-for="page in Math.min(5, organizationStore.pagination.last_page)"
                                    :key="page"
                                    @click="handlePageChange(page)"
                                    :variant="page === organizationStore.pagination.current_page ? 'default' : 'outline'"
                                    size="sm"
                                >
                                    {{ page }}
                                </Button>
                                <Button
                                    @click="handlePageChange(organizationStore.pagination.current_page + 1)"
                                    :disabled="organizationStore.pagination.current_page === organizationStore.pagination.last_page"
                                    variant="outline"
                                    size="sm"
                                >
                                    Next
                                </Button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
