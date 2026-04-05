---
name: copc-sdm-search-ux
description: >
  Use this skill when improving, enhancing, or fixing the search experience
  for the COPC-SDM (Certificate of Program Compliance Management System).
  The system is already built in vanilla PHP, JavaScript, and Tailwind CSS.
  Trigger whenever the user asks to improve search, add keyword highlighting,
  fix search behavior, add filter chips, improve result feedback, or make the
  search smarter — even if they don't use exact technical terms. Do NOT generate
  full UI rebuilds or React/JSX artifacts. Output vanilla JS and PHP snippets
  that integrate into the existing system.
---

# COPC-SDM Search & Filter UX Skill

This skill guides Claude in **improving the search experience** of an already-built system:

**COPC-SDM — Certificate of Program Compliance Management System**
Stack: Vanilla PHP · Vanilla JavaScript · Tailwind CSS

The system already has a working search bar, filter dropdowns (Document Type,
Program, Approval Year), and a results table. The goal is to make searching
faster, smarter, and more feedback-rich — without rebuilding what already works.

---

## Ground Rules

- **Do NOT rewrite the full UI.** Work with what exists.
- **Do NOT suggest React, Vue, Alpine, or any JS framework.** Vanilla JS only.
- **Do NOT use CSS outside Tailwind utility classes** unless absolutely necessary
  for a micro-interaction (e.g., `<mark>` highlight color).
- **Output focused, drop-in snippets** — a function, an event listener, a PHP
  helper — not full page rewrites.
- Always ask which file or section to target if context is ambiguous.

---

## Output Format

When the user asks to improve something specific, output:

1. **The specific JS function or PHP snippet** that handles the improvement
2. **Where to place it** (e.g., "add this after line X in search.js", "replace
   the existing `renderRows()` function")
3. **What it replaces or extends** in the existing code

Keep snippets short and surgical. If a change touches both PHP and JS, separate
them clearly with labeled code blocks.

---

## 1. Keyword Highlighting (Priority #1)

This is the highlight feature of the system. When rendering search results,
every matched keyword in the visible columns must be visually highlighted.

**Implementation approach (vanilla JS):**

- After fetching results (AJAX or page reload), run a `highlight(text, query)`
  function over each cell's text content before inserting into the DOM.
- Wrap matched substrings in `<mark>` tags.
- Style `<mark>` with Tailwind: `bg-yellow-200 text-yellow-900 rounded px-0.5`
  or a small inline `<style>` block if Tailwind's purge removes it.

```js
function highlight(text, query) {
  if (!query || query.length < 2) return text;
  const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark>$1</mark>');
}
```

**Apply to columns:** School, Program, Student Names, and any OCR content field.

**Debounce the search input** to avoid re-rendering on every keystroke:

```js
let debounceTimer;
searchInput.addEventListener('input', function () {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => runSearch(), 300);
});
```

---

## 2. Live Result Count & Feedback

Users need to know immediately how many records match. Add a count display
beneath the search bar that updates in real time.

- Show: `1,245 results found` when results exist
- Show: `No results for "keyword"` when empty
- Show nothing when the search is blank and no filters are applied

In PHP (server-rendered): pass the count to the template and render it in a
`<span id="result-count">` element.

In JS (AJAX/live): update `document.getElementById('result-count').textContent`
after each search response.

---

## 3. Clear (✕) Button Inside Search Input

Add a clear button that appears inside the search box as soon as the user
starts typing. Clicking it resets the input and re-runs the search.

- Position: absolute, right side of the input, vertically centered
- Show/hide: toggle `hidden` Tailwind class based on input value
- On click: clear input value, trigger search, return focus to input

This is a small change with high perceived impact — users should never have
to manually select-all and delete.

---

## 4. Active Filter Chips

When a filter dropdown is changed from its default (e.g., "All Types" →
"COPC Only"), show a removable chip below the filter row so users know
a filter is active.

**Chip format:**
```
[Document Type: COPC Only  ×]
```

**Behavior:**
- Clicking `×` resets that dropdown to its default value and re-runs the search
- If 2+ chips are active, show a `Clear All` text link at the end of the chip row
- Chips row is hidden when no filters are active

**In vanilla JS:** listen to `change` events on each filter `<select>`, then
call a `renderChips()` function that rebuilds the chips container's innerHTML.

---

## 5. Structured Query Syntax (Power Users)

Allow users to type field-scoped queries directly in the search bar:

| Syntax | Effect |
|---|---|
| `program: nursing` | Filters by program name |
| `school: ust` | Filters by school name |
| `year: 2023` | Filters by approval year |

**Parse on the JS side** before sending to PHP:

```js
function parseQuery(q) {
  const structured = {};
  let plain = q;
  const re = /(program|school|year):\s*([^\s]+)/gi;
  let m;
  while ((m = re.exec(q)) !== null) {
    structured[m[1].toLowerCase()] = m[2];
    plain = plain.replace(m[0], '').trim();
  }
  return { structured, plain };
}
```

Pass `structured` fields as separate GET/POST parameters to PHP so they can
be applied as precise WHERE clauses, and use `plain` for the general full-text
search.

---

## 6. Search Hint Text

Below the search bar, show a subtle hint that updates contextually:

- Default: `Try: program: nursing  |  school: ust  |  year: 2023`
- While typing: hide the hint (it's no longer needed)
- On empty result: show `Try broader keywords or remove filters`

Use a `<p id="search-hint">` element and toggle its content with JS.

---

## 7. Empty State

When the search + filters return zero results, replace the empty table with
a centered message block. Do not leave a blank table visible.

```html
<div id="empty-state" class="hidden text-center py-16">
  <p class="text-gray-400 text-4xl mb-3">🔍</p>
  <p class="text-gray-700 font-semibold text-base mb-1">No results found</p>
  <p class="text-gray-400 text-sm leading-relaxed">
    Check your spelling · Remove a filter · Try broader keywords
  </p>
  <button id="empty-clear" class="mt-4 text-sm border border-gray-300 rounded px-4 py-1.5 hover:bg-gray-50">
    Clear All Filters
  </button>
</div>
```

Toggle between `<table>` and `#empty-state` based on result count.

---

## 8. PHP-Side Search Tips

When helping with the backend search logic:

- Use `LIKE %keyword%` for general text search across school, program, and
  student name columns
- For OCR content, search the stored OCR text column with `LIKE %keyword%`
- Prefer `LOWER()` on both sides for case-insensitive matching in MySQL
- Return the total matched count alongside results so JS can display it
  without a second query: use `SQL_CALC_FOUND_ROWS` + `FOUND_ROWS()`
- Sanitize all inputs with `mysqli_real_escape_string()` or prepared statements

---

## 9. UX Quality Standards

- Highlight matched keywords in School, Program, and Student Names columns
- Debounce the search input (300ms) to avoid excessive PHP queries
- Never leave users staring at an empty table — always show count or empty state
- Keep filter interactions instant — chips appear/disappear without page reload
- Preserve the search state in the URL (via query string) so results are shareable

---

## Improvement Priority Order

When the user asks "how do I improve the search?" suggest improvements in
this order — each one is independent and can be done separately:

1. Keyword highlighting in result rows
2. Live result count display
3. Clear (✕) button in the search input
4. Active filter chips
5. Empty state message
6. Search hint text
7. Debounced input
8. Structured query syntax

---

## Goal

Make searching feel **instant, intelligent, and transparent** — users should
always know what they searched, how many results matched, and what filters
are active, without any page ambiguity.
