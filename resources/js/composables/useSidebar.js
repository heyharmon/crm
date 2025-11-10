import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export function useSidebar(store) {
    const route = useRoute()
    const router = useRouter()
    const sidebarMode = ref(null) // 'view' | 'edit' | null
    const sidebarOrgId = ref(null)

    const isDrawerOpen = computed(() => !!sidebarMode.value && !!sidebarOrgId.value)

    const selectedOrganization = computed(() => {
        const id = Number(sidebarOrgId.value)
        return store.organizations.find((o) => o.id === id) || store.currentOrganization
    })

    const syncFromRoute = () => {
        const { org, mode } = route.query
        if (org && (mode === 'view' || mode === 'edit')) {
            sidebarOrgId.value = org
            sidebarMode.value = mode
        } else {
            sidebarOrgId.value = null
            sidebarMode.value = null
        }
    }

    const openSidebar = async (mode, id) => {
        const q = { ...route.query, org: String(id), mode }
        await router.replace({ query: q })
        try {
            await store.fetchOrganization(id)
        } catch (e) {
            // non-fatal; the nested components also handle loading
        }
    }

    const closeSidebar = async () => {
        const q = { ...route.query }
        delete q.org
        delete q.mode
        await router.replace({ query: q })
    }

    const handleEditSubmit = async (organizationData) => {
        if (!sidebarOrgId.value) return
        try {
            await store.updateOrganization(Number(sidebarOrgId.value), organizationData)
            await store.fetchOrganizations(store.pagination.current_page)
            openSidebar('view', sidebarOrgId.value)
        } catch (error) {
            console.error('Error updating organization:', error)
        }
    }

    onMounted(syncFromRoute)
    watch(() => route.query, syncFromRoute, { deep: true })

    return {
        sidebarMode,
        sidebarOrgId,
        isDrawerOpen,
        selectedOrganization,
        openSidebar,
        closeSidebar,
        handleEditSubmit
    }
}
