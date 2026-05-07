# Fix These Sentences Streak App

## Structure

- `application/config/app.php`
  Holds app name, provider settings, database settings, and the static fallback sentence bank.
- `application/database/mysql/schema.sql`
  MySQL table definition for offline template-driven exercises.
- `application/database/mysql/seed_challenge_templates.sql`
  Seed data for the template engine.
- `application/service/service.php`
  Handles session state, provider fallback order, sentence selection, and grading.
- `application/service/database.php`
  PDO MySQL challenge repository, template validation, weighted selection, and rendering.
- `application/service/openai.php`
  Optional OpenAI generation and grading layer.
- `application/templates/layouts/base.php`
  Base HTML layout.
- `application/templates/pages/home.php`
  Main streak page.
- `public_html/index.php`
  App entry point.
- `public_html/api/check_sentence.php`
  JSON endpoint for checking answers and advancing the streak flow.
- `public_html/api/health.php`
  Simple health endpoint.
- `public_html/css/app.css`
  App styling.
- `public_html/js/app.js`
  Frontend request and UI update logic.

## Minimal Feature Set

- show one incorrect sentence
- accept a corrected version
- check whether the correction matches the expected sentence or an accepted variant
- track current streak in session
- track best streak in session
- rotate to the next sentence after every submission

## Notes

- This app uses PHP session storage only.
- MySQL templates are the primary offline question source.
- The static sentence bank in `application/config/app.php` remains the last-resort fallback.

## Environment Setup

The app now loads a root-level `.env` file automatically from:

- `/Users/waos/Sites/zavout_fixsentences/.env`

Use `.env.example` as the template. Server-defined environment variables still take precedence over values in `.env`.

Set these environment variables for database-backed question generation and grading:

- `FTSS_CHALLENGE_PROVIDER`
  `database`, `openai`, or `static`. Defaults to `database`.
- `FTSS_GRADING_PROVIDER`
  `database`, `openai`, or `static`. Defaults to `database`.
- `FTSS_DB_HOST`
  MySQL host name. Defaults to `127.0.0.1`.
- `FTSS_DB_PORT`
  MySQL port. Defaults to `3306`.
- `FTSS_DB_NAME`
  MySQL database name.
- `FTSS_DB_USER`
  MySQL username.
- `FTSS_DB_PASS`
  MySQL password.
- `FTSS_DB_CHARSET`
  Defaults to `utf8mb4`.
- `FTSS_DB_CONNECT_TIMEOUT`
  Connection timeout in seconds. Defaults to `3`.

Optional OpenAI variables:

- `OPENAI_API_KEY`
  Required only for OpenAI mode.
- `FTSS_OPENAI_MODEL`
  Defaults to `gpt-4o-mini`.
- `FTSS_OPENAI_TIMEOUT`
  Request timeout in seconds. Defaults to `20`.
- `FTSS_OPENAI_API_KEY_ENV`
  Optional override for the API key variable name. Defaults to `OPENAI_API_KEY`.
- `FTSS_OPENAI_BASE_URL`
  Optional override for the OpenAI base URL. Defaults to `https://api.openai.com/v1`.

## MySQL Setup

Create the table and seed the templates:

```sql
SOURCE application/database/mysql/schema.sql;
SOURCE application/database/mysql/seed_challenge_templates.sql;
```

The seed set includes 25 grammar templates across:

- subject-verb agreement
- auxiliary verb choice
- past participles
- singular/plural agreement
- article usage
- prepositions
- simple tense consistency

## Runtime Behavior

- On page load, challenge generation follows the configured fallback order:
  - `openai -> database -> static` when `FTSS_CHALLENGE_PROVIDER=openai`
  - `database -> static` when `FTSS_CHALLENGE_PROVIDER=database`
  - `static` when `FTSS_CHALLENGE_PROVIDER=static`
- On answer submission, grading follows the configured fallback order:
  - `openai -> database -> static` when `FTSS_GRADING_PROVIDER=openai`
  - `database -> static` when `FTSS_GRADING_PROVIDER=database`
  - `static` when `FTSS_GRADING_PROVIDER=static`
- Database grading accepts the canonical corrected sentence plus any pre-rendered accepted variants, while ignoring case and terminal punctuation differences.
- Invalid template rows are skipped safely. If no valid database templates are available, the app falls back to the static sentence bank.
- `public_html/api/health.php` reports the active providers, provider fallback order, and whether OpenAI/MySQL are currently available to PHP.
