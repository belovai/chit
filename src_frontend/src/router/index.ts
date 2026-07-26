import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: () => import('../components/layout/AppShell.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('../views/DashboardView.vue'),
        },
        {
          path: 'receipts',
          name: 'receipts',
          component: () => import('../views/ReceiptsView.vue'),
        },
        {
          path: 'transactions',
          name: 'transactions',
          component: () => import('../views/TransactionsView.vue'),
        },
        {
          path: 'settings',
          component: () => import('../views/settings/SettingsLayoutView.vue'),
          children: [
            { path: '', redirect: { name: 'settings-profile' } },
            {
              path: 'profile',
              name: 'settings-profile',
              component: () => import('../views/settings/SettingsProfileView.vue'),
            },
            {
              path: 'merchants',
              name: 'settings-merchants',
              component: () => import('../views/settings/SettingsMerchantsView.vue'),
            },
            {
              path: 'merchants/new',
              name: 'settings-merchant-new',
              component: () => import('../views/settings/SettingsMerchantEditView.vue'),
            },
            {
              path: 'merchants/:hashId',
              name: 'settings-merchant-edit',
              component: () => import('../views/settings/SettingsMerchantEditView.vue'),
            },
            {
              path: 'tags',
              name: 'settings-tags',
              component: () => import('../views/settings/SettingsTagsView.vue'),
            },
          ],
        },
      ],
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
