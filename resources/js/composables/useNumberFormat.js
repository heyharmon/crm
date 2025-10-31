import { ref, watch } from 'vue'

/**
 * Composable for formatting numbers with commas for display while maintaining clean numeric values
 *
 * @param {Object} props - The props object containing filters
 * @param {Function} updateFilter - Function to update filter values
 * @param {string} filterKey - The key of the filter to format (e.g., 'assets_min')
 * @returns {Object} - Object containing displayValue ref and handleInput function
 */
export function useNumberFormat(props, updateFilter, filterKey) {
    const displayValue = ref('')

    /**
     * Format a number with commas
     * @param {string|number} value - The value to format
     * @returns {string} - Formatted number with commas
     */
    const formatNumber = (value) => {
        if (!value && value !== 0) return ''
        const num = value.toString().replace(/,/g, '')
        if (num === '' || isNaN(num)) return ''
        return num.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    }

    /**
     * Parse a formatted number to remove commas
     * @param {string} value - The formatted value
     * @returns {string} - Clean numeric string
     */
    const parseNumber = (value) => {
        if (!value) return ''
        return value.toString().replace(/,/g, '')
    }

    /**
     * Handle input changes - format for display and emit clean value
     * @param {string} value - The input value
     */
    const handleInput = (value) => {
        const cleanValue = parseNumber(value)
        displayValue.value = formatNumber(cleanValue)
        updateFilter(filterKey, cleanValue)
    }

    // Initialize display value from props
    watch(
        () => props.filters[filterKey],
        (newValue) => {
            if (newValue !== parseNumber(displayValue.value)) {
                displayValue.value = formatNumber(newValue)
            }
        },
        { immediate: true }
    )

    return {
        displayValue,
        handleInput,
        formatNumber,
        parseNumber
    }
}
