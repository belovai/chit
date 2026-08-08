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
          path: 'receipts/:hashId/review',
          name: 'receipt-review',
          component: () => import('../views/ReceiptReviewView.vue'),
        },
        {
          path: 'pipelines',
          name: 'pipelines',
          component: () => import('../views/PipelineRunsView.vue'),
        },
        {
          path: 'pipelines/:hashId',
          name: 'pipeline-detail',
          component: () => import('../views/PipelineRunDetailView.vue'),
        },
        // The `transactions` entry must come BEFORE `transactions/:hashId`,
        // and the static `new` child before the param branch — otherwise the
        // param branch would swallow the `/transactions/new` route.
        {
          path: 'transactions',
          name: 'transactions',
          component: () => import('../views/TransactionsView.vue'),
          children: [
            {
              path: 'new',
              name: 'transaction-new',
              components: {
                modal: () => import('../views/TransactionFormSlideOver.vue'),
              },
            },
          ],
        },
        {
          path: 'transactions/:hashId',
          name: 'transaction-detail',
          component: () => import('../views/TransactionDetailView.vue'),
          children: [
            {
              path: 'edit',
              name: 'transaction-edit',
              components: {
                modal: () => import('../views/TransactionFormSlideOver.vue'),
              },
            },
          ],
        },
        {
          path: 'settings',
          component: () => import('../views/settings/SettingsLayoutView.vue'),
          children: [
            { path: '', redirect: { name: 'settings-account' } },
            {
              path: 'account',
              name: 'settings-account',
              component: () => import('../views/settings/SettingsAccountView.vue'),
            },
            {
              path: 'merchants',
              name: 'settings-merchants',
              component: () => import('../views/settings/SettingsMerchantsView.vue'),
              children: [
                {
                  path: 'new',
                  name: 'settings-merchant-new',
                  components: {
                    modal: () => import('../views/settings/MerchantFormSlideOver.vue'),
                  },
                },
                {
                  path: ':hashId',
                  name: 'settings-merchant-edit',
                  components: {
                    modal: () => import('../views/settings/MerchantFormSlideOver.vue'),
                  },
                },
              ],
            },
            {
              path: 'products',
              name: 'settings-products',
              component: () => import('../views/settings/SettingsProductsView.vue'),
              children: [
                {
                  path: 'new',
                  name: 'settings-product-new',
                  components: {
                    modal: () => import('../views/settings/ProductFormModal.vue'),
                  },
                },
                {
                  path: ':hashId',
                  name: 'settings-product-edit',
                  components: {
                    modal: () => import('../views/settings/ProductFormModal.vue'),
                  },
                },
              ],
            },
            {
              path: 'tags',
              name: 'settings-tags',
              component: () => import('../views/settings/SettingsTagsView.vue'),
            },
            {
              path: 'ai',
              name: 'settings-ai',
              component: () => import('../views/settings/SettingsAiView.vue'),
              children: [
                {
                  path: 'new',
                  name: 'settings-ai-new',
                  components: {
                    modal: () => import('../views/settings/AiCredentialFormSlideOver.vue'),
                  },
                },
                {
                  path: ':hashId',
                  name: 'settings-ai-edit',
                  components: {
                    modal: () => import('../views/settings/AiCredentialFormSlideOver.vue'),
                  },
                },
              ],
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
