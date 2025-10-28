import { defineStore } from 'pinia'
import api from '@/services/api'

export const useOrganizationStore = defineStore('organization', {
    state: () => ({
        organizations: [],
        currentOrganization: null,
        // Separate loading flags to avoid unintended UI refreshes
        listLoading: false,
        currentLoading: false,
        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0
        },
        filters: {
            search: '',
            city: '',
            state: '',
            country: '',
            category: '',
            cms: '',
            website: '',
            website_rating: '',
            website_status: [],
            // Multi-sort: ordered list of "field:direction"; empty by default (no sorting)
            sort: []
        },
        // keep isLoading for create/update flows only
        isLoading: false,
        error: null
    }),

    actions: {
        async fetchOrganizations(page = 1) {
            this.listLoading = true
            this.error = null

            try {
                const params = {
                    page,
                    ...this.filters
                }

                const response = await api.get('/organizations', { params })

                this.organizations = response.data
                this.pagination = {
                    current_page: response.current_page,
                    last_page: response.last_page,
                    per_page: response.per_page,
                    total: response.total
                }
            } catch (error) {
                this.error = error.message || 'Failed to fetch organizations'
                console.error('Error fetching organizations:', error)
            } finally {
                this.listLoading = false
            }
        },

        async fetchOrganization(id) {
            this.currentLoading = true
            this.error = null

            try {
                const response = await api.get(`/organizations/${id}`)
                this.currentOrganization = response
                return response
            } catch (error) {
                this.error = error.message || 'Failed to fetch organization'
                console.error('Error fetching organization:', error)
                throw error
            } finally {
                this.currentLoading = false
            }
        },

        async createOrganization(organizationData) {
            this.isLoading = true
            this.error = null

            try {
                const response = await api.post('/organizations', organizationData)
                await this.fetchOrganizations(this.pagination.current_page)
                return response
            } catch (error) {
                this.error = error.message || 'Failed to create organization'
                console.error('Error creating organization:', error)
                throw error
            } finally {
                this.isLoading = false
            }
        },

        async updateOrganization(id, organizationData) {
            //   this.isLoading = true;
            this.error = null

            try {
                const response = await api.put(`/organizations/${id}`, organizationData)

                if (this.currentOrganization && this.currentOrganization.id === id) {
                    this.currentOrganization = response
                }

                // await this.fetchOrganizations(this.pagination.current_page)
                return response
            } catch (error) {
                this.error = error.message || 'Failed to update organization'
                console.error('Error updating organization:', error)
                throw error
            } finally {
                // this.isLoading = false;
            }
        },

        async setWebsiteRating(organizationId, optionId) {
            this.error = null

            try {
                const response = await api.post(`/organizations/${organizationId}/website-ratings`, {
                    website_rating_option_id: optionId
                })

                this.applyRatingUpdate(response)
                return response
            } catch (error) {
                this.error = error.message || 'Failed to submit website rating'
                console.error('Error submitting website rating:', error)
                throw error
            }
        },

        async clearWebsiteRating(organizationId) {
            this.error = null

            try {
                const response = await api.delete(`/organizations/${organizationId}/website-ratings`)
                this.applyRatingUpdate(response)
                return response
            } catch (error) {
                this.error = error.message || 'Failed to clear website rating'
                console.error('Error clearing website rating:', error)
                throw error
            }
        },

        applyRatingUpdate(payload) {
            if (!payload || !payload.organization_id) return

            const {
                organization_id: organizationId,
                website_rating_average,
                website_rating_summary,
                website_rating_count,
                my_website_rating_option_id,
                my_website_rating_option_slug = null,
                my_website_rating_option_name = null,
                website_rating_weighted = null
            } = payload

            const update = (org) => {
                if (!org || org.id !== organizationId) return
                org.website_rating_average = website_rating_average
                org.website_rating_summary = website_rating_summary
                org.website_rating_count = website_rating_count
                org.website_rating_weighted = website_rating_weighted
                org.my_website_rating_option_id = my_website_rating_option_id
                org.my_website_rating_option_slug = my_website_rating_option_slug
                org.my_website_rating_option_name = my_website_rating_option_name
            }

            const organization = this.organizations.find((o) => o.id === organizationId)
            update(organization)

            if (this.currentOrganization && this.currentOrganization.id === organizationId) {
                update(this.currentOrganization)
            }
        },

        async deleteOrganization(id) {
            // Do not toggle global isLoading to avoid list flashing
            this.error = null

            try {
                await api.delete(`/organizations/${id}`)
                // Remove the deleted organization from local state instead of refetching
                this.organizations = this.organizations.filter(org => org.id !== id)

                // Clear currentOrganization if it was the one deleted
                if (this.currentOrganization && this.currentOrganization.id === id) {
                    this.currentOrganization = null
                }

                // Keep pagination total in sync locally
                if (typeof this.pagination.total === 'number' && this.pagination.total > 0) {
                    this.pagination.total -= 1
                }
            } catch (error) {
                this.error = error.message || 'Failed to delete organization'
                console.error('Error deleting organization:', error)
                throw error
            }
        },

        async detectWebsiteRedesign(organizationId) {
            this.error = null

            try {
                return await api.post(`/organizations/${organizationId}/website-redesigns`)
            } catch (error) {
                this.error = error.message || 'Failed to queue redesign detection'
                console.error('Error queuing redesign detection:', error)
                throw error
            }
        },

        async detectOrganizationCms(organizationId) {
            this.error = null

            try {
                return await api.post(`/organizations/${organizationId}/cms-detections`)
            } catch (error) {
                this.error = error.message || 'Failed to queue CMS detection'
                console.error('Error queuing CMS detection:', error)
                throw error
            }
        },

        async checkWebsiteStatus(organizationId) {
            this.error = null

            try {
                return await api.post(`/organizations/${organizationId}/website-status-check`)
            } catch (error) {
                this.error = error.message || 'Failed to queue website status check'
                console.error('Error queuing website status check:', error)
                throw error
            }
        },

        async runBatchOrganizationAction(action, organizationIds) {
            this.error = null

            try {
                return await api.post('/organizations/batch/actions', {
                    action,
                    organization_ids: organizationIds
                })
            } catch (error) {
                this.error = error.message || 'Failed to run batch organization action'
                console.error('Error running batch organization action:', error)
                throw error
            }
        },

        resetOrganizationRedesignData(organizationId) {
            const clearData = (org) => {
                if (!org || org.id !== organizationId) return
                org.last_major_redesign_at = null
                org.website_redesigns = []
                org.website_redesign_status = null
                org.website_redesign_status_message = null
            }

            this.organizations.forEach((organization) => {
                clearData(organization)
            })

            clearData(this.currentOrganization)
        },

        setFilters(newFilters) {
            this.filters = { ...this.filters, ...newFilters }
        },

        resetFilters() {
            this.filters = {
                search: '',
                city: '',
                state: '',
                country: '',
                category: '',
                cms: '',
                website: '',
                website_rating: '',
                website_status: [],
                sort: []
            }
        }
    }
})
