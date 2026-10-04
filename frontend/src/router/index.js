import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import ActivityLogsView from '@/views/ActivityLogsView.vue'
import { navigationRoutes } from '@/navigation'
import { useAuthStore } from '@/stores/auth'
import ChangePasswordView from '@/views/ChangePasswordView.vue'
import CompaniesView from '@/views/CompaniesView.vue'
import CompanyProfileView from '@/views/CompanyProfileView.vue'
import DealerProfileView from '@/views/DealerProfileView.vue'
import ApplicationDetailView from '@/views/ApplicationDetailView.vue'
import ApplicationsView from '@/views/ApplicationsView.vue'
import DashboardView from '@/views/DashboardView.vue'
import DealersView from '@/views/DealersView.vue'
import DocumentsIncompleteView from '@/views/DocumentsIncompleteView.vue'
import ChecklistsView from '@/views/ChecklistsView.vue'
import EmptyView from '@/views/EmptyView.vue'
import HealthView from '@/views/HealthView.vue'
import ImportsView from '@/views/ImportsView.vue'
import LoginView from '@/views/LoginView.vue'
import LookupsView from '@/views/LookupsView.vue'
import PersonsView from '@/views/PersonsView.vue'
import ProductsMasterView from '@/views/ProductsMasterView.vue'
import SettingsFeesView from '@/views/SettingsFeesView.vue'
import SettingsGeneralView from '@/views/SettingsGeneralView.vue'
import UsersView from '@/views/UsersView.vue'
import VerificationView from '@/views/VerificationView.vue'
import VerifyView from '@/views/VerifyView.vue'
import LicensesView from '@/views/LicensesView.vue'
import PreviousLicenseView from '@/views/PreviousLicenseView.vue'
import PortalApplicationsView from '@/views/PortalApplicationsView.vue'
import PortalCompanyView from '@/views/PortalCompanyView.vue'
import PortalDashboardView from '@/views/PortalDashboardView.vue'
import PortalCsrRndView from '@/views/PortalCsrRndView.vue'
import PortalDocumentsView from '@/views/PortalDocumentsView.vue'
import PortalProductsView from '@/views/PortalProductsView.vue'
import PortalStaffView from '@/views/PortalStaffView.vue'
import PortalUsersView from '@/views/PortalUsersView.vue'
import WorkflowView from '@/views/WorkflowView.vue'

const views = {
  dashboard: DashboardView,
  'activity-logs': ActivityLogsView,
  imports: ImportsView,
  companies: CompaniesView,
  dealers: DealersView,
  applications: ApplicationsView,
  licenses: LicensesView,
  users: UsersView,
  verification: VerificationView,
  'settings-general': SettingsGeneralView,
  'settings-fees': SettingsFeesView,
  'settings-lookups': LookupsView,
  persons: PersonsView,
  'settings-products': ProductsMasterView,
  'settings-checklists': ChecklistsView,
  'settings-workflow': WorkflowView,
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/health',
      name: 'health',
      component: HealthView,
      meta: { public: true },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true },
    },
    {
      path: '/verify/:token',
      name: 'verify',
      component: VerifyView,
      meta: { public: true },
    },
    {
      path: '/change-password',
      name: 'change-password',
      component: ChangePasswordView,
      meta: { requiresAuth: true },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        ...navigationRoutes().map((item) => ({
          path: item.to === '/' ? '' : item.to.slice(1),
          name: item.name,
          component: views[item.name] ?? EmptyView,
          meta: {
            title: item.title || item.label,
            permission: item.permission,
            anyOf: item.anyOf,
          },
        })),
        {
          path: 'licenses/previous',
          name: 'previous-license',
          component: PreviousLicenseView,
          meta: {
            title: 'Record a previous license',
            permission: 'licenses.issue',
          },
        },
        {
          path: 'documents-incomplete',
          name: 'documents-incomplete',
          component: DocumentsIncompleteView,
          meta: {
            title: 'Documents incomplete',
            permission: 'dashboard.view',
          },
        },
        {
          path: 'companies/:id',
          name: 'company-profile',
          component: CompanyProfileView,
          meta: {
            title: 'Company',
            permission: 'companies.view',
          },
        },
        {
          path: 'dealers/:id',
          name: 'dealer-profile',
          component: DealerProfileView,
          meta: {
            title: 'Dealer',
            permission: 'dealers.view',
          },
        },
        {
          path: 'applications/:id',
          name: 'application-detail',
          component: ApplicationDetailView,
          meta: {
            title: 'Application',
            permission: 'applications.view',
          },
        },
        {
          path: 'portal',
          name: 'portal',
          component: PortalDashboardView,
          meta: { title: 'Company portal', permission: 'portal.access' },
        },
        {
          path: 'portal/company',
          name: 'portal-company',
          component: PortalCompanyView,
          meta: { title: 'Company information', permission: 'portal.access' },
        },
        {
          path: 'portal/staff',
          name: 'portal-staff',
          component: PortalStaffView,
          meta: { title: 'Staff', permission: 'portal.access' },
        },
        {
          path: 'portal/documents',
          name: 'portal-documents',
          component: PortalDocumentsView,
          meta: { title: 'Documents', permission: 'portal.access' },
        },
        {
          path: 'portal/csr-rnd',
          name: 'portal-csr-rnd',
          component: PortalCsrRndView,
          meta: { title: 'CSR/R&D', permission: 'portal.access' },
        },
        {
          path: 'portal/products',
          name: 'portal-products',
          component: PortalProductsView,
          meta: { title: 'Products', permission: 'portal.access' },
        },
        {
          path: 'portal/applications',
          name: 'portal-applications',
          component: PortalApplicationsView,
          meta: { title: 'Applications', permission: 'portal.access' },
        },
        {
          path: 'portal/users',
          name: 'portal-users',
          component: PortalUsersView,
          meta: { title: 'Users', permission: 'portal.users.manage' },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.fetchUser()

  if (to.meta.public) {
    return true
  }

  if (to.meta.guest) {
    if (!auth.user) {
      return true
    }

    return auth.user.must_change_password
      ? { name: 'change-password' }
      : (auth.user.user_type === 'company' ? { name: 'portal' } : { name: 'dashboard' })
  }

  if (!auth.user) {
    return { name: 'login' }
  }

  if (auth.user.must_change_password && to.name !== 'change-password') {
    return { name: 'change-password' }
  }

  const companyUser = auth.user.user_type === 'company'
  const home = companyUser ? { name: 'portal' } : { name: 'dashboard' }

  if (companyUser && to.name !== 'change-password' && !to.path.startsWith('/portal')) {
    return home
  }

  if (!companyUser && to.path.startsWith('/portal')) {
    return home
  }

  if (to.meta.permission && !auth.can(to.meta.permission)) {
    return home
  }

  if (to.meta.anyOf && !auth.canAny(to.meta.anyOf)) {
    return home
  }

  return true
})

export default router
