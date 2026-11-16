# E11 — Dark mode

**Goal:** any user can use the app with a dark theme. By default it follows the theme of their operating system, and from the header they can force light or dark. The choice is remembered in their browser, so every page, the login included, opens in the right theme without flashing.

Related decisions: PD-8, PD-20, PD-41, PD-74, PD-75, PD-76, PD-77, PD-78, PD-79.

## Acceptance criteria

- With no stored choice, the app uses the theme of the operating system and switches when the system theme changes, without reloading (PD-74).
- A browser that reports no color scheme preference gets the light theme (PD-74).
- The header of every logged-in page has a "Tema" dropdown with "Sistema", "Claro" and "Oscuro", showing the current choice (PD-76).
- Choosing "Claro" or "Oscuro" applies that theme at once and keeps it after a reload and on `/login`, `/setup` and `/activate/:token` (PD-75).
- Choosing "Sistema" again makes the app follow the operating system again (PD-74).
- A missing or unknown stored value behaves as "Sistema" (PD-75).
- The page is painted in the resolved theme from the start: no flash of the light theme before the dark one (PD-75).
- Every color token has a dark value, and every text/background pair the UI uses reaches WCAG AA contrast (4.5:1) in both themes (PD-77).
- Native controls (inputs, selects, checkboxes, scrollbars) match the active theme (`color-scheme`).
- The activation QR code is dark on light in both themes (PD-78).
- Each supermarket keeps a recognisable brand color with a readable foreground in the dark theme (PD-79).
- All new copy is Spanish in `es.json` (PD-8).

## Tasks

### Theme tokens

- [x] E11.1 Add a contrast test to `shared/ui/theme.test.ts` that reads the tokens of `frontend/src/index.css` for the light theme and checks that every pair the UI uses (`foreground` and `muted` on `background` and `surface`, `primary`/`danger` with their `-foreground`, and each supermarket with its `-foreground`) reaches 4.5:1 (PD-77). The current light palette must pass. If a pair fails, stop and ask before changing a light color.
- [x] E11.2 Add the dark palette to `index.css`: every color token is redefined under `:root[data-theme="dark"]`, with `color-scheme: dark` there and `color-scheme: light` on `:root`. Extend the E11.1 test so every color token has a dark value and every pair also passes in the dark theme. The supermarket tokens get dark shades that recall each brand (PD-79). Propose the shades in the pull request for the product owner to review (PD-77).
- [x] E11.3 Keep the activation QR code dark on light in both themes (PD-78): add the fixed tokens `qr` and `qr-background`, which are not redefined for the dark theme, add them to `cn()`, and make `QrCode` use them. Test that `QrCode` uses the QR tokens instead of `background`/`foreground`.

### Feature `theme` (frontend)

- [x] E11.4 Add `features/theme/domain`: a `ThemePreference` (`system`, `light`, `dark`), `parseThemePreference(value)` (anything unknown or missing → `system`, PD-75), and `resolveTheme(preference, systemPrefersDark)` → `light` | `dark` (`system` follows the system; no system preference counts as light, PD-74).
- [x] E11.5 Add `features/theme/infrastructure`: a `ThemePreferenceStore` port in `application/` and a `LocalStorageThemePreferenceStore` adapter under one `localStorage` key. Reading never throws: an unavailable `localStorage` or an unknown value gives `system`. Writing `system` removes the key. Test with an in-memory fake and with jsdom's `localStorage`.
- [x] E11.6 Apply the stored theme before the first paint (PD-75): an inline script in `frontend/index.html` reads the same key and `matchMedia('(prefers-color-scheme: dark)')`, and sets `data-theme` on `<html>` before the bundle loads. Keep the key and the resolution rule in one place shared with E11.4/E11.5, or cover the script with a test that checks it matches `resolveTheme` for every preference and system value.
- [x] E11.7 Add `useThemePreference()` in `features/theme/application`: it returns the current preference and `setPreference`. Setting a preference stores it and updates `data-theme` on `<html>` at once. While the preference is `system`, it listens to `prefers-color-scheme` changes and updates `data-theme` live, and it stops listening on unmount or when the preference changes (PD-74). Test with a fake `matchMedia` and the in-memory store.

### UI

- [x] E11.8 Add the presentational `ThemeSelect` (`features/theme/ui/components`): a labelled `Select` with "Sistema", "Claro" and "Oscuro", the current value selected, and `onChange` with the new preference. Add `ThemeSelectContainer`, which wires it to `useThemePreference()`, and render it in the `AppShell` header next to `SessionMenuContainer` (PD-76). Add the copy to `es.json` (`theme.label` "Tema", `theme.options.system` "Sistema", `theme.options.light` "Claro", `theme.options.dark` "Oscuro"). Test that choosing an option updates `data-theme` and the stored choice.

### End to end

- [ ] E11.9 Add `frontend/e2e/theme.spec.ts`: with an emulated light system scheme, a logged-in user picks "Oscuro" in the header and `<html>` has `data-theme="dark"`. After a reload it is still dark, and after logging out `/login` is dark too. Back in the app, picking "Sistema" with an emulated dark system scheme keeps it dark, and switching the emulated scheme to light makes it light without reloading.

### Docs

- [ ] E11.10 Update `docs/architecture.md` → Frontend structure (the `theme` feature, the `data-theme` mechanism, the inline script, the QR tokens and the token list) and the README (dark mode in "What it does" and the MVP scope table).
