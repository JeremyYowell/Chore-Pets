# Decisions Log - Chore Pets

> Newest entries first. Do not delete old decisions; mark them as superseded if needed.

## 2026-03-31 - Repository initialized and published
**Decision:** The Chore Pets MVP package was initialized as its own git repository, the default branch was normalized to `main`, and the project was published to GitHub at `JeremyYowell/Chore-Pets`.
**Reason:** The packaged skill needed durable version control and a stable remote for future work and AI handoff.
**Alternatives Rejected:** Leaving the project only on disk without repo history.
**Impact:** Future work can now be tracked, committed, reviewed, and shared through GitHub.
**AI involved:** Codex
**Status:** Active

## 2026-03-31 - Commit-safe runtime configuration pattern
**Decision:** Tracked `php/config.php` now contains safe defaults and loads secrets from untracked `php/config.local.php`; `php/config.local.php.example` documents the expected values.
**Reason:** The project was already working in dev, but publishing it safely required keeping live credentials out of git while preserving the deployed configuration model.
**Alternatives Rejected:** Committing live Dreamhost and Alexa values directly; removing config from the project entirely.
**Impact:** The repo can be shared safely while local and deployed environments still have a simple override path.
**AI involved:** Codex
**Status:** Active

## 2026-03-31 - Dreamhost PHP overrides shipped as project files
**Decision:** Dreamhost-specific PHP settings were included as `php/.user.ini`, and `.htaccess` was retained for routing and access hardening.
**Reason:** Dreamhost shared hosting does not support `php_value` directives in `.htaccess`, so the repo should reflect the deployment-ready pattern.
**Alternatives Rejected:** Storing PHP runtime settings only in undocumented hosting panel steps.
**Impact:** New environments have a clearer, more reproducible deployment path.
**AI involved:** Codex
**Status:** Active

## 2026-03-31 - Pet assets committed with generator script
**Decision:** Runtime pet assets were committed under `assets/pets/`, and a standard-library-only `assets/generate_pets.py` script was added to regenerate SVG source art.
**Reason:** The README described a generator workflow, but the actual script was missing from the package.
**Alternatives Rejected:** Leaving pet art generation undocumented or requiring manual recreation.
**Impact:** The asset pipeline is now self-contained inside the repository.
**AI involved:** Codex
**Status:** Active

## 2026-03-31 - Hosting architecture confirmed as Dreamhost PHP endpoint
**Decision:** The MVP remains a Dreamhost-hosted PHP HTTPS Alexa endpoint backed by MySQL rather than being migrated to AWS Lambda.
**Reason:** The skill is already functioning in dev with this setup, and the immediate task was packaging and versioning the working MVP rather than replatforming it.
**Alternatives Rejected:** AWS Lambda migration during MVP packaging.
**Impact:** Future work should assume Dreamhost constraints unless a deliberate migration decision is made.
**AI involved:** Jeremy + Codex
**Status:** Active
