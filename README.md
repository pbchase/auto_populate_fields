# Default From Query

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.?????.svg)](https://doi.org/10.5281/zenodo.?????)

Default From Query is a REDCap External Module that allows REDCap fields to be auto-populated with values derived from SQL queries against REDCap's own database. Queries are defined by a system administrator at the system level and referenced by name from a field's action tag.

## Prerequisites

- REDCap >= 14.6.4
- PHP >= 8.2.0

## Installation

- Obtain this module from the Consortium [REDCap Repo](https://redcap.vumc.edu/consortium/modules/index.php) from the control center.
- Go to **Control Center > Manage External Modules** and enable Default From Query.

The module operates at the system level; there is no per-project configuration.

## Usage

### 1. Define a query (system administrator)

In the REDCap Control Center, External Modules Management, locate the Default From Query module and access its configuration. Add one or more query entries. Each entry requires:

| Field | Description |
|-------|-------------|
| **Query Name** | A short identifier used to reference this query in action tags |
| **Project ID** | The REDCap project this query applies to |
| **SQL** | A SQL statement that returns a single scalar value |

### 2. Enable the module on a project where it is needed. 

### 3. Within that project, in the project designer, reference the query by name in a field's action tag 

In the Online Designer, add the following action tag to any field you want auto-populated:

```
@DEFAULT-FROM-QUERY='query_name'
```

where `query_name` matches the **Query Name** configured in system settings. When a user opens a data entry form for a record that has no existing data on that form, the module executes the associated query and injects the result as the field's default value.

### Variable substitution in SQL

Queries may reference the following placeholders, which are substituted with the current context before execution:

| Placeholder | Substituted with |
|-------------|-----------------|
| `[record_id]` | The current record ID |
| `[project_id]` | The current project ID |
| `[field_name]` | The name of the field being populated |

Example:

```sql
SELECT value
FROM redcap_data
WHERE project_id = [project_id]
  AND record = [record_id]
  AND field_name = [field_name]
ORDER BY instance DESC
LIMIT 1
```

#### Caveats

- Do not enclose any of these substitions with quotes or the query will fail and return to no value.

```sql
SELECT value
FROM redcap_data
WHERE project_id = [project_id]
  AND record = '[record_id]' -- this will fail!
  AND field_name = '[field_name]' -- this will also fail!
ORDER BY instance DESC
LIMIT 1
```

- `record` and `field_name` _must_ be wrapped in quotes if you are _not_ using the substition value for that column value.

```sql
SELECT value
FROM redcap_data
WHERE project_id = [project_id]
  AND record = [record_id]
  AND field_name = 'some_other_field' -- Quotes are required in this context
ORDER BY instance DESC
LIMIT 1
```

- If your query needs to query multiple project_ids, record_ids or field_names, you will need to hard-code some of those values. The substition values will always be in the context of the project, field, and record of the form as it is opened on the data entry page. If different things are needed to query the different projects, you will need to manage those differences as you write the query.

```sql
select coalesce(max(max_visitnum) + 1, 1) as next_visitnum
from (
        (
            select max(cast(value as SIGNED)) as max_visitnum
            from redcap_data
            where project_id = 123 -- the project_ids do not match so hardcode them
                and field_name = [field_name]
                and record = [record_id] -- when you need only the value provided by the substitution, you can use it.
        )
        union
        (
            select max(cast(value as SIGNED)) as max_visitnum
            from redcap_data4
            where project_id = 456 -- the project_ids do not match so hardcode them
                and field_name = [field_name]
                and record = [record_id]
        )
    ) as dummy;
```

## License

Apache 2.0 — see [LICENSE](LICENSE).
