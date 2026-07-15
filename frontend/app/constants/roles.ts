// Built-in role names shared with the backend. `Super Admin` is a protocol
// constant — it's the `Gate::before` bypass server-side and the seeded
// `config('users.default_user.name')` — so the UI must special-case the exact
// same string wherever it gates on super-admin (authz bypass, nav visibility,
// protected-role rows). Single-sourced here so a rename can't silently diverge.
export const SUPER_ADMIN_ROLE = 'Super Admin'
