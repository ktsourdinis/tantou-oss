# Security Policy & Vulnerability Disclosure

Tantou (担当) is built to power mission-critical operations, IT departments, and public sector human capacity coordination. We prioritize enterprise-grade security, data privacy, and compliance with modern standards such as the **EU NIS2 Directive** and **GDPR**.

---

## 🛡️ Supported Versions

Security updates and vulnerability patches are actively maintained for the following versions:

| Version | Supported | Status |
| :--- | :---: | :--- |
| **1.0.x** | ✅ | **Active Support (Current Production Release)** |
| < 1.0.0 | ❌ | End of Life / Pre-release |

---

## 🔒 Security Architecture Highlights

Tantou incorporates defense-in-depth architectural safeguards:

1. **3-Tier Visibility Firewalls:**
   - Multi-lens column visibility (`public`, `internal`, `private`).
   - Strict server-side validation preventing external client requests from ever entering internal or private collaborator columns.
   - Requester anonymization for non-admin team members and clients.
2. **Multi-Tenant Data Isolation:**
   - Strict database foreign-key scoping (`tenant_id`) enforced across all Eloquent queries and background jobs.
3. **Authentication & Cryptography:**
   - High-entropy password hashing (`BCRYPT_ROUNDS=12`).
   - Timing-attack safe token comparison for magic links, password resets, and Telegram pairing tokens.
   - Ephemeral CSRF tokens and signed URLs for sensitive state modifications.
4. **Rate Limiting & Anti-Abuse:**
   - Redis-backed rate limiting on all public QR drop-boxes, tracking endpoints, authentication forms, and Telegram polling webhooks.
   - Honeypot spam protection on unauthenticated guest request forms.
5. **GDPR / NIS2 Privacy Mode:**
   - Optional sanitization of personal Telegram metadata and customer identifiers in audit logs and message broadcasts.

---

## 🚨 Reporting a Vulnerability (Responsible Disclosure)

**Please do NOT report security vulnerabilities via public GitHub issues, discussions, or social media.**

If you believe you have discovered a security vulnerability or weakness in Tantou:

1. **Private GitHub Advisory:**
   - Submit a report via GitHub's Private Vulnerability Reporting feature at:
     [https://github.com/ktsourdinis/tantou-oss/security/advisories/new](https://github.com/ktsourdinis/tantou-oss/security/advisories/new)
2. **Email Disclosure:**
   - Alternatively, report directly to the security maintainer:
     **Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO** via GitHub profile contact: [https://github.com/ktsourdinis](https://github.com/ktsourdinis)

### What to Include in Your Report
To help us triage and resolve the issue quickly, please provide:
- A clear description of the vulnerability and potential impact.
- Step-by-step reproduction instructions or a minimal Proof of Concept (PoC).
- The affected component, file, or endpoint.
- Any suggested mitigations or patches, if known.

### Our Commitment
- **Acknowledgment:** We will acknowledge receipt of your vulnerability report within 48 hours.
- **Triage & Remediation:** We will assess severity, validate the issue, and prepare an expedited patch.
- **Credit & Attribution:** Valid vulnerabilities will receive public credit in release notes and security advisories (unless you prefer anonymity).
- **Safe Harbor:** We consider security research conducted in good faith, following responsible disclosure without data destruction or service disruption, to be authorized and protected under our safe harbor policy.
