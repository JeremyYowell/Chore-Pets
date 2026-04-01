# PROJECT BRIEF - Chore Pets

## Project

Chore Pets is a private Amazon Alexa custom skill for Jeremy's household. It helps kids track daily chores, earn progress toward pet happiness, and interact with the system through both voice and Echo Show touch screens.

## Product Goal

Provide a simple, kid-friendly daily chores experience that:
- lets a parent onboard children and their chores by voice
- shows each child's daily checklist on Echo Show
- rewards consistency with virtual pet states and pet naming unlocks
- stays lightweight enough to run reliably on Dreamhost shared hosting

## Current Status

- MVP is functioning in the Alexa development environment.
- Backend is deployed on Dreamhost and responds correctly for Alexa-originated requests.
- Core onboarding flow is working: add child, add chores, select pet, continue for more children.
- Daily use flow is working: open skill, select child, list chores, mark chores complete, check pet status.
- Local git repo has been initialized and published to GitHub.

## Hosting

- Platform: Amazon Alexa Skills Kit
- Backend: PHP HTTPS endpoint on Dreamhost shared hosting
- Endpoint path: `/chores/index.php`
- Public repo: `https://github.com/JeremyYowell/Chore-Pets`
- Runtime config pattern: tracked `php/config.php` plus untracked `php/config.local.php`

## Tech Stack

- PHP backend
- MySQL / MariaDB on Dreamhost
- Alexa interaction model JSON
- Alexa Presentation Language (APL) JSON for Echo Show UI
- Python 3 helper script for regenerating SVG pet source assets

## Key Files

- `php/index.php`: Alexa endpoint and request routing
- `php/config.php`: commit-safe config defaults loader
- `php/lib/Database.php`: PDO connection wrapper
- `php/lib/ChoreManager.php`: household, child, chore, and completion CRUD
- `php/lib/PetEngine.php`: pet state and naming logic
- `php/lib/AlexaResponse.php`: Alexa response and APL helpers
- `interaction-model/en-US.json`: Alexa interaction model
- `sql/schema.sql`: database schema
- `assets/pets/`: committed pet images

## Constraints

- Alexa requests must return valid JSON within 8 seconds.
- Dreamhost shared hosting rules apply: use `.htaccess` plus `.user.ini`; do not rely on `php_value` directives.
- Do not commit live credentials or secrets.
- Pet image URLs must remain publicly reachable over HTTPS for Echo Show rendering.
- The endpoint should not be treated like a generic public API test target; normal requests without Alexa context can return `Bad Request`.

## Known Environment Notes

- The working live local secrets live in `php/config.local.php` and are intentionally gitignored.
- PHP CLI is not installed in this Codex environment, so syntax linting may need to happen elsewhere if required.
- OneDrive can occasionally interfere with git lock files during local repository operations.

## Next Likely Tasks

- Improve project docs and setup guidance further if needed.
- Add issue tracking or roadmap notes for post-MVP features.
- Optionally harden Alexa request verification for production usage if the skill moves beyond dev-only use.
- Optionally add tests or a lightweight local request fixture workflow.
