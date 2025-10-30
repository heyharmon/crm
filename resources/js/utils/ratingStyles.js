const palette = {
    excellent: {
        pill: 'bg-emerald-100 text-emerald-900 border-emerald-200',
        button: {
            inactive: 'bg-green-400 text-neutral-900 hover:bg-green-500 focus-visible:outline-green-400',
            active: 'bg-green-500 text-neutral-900 shadow-sm hover:bg-green-600 focus-visible:outline-green-600'
        }
    },
    good: {
        pill: 'bg-lime-100 text-lime-900 border-lime-200',
        button: {
            inactive: 'bg-lime-300 text-neutral-900 hover:bg-lime-400 focus-visible:outline-lime-400',
            active: 'bg-lime-400 text-neutral-900 shadow-sm hover:bg-lime-500 focus-visible:outline-lime-500'
        }
    },
    okay: {
        pill: 'bg-yellow-100 text-yellow-900 border-yellow-200',
        button: {
            inactive: 'bg-yellow-300 text-neutral-900 hover:bg-yellow-400 focus-visible:outline-yellow-400',
            active: 'bg-yellow-400 text-neutral-900 shadow-sm hover:bg-yellow-500 focus-visible:outline-yellow-500'
        }
    },
    poor: {
        pill: 'bg-orange-100 text-orange-900 border-orange-200',
        button: {
            inactive: 'bg-orange-300 text-neutral-900 hover:bg-orange-400 focus-visible:outline-orange-400',
            active: 'bg-orange-400 text-neutral-900 shadow-sm hover:bg-orange-500 focus-visible:outline-orange-500'
        }
    },
    bad: {
        pill: 'bg-red-100 text-red-900 border-red-200',
        button: {
            inactive: 'bg-red-300 text-neutral-900 hover:bg-red-400 focus-visible:outline-red-400',
            active: 'bg-red-400 text-neutral-900 shadow-sm hover:bg-red-500 focus-visible:outline-red-500'
        }
    }
}

const fallbackStyles = {
    pill: 'bg-neutral-100 text-neutral-700 border-neutral-200',
    button: {
        inactive: 'bg-neutral-100 text-neutral-900 hover:bg-neutral-200 focus-visible:outline-neutral-400',
        active: 'bg-neutral-900 text-white shadow-sm hover:bg-neutral-800 focus-visible:outline-neutral-900'
    }
}

export const getRatingLabel = (slug) => {
    if (!slug || typeof slug !== 'string') return null
    return slug
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
}

export const getRatingPillClasses = (slug) => {
    return (palette[slug] || fallbackStyles).pill
}

export const getRatingButtonClasses = (slug, isActive = false) => {
    const styles = palette[slug]?.button || fallbackStyles.button
    return isActive ? styles.active : styles.inactive
}
