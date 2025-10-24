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
import WebsiteRatingOptionsIndex from '@/pages/website-rating-options/WebsiteRatingOptionsIndex.vue'
import WebsiteRatings from '@/pages/WebsiteRatings.vue'

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
        path: '/website-rating-options',
        name: 'website-rating-options.index',
        component: WebsiteRatingOptionsIndex,
        meta: { requiresAuth: true }
    },
    {
        path: '/website-ratings',
        name: 'website-ratings',
        component: WebsiteRatings,
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
