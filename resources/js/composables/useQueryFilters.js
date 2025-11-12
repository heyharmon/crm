import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export function useQueryFilters(store, options = {}) {
    const route = useRoute()
    const router = useRouter()
    const syncingQuery = ref(false)
    const { onClearSelection = () => {} } = options

    const parseFiltersFromQuery = (q) => {
        const toStr = (v) => (typeof v === 'string' ? v : '')
        const toArr = (v) => (Array.isArray(v) ? v : v ? [String(v)] : [])
        const toNumArr = (v) => {
            if (!v) return []
            const arr = Array.isArray(v) ? v : [v]
            return arr.map((item) => {
                if (item === 'null' || item === null) return null
                const num = Number(item)
                return isNaN(num) ? null : num
            })
        }
        return {
            filters: {
                search: toStr(q.search),
                city: toStr(q.city),
                state: toStr(q.state),
                country: toStr(q.country),
                category: toStr(q.category),
                category_ids: toNumArr(q.category_ids),
                cms: toStr(q.cms),
                website: toStr(q.website),
                last_redesign: toStr(q.last_redesign),
                last_redesign_actual: toStr(q.last_redesign_actual),
                website_rating: toStr(q.website_rating),
                website_status: toArr(q.website_status),
                sort: toArr(q.sort),
                assets_min: toStr(q.assets_min),
                assets_max: toStr(q.assets_max),
                asset_growth_min: toStr(q.asset_growth_min),
                asset_growth_max: toStr(q.asset_growth_max),
                pages_min: toStr(q.pages_min),
                pages_max: toStr(q.pages_max),
                last_redesign_year_min: toStr(q.last_redesign_year_min),
                last_redesign_year_max: toStr(q.last_redesign_year_max)
            },
            page: q.page ? Number(q.page) || 1 : 1
        }
    }

    const buildQueryFromFilters = (filters, page, base = {}) => {
        const q = { ...base }
        // drop existing filter keys so we can rebuild cleanly
        delete q.search
        delete q.city
        delete q.state
        delete q.country
        delete q.category
        delete q.category_ids
        delete q.cms
        delete q.website
        delete q.last_redesign
        delete q.last_redesign_actual
        delete q.website_rating
        delete q.website_status
        delete q.sort
        delete q.page
        delete q.assets_min
        delete q.assets_max
        delete q.asset_growth_min
        delete q.asset_growth_max
        delete q.pages_min
        delete q.pages_max
        delete q.last_redesign_year_min
        delete q.last_redesign_year_max

        if (filters.search) q.search = filters.search
        if (filters.city) q.city = filters.city
        if (filters.state) q.state = filters.state
        if (filters.country) q.country = filters.country
        if (filters.category) q.category = filters.category
        if (Array.isArray(filters.category_ids) && filters.category_ids.length) {
            q.category_ids = filters.category_ids.map((id) => (id === null ? 'null' : String(id)))
        }
        if (filters.cms) q.cms = filters.cms
        if (filters.website) q.website = filters.website
        if (filters.last_redesign) q.last_redesign = filters.last_redesign
        if (filters.last_redesign_actual) q.last_redesign_actual = filters.last_redesign_actual
        if (filters.website_rating) q.website_rating = filters.website_rating
        if (Array.isArray(filters.website_status) && filters.website_status.length) q.website_status = [...filters.website_status]
        if (Array.isArray(filters.sort) && filters.sort.length) q.sort = [...filters.sort]
        if (filters.assets_min) q.assets_min = filters.assets_min
        if (filters.assets_max) q.assets_max = filters.assets_max
        if (filters.asset_growth_min) q.asset_growth_min = filters.asset_growth_min
        if (filters.asset_growth_max) q.asset_growth_max = filters.asset_growth_max
        if (filters.pages_min) q.pages_min = filters.pages_min
        if (filters.pages_max) q.pages_max = filters.pages_max
        if (filters.last_redesign_year_min) q.last_redesign_year_min = filters.last_redesign_year_min
        if (filters.last_redesign_year_max) q.last_redesign_year_max = filters.last_redesign_year_max
        if (page && page > 1) q.page = String(page)
        return q
    }

    const initializeFilters = async () => {
        const hasQueryParams = Object.keys(route.query).length > 0
        const { filters, page } = parseFiltersFromQuery(route.query)

        if (!hasQueryParams) {
            filters.website_status = ['up']
        }

        syncingQuery.value = true
        try {
            if (filters) store.setFilters(filters)

            if (!hasQueryParams) {
                const nextQuery = buildQueryFromFilters(filters, page)
                await router.replace({ query: nextQuery })
            }
        } finally {
            syncingQuery.value = false
        }

        await store.fetchOrganizations(page)
    }

    const handleSearch = async (onComplete) => {
        const q = buildQueryFromFilters(store.filters, 1, route.query)
        syncingQuery.value = true
        try {
            await router.replace({ query: q })
        } finally {
            syncingQuery.value = false
        }
        onClearSelection()
        await store.fetchOrganizations(1)
        if (onComplete) onComplete()
    }

    const handlePageChange = async (page) => {
        const q = buildQueryFromFilters(store.filters, page, route.query)
        syncingQuery.value = true
        try {
            await router.replace({ query: q })
        } finally {
            syncingQuery.value = false
        }
        onClearSelection()
        await store.fetchOrganizations(page)
    }

    const handlePerPageChange = async (perPage) => {
        const q = buildQueryFromFilters(store.filters, 1, route.query)
        syncingQuery.value = true
        try {
            await router.replace({ query: q })
        } finally {
            syncingQuery.value = false
        }
        onClearSelection()
        await store.fetchOrganizations(1, perPage)
    }

    // Watch for filter changes
    watch(
        () => store.filters,
        async (newFilters) => {
            if (syncingQuery.value) return
            syncingQuery.value = true

            const page = 1
            const nextQuery = buildQueryFromFilters(newFilters, page, route.query)
            try {
                await router.replace({ query: nextQuery })
            } finally {
                syncingQuery.value = false
            }
            onClearSelection()
            await store.fetchOrganizations(page)
        },
        { deep: true }
    )

    // React to route query changes
    watch(
        () => route.query,
        async (q, prevQ) => {
            if (syncingQuery.value) return
            const keys = ['search', 'city', 'state', 'category', 'cms', 'sort', 'page', 'pages_min', 'pages_max']
            const relevantChanged = keys.some((k) => JSON.stringify(q[k]) !== JSON.stringify(prevQ?.[k]))
            if (!relevantChanged) return

            const { filters, page } = parseFiltersFromQuery(q)
            store.setFilters(filters)
            onClearSelection()
            await store.fetchOrganizations(page)
        },
        { deep: true }
    )

    return {
        initializeFilters,
        handleSearch,
        handlePageChange,
        handlePerPageChange,
        parseFiltersFromQuery,
        buildQueryFromFilters
    }
}
