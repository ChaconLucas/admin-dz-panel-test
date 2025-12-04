# Copilot / AI Agent Instructions for this repo

Purpose

- Short: help AI coding agents be productive on this small static site.
- Scope: only document _discoverable_ patterns from the repository (no aspirational rules).

Big picture

- This is a minimal static single-page dashboard: `index.html` is the app, `style.css` contains CSS (currently empty), and static assets live in `images/` (e.g., `images/logo.png`).
- No backend, build tools, or test harness are present. Treat this as a static website project.

Key files to reference

- `index.html` — primary source of truth: sidebar structure, `aside` markup, repeated `.sidebar` items, and use of Google Material Symbols (via external font link).
- `style.css` — project styles (file exists but currently empty). Update with minimal, focused edits only.
- `images/` — static assets; reference paths exactly as used in HTML (e.g., `images/logo.png`).

Patterns & conventions (from code)

- Single-page layout: the app content is entirely inside `index.html`; do not split into multiple HTML pages unless explicitly requested.
- Sidebar items use repeated `<div class="sidebar">` blocks. When modifying, preserve the existing structure and class names to avoid breaking CSS selectors or future JS that may rely on them.
- Icon usage: the project links Google Material Symbols with `<link href="https://fonts.googleapis.com/...">` in the head; prefer using `<span class="material-symbols-sharp">ICON_NAME</span>` for icons (see existing usage).
- Assets: images are loaded with relative paths from root (e.g., `images/logo.png`). Do not change to absolute paths.

Developer workflows (what works here)

- Local preview: open `index.html` in a browser, or run a simple HTTP server from the repo root. Examples (PowerShell):

```powershell
# if Python is available
python -m http.server 8000
# or using npm http-server (if node installed)
npx http-server -p 8080
```

- Editor Live Preview: the recommended quick workflow is to use a Live Server extension in VS Code to avoid CORS/asset path issues.

What NOT to assume

- There is no build step, package.json, or tests. Do not introduce new build tooling without user approval.
- There is no JS file currently. If you add JS, keep it minimal and reference it in `index.html`.

Examples to cite in edits

- When adding a new sidebar item, follow existing pattern:

```html
<div class="sidebar">
  <a href="#">
    <span class="material-symbols-sharp"><h3>Reports</h3></span>
  </a>
</div>
```

- Preserve `aside` and `.container` wrapper when changing layout to avoid breaking semantics.

Merge guidance (if this file already existed)

- Preserve any historical guidance in the existing `.github/copilot-instructions.md` that references repo-specific decisions.
- Keep the final file concise (20–50 lines); prefer actionable examples over long-winded rules.

If something is unclear or missing

- Ask the repo owner whether they plan to add build tooling, JavaScript, or tests before proposing such changes.
- Ask where CSS styles are expected to live if a different structure (e.g., `scss/` or component CSS) is preferred.

Next steps I can take

- Add small starter CSS rules to `style.css` and preview locally.
- Create a minimal `README.md` with preview instructions if you want a developer-friendly entry point.

Please review this draft and tell me any missing specifics (e.g., desired CSS conventions, planned JS, or preferred preview commands).
