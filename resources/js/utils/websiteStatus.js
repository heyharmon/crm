const WEBSITE_STATUS_META = {
    up: { label: 'Up', classes: 'border-green-100 bg-green-50 text-green-700' },
    down: { label: 'Down', classes: 'border-red-100 bg-red-50 text-red-700' },
    redirected: { label: 'Redirected', classes: 'border-amber-100 bg-amber-50 text-amber-700' },
    unknown: { label: 'Unknown', classes: 'border-neutral-100 bg-neutral-50 text-neutral-600' }
}

const normalizeWebsiteStatus = (status) => {
    if (!status) return 'unknown'
    if (['missing', 'cert-error', 'timeout'].includes(status)) {
        return 'down'
    }
    return status
}

const getWebsiteStatusMeta = (status) => WEBSITE_STATUS_META[normalizeWebsiteStatus(status)] ?? WEBSITE_STATUS_META.unknown

const formatWebsiteStatus = (status) => getWebsiteStatusMeta(status).label

const getWebsiteStatusClasses = (status) => getWebsiteStatusMeta(status).classes

export { WEBSITE_STATUS_META, normalizeWebsiteStatus, formatWebsiteStatus, getWebsiteStatusClasses }
