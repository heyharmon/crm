import { computed } from 'vue'
import moment from 'moment'

export function useRedesignAccuracy(organization) {
    const accuracy = computed(() => {
        if (!organization.value?.last_major_redesign_at || !organization.value?.last_major_redesign_at_actual) {
            return null
        }

        const predicted = moment(organization.value.last_major_redesign_at)
        const actual = moment(organization.value.last_major_redesign_at_actual)
        const daysDiff = Math.abs(predicted.diff(actual, 'days'))

        if (daysDiff <= 30) return 'green'
        if (daysDiff <= 90) return 'yellow'
        if (daysDiff <= 180) return 'orange'
        return 'red'
    })

    const classes = computed(() => {
        const acc = accuracy.value

        if (!acc) {
            return 'border-neutral-200 bg-neutral-50'
        }

        const colorMap = {
            green: 'border-green-200 bg-green-50',
            yellow: 'border-yellow-200 bg-yellow-50',
            orange: 'border-orange-200 bg-orange-50',
            red: 'border-red-200 bg-red-50'
        }

        return colorMap[acc] || 'border-neutral-200 bg-neutral-50'
    })

    const textClasses = computed(() => {
        const acc = accuracy.value

        if (!acc) {
            return 'text-neutral-900'
        }

        const colorMap = {
            green: 'text-green-900',
            yellow: 'text-yellow-900',
            orange: 'text-orange-900',
            red: 'text-red-900'
        }

        return colorMap[acc] || 'text-neutral-900'
    })

    const dateClasses = computed(() => {
        const acc = accuracy.value

        if (!acc) {
            return 'text-neutral-700'
        }

        const colorMap = {
            green: 'text-green-700',
            yellow: 'text-yellow-700',
            orange: 'text-orange-700',
            red: 'text-red-700'
        }

        return colorMap[acc] || 'text-neutral-700'
    })

    return {
        accuracy,
        classes,
        textClasses,
        dateClasses
    }
}
