# BUILDFLOW — SECURITY & DATA PROTECTION POLICIES

## 1. Multi-Tenant Isolation
- **Global Scoping**: Every business query is bound to the active `company_id`.
- **Server-Side Enforcement**: All controller endpoints verify that requested entity IDs belong to the user's active tenant session, preventing Insecure Direct Object References (IDOR).
- **Session Protection**: Tenant switches regenerate the session ID and invalidate cached authorization credentials.

---

## 2. Authentication & Authorization
- **Password Security**: Strong hashing with Bcrypt/Argon2.
- **Login Throttling**: Rate-limiting on authentication endpoints (5 attempts per minute per IP/email).
- **Session Invalidation**: Immediate session regeneration on privilege escalation, password change, or logout.
- **Granular RBAC**: Role-based permissions controlling `view`, `create`, `edit`, `approve`, and `delete` across all modules.

---

## 3. Financial & Operational Integrity
- **Double-Entry Balance Verification**: Journal entries with unequal debits and credits throw database transaction rollbacks.
- **Immutable Financial Ledgers**: Posted journals, invoices, and stock movements cannot be hard-deleted.
- **Safe Precision**: All monetary values are computed using exact decimal arithmetic (`bcmath` or string decimals) and stored in `DECIMAL(15,2)` columns.

---

## 4. Input Sanitization & Attack Mitigation
- **SQL Injection**: Parameterized PDO bindings via Eloquent ORM. No raw SQL concatenations.
- **Cross-Site Scripting (XSS)**: Blade automatic output escaping (`{{ ... }}`) for all user inputs.
- **CSRF**: Automatic CSRF token verification across all non-idempotent HTTP methods (`POST`, `PUT`, `PATCH`, `DELETE`).
- **File Upload Hardening**: Strict MIME-type validation, size limits (max 10MB), and private disk storage preventing direct execution of user-submitted scripts.
