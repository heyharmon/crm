import { createRouter, createWebHistory } from 'vue-router'

// Import pages
import Dashboard from '@/pages/Dashboard.vue'
import Login from '@/pages/auth/Login.vue'
import Register from '@/pages/auth/Register.vue'
import TeamIndex from '@/pages/teams/TeamIndex.vue'
import TeamShow from '@/pages/teams/TeamShow.vue'
// Organization pages
import OrganizationIndex from '@/pages/organizations/OrganizationIndex.vue'
import OrganizationCreate from '@/pages/organizations/OrganizationCreate.vue'
import OrganizationEdit from '@/pages/organizations/OrganizationEdit.vue'
import OrganizationImport from '@/pages/organizations/OrganizationImport.vue'
import OrganizationCategoriesIndex from '@/pages/organization-categories/OrganizationCategoriesIndex.vue'
import WebsiteRatingOptions from '@/pages/websites/WebsiteRatingOptions.vue'
import WebsiteRatings from '@/pages/websites/WebsiteRatings.vue'

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: Dashboard,
        meta: { requiresAuth: true }
    },
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { guest: true }
    },
    {
        path: '/register',
        name: 'register',
        component: Register,
        meta: { guest: true }
    },
    {
        path: '/teams',
        name: 'teams.index',
        component: TeamIndex,
        meta: { requiresAuth: true }
    },
    {
        path: '/teams/:id',
        name: 'teams.show',
        component: TeamShow,
        meta: { requiresAuth: true }
    },
    {
        path: '/organizations',
        name: 'organizations.index',
        component: OrganizationIndex,
        meta: { requiresAuth: true }
    },
    {
        path: '/organizations/browse',
        redirect: { name: 'organizations.index', query: { view: 'grid' } },
        meta: { requiresAuth: true }
    },
    {
        path: '/organizations/create',
        name: 'organizations.create',
        component: OrganizationCreate,
        meta: { requiresAuth: true }
    },
    {
        path: '/organizations/import',
        name: 'organizations.import',
        component: OrganizationImport,
        meta: { requiresAuth: true }
    },
    {
        path: '/organizations/:id/edit',
        name: 'organizations.edit',
        component: OrganizationEdit,
        meta: { requiresAuth: true }
    },
    {
        path: '/organization-categories',
        name: 'organization-categories.index',
        component: OrganizationCategoriesIndex,
        meta: { requiresAuth: true }
    },
    {
        path: '/websites/options',
        name: 'websites.options',
        component: WebsiteRatingOptions,
        meta: { requiresAuth: true }
    },
    {
        path: '/websites/ratings',
        name: 'websites.ratings',
        component: WebsiteRatings,
        meta: { requiresAuth: true }
    },
    {
        path: '/website-rating-options',
        redirect: { name: 'websites.options' },
        meta: { requiresAuth: true }
    },
    {
        path: '/website-ratings',
        redirect: { name: 'websites.ratings' },
        meta: { requiresAuth: true }
    },
    {
        path: '/organization-websites/options',
        redirect: { name: 'websites.options' },
        meta: { requiresAuth: true }
    },
    {
        path: '/organization-websites/ratings',
        redirect: { name: 'websites.ratings' },
        meta: { requiresAuth: true }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Navigation guard for authentication
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token')

    if (to.matched.some((record) => record.meta.requiresAuth)) {
        if (!token) {
            next({ name: 'login' })
        } else {
            next()
        }
    } else if (to.matched.some((record) => record.meta.guest)) {
        if (token) {
            next({ name: 'dashboard' })
        } else {
            next()
        }
    } else {
        next()
    }
})

export default router
