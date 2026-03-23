# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **REDCap External Module** ("Default From Query", v1.0.0) that allows REDCap fields to be auto-populated with values derived from SQL queries against REDCap's own database. Queries are defined by a system administrator at the system level and referenced by name from a field's action tag.

- **Namespace:** `UF_CTSI\ExternalModule`
- **Framework version:** 15
- **Requires:** REDCap >= 14.0.2, PHP >= 7.4

## Testing

There is no automated test suite. Testing is manual using the REDCap instance:
- See `testing.md` for instructions
- Install the module by symlinking or copying the repo to `<redcap-root>/modules/default_from_query_v<version>`

## Architecture

All backend logic lives in a single file: **`ExternalModule.php`**.

The primary hook is:
- `redcap_every_page_top()` — main entry point for data entry pages

### Core flow in `redcap_every_page_top()`

1. **`setDefaultValues()`** — The central method. Iterates project metadata, finds fields tagged with `@DEFAULT-FROM-QUERY`, looks up the named query from system settings, executes it against the REDCap database scoped to the current project, and injects the resulting value as the field default before REDCap renders the page.

### System Settings

Queries are stored as system-level (global) settings, not project settings. Each stored query has:
- A **name** — used as the key in `@DEFAULT-FROM-QUERY='name'`
- A **project ID** association — restricts which project the query applies to
- A **SQL string** — executed against the REDCap database; must return a single scalar value

### Key helper methods in ExternalModule.php

| Method | Purpose |
|--------|---------|
| `getValueInActionTag()` | Extracts the query name from the action tag value |
| `getQueryByName()` | Looks up a named query from system settings for the current project |
| `executeQuery()` | Runs the stored SQL and returns the scalar result |
| `currentFormHasData()` | Prevents overwriting existing data |

## Action Tags Implemented

- `@DEFAULT-FROM-QUERY='query_name'` — Populates a field's default value by executing the named query stored in system settings. The query must be associated with the current project and must return a single value.

## Module Settings (config.json)

Queries are stored at the **system level**, not as project settings. The system settings schema for each query entry:

| Key | Purpose |
|-----|---------|
| `query_name` | Identifier referenced in the action tag |
| `query_project_id` | The REDCap project ID this query applies to |
| `query_sql` | The SQL to execute; must return a single scalar value |

## Important Conventions

- No `eval()` in PHP
- PSR-2 code style
- No Composer, npm, or build tooling — pure PHP
- When adding action tags, declare them in the `action-tags` array in `config.json` — the framework renders this documentation in the Online Designer automatically
- The module must be backward-compatible with the REDCap minimum version; check for function existence before calling REDCap internals (see existing `method_exists`/`function_exists` guards in the code)
- SQL queries stored in settings must be validated/sanitized before execution to prevent injection; use REDCap's `db_query()` or the framework's query methods, never raw `mysqli_query()` with unsanitized input
- Like all REDCap External modules, this module conforms to the documentation and conventions described in the REDCap External Module Framework at https://github.com/vanderbilt-redcap/external-module-framework-docs
- The official git repo will be at https://github.com/ctsit/default_from_query/
- Authors, DOI, and other citation-related details are managed in CITATION.cff 
