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

- This starter version uses PHP session storage only.
- No database tables are required for the first pass.
- The sentence bank can be expanded in `application/config/app.php`.
