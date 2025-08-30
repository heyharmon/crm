<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import OrganizationFilters from '@/components/OrganizationFilters.vue'
import Pagination from '@/components/Pagination.vue'
import api from '@/services/api'

const organizationStore = useOrganizationStore()
const route = useRoute()
const router = useRouter()

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

// Ensure website links include protocol
const formatWebsite = (url) => {
    if (!url) return ''
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

// Unified view toggle (table/grid) synced with ?view=grid
const view = ref(route.query.view === 'grid' ? 'grid' : 'table')
watch(
    () => route.query.view,
    (v) => {
        view.value = v === 'grid' ? 'grid' : 'table'
    }
)
watch(view, (v) => {
    const q = { ...route.query }
    if (v === 'grid') q.view = 'grid'
    else delete q.view
    router.replace({ query: q })
})

// Grid helpers
const columns = ref(3)
const getScreenshotUrl = (website) => {
    if (!website) return null
    const baseUrl = 'https://api.apiflash.com/v1/urltoimage'
    const accessKey = '3725d3868ee3426e82b2a3b9eebde219'
    return `${baseUrl}?access_key=${accessKey}&wait_until=page_loaded&no_cookie_banners=true&url=${encodeURIComponent(website)}`
}
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

// Taller screenshot heights for 1–2 column modes
const cardImageHeightClass = computed(() => {
    // Taller screenshots for low column counts
    if (columns.value === 1) return 'h-[36rem] sm:h-[42rem]' // ~576–672px
    if (columns.value === 2) return 'h-96 sm:h-[30rem]' // ~384–480px (unchanged)
    return 'h-40 sm:h-48' // 160–192px for 3–4 columns
})
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto py-8 px-4">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-2xl font-bold">Organizations</h1>
                <div class="flex space-x-2">
                    <div class="inline-flex rounded-md overflow-hidden border border-neutral-300">
                        <button
                            class="px-3 py-1 text-sm focus:outline-none"
                            :class="view === 'table' ? 'bg-neutral-900 text-white' : 'bg-white hover:bg-neutral-50'"
                            @click="view = 'table'"
                        >
                            Table
                        </button>
                        <button
                            class="px-3 py-1 text-sm border-l border-neutral-300 focus:outline-none"
                            :class="view === 'grid' ? 'bg-neutral-900 text-white' : 'bg-white hover:bg-neutral-50'"
                            @click="view = 'grid'"
                        >
                            Grid
                        </button>
                    </div>
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

            <div v-else>
                <!-- Table view -->
                <div v-if="view === 'table'" class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
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
                                            <a
                                                v-if="organization.website"
                                                :href="formatWebsite(organization.website)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-sm text-blue-600 hover:text-blue-800 cursor-pointer break-all"
                                            >
                                                {{ organization.website }}
                                            </a>
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

                <!-- Grid view -->
                <div v-else>
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
                            <button
                                class="px-3 py-1 text-sm border-l border-neutral-300 focus:outline-none"
                                :class="columns === 4 ? 'bg-neutral-900 text-white' : 'bg-white hover:bg-neutral-50'"
                                @click="columns = 4"
                            >
                                4
                            </button>
                        </div>
                    </div>

                    <div
                        class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2"
                        :class="{
                            'lg:grid-cols-1': columns === 1,
                            'lg:grid-cols-2': columns === 2,
                            'lg:grid-cols-3': columns === 3,
                            'lg:grid-cols-4': columns === 4
                        }"
                    >
                        <div
                            v-for="organization in organizationStore.organizations"
                            :key="organization.id"
                            class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden hover:shadow-md transition-shadow"
                        >
                            <div
                                class="relative bg-neutral-100 cursor-pointer"
                                :class="cardImageHeightClass"
                                @click="openWebsite(organization.website)"
                                :title="organization.website ? 'Visit website' : 'No website available'"
                            >
                                <img
                                    v-if="organization.website && getScreenshotUrl(organization.website)"
                                    :src="getScreenshotUrl(organization.website)"
                                    :alt="`Screenshot of ${organization.name} website`"
                                    class="absolute inset-0 w-full h-full object-cover opacity-90 hover:opacity-100 transition-opacity"
                                    @error="(e) => (e.target.style.display = 'none')"
                                />
                                <div v-else class="absolute inset-0 flex items-center justify-center text-neutral-500">
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
        </div>
    </DefaultLayout>
</template>
