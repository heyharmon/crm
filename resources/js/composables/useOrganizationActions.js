import api from '@/services/api'

export function useOrganizationActions(store) {
    const deleteOrganization = async (id) => {
        try {
            await store.deleteOrganization(id)
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
            await api.post('/web-scraper/start', {
                organization_id: organization.id
            })
        } catch (error) {
            console.error('Error starting web scraping:', error)
            alert('Failed to start web scraping. Please try again.')
        }
    }

    const detectWebsiteRedesign = async (organization) => {
        if (!organization?.id) return
        try {
            await store.detectWebsiteRedesign(organization.id)
        } catch (error) {
            console.error('Error queuing redesign detection:', error)
        }
    }

    const detectWebsiteCms = async (organization) => {
        if (!organization?.id) return

        if (!organization.website) {
            alert('This organization does not have a website to analyze.')
            return
        }

        try {
            const response = await store.detectOrganizationCms(organization.id)
            const message = response?.message || 'CMS detection queued.'
            alert(message)
        } catch (error) {
            console.error('Error queuing CMS detection:', error)
            const errorMessage = error?.message || 'Failed to queue CMS detection. Please try again.'
            alert(errorMessage)
        }
    }

    const checkWebsiteStatus = async (organization) => {
        if (!organization?.id) return

        if (!organization.website) {
            alert('This organization does not have a website to check.')
            return
        }

        try {
            const response = await store.checkWebsiteStatus(organization.id)
            const message = response?.message || 'Website status check queued.'
            alert(message)
        } catch (error) {
            console.error('Error queuing website status check:', error)
            const errorMessage = error?.message || 'Failed to queue website status check. Please try again.'
            alert(errorMessage)
        }
    }

    const submitWebsiteRating = async (organizationId, optionId) => {
        try {
            await store.setWebsiteRating(organizationId, optionId)
        } catch (error) {
            console.error('Error submitting website rating:', error)
        }
    }

    const clearWebsiteRating = async (organizationId) => {
        try {
            await store.clearWebsiteRating(organizationId)
        } catch (error) {
            console.error('Error clearing website rating:', error)
        }
    }

    const formatWebsite = (url) => {
        if (!url) return ''
        return /^https?:\/\//i.test(url) ? url : `https://${url}`
    }

    return {
        deleteOrganization,
        startWebScraping,
        detectWebsiteRedesign,
        detectWebsiteCms,
        checkWebsiteStatus,
        submitWebsiteRating,
        clearWebsiteRating,
        formatWebsite
    }
}
