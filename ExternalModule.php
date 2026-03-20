<?php

/**
 * @file
 * Provides ExternalModule class for Default From Query.
 */

namespace DefaultFromQuery\ExternalModule;

use ExternalModules\AbstractExternalModule;
use Form;
use Records;

/**
 * ExternalModule class for Default From Query.
 */
class ExternalModule extends AbstractExternalModule
{
    /**
     * @inheritdoc
     */
    function redcap_every_page_top($project_id)
    {
        if (!$project_id) {
            return;
        }

        if (PAGE == 'DataEntry/index.php' && !empty($_GET['id'])) {
            if (!$this->currentFormHasData()) {
                $this->setDefaultValues($project_id);
            }
        }
    }

    /**
     * Sets default values for fields tagged with @DEFAULT-FROM-QUERY.
     *
     * Iterates project metadata for the current form, looks up the named query
     * from system settings, executes it, and injects the result as the value
     * of the @DEFAULT action tag so REDCap renders it as the field default.
     *
     * @param int $project_id
     */
    function setDefaultValues($project_id)
    {
        global $Proj;

        $fields = empty($_GET['page']) ? $Proj->metadata : $Proj->forms[$_GET['page']]['fields'];

        foreach (array_keys($fields) as $field_name) {
            $misc = $Proj->metadata[$field_name]['misc'];

            $query_name = Form::getValueInQuotesActionTag($misc, '@DEFAULT-FROM-QUERY');
            if (empty($query_name)) {
                continue;
            }

            $sql = $this->getQueryByName($query_name, $project_id);
            if ($sql === null) {
                continue;
            }

            [$sql, $params] = $this->pipeSqlVariables($sql, $project_id, $field_name, $_GET['id']);
            $value = $this->executeQuery($sql, $params);
            if ($value === null) {
                continue;
            }

            $Proj->metadata[$field_name]['misc'] = $this->overrideActionTag('@DEFAULT', $value, $misc);
        }
    }

    /**
     * Looks up the SQL for a named query associated with a project.
     *
     * @param string $query_name
     * @param int $project_id
     * @return string|null The SQL string, or null if not found.
     */
    function getQueryByName($query_name, $project_id)
    {
        $queries = $this->getSubSettings('queries');
        foreach ($queries as $query) {
            if (
                $query['query_name'] === $query_name &&
                (int) $query['query_project_id'] === (int) $project_id
            ) {
                return $query['query_sql'];
            }
        }
        return null;
    }

    /**
     * Substitutes [record_id], [project_id], and [field_name] placeholders in
     * a SQL string with safe parameterized query markers.
     *
     * Each placeholder occurrence is replaced with a ? and the corresponding
     * value is appended to the returned params array in order, making the
     * output safe for use with a prepared statement.
     *
     * @param string $sql        The raw SQL string containing placeholders.
     * @param int    $project_id The current REDCap project ID.
     * @param string $field_name The field name being processed.
     * @param string $record_id  The current record ID (from $_GET['id']).
     * @return array{0: string, 1: array} A tuple of [piped SQL, bound values].
     */
    function pipeSqlVariables($sql, $project_id, $field_name, $record_id)
    {
        $map = [
            'project_id' => $project_id,
            'field_name' => $field_name,
            'record_id'  => $record_id,
        ];

        $params = [];
        $piped = preg_replace_callback(
            '/\[(project_id|field_name|record_id)\]/',
            function ($matches) use ($map, &$params) {
                $params[] = $map[$matches[1]];
                return '?';
            },
            $sql
        );

        return [$piped, $params];
    }

    /**
     * Executes a SQL query and returns the first column of the first row.
     *
     * @param string $sql
     * @param array  $params Bound parameter values for prepared statement placeholders.
     * @return string|null The scalar result, or null if no rows returned.
     */
    function executeQuery($sql, $params = [])
    {
        $result = $this->query($sql, $params);
        if (!$result) {
            return null;
        }
        $row = $result->fetch_row();
        return $row ? (string) $row[0] : null;
    }

    /**
     * Injects a value into the @DEFAULT action tag within a misc field string.
     *
     * Replaces the existing @DEFAULT value if present, otherwise appends it.
     *
     * @param string $key     The action tag name (e.g. @DEFAULT).
     * @param string $value   The value to inject.
     * @param string $subject The misc field string.
     * @return string
     */
    function overrideActionTag($key, $value, $subject)
    {
        $escaped = str_replace('"', '\\"', $value);
        $pattern = '/' . preg_quote($key, '/') . '\s*=\s*("[^"]*"|\'[^\']*\')/';

        if (preg_match($pattern, $subject)) {
            return preg_replace($pattern, $key . '="' . $escaped . '"', $subject);
        }

        return $subject . ' ' . $key . '="' . $escaped . '"';
    }

    /**
     * Checks if the current form already has data.
     *
     * Prevents overwriting existing record data with defaults.
     *
     * @return bool TRUE if the form contains data, FALSE otherwise.
     */
    function currentFormHasData()
    {
        global $double_data_entry, $user_rights;

        $record = $_GET['id'];
        if ($double_data_entry && $user_rights['double_data'] != 0) {
            $record = $record . '--' . $user_rights['double_data'];
        }

        return (bool) Records::formHasData($record, $_GET['page'], $_GET['event_id'], $_GET['instance']);
    }

}
