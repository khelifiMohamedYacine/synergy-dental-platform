import { adminRequest, findStaff } from './global-setup.js'

// Leave no usable test account behind (it stays listed, deactivated, because it appears in the
// status history of the enquiries it handled).
export default async function globalTeardown() {
  const staff = await findStaff()
  if (staff?.is_active) await adminRequest(`/users/${staff.id}`, { method: 'PATCH', body: { is_active: false } })
}
