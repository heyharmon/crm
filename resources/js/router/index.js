import { createRouter, createWebHistory } from 'vue-router'

// Import pages
import Home from '@/pages/Home.vue'
import Login from '@/pages/auth/Login.vue'
import Register from '@/pages/auth/Register.vue'
import TeamIndex from '@/pages/teams/TeamIndex.vue'
import TeamShow from '@/pages/teams/TeamShow.vue'
// Organization pages
import OrganizationIndex from '@/pages/organizations/OrganizationIndex.vue'
import OrganizationShow from '@/pages/organizations/OrganizationShow.vue'
import OrganizationCreate from '@/pages/organizations/OrganizationCreate.vue'
import OrganizationEdit from '@/pages/organizations/OrganizationEdit.vue'
import OrganizationImport from '@/pages/organizations/OrganizationImport.vue'
import OrganizationCategoriesIndex from '@/pages/organization-categories/OrganizationCategoriesIndex.vue'
import OrganizationWebsiteRatingOptions from '@/pages/organization-websites/OrganizationWebsiteRatingOptions.vue'
import OrganizationWebsiteRatings from '@/pages/organization-websites/OrganizationWebsiteRatings.vue'

const routes = [
    {
        path: '/',
        name: 'home',
        component: Home,
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
        path: '/organizations/:id',
        name: 'organizations.show',
        component: OrganizationShow,
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
        path: '/organization-websites/options',
        name: 'organization-websites.options',
        component: OrganizationWebsiteRatingOptions,
        meta: { requiresAuth: true }
    },
    {
        path: '/organization-websites/ratings',
        name: 'organization-websites.ratings',
        component: OrganizationWebsiteRatings,
        meta: { requiresAuth: true }
    },
    {
        path: '/website-rating-options',
        redirect: { name: 'organization-websites.options' },
        meta: { requiresAuth: true }
    },
    {
        path: '/website-ratings',
        redirect: { name: 'organization-websites.ratings' },
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
            next({ name: 'home' })
        } else {
            next()
        }
    } else {
        next()
    }
})

export default router
