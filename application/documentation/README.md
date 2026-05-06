# Fix These Sentences Streak App

## Structure

- `application/config/app.php`
  Holds app name, tagline, and the starter sentence bank.
- `application/service/service.php`
  Handles session state, sentence selection, and answer checking.
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
- check whether the correction matches the expected sentence
- track current streak in session
- track best streak in session
- rotate to the next sentence after every submission

## Notes

- This app uses PHP session storage only.
- No database tables are required for the current version.
- The sentence bank in `application/config/app.php` is now the fallback source if OpenAI is unavailable.

## OpenAI Setup

The app now loads a root-level `.env` file automatically from:

- `/Users/waos/Sites/zavout_fixsentences/.env`

Use `.env.example` as the template. Server-defined environment variables still take precedence over values in `.env`.

Set these environment variables for OpenAI-backed question generation and grading:

- `OPENAI_API_KEY`
  Required for OpenAI mode.
- `FTSS_CHALLENGE_PROVIDER`
  `openai` or `static`. Defaults to `openai`.
- `FTSS_GRADING_PROVIDER`
  `openai` or `static`. Defaults to `openai`.
- `FTSS_OPENAI_MODEL`
  Defaults to `gpt-4o-mini`.
- `FTSS_OPENAI_TIMEOUT`
  Request timeout in seconds. Defaults to `20`.
- `FTSS_OPENAI_API_KEY_ENV`
  Optional override for the API key variable name. Defaults to `OPENAI_API_KEY`.
- `FTSS_OPENAI_BASE_URL`
  Optional override for the OpenAI base URL. Defaults to `https://api.openai.com/v1`.

## Runtime Behavior

- On page load, the app attempts to generate the current challenge with OpenAI when `FTSS_CHALLENGE_PROVIDER=openai`.
- On answer submission, the app attempts to grade the answer with OpenAI when `FTSS_GRADING_PROVIDER=openai`.
- If the OpenAI API key is missing, the HTTP request fails, or the model returns invalid JSON, the app falls back to the static sentence bank and local exact-match grading.
- `public_html/api/health.php` reports the active providers and whether an OpenAI API key is currently available to PHP.
