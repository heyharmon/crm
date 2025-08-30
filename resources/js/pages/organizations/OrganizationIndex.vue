<script setup>
import { ref, onMounted, watch } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import OrganizationFilters from '@/components/OrganizationFilters.vue'
import Pagination from '@/components/Pagination.vue'
import api from '@/services/api'

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

const deleteOrganization = async (id) => {
    try {
        await organizationStore.deleteOrganization(id)
    } catch (error) {
        console.error('Error deleting organization:', error)
    }
}

const startWebScraping = async (organization) => {
    if (!organization.website) {
        alert('This organization does not have a website to scrape.')
        return
    }

    try {
        const data = await api.post('/web-scraper/start', {
            organization_id: organization.id,
            max_pages: 50,
            max_depth: 2
        })
    } catch (error) {
        console.error('Error starting web scraping:', error)
        alert('Failed to start web scraping. Please try again.')
    }
}
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto py-8 px-4">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-2xl font-bold">Organizations</h1>
                <div class="flex space-x-2">
                    <router-link to="/organizations/browse">
                        <Button variant="outline">Browse View</Button>
                    </router-link>
                    <router-link to="/organizations/import">
                        <Button class="bg-green-600 hover:bg-green-700 text-white"> Import from Google Maps </Button>
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

            <div v-else class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-neutral-50 border-b border-neutral-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Reviews</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Website Rating</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Pages</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-neutral-200">
                            <tr v-for="organization in organizationStore.organizations" :key="organization.id" class="hover:bg-neutral-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <img
                                            v-if="organization.banner"
                                            :src="organization.banner"
                                            :alt="organization.name"
                                            class="h-10 w-10 rounded-full mr-3 object-cover"
                                        />
                                        <div class="h-10 w-10 rounded-full mr-3 bg-neutral-200 flex items-center justify-center" v-else>
                                            <span class="text-neutral-500 font-medium">{{ organization.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-sm font-medium text-neutral-900">{{ organization.name }}</div>
                                            <div v-if="organization.phone" class="text-sm text-neutral-500">{{ organization.phone }}</div>
                                            <div v-if="organization.website" class="text-sm text-neutral-500">{{ organization.website }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    {{ organization.category?.name || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    <div>{{ organization.city || '-' }}</div>
                                    <div class="text-neutral-500">{{ organization.state || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    <div v-if="organization.score" class="flex items-center">
                                        <span class="text-yellow-400">★</span>
                                        <span class="ml-1">{{ organization.score }}</span>
                                    </div>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    <span v-if="organization.reviews !== null && organization.reviews !== undefined">
                                        {{ organization.reviews }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    <span
                                        v-if="organization.website_rating"
                                        :class="{
                                            'text-green-600 bg-green-100': organization.website_rating === 'good',
                                            'text-yellow-600 bg-yellow-100': organization.website_rating === 'okay',
                                            'text-red-600 bg-red-100': organization.website_rating === 'bad'
                                        }"
                                        class="px-2 py-1 rounded-full text-xs font-medium capitalize"
                                    >
                                        {{ organization.website_rating }}
                                    </span>
                                    <div
                                        v-else-if="!organization.website"
                                        class="inline-block px-2 py-1 text-xs font-medium text-neutral-700 bg-neutral-100 rounded-full"
                                    >
                                        No Website
                                    </div>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    {{ organization.pages_count || 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <router-link
                                            :to="{ name: 'organizations.show', params: { id: organization.id } }"
                                            class="text-blue-600 hover:text-blue-900"
                                        >
                                            View
                                        </router-link>
                                        <router-link
                                            :to="{ name: 'organizations.edit', params: { id: organization.id } }"
                                            class="text-green-600 hover:text-green-900"
                                        >
                                            Edit
                                        </router-link>
                                        <button
                                            v-if="organization.website"
                                            @click="startWebScraping(organization)"
                                            class="text-purple-600 hover:text-purple-900"
                                        >
                                            Scrape
                                        </button>
                                        <button @click="deleteOrganization(organization.id)" class="text-red-600 hover:text-red-900">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :pagination="organizationStore.pagination" @page-change="handlePageChange" class="border-t border-neutral-200" />
            </div>
        </div>
    </DefaultLayout>
</template>
