# Styling changes

- Make all styling changes in the SCSS source files under `styles/scss/`. Do not make CSS-only changes or edit generated CSS directly.
- After every SCSS change, compile all SCSS entry points into `styles/css/` so the styles loaded by the live site reflect the source changes:

  ```sh
  npx --no-install sass styles/scss:styles/css
  ```

- Include the regenerated CSS and source maps with the SCSS changes. Verify that compilation succeeds and that the affected pages display the updated styles before considering the work complete.
