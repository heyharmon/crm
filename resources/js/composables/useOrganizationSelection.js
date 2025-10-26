import { computed, ref, watch } from 'vue'

export function useOrganizationSelection(itemsRef) {
    const selectedIds = ref([])
    const lastIndex = ref(null)

    const normalizeId = (value) => {
        const numeric = Number(value)
        return Number.isFinite(numeric) ? numeric : null
    }

    const visibleIds = computed(() => {
        const items = itemsRef?.value || []
        if (!Array.isArray(items)) return []

        return items
            .map((item) => {
                const value = item?.id
                const numeric = Number(value)
                return Number.isFinite(numeric) ? numeric : null
            })
            .filter((id) => id !== null)
    })

    const selectedCount = computed(() => selectedIds.value.length)

    const selectionSet = computed(() => new Set(selectedIds.value))

    const allVisibleSelected = computed(() => {
        if (!visibleIds.value.length) return false
        return visibleIds.value.every((id) => selectionSet.value.has(id))
    })

    const isIndeterminate = computed(() => selectedCount.value > 0 && !allVisibleSelected.value)

    const isSelected = (id) => selectionSet.value.has(id)

    const updateSelection = (ids, checked) => {
        const next = new Set(selectionSet.value)
        ids.forEach((rawId) => {
            const id = normalizeId(rawId)
            if (id === null) return
            if (checked) {
                next.add(id)
            } else {
                next.delete(id)
            }
        })
        selectedIds.value = Array.from(next)
    }

    const toggleRow = (id, checked, options = {}) => {
        const normalizedId = normalizeId(id)
        if (normalizedId === null) return

        const rowIndex = visibleIds.value.indexOf(normalizedId)
        if (rowIndex === -1) return

        if (options.shiftKey && lastIndex.value !== null) {
            const start = Math.min(lastIndex.value, rowIndex)
            const end = Math.max(lastIndex.value, rowIndex)
            const range = visibleIds.value.slice(start, end + 1)
            updateSelection(range, checked)
        } else {
            updateSelection([normalizedId], checked)
        }

        lastIndex.value = rowIndex
    }

    const toggleAllVisible = (checked) => {
        updateSelection(visibleIds.value, checked)
        lastIndex.value = null
    }

    const clearSelection = () => {
        selectedIds.value = []
        lastIndex.value = null
    }

    watch(visibleIds, (ids) => {
        const allowed = new Set(ids)
        selectedIds.value = selectedIds.value.filter((id) => allowed.has(id))
        lastIndex.value = null
    })

    return {
        selectedIds,
        selectedCount,
        isSelected,
        allVisibleSelected,
        isIndeterminate,
        toggleRow,
        toggleAllVisible,
        clearSelection
    }
}
