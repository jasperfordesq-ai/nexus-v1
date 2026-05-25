# Project NEXUS — Claude Code Guide

@AGENTS.md

---

## Claude Code-Specific Instructions

The sections below apply only to Claude Code. All universal project rules, stack information, mandatory conventions, and commands are in `AGENTS.md` above.

---

## HeroUI MCP Migration Workflow

For any HeroUI v2/v3 migration, component API question, or related React code change, use the `heroui-react` MCP server before giving migration advice or editing components.

- Prefer official HeroUI v3 migration docs from the MCP over memory.
- Check the component-specific migration page before changing `Select`, `Dropdown`, `Accordion`, `Progress`, `DateInput`, `TimeInput`, modals, hooks, or styling.
- Use the project-installed HeroUI skills in `.agents/skills/` as persistent local guidance, and use `https://heroui.com/react/llms.txt` as the lightweight docs index when an MCP tool is unavailable.
- For broader static reference, use `https://heroui.com/react/llms-full.txt`; for narrower reference, use `https://heroui.com/react/llms-components.txt` or `https://heroui.com/react/llms-patterns.txt`.
- Treat broad renames as suspicious until verified against the MCP docs, because many v3 components use compound APIs rather than simple find-and-replace migrations.
- In progress updates or final summaries, state which HeroUI MCP docs were checked when HeroUI migration work was involved.

## 🔴 PRIORITIZE PUBLIC REPOSITORIES & SHARED COMPONENTS

When upgrading this platform, ALWAYS look through public repositories for the best available shared components before writing custom code. **Your 1st priority must ALWAYS be using HeroUI and Tailwind CSS shared components.** It is highly preferable to use established, working components rather than building custom variations from scratch.

## 🔴 Agent Teams (Swarm Mode) — ENABLED

This project uses **Claude Opus 4.6 Agent Teams** (swarm mode) for large, multi-step tasks. Configuration:

```json
{ "CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS": "1", "teammateMode": "in-process" }
```

**Rules for team/swarm mode:**

- **Use teams for large tasks**: When a task involves 3+ independent workstreams, spawn teammate agents to work in parallel
- **Autonomous operation**: The user may be away or sleeping — work autonomously, make decisions, complete tasks without waiting for confirmation on routine choices
- **Agent types**: Use the right agent — `general-purpose` for implementation, `Explore` for research, `Plan` for architecture, `feature-dev:code-reviewer` for reviews
- **Quality over speed**: Each agent should follow all project conventions (tenant scoping, HeroUI, TypeScript strict, etc.)

**When NOT to use teams:** Single-file edits, inherently sequential tasks, simple research questions.
