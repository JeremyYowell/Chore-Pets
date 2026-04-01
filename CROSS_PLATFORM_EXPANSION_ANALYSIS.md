# Cross-Platform Expansion Analysis

Date: 2026-03-31

## Purpose

This document evaluates how Chore Pets could evolve from an Alexa-only MVP into a broader platform product that may support:

- Web
- Android
- Apple devices
- Google ecosystem surfaces

It is written as a future decision aid, not as a commitment to execute immediately.

## Executive Summary

The best long-term path is not to keep treating Alexa as the center of the system.

Instead, Chore Pets should evolve into:

1. A platform-neutral backend and domain model
2. A first-class web application, ideally a PWA
3. Native or near-native mobile clients for Android and iOS
4. Voice adapters layered on top of the shared backend

This matters because the current Alexa implementation is tightly coupled to Alexa request/response handling, Alexa session state, and Alexa-specific interaction model concepts. That is fine for an MVP, but it is not the right center of gravity for a multi-platform product.

The most practical expansion order is:

1. Web/PWA
2. Android app
3. iOS app
4. Android voice via Google Assistant App Actions
5. Apple voice via App Intents / Siri / Shortcuts

Important reality: Google does not currently offer a clean equivalent to the old "custom Alexa skill for a smart speaker" path for this kind of non-smart-home chore app. As of this analysis, Google's Conversational Actions were sunset on June 13, 2023, and the current official Google Assistant path is App Actions for Android apps. That means "Google Home support" is best interpreted as either:

- support for Android devices using Google Assistant, or
- support for Google Home hardware only indirectly through web/mobile surfaces,

not as a straightforward custom voice app running on Google smart speakers in the same way this Alexa skill works today.

This is an inference from Google's current official platform guidance.

## Current State

Today the repo contains:

- An Alexa custom skill backend in PHP
- Alexa interaction model JSON
- Echo Show APL screen definitions
- MySQL-backed chore and pet logic
- Dreamhost deployment assumptions

The codebase already has one important strength for future expansion:

- Core chore and pet behavior is backed by MySQL rather than being stored only in Alexa session state or an Alexa-native persistence layer

That is a strong starting point. However, the current code is still packaged as an Alexa-first application rather than as a reusable platform core.

## What "Cross-Platform" Should Mean for This Project

For Chore Pets, cross-platform should mean:

- one canonical household/account model
- one chore engine
- one pet/progress engine
- one authenticated API surface
- multiple user experiences that consume that API

Those user experiences could include:

- Alexa skill
- Web app for parents and children
- Android app
- iPhone/iPad app
- Assistant/Siri actions that launch into native app flows or complete lightweight tasks

The system should not duplicate business rules separately in Alexa, Android, iOS, and web.

## Best Practices

## 1. Make the backend the source of truth

Move all durable business rules into a platform-neutral backend layer:

- households
- children
- chores
- completion history
- pet states
- naming unlock rules
- streak logic
- settings

The backend should own these rules. Client apps should render and invoke them, not reimplement them.

### Why this matters

If each client reimplements pet state thresholds, streak logic, or completion rules, the product will drift and bug fixes will multiply.

## 2. Split "domain logic" from "channel adapters"

Architecturally, the project should separate into:

- Domain layer: chores, pets, streaks, permissions, validation
- Application/API layer: HTTP endpoints, auth, versioning
- Channel adapters:
  - Alexa adapter
  - Web UI
  - Android UI
  - iOS UI
  - Android Assistant integration
  - Apple App Intents integration

Today, much of the Alexa flow lives in `php/index.php`. Over time, that file should become thinner and delegate more logic to reusable services.

## 3. Introduce a formal app API

The current backend behaves like a private Alexa webhook, not a public app API. For cross-platform support, create a proper application API with:

- explicit routes
- authenticated requests
- stable JSON contracts
- API versioning
- structured error responses
- audit/logging hooks

Suggested first API areas:

- auth/session
- household profile
- children CRUD
- chores CRUD
- daily checklist retrieval
- mark chore complete / incomplete
- pet status
- onboarding progress

## 4. Add real authentication and authorization

Alexa currently identifies households through Alexa user context. That is not sufficient for multi-platform support.

Future support needs:

- parent/admin accounts
- child-safe access model
- session or token-based auth
- household membership model
- password reset / sign-in flows or passwordless email magic links
- optional family invitation flow

Recommended principle:

- parents manage structure and settings
- children can view and complete only their own chores unless explicitly allowed more access

## 5. Design around a single UI model, not a single screen technology

The product should define shared concepts like:

- Household
- Child summary card
- Daily chore list
- Pet state card
- Streak / reward status
- Parent onboarding flow

Then each platform should map those concepts into its own UI patterns rather than copying Alexa screen structures directly.

APL is not a portable frontend technology. It should be treated as one presentation layer only.

## 6. Prefer web-first for parent/admin experiences

For a household chores product, the parent/admin workflow is usually:

- configure children
- create and edit chores
- review progress
- adjust pets/settings/rewards

Those tasks are often easier on the web than by voice. A web app should likely become the primary parent control surface.

This also reduces friction for future mobile apps because both Android and iOS can consume the same backend API the web app uses.

## 7. Use PWA principles for maximum reach

A good first cross-platform client is likely a responsive web app that is:

- installable
- mobile friendly
- reliable in weak connectivity
- fast to load
- usable on tablets and phones

This gives the broadest reach for the least platform-specific effort.

## 8. Keep voice integrations narrow and task-oriented

Voice works best for:

- "What chores do I have today?"
- "Mark brushing teeth done"
- "How is Emma's pet?"
- "Open Chore Pets"

Voice works poorly for:

- full family onboarding
- editing many chores
- complex settings management
- conflict resolution

Best practice is to let voice handle quick commands and status, while web/mobile handle richer management tasks.

## 9. Add observability before adding platforms

Before expansion, add:

- request logging
- error tracking
- API timing metrics
- platform/client tagging
- basic analytics events

Without this, debugging platform-specific problems will become much harder.

## 10. Build contract tests before multiplying clients

Once multiple clients exist, regressions become expensive. Add tests for:

- pet state calculations
- streak calculations
- chore completion behavior
- onboarding validation
- JSON response contracts

The goal is for backend behavior to remain consistent regardless of client.

## Platform-by-Platform Reality Check

## Alexa

Current state:

- Already working
- Best preserved as one client on top of a broader backend

Future role:

- Quick voice interaction
- Echo Show family dashboard surface

## Web

This is the strongest next platform.

Why:

- lowest incremental platform risk
- best admin/parent UX
- broad device reach
- easiest path to productizing the current backend
- useful even if native mobile apps are delayed

Recommended web scope:

- parent login
- household settings
- child setup
- chore editing
- daily child view
- pet status and streak history

Best implementation style:

- responsive web app
- PWA features where worthwhile
- backend API consumed over authenticated HTTPS

## Android

Android is a strong second platform, especially because Google Assistant App Actions depend on having an Android app.

Recommended Android scope:

- daily child checklist
- parent management views
- push notifications/reminders
- optional home screen widget

Recommended architecture:

- clear data layer / repository layer
- UI layer driven by backend data
- deep links for navigation targets

## Apple devices

Apple support is best understood as:

- iPhone / iPad app
- App Intents for Siri and Shortcuts
- widgets / shortcuts later if valuable

There is no Siri-only hosted equivalent that cleanly mirrors the current Alexa webhook model. Apple voice support is most naturally attached to an iOS app.

Recommended Apple scope:

- daily child checklist
- parent management views
- Siri shortcuts for lightweight tasks

## Google ecosystem

This needs careful wording.

### What is feasible

- Android app support
- Google Assistant App Actions for Android app features
- Possibly installable web support on devices with browsers

### What is not a straightforward fit

- A custom Google Assistant smart-speaker experience comparable to legacy custom voice Actions

Google sunset Conversational Actions on June 13, 2023. Current official Assistant guidance emphasizes App Actions for Android apps, while Google Home APIs focus on smart home entities like structures, rooms, devices, and automations.

For Chore Pets, that means:

- Google Assistant support is realistic on Android if an Android app exists
- Google Home smart home APIs do not appear to be a natural fit for a chores/pet checklist product
- attempting a "Google Home version of the Alexa skill" is likely to create confusion and wasted effort

This conclusion is an inference from current official documentation and product positioning.

## Recommended Target Architecture

## Phase 0 architecture

Current:

- Alexa webhook endpoint
- MySQL
- Alexa-specific request/response flow

## Phase 1 target architecture

- Core service layer in backend
- Formal authenticated API
- Alexa adapter calling shared services
- Web client calling same API

## Phase 2 target architecture

- Android app consuming same API
- iOS app consuming same API
- Assistant / Siri integrations launching app functionality or invoking lightweight actions

## Suggested backend shape

- `Domain`
  - household rules
  - child rules
  - chore rules
  - pet rules
  - streak rules

- `Application services`
  - onboarding service
  - completion service
  - pet status service
  - child dashboard service

- `Adapters`
  - Alexa adapter
  - REST/JSON API adapter
  - admin CLI or scripts if needed later

## Effort Estimate

These estimates assume one experienced full-stack engineer, with AI help, working part-time to moderately focused. They are directional planning estimates, not promises.

## Option A: Minimal cross-platform foundation only

Scope:

- extract reusable backend services
- add authenticated JSON API
- keep Alexa working
- no new frontend yet

Estimated effort:

- 2 to 4 weeks

Risk:

- moderate

Value:

- high architectural payoff
- low immediate user-facing payoff

## Option B: Foundation + Web/PWA

Scope:

- Option A
- responsive web frontend
- parent/admin management
- child daily checklist view

Estimated effort:

- 5 to 9 weeks total

Risk:

- moderate

Value:

- very high

This is the most balanced next step.

## Option C: Foundation + Web/PWA + Android app

Scope:

- Option B
- Android app
- deep links
- App Actions exploration

Estimated effort:

- 9 to 15 weeks total

Risk:

- moderate to high

Value:

- high if Android usage is a priority

## Option D: Foundation + Web/PWA + Android + iOS

Scope:

- Option C
- iPhone/iPad app
- App Intents / Siri / Shortcuts

Estimated effort:

- 14 to 24 weeks total

Risk:

- high

Value:

- highest long-term product reach

## Voice-specific incremental effort

### Alexa hardening only

- 1 to 2 weeks

### Android App Actions after Android app exists

- 1 to 3 weeks incremental

### Apple App Intents after iOS app exists

- 1 to 3 weeks incremental

### "Google Home smart speaker parity with Alexa skill"

Estimated effort:

- not recommended as a planning target right now

Reason:

- current Google platform paths do not map cleanly to this product shape

## Recommended Execution Sequence

## Phase 1 - Decision and architecture prep

1. Confirm desired expansion goal:
   - web-first household app
   - Android voice support
   - iPhone support
   - all of the above
2. Decide whether Chore Pets will remain a private household tool or become a more general product.
3. Define the canonical account/household model.
4. Define API boundaries and auth strategy.

## Phase 2 - Backend refactor

1. Extract business logic from Alexa routing into reusable services.
2. Add an authenticated JSON API.
3. Add API docs and fixtures.
4. Add tests for pet, streak, and chore logic.
5. Preserve Alexa behavior as a regression target.

## Phase 3 - Web/PWA

1. Build parent/admin flows.
2. Build child daily checklist views.
3. Add installability and responsive layout.
4. Add notifications/reminders only if truly valuable.

## Phase 4 - Android

1. Build app around API-backed repositories.
2. Support deep links for task-specific destinations.
3. Add widgets or notifications if useful.
4. Add App Actions for narrow high-value voice tasks.

## Phase 5 - iOS

1. Build app around the same API contracts.
2. Add App Intents for narrow high-value actions.
3. Add Siri/Shortcuts support where it improves daily use.

## Phase 6 - Optional platform polish

1. Family invitations
2. Reward customization
3. Push reminders
4. History and analytics
5. Parental dashboards

## Recommended Near-Term Decision

If the project expands, the recommended next investment is:

- first, refactor the backend into a reusable app/API core
- second, build a web/PWA experience
- third, choose Android or iOS next based on actual household usage

This is likely better than trying to jump directly from Alexa to multiple native platforms at once.

## Multi-Agent Execution Strategy

If this work is executed later using multiple agents, it should be split by ownership boundaries with minimal overlap.

## Suggested agent breakdown

### Agent 1 - Backend/API architecture

Owns:

- service extraction
- auth design
- route design
- API contracts
- tests for shared business logic

Deliverables:

- service layer
- API endpoints
- schema changes if needed
- contract docs

### Agent 2 - Alexa preservation

Owns:

- ensuring existing Alexa functionality still works
- adapting Alexa handlers to new services
- regression checklist for onboarding and daily use

Deliverables:

- updated Alexa adapter
- regression notes
- compatibility fixes

### Agent 3 - Web/PWA frontend

Owns:

- responsive UI
- installability
- parent/admin flows
- child checklist flows

Deliverables:

- web client
- design tokens
- PWA manifest/service worker if chosen

### Agent 4 - Android client

Owns:

- Android app architecture
- repositories and API client
- key screens
- deep links
- App Actions research and implementation

Deliverables:

- Android app module
- App Actions integration

### Agent 5 - iOS client

Owns:

- iOS app architecture
- API client
- key screens
- App Intents / Siri shortcuts

Deliverables:

- iOS app module
- App Intents integration

### Agent 6 - QA / contract verification

Owns:

- API contract validation
- cross-platform logic parity checks
- regression matrix

Deliverables:

- test cases
- bug list
- acceptance checklist

## Multi-agent sequencing

Recommended order:

1. Backend/API architecture agent starts first
2. Alexa preservation agent works in parallel once service boundaries are known
3. Web/PWA agent starts once initial API contracts stabilize
4. Android and iOS agents start after API contracts and auth approach settle
5. QA/verification agent runs throughout but intensifies near integration

## Risks

## Product risks

- trying to make voice do too much
- expanding platform count before clarifying primary household workflows
- overbuilding private-household software before validating what is actually useful day to day

## Technical risks

- coupling new clients too tightly to Alexa-era data structures
- weak auth model
- inconsistent business logic across clients
- insufficient tests
- Dreamhost constraints becoming painful once API usage expands

## Operational risks

- no observability
- no migration plan for schema changes
- no staging environment for new clients

## Questions to Answer Before Execution

1. Is the main goal family convenience for one household, or a reusable product architecture?
2. Should children have their own logins, or should devices stay shared?
3. Is the parent workflow expected to live mostly on web or mobile?
4. Does Jeremy care more about Android first, iPhone first, or web first?
5. Is "Google Home support" actually desired on smart speakers, or is Android Assistant support sufficient?
6. Is Dreamhost still the right long-term backend host if app/API traffic grows?

## Recommended Future Decision Statement

If this project expands beyond Alexa, the recommended decision is:

"Refactor Chore Pets into a platform-neutral backend plus authenticated API, then build a web/PWA client first. Treat Alexa as one adapter, add Android and iOS apps after the shared API is stable, and treat Google Assistant and Siri as app-attached voice layers rather than as the primary product surface."

## Sources

Official sources reviewed for this analysis:

- Google Assistant Conversational Actions sunset overview: https://developers.google.com/assistant/ca-sunset
- Google Assistant for Android / App Actions overview: https://developer.android.com/guide/app-actions/overview
- Build App Actions: https://developer.android.com/develop/devices/assistant/get-started
- Android architecture guide: https://developer.android.com/topic/architecture
- Android architecture recommendations: https://developer.android.com/topic/architecture/recommendations
- Google Home APIs overview: https://developers.home.google.com/apis
- Google Home APIs for Android overview: https://developers.home.google.com/apis/android/overview
- Google Home APIs for iOS overview: https://developers.home.google.com/apis/ios/overview
- Apple App Intents documentation: https://developer.apple.com/documentation/AppIntents/app-intents
- Siri for developers: https://developer.apple.com/siri/
- Requesting authorization to use Siri: https://developer.apple.com/documentation/sirikit/requesting-authorization-to-use-siri
- Progressive Web Apps overview: https://web.dev/learn/pwa/progressive-web-apps
- PWA checklist: https://web.dev/articles/pwa-checklist
