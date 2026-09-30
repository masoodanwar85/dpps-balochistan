export const navigation = [
  {
    label: 'Dashboard',
    title: 'DIRECTORATE OF PLANT PROTECTION - MAIN DASHBOARD',
    to: '/',
    name: 'dashboard',
    permission: 'dashboard.view',
  },
  {
    label: 'Companies',
    title: 'Companies',
    to: '/companies',
    name: 'companies',
    permission: 'companies.view',
  },
  {
    label: 'Dealers',
    to: '/dealers',
    name: 'dealers',
    permission: 'dealers.view',
  },
  {
    label: 'Persons (CNIC search)',
    title: 'Persons',
    to: '/persons',
    name: 'persons',
    permission: 'persons.view',
  },
  {
    label: 'Applications',
    to: '/applications',
    name: 'applications',
    permission: 'applications.view',
  },
  {
    label: 'Verification Queue',
    to: '/verification',
    name: 'verification',
    anyOf: ['staff.verify', 'documents.verify', 'products.verify'],
  },
  {
    label: 'Licenses',
    to: '/licenses',
    name: 'licenses',
    anyOf: ['licenses.issue', 'licenses.suspend', 'licenses.cancel', 'licenses.restore'],
  },
  {
    label: 'Settings',
    children: [
      { label: 'General', title: 'Settings › General', to: '/settings/general', name: 'settings-general', permission: 'settings.manage' },
      { label: 'Checklists', title: 'Settings › Checklists', to: '/settings/checklists', name: 'settings-checklists', permission: 'checklists.manage' },
      { label: 'Workflow', title: 'Settings › Workflow', to: '/settings/workflow', name: 'settings-workflow', permission: 'workflow.manage' },
      { label: 'Lookups', title: 'Settings › Lookups', to: '/settings/lookups', name: 'settings-lookups', permission: 'lookups.manage' },
      { label: 'Products Master', title: 'Settings › Products Master', to: '/settings/products', name: 'settings-products', permission: 'products_master.manage' },
    ],
  },
  {
    label: 'Users & Roles',
    to: '/users',
    name: 'users',
    anyOf: ['users.manage', 'roles.manage'],
  },
  {
    label: 'Activity Logs',
    title: 'Activity Log',
    to: '/activity-logs',
    name: 'activity-logs',
    permission: 'activity_logs.view',
  },
  {
    label: 'Data Import',
    title: 'Data Import',
    to: '/imports',
    name: 'imports',
    anyOf: ['imports.run', 'imports.resolve'],
  },
]

function allowed(item, permissions) {
  if (item.permission) {
    return permissions.includes(item.permission)
  }

  if (item.anyOf) {
    return item.anyOf.some((permission) => permissions.includes(permission))
  }

  return false
}

export const portalNavigation = [
  { label: 'Dashboard', title: 'Company portal', to: '/portal', name: 'portal', permission: 'portal.access' },
  { label: 'Company Info', title: 'Company information', to: '/portal/company', name: 'portal-company', permission: 'portal.access' },
  { label: 'Staff', title: 'Staff', to: '/portal/staff', name: 'portal-staff', permission: 'portal.access' },
  { label: 'Documents', title: 'Documents', to: '/portal/documents', name: 'portal-documents', permission: 'portal.access' },
  { label: 'Products', title: 'Products', to: '/portal/products', name: 'portal-products', permission: 'portal.access' },
  { label: 'Applications', title: 'Applications', to: '/portal/applications', name: 'portal-applications', permission: 'portal.access' },
  { label: 'Users', title: 'Users', to: '/portal/users', name: 'portal-users', permission: 'portal.users.manage' },
]

export function visibleNavigation(permissions, source = navigation) {
  return source.flatMap((item) => {
    if (item.children) {
      const children = item.children.filter((child) => allowed(child, permissions))

      return children.length ? [{ ...item, children }] : []
    }

    return allowed(item, permissions) ? [item] : []
  })
}

export function navigationRoutes() {
  return navigation.flatMap((item) => item.children ?? [item])
}
