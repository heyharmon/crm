import { ref, watch } from 'vue'

export function useMobileUI() {
    const mobileFiltersOpen = ref(false)
    const mobileActionsOpen = ref(false)

    // Ensure only one mobile panel is open at a time
    watch(mobileActionsOpen, (open) => {
        if (open) {
            mobileFiltersOpen.value = false
        }
    })

    watch(mobileFiltersOpen, (open) => {
        if (open) {
            mobileActionsOpen.value = false
        }
    })

    return {
        mobileFiltersOpen,
        mobileActionsOpen
    }
}
