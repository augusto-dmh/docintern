# Pull Request Guidelines

## Title

- Keep it under 70 characters
- Use imperative mood (e.g., "Add authentication flow", not "Added authentication flow")
- Prefix with the area of change when helpful (e.g., "Auth: add two-factor support")

## Description Template

```markdown
## Why

Explain the motivation behind this PR. What problem does it solve? Why is this change necessary now?

- Focus on the **context** a reviewer needs to understand the change
- Link to related issues, discussions, or documentation when relevant

## What changed

Summarize the concrete changes made. Group related changes together and describe them at a level that helps reviewers navigate the diff.

- Describe changes in terms of behavior or capability, not just files touched
- Call out any non-obvious decisions or trade-offs made during implementation

## Notes (optional)

Anything reviewers should pay special attention to, or that affects how this change is tested/deployed.

- Side effects, migrations, environment variable changes, etc.
- Known limitations or follow-up work planned
```

## Example

**Title:** Bootstrap AI-assisted development tooling

**Description:**

### Why

As the project grows, keeping development guidelines consistent and accessible becomes harder. This PR sets up tooling to solve two problems:

1. **Stale guidelines** — Laravel Boost auto-generates and updates project-specific guidelines from package docs, triggered automatically on `composer update`.
2. **AI context drift** — Claude Code skills and a comprehensive `CLAUDE.md` give the AI assistant deep knowledge of our stack (Laravel 12, Vue 3, Inertia, Pest, TailwindCSS) so it produces code that follows our conventions from the start.

### What changed

- Installed `laravel/boost` (dev dependency) with MCP server config and `boost.json`
- Hooked `boost:update` into Composer's `post-update-cmd` lifecycle
- Created `CLAUDE.md` covering architecture, code style, and workflow rules
- Added 5 skill files under `.claude/skills/` for domain-specific AI guidance

### Notes

`boost:update` runs automatically on every `composer update`, keeping guidelines in sync with installed packages.

## Principles

1. **Lead with "why"** — Reviewers should understand the motivation before reading the diff
2. **Be specific in "what changed"** — Describe behavioral changes, not just file lists
3. **Surface non-obvious decisions** — Call out trade-offs, alternatives considered, or anything that might surprise a reviewer
4. **Keep it scannable** — Use headers, bullet points, and numbered lists over long paragraphs
5. **Link context** — Reference issues, docs, or discussions so reviewers can dig deeper if needed
