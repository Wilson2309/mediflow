const defaultCredentials = Object.freeze({
  admin: {
    email: 'admin@mediflow.com',
    password: 'Admin123*',
    expectedUrl: '**/dashboard',
  },
  reception: {
    email: 'recepcionista@mediflow.com',
    password: 'Password123*',
    expectedUrl: '**/dashboard',
  },
  cash: {
    email: 'caja@mediflow.com',
    password: 'Password123*',
    expectedUrl: '**/dashboard',
  },
  doctor: {
    email: 'medico@mediflow.com',
    password: 'Password123*',
    expectedUrl: '**/dashboard',
  },
  superAdmin: {
    email: 'superadmin.e2e@mediflow.test',
    password: 'Password123*',
    expectedUrl: '**/super-admin/clinics',
  },
});

export const E2E_ROLES = Object.freeze({
  admin: {
    ...defaultCredentials.admin,
    email: process.env.E2E_ADMIN_EMAIL ?? defaultCredentials.admin.email,
    password: process.env.E2E_ADMIN_PASSWORD ?? defaultCredentials.admin.password,
  },
  reception: {
    ...defaultCredentials.reception,
    email: process.env.E2E_RECEPTION_EMAIL ?? defaultCredentials.reception.email,
    password: process.env.E2E_RECEPTION_PASSWORD ?? defaultCredentials.reception.password,
  },
  cash: {
    ...defaultCredentials.cash,
    email: process.env.E2E_CASH_EMAIL ?? defaultCredentials.cash.email,
    password: process.env.E2E_CASH_PASSWORD ?? defaultCredentials.cash.password,
  },
  doctor: {
    ...defaultCredentials.doctor,
    email: process.env.E2E_DOCTOR_EMAIL ?? defaultCredentials.doctor.email,
    password: process.env.E2E_DOCTOR_PASSWORD ?? defaultCredentials.doctor.password,
  },
  superAdmin: {
    ...defaultCredentials.superAdmin,
    email: process.env.E2E_SUPERADMIN_EMAIL ?? defaultCredentials.superAdmin.email,
    password: process.env.E2E_SUPERADMIN_PASSWORD ?? defaultCredentials.superAdmin.password,
  },
});

export function e2eRole(role) {
  const account = E2E_ROLES[role];

  if (!account) {
    throw new Error(`Unknown E2E role: ${role}`);
  }

  return account;
}