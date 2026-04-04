# Decisions Log - Chore Pets

> Newest entries first. Do not delete old decisions; mark them as superseded if needed.

## 2026-04-04 - Runtime config now fails fast when secrets are missing
**Decision:** The tracked `php/config.php` now throws a clear runtime exception if `config.local.php` is missing or if required values are still set to placeholders.
**Reason:** A deploy temporarily replaced working runtime configuration with placeholder defaults, which led to confusing endpoint failures and delayed diagnosis.
**Alternatives Rejected:** Continuing to rely on silent fallback placeholders during live execution.
**Impact:** Future deploy mistakes should fail loudly and point directly at missing credentials rather than surfacing as generic Alexa endpoint errors.
**AI involved:** Jeremy + Codex
**Status:** Active

## 2026-04-04 - Alexa response layer hardened after invalid-response debugging
**Decision:** `php/lib/AlexaResponse.php` was hardened to escape dynamic SSML text safely and to fall back cleanly if `json_encode()` fails.
**Reason:** Alexa was reporting `INVALID_RESPONSE`, so the response builder needed protection against malformed SSML or malformed JSON bodies.
**Alternatives Rejected:** Leaving response generation permissive and depending only on catch-all exception handling.
**Impact:** Dynamic text like child names, pet names, and chore names is now safer to send back through Alexa without breaking response validation.
**AI involved:** Jeremy + Codex
**Status:** Active

## 2026-04-04 - Pet interactions now degrade safely if the new table is unavailable
**Decision:** `php/lib/PetEngine.php` now treats the `pet_interactions` feature as optional at runtime and disables it gracefully if the table cannot be queried.
**Reason:** During debugging, the new Feed/Water/Play feature was a plausible source of endpoint errors, and defensive behavior is safer during staged rollouts.
**Alternatives Rejected:** Allowing missing or inaccessible interaction storage to crash the skill.
**Impact:** The core chore skill remains usable even if the interaction feature is not fully migrated in a given environment.
**AI involved:** Jeremy + Codex
**Status:** Active

## 2026-04-03 — Feed/Water/Play feature implemented (Phase 2, task 1)
**Decision:** Implemented the complete Feed/Water/Play pet interaction system in a single
development session (April 3, 2026).
**Files changed:**
- `sql/schema.sql` — added `pet_interactions` table (child_id, interaction_type ENUM, date,
  UNIQUE key prevents double-use per day per type)
- `php/config.php` — added `PET_INTERACTION_TYPES` constant `['feed', 'water', 'play']`
- `php/lib/PetEngine.php` — added `interactionsUnlocked()`, `getTodayInteractions()`,
  `recordInteraction()` methods
- `php/lib/AlexaResponse.php` — updated `buildChildDatasource()` to include
  `interactionsUnlocked`, `interactions` (fed/watered/played booleans), `allInteractionsDone`
- `php/apl/child-view.json` — added interaction zone below pet image: purple "Thriving!
  unlocked" banner, green "all done" banner, three 82×72dp buttons per pet in row layout.
  Each button has three `when`-gated states: locked (grey 🔒), available (colored + emoji),
  used (green ✅). Feed=orange 🍖, Water=blue 💧, Play=purple 🎾. Pet image slightly reduced
  (180×190dp, was 200×220dp) to fit new zone in viewport.
- `php/index.php` — added `FeedPetIntent`, `WaterPetIntent`, `PlayWithPetIntent` cases in
  intent switch; added `feedPet`, `waterPet`, `playPet` APL event cases; added
  `handlePetInteraction(string $type, array $child)` helper function
- `interaction-model/en-US.json` — added three new intents with childName slot and natural
  language samples
**Schema migration note:** Run `ALTER TABLE` or re-run `schema.sql` on the live DB to add
the `pet_interactions` table before deploying.
**AI involved:** Claude (Cowork mode)
**Status:** Active — ready to deploy and test on Echo Show

## 2026-04-03 — Pet interactions unlock condition confirmed: Thriving only
**Decision:** Feed, Water, and Play interaction buttons on the Child Task Page unlock only when
the pet's state is **Thriving** (≥90% chore completion average over last 3 days) — not simply
when today's chores are complete. Each interaction is available once per day. All three (Feed,
Water, Play) are available simultaneously when the unlock condition is met.
**Reason:** Ties the richest reward to the highest level of consistent performance. Makes
"Thriving" the meaningful unlock condition rather than just an aesthetic label.
**Alternatives Rejected:** Unlocking on "all chores done today" (Katie's original spec) — changed
because the 5-state system is being kept and Thriving is the more appropriate trigger.
**Impact:** Backend needs a daily interaction tracking mechanism (has_fed, has_watered, has_played
per child per day). PetEngine::getState() already calculates Thriving. New DB columns or table
needed. New APL buttons needed on child-view. New intent or APL touch events needed.
**AI involved:** Jeremy + Claude (Cowork mode)
**Status:** Active — no code written yet

## 2026-04-03 — Pet state system: keep all 5 states
**Decision:** Retain the existing 5-state system (Sick, Sad, Neutral, Happy, Thriving) with its
current percentage-based calculation. Do not simplify to 3 states.
**Reason:** The 5-state system is already built, sprites exist for all 5 states, and "at minimum
three" in Katie's spec is satisfied. Thriving is now the trigger for pet interactions, making
all 5 states functionally meaningful.
**Alternatives Rejected:** Simplifying to 3 states (Happy/Sad/Sick) as described in Katie's
original spec.
**Impact:** None to existing code. `PetEngine.php` and all 5-state sprites stay as-is.
**AI involved:** Jeremy + Claude (Cowork mode)
**Status:** Active

## 2026-04-03 — Animated sprites: Option C (true frame-by-frame animation)
**Decision:** Target true frame-by-frame sprite animation for each pet × each state (Tamagotchi-
style). This requires new art assets — the current static PNGs are a placeholder, not the final
visual target.
**Reason:** The Tamagotchi emotional connection is the core design hook. Static images undercut it.
**Alternatives Rejected:** (A) APL motion effects on existing static images. (B) Bounce/scale
transitions on tap only.
**Impact:** Significant art production work. Each pet (4) × each state (5) needs an animation
sequence. APL delivery options: APNG, SVG SMIL animation, or APL AnimatedImage with a spritesheet.
Asset pipeline (`generate_pets.py`) will need to be extended or replaced for animated output.
This is a Phase 3 item — static PNGs remain in use until new art is ready.
**AI involved:** Jeremy + Claude (Cowork mode)
**Status:** Active — art production not yet started

## 2026-04-03 — Cosmetic accessories: hats, streak-earned, pulsing unlock notification
**Decision:** First cosmetic category is **hats**. Accessories are earned via chore completion
streaks (threshold TBD). When a child qualifies for a new accessory or action, a flashing/pulsing
prize icon appears in the UI to surface the reward. Children select/equip the hat on their pet.
**Reason:** Hats are visually unambiguous, easy to layer over existing sprites, and immediately
legible to kids. Streak-gating ties cosmetics to the same consistency mechanic as pet naming.
**Alternatives Rejected:** Always-available cosmetics (removes the earning mechanic).
**Impact:** Requires new artwork (hat overlay PNGs per pet), new DB tables (accessories catalog,
child_accessories with earned/equipped flags), APL layered image rendering (AbsoluteLayout with
pet image + hat overlay), new voice intent for equipping, and a pulsing/flashing unlock UI element.
Specific streak thresholds for each hat TBD.
**AI involved:** Jeremy + Claude (Cowork mode)
**Status:** Active — design and thresholds TBD before implementation

## 2026-04-03 — UI theme overhaul deferred to Phase 2
**Decision:** The current dark theme (#111827) is acceptable for now. A bright, colorful,
child-friendly theme overhaul (as specified in Katie's doc) is deferred to Phase 2.
**Reason:** No blocking issue with the current theme for functional development. Deferring keeps
Phase 2 scope clean.
**Impact:** Add to Phase 2 roadmap. All new APL work in Phase 2 should use the new palette.
**AI involved:** Jeremy + Claude (Cowork mode)
**Status:** Active — deferred

## 2026-04-03 — Pet naming unlock (7-day streak): staying
**Decision:** The 7-consecutive-day 100% completion streak → pet naming unlock feature stays in
the product. Not mentioned in Katie's spec but confirmed by Jeremy.
**Reason:** Already built and working. Adds a meaningful longer-term goal.
**Impact:** None — existing code and DB column (`naming_unlocked`) unchanged.
**AI involved:** Jeremy + Claude (Cowork mode)
**Status:** Active

## 2026-04-03 — Parent management on Echo Show: add child, add chore, voice editing only
**Decision:** Parent management accessible from a button/icon on the Home Screen. Scope for MVP
management: add a new child, add a chore to an existing child. Editing existing names/chore
descriptions is done by voice replacement (e.g., "rename Emma's chore 'make bed' to 'make your
bed'"). No keyboard editing UI — Echo Show has no keyboard.
**Reason:** Matches Echo Show capabilities. Complex form editing belongs in a future web interface.
**Impact:** Home Screen APL needs a management button. New APL management screen needed. New
intents needed for rename child, rename chore, and remove chore. The management screen is best
a simple voice-guided flow similar to onboarding.
**AI involved:** Jeremy + Claude (Cowork mode)
**Status:** Active — no code written yet

## 2026-04-03 — Katie's spec reviewed; open decisions identified
**Decision:** Katie's functional spec ("Chore Pets - Cards and functionality.docx", April 2026)
was reviewed and compared against the existing MVP codebase. Several design conflicts and
new features were identified. No code changes made — pending decisions must be resolved first.
**Reason:** Aligning both contributors' visions before further development.
**Key conflicts identified:**
- Pet state system: Katie specifies 3 states (Happy/Sad/Sick, day-based triggers) vs. current 5-state
  percentage-based system. Katie's doc says "at minimum three states," so 5-state may be acceptable.
- Theme: Katie's spec calls for bright/colorful/child-friendly UI; current app uses a dark theme.
- Animated sprites: Katie assumes animated pet sprites; current assets are static PNGs.
**New features identified (not in current MVP):**
- Feed/Water/Play interaction buttons (unlock after all chores done)
- Cosmetic accessories (hats, equippable items overlaid on pet sprites)
- Celebratory animations/sparkles on task completion
- Parent management button visible on Home Screen
**AI involved:** Claude (Cowork mode)
**Status:** Pending — see Open Decisions in PROJECT_BRIEF.md

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
