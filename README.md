# Chore Pets – Setup Guide

A private Amazon Alexa skill for the Echo Show 10 with daily chore lists, virtual pets,
and voice-driven onboarding. Hosted on Dreamhost via HTTPS.

## Packaging Notes

- `php/config.php` is now commit-safe and reads real values from `php/config.local.php` when present.
- Start from `php/config.local.php.example` when setting up a new environment.
- `php/.user.ini` is included for Dreamhost FastCGI overrides.
- `assets/pets/` contains committed runtime PNG assets for Alexa APL plus SVG source art.
- `assets/generate_pets.py` regenerates the SVG source art only.

---

## Project Structure

```
Chore Skill/
├── README.md                        ← this file
├── sql/
│   └── schema.sql                   ← run once to create the database
├── assets/
│   ├── generate_pets.py             ← run once to generate the 20 SVG pet images
│   └── pets/                        ← generated SVGs land here (cat/dog/hamster/panda × 5 states)
├── interaction-model/
│   └── en-US.json                   ← paste into Alexa Developer Console
└── php/                             ← upload entire folder to Dreamhost
    ├── .htaccess
    ├── config.php                   ← edit with your credentials before uploading
    ├── index.php                    ← Alexa HTTPS endpoint
    ├── lib/
    │   ├── Database.php
    │   ├── PetEngine.php
    │   ├── ChoreManager.php
    │   └── AlexaResponse.php
    └── apl/
        ├── home.json
        ├── child-view.json
        ├── pet-select.json
        └── onboarding.json
```

---

## Step 1 – Generate Pet Graphics

Run the SVG generator once on any machine with Python 3:

```bash
cd "Chore Skill/assets"
python generate_pets.py
```

This creates 20 files in `assets/pets/` (e.g. `cat-happy.svg`, `panda-sick.svg`).

---

## Step 2 – Set Up the Dreamhost Database

1. Log in to the **Dreamhost Panel** → Goodies → MySQL Databases.
2. Create a new database (e.g. `chore_champion`) and a dedicated MySQL user with full privileges on it.
3. Note the **hostname** shown (e.g. `mysql.yourdomain.com`).
4. Connect to the database with any MySQL client (phpMyAdmin is available in Dreamhost Panel) and run:

```sql
-- contents of sql/schema.sql
```

Or upload and run `schema.sql` directly via phpMyAdmin → Import.

---

## Step 3 – Configure `php/config.php`

Copy `php/config.local.php.example` to `php/config.local.php`, then fill in your values:

```php
define('ALEXA_SKILL_ID',  'amzn1.ask.skill.XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX');
define('VERIFY_SIGNATURES', true);   // set false only during local testing

define('DB_HOST', 'mysql.yourdomain.com');
define('DB_NAME', 'chore_champion');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Public HTTPS URL where pet SVGs will live after upload
define('ASSETS_URL', 'https://yourdomain.com/chores/assets');
```

Leave all other constants at their defaults unless you want to adjust pet state thresholds or the naming streak length.

`config.php` stays safe to commit and reads `config.local.php` automatically when it exists.

---

## Step 4 – Upload Files to Dreamhost

Upload the entire `php/` folder and the `assets/pets/` folder to your Dreamhost web root.
Recommended layout on the server:

```
public_html/
└── chores/
    ├── .htaccess
    ├── config.php
    ├── index.php
    ├── lib/
    ├── apl/
    └── assets/
        └── pets/          ← SVG files go here
```

The HTTPS URL for the endpoint will be: `https://yourdomain.com/chores/index.php`

> **Dreamhost note:** Make sure your domain has an active SSL certificate (free Let's Encrypt
> via the Dreamhost panel under Domains → Manage Domains → HTTPS).

---

## Step 5 – Create the Alexa Skill

1. Go to [developer.amazon.com/alexa](https://developer.amazon.com/alexa) and sign in with your Amazon account.
2. Click **Create Skill**.
   - Skill name: **Chore Pets**
   - Primary locale: **English (US)**
   - Model: **Custom**
   - Hosting: **Provision your own** (not Alexa-hosted)
3. Click **Create Skill**.

---

## Step 6 – Configure the Interaction Model

1. In the left sidebar, click **Interaction Model → JSON Editor**.
2. Replace the entire contents with the contents of `interaction-model/en-US.json`.
3. Click **Save Model**, then **Build Model**. Wait for the build to finish (a minute or two).

---

## Step 7 – Set the HTTPS Endpoint

1. In the left sidebar, click **Endpoint**.
2. Select **HTTPS**.
3. In the **Default Region** box, enter:
   ```
   https://yourdomain.com/chores/index.php
   ```
4. Under the SSL certificate dropdown, select:
   **My development endpoint has a certificate from a trusted certificate authority**
   (Let's Encrypt qualifies.)
5. Click **Save Endpoints**.

---

## Step 8 – Copy the Skill ID into config.php

1. In the Alexa Developer Console, go to **Your Skills** and click the **Chore Pets** tile.
2. The **Skill ID** is displayed at the top (starts with `amzn1.ask.skill.`).
3. Copy it into `php/config.php` → `ALEXA_SKILL_ID`, then re-upload `config.php`.

---

## Step 9 – Enable the Skill on Your Echo Show 10

Because this is a private (development) skill, it's only available to accounts linked to your
Amazon Developer account.

1. Open the **Alexa app** on your phone.
2. Tap **More → Skills & Games → Your Skills → Dev**.
3. Find **Chore Pets** and tap **Enable to Use**.
4. The skill is now live on your Echo Show 10.

---

## Step 10 – Test It

Say: **"Alexa, open Chore Pets"**

The Echo Show will display the onboarding screen and Alexa will walk you through adding children,
chores, and choosing a virtual pet. After setup, say a child's name to see their chore list.

### Useful test phrases

| Phrase | What it does |
|--------|-------------|
| "Alexa, open Chore Pets" | Launch the skill |
| "Emma" | Show Emma's chore list |
| "How is Emma's pet?" | Pet happiness check |
| "Name Emma's pet Whiskers" | Name the pet (requires 7-day streak) |
| "Help" | List available commands |
| "Stop" | Close the skill |

---

## Troubleshooting

**Alexa says "There was a problem with the requested skill's response"**
- Check your Dreamhost error logs (Panel → Manage Domains → Logs).
- Common causes: PHP syntax error, wrong DB credentials, HTTPS certificate not trusted.

**Pet images not showing on Echo Show**
- Verify the `ASSETS_URL` in config.php matches the actual URL of the `assets/pets/` folder.
- Check that SVG files are uploaded and publicly accessible (open one in a browser).

**Signature verification failing**
- Temporarily set `VERIFY_SIGNATURES` to `false` in config.php to rule this out.
- Re-enable before leaving the skill in use.

**Skill not appearing in Alexa app under Dev Skills**
- Make sure you're signed into the Alexa app with the same Amazon account used in the Developer Console.

---

## Future Enhancements (Out of MVP Scope)

- X-times-per-week recurring tasks (not daily)
- Pet hunger/feeding mechanic separate from chores
- Image-only mode for very young children
- Multiple households / family sharing
- Push notifications ("reminder: you haven't done your chores yet!")
- Public skill certification and Alexa Skills Store listing

---

## Pet States Reference

| State | Threshold | Label |
|-------|-----------|-------|
| Thriving | ≥ 90% avg (last 3 days) | Super happy! 🌟 |
| Happy | ≥ 70% | Feeling good! 😊 |
| Neutral | ≥ 50% | Doing okay 😐 |
| Sad | ≥ 25% | Missing you 😢 |
| Sick | < 25% | Not feeling great 😷 |

Pet naming unlocks after **7 consecutive days** of 100% chore completion.
