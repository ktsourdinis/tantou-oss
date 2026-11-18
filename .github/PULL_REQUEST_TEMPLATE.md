## 📝 Description of Changes

<!-- Please provide a clear and concise description of what this PR does, why it is needed, and link to any related issues. -->

Fixes / Closes #(issue)

### Type of Change
- [ ] 🐛 Bug fix (non-breaking change which fixes an issue)
- [ ] ✨ New feature (non-breaking change which adds functionality)
- [ ] ⚡ Performance improvement
- [ ] 🎨 Code style / Refactor
- [ ] 📝 Documentation update
- [ ] 🔒 Security fix

---

## 🧪 Testing & Verification Checklist

Please verify that you have tested your changes locally:

- [ ] Formatted all PHP code with Laravel Pint:
  ```bash
  vendor/bin/pint --test
  ```
- [ ] Executed the complete test suite with 100% passing tests:
  ```bash
  php artisan test
  ```
- [ ] Compiled frontend assets without errors (if Blade / CSS / JS changed):
  ```bash
  npm run build
  ```
- [ ] Tested multi-tenant isolation and 3-tier firewall rules (if modifying capacity or task logic).
- [ ] Tested localization strings with `__('...')` and updated `lang/el.json` (if adding new UI text).

---

## 📜 Contributor License Agreement (CLA)

> [!IMPORTANT]
> To protect the open-source community, ensure project sustainability, and allow dual-licensing (AGPL-3.0-or-later and Commercial licenses for Tantou Cloud & Enterprise), all contributions require acceptance of the CLA outlined in [CONTRIBUTING.md](CONTRIBUTING.md).

- [ ] **I have read CONTRIBUTING.md and hereby agree to the Contributor License Agreement (CLA), granting the project maintainer the right to distribute my contribution under both AGPL-3.0-or-later and Commercial licenses.**
