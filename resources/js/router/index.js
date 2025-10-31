import { createRouter, createWebHistory } from 'vue-router'
import auth from '@/services/auth'

// Import pages
import Dashboard from '@/pages/Dashboard.vue'
import Login from '@/pages/auth/Login.vue'
import Register from '@/pages/auth/Register.vue'
import UsersIndex from '@/pages/users/UsersIndex.vue'
import UserShow from '@/pages/users/UserShow.vue'
// Organization pages
import OrganizationIndex from '@/pages/organizations/OrganizationIndex.vue'
import OrganizationCreate from '@/pages/organizations/OrganizationCreate.vue'
import OrganizationImport from '@/pages/organizations/OrganizationImport.vue'
import OrganizationCategoriesIndex from '@/pages/organization-categories/OrganizationCategoriesIndex.vue'
import WebsiteRatingOptions from '@/pages/websites/WebsiteRatingOptions.vue'
import WebsiteRatings from '@/pages/websites/WebsiteRatings.vue'
import MyWebsiteRatings from '@/pages/websites/MyWebsiteRatings.vue'

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: Dashboard,
        meta: { requiresAuth: true, roles: ['admin'] }
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
        path: '/users',
        name: 'users.index',
        component: UsersIndex,
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/users/:id',
        name: 'users.show',
        component: UserShow,
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/organizations',
        name: 'organizations.index',
        component: OrganizationIndex,
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/organizations/browse',
        redirect: { name: 'organizations.index', query: { view: 'grid' } },
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/organizations/create',
        name: 'organizations.create',
        component: OrganizationCreate,
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/organizations/import',
        name: 'organizations.import',
        component: OrganizationImport,
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/organization-categories',
        name: 'organization-categories.index',
        component: OrganizationCategoriesIndex,
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/websites/options',
        name: 'websites.options',
        component: WebsiteRatingOptions,
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/websites/ratings',
        name: 'websites.ratings',
        component: WebsiteRatings,
        meta: { requiresAuth: true, roles: ['admin', 'guest'] }
    },
    {
        path: '/websites/my-ratings',
        name: 'websites.my-ratings',
        component: MyWebsiteRatings,
        meta: { requiresAuth: true, roles: ['admin', 'guest'] }
    },
    {
        path: '/website-rating-options',
        redirect: { name: 'websites.options' },
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/website-ratings',
        redirect: { name: 'websites.ratings' },
        meta: { requiresAuth: true, roles: ['admin', 'guest'] }
    },
    {
        path: '/organization-websites/options',
        redirect: { name: 'websites.options' },
        meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
        path: '/organization-websites/ratings',
        redirect: { name: 'websites.ratings' },
        meta: { requiresAuth: true, roles: ['admin', 'guest'] }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Navigation guard for authentication and authorization
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token')
    const userRole = auth.getUserRole()

    if (to.matched.some((record) => record.meta.requiresAuth)) {
        if (!token) {
            next({ name: 'login' })
        } else {
            // Check role-based access
            const allowedRoles = to.meta.roles
            if (allowedRoles && !allowedRoles.includes(userRole)) {
                // Redirect guests to their allowed page
                next({ name: 'websites.ratings' })
            } else {
                next()
            }
        }
    } else if (to.matched.some((record) => record.meta.guest)) {
        if (token) {
            // Redirect based on role after login
            if (userRole === 'admin') {
                next({ name: 'dashboard' })
            } else {
                next({ name: 'websites.ratings' })
            }
        } else {
            next()
        }
    } else {
        next()
    }
})

export default router
