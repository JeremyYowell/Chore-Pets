# PROJECT BRIEF - Chore Pets

## Project

Chore Pets is a private Amazon Alexa custom skill for Jeremy's household. It helps kids track daily chores, earn progress toward pet happiness, and interact with the system through both voice and Echo Show touch screens.

## Product Goal

Provide a simple, kid-friendly daily chores experience that:
- lets a parent onboard children and their chores by voice
- shows each child's daily checklist on Echo Show
- rewards consistency with virtual pet states and pet naming unlocks
- stays lightweight enough to run reliably on Dreamhost shared hosting

## Design Vision (from Katie's Spec — April 2026)

The intended feel is **Tamagotchi-style** — the pet's emotional state is a direct reflection of
how consistently the child completes tasks. The experience should feel:
- Playful, colorful, and child-friendly (bright colors, rounded UI, large tap targets)
- Animated: pet sprites should react and move (not just static images)
- Two roles (Parent/Child) but no login — both accessed from the same screen

**Note:** The current UI uses a dark theme (#111827). Katie's spec calls for bright, colorful,
child-friendly aesthetics. A theme overhaul is in the roadmap.

## Current Status

- MVP is functioning in the Alexa development environment.
- Backend is deployed on Dreamhost and responds correctly for Alexa-originated requests.
- Core onboarding flow is working: add child, add chores, select pet, continue for more children.
- Daily use flow is working: open skill, select child, list chores, mark chores complete, check pet status.
- Local git repo has been initialized and published to GitHub.
- Runtime config now fails fast if `config.local.php` is missing or still using placeholder values.
- Alexa response generation has been hardened to escape dynamic SSML safely and to fall back cleanly if JSON encoding fails.
- **Phase 2 in progress (April 2026):** Feed/Water/Play interaction system fully implemented
  in code — schema, backend logic, APL buttons, voice intents all written.
  Live schema support is in place, and the backend now degrades safely if interaction storage is unavailable.

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
- Server deploys must preserve `php/config.local.php`; tracked code can be updated independently of secrets.
- PHP CLI is not installed in this Codex environment, so syntax linting may need to happen elsewhere if required.
- OneDrive can occasionally interfere with git lock files during local repository operations.

## Open Decisions (resolved April 2026 — see DECISIONS_LOG.md for full rationale)

| Decision | Resolution |
|----------|-----------|
| Pet state system | **Keep all 5 states** (Sick/Sad/Neutral/Happy/Thriving, %-based). Thriving is the unlock condition for pet interactions. |
| Feed/Water/Play mechanic | **Unlocks when pet is Thriving.** One use each per day. Pure reward animations — no separate health metric. |
| Animated sprites | **Option C: true frame-by-frame animation** (new art assets required per pet × per state). Static PNGs used until art is ready. Phase 3. |
| Cosmetic accessories | **Hats first**, earned via streak. Pulsing/flashing prize icon surfaces new unlocks. Child equips by voice or tap. DB + layered APL rendering needed. |
| Dark vs bright theme | **Deferred to Phase 2.** Current dark theme acceptable for now. All new Phase 2 APL work should target the bright/colorful palette. |
| Pet naming (7-day streak) | **Staying.** Existing code and DB column unchanged. |
| Parent management UI | **Add child + add chore from Home Screen.** Editing/renaming done by voice. No keyboard UI — Echo Show only. |

## Feature Roadmap (post-MVP)

### Phase 2 — Core UX improvements
- [x] Feed/Water/Play interaction buttons on Child Task Page — implemented April 2026.
      Unlocks when pet is Thriving, once per day each. `pet_interactions` DB table, APL buttons,
      voice intents, and APL touch events all complete. **Deploy: run schema migration on live DB.**
- [ ] Pulsing/flashing prize icon when a new cosmetic or action is newly unlocked
- [ ] Celebratory animation/sparkles on Echo Show when all chores complete
- [ ] Parent management button on Home Screen → guided voice flow to add child / add chore /
      rename child or chore by voice
- [ ] Bright/colorful theme overhaul (current dark #111827 palette → child-friendly colors)
- [ ] Remove chore / deactivate child (voice-driven, post-setup)

### Phase 3 — Enrichment
- [ ] Cosmetic accessories — **hats first**. Earned via streak (thresholds TBD).
      Rendered as PNG overlay on pet sprite via APL AbsoluteLayout. Child equips by tap or voice.
      Requires: hat artwork per pet, accessories DB table, child_accessories DB table, APL layering.
- [ ] Animated pet sprites — true frame-by-frame animation (art production required per pet × state).
      APL delivery: APNG or APL AnimatedImage with spritesheet. Static PNGs remain until art is ready.
- [ ] X-times-per-week recurring tasks (not daily-only)

### Phase 4 — Platform expansion
- [ ] Web/PWA parent management interface (better suited than Echo Show for complex editing)
- [ ] Android/iOS apps
- [ ] Push notifications / reminders
- [ ] Multiple households / family sharing

See `CROSS_PLATFORM_EXPANSION_ANALYSIS.md` for detailed platform roadmap.

## Next Likely Tasks

1. **Deploy Feed/Water/Play** — run schema migration (`pet_interactions` table) on live Dreamhost
   DB, upload changed files, rebuild interaction model in dev console, test on Echo Show.
2. **Parent management from Home Screen** — add gear/settings icon to `home.json` header,
   create a new `manage.json` APL screen, add voice intents (RenameChildIntent, RemoveChoreIntent,
   RenameChoreIntent).
3. **Celebratory animation** — APL `animateItem` sequence triggered on all-chores-complete event.
4. **Cosmetic accessories DB schema** — design `accessories` and `child_accessories` tables even
   before art is ready, so the unlock logic can be built and tested with placeholder images.
5. Optionally harden Alexa request verification for production usage if the skill moves beyond dev-only use.
6. Optionally add tests or a lightweight local request fixture workflow.
