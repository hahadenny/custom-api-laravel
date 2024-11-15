<?php

namespace App\Services\Engines;

// FormIOHelper
use Illuminate\Support\Str;

class EngineSchemaHelper
{
    /**
     * FormioHelper.js > FormioHelperClass > findNodeWithKey(schema, key)
     */
    public function findNodeWithKey($schema, $key) {
        if (isset($schema['key']) && $schema['key'] === $key) {
            return $schema;
        }

        if (isset($schema['components'])) {
            foreach ($schema['components'] as $component) {
                $node = $this->findNodeWithKey($component, $key);
                if (isset($node)) {
                    return $node;
                }
            }
        }
        if (isset($schema['rows']) && is_iterable($schema['rows'])) {
            foreach($schema['rows'] as $row) {
                foreach($row as $col) {
                    $node = $this->findNodeWithKey($col, $key);
                    if (isset($node)) {
                        return $node;
                    }
                }
            }
        }
        if (isset($schema['columns']) && is_iterable($schema['columns'])) {
            foreach($schema['columns'] as $column) {
                $node = $this->findNodeWithKey($column, $key);
                if (isset($node)) {
                    return $node;
                }
            }
        }
    }

    /**
     * FormioHelper.js > FormioHelperClass > findNodesOfType(schema, type)
     */
    public function findNodesOfType($schema, $type) : array
    {
        $ret = [];
        $this->propsFromSchemaHelper($schema, function ($node) use (&$ret, $type) {
            if (isset($node['type']) && $node['type'] === $type) {
                $ret []= $node;
            }
        });

        return $ret;
    }

    /**
     * Find node based on predicate function $pred
     *
     * FormioHelper.js > FormioHelperClass > findNodesWithPred(schema, pred)
     */
    public function findNodesWithPred($schema, Callable $pred) : array
    {
        $ret = [];
        // this.propsFromSchemaHelper(schema, (node) => { ... });
        $this->propsFromSchemaHelper($schema, function($node) use ($pred, &$ret) {
            if($pred($node)){
                $ret []= $node;
            }
        });
        return $ret;
    }

    public function findNodesBeginningWith($schema, $start) {
        $ret = [];
        $this->propsFromSchemaHelper($schema, function ($node) use ($start, &$ret) {
            if (isset($node['key']) && is_string($node['key'])
                && Str::startsWith($node['key'], $start)) {
                $ret []= $node;
            }
        });

        return $ret;
    }

    /**
     * FormioHelper.js > FormioHelperClass > getPropertiesFromSchema(schema)
     */
    public function getPropertiesFromSchema($schema) : array
    {
        $props = [];

        $this->propsFromSchemaHelper($schema, function ($node) use (&$props) {
            if (isset($node['ueprop'])) {
                $props []= ($node['ueprop']);
            }
        });

        return $props;
    }

    /**
     * FormioHelper.js > FormioHelperClass > getFunctionsFromSchema(schema, funcArgSuffix)
     */
    public function getFunctionsFromSchema($schema, $func_arg_suffix) : array
    {
        // should be a Set()
        $functions = [];

        $this->propsFromSchemaHelper($schema, function ($node) use($func_arg_suffix, &$functions) {
            if (isset($node['key']) && str_contains($node['key'], $func_arg_suffix)) {
                $functions []= $node['key'].substr(0, strpos($node['key'], $func_arg_suffix) - 1);
            }
        });

        // return unique because JS Set()
        return array_unique($functions);
    }

    /**
     * FormioHelper.js > FormioHelperClass > propsFromSchemaHelper(schema, callback)
     */
    public function propsFromSchemaHelper($schema, callable $callback) : void
    {
        $callback($schema);

        if (isset($schema['components'])) {
            foreach($schema['components'] as $component) {
                $this->propsFromSchemaHelper($component, $callback);
            }
        }
        if (isset($schema['columns'])) {
            foreach ($schema['columns'] as $column) {
                $this->propsFromSchemaHelper($column, $callback);
            }
        }

        if (isset($schema['rows']) && is_iterable($schema['rows'])) {
            foreach($schema['rows'] as $row){
                foreach($row as $col) {
                    $this->propsFromSchemaHelper($col, $callback);
                }
            }
        }
    }

    /**
     * Get a component by its key
     *
     * Utils.getComponent() - Returns the component with the given key or undefined if not found.
     * @link https://help.form.io/developers/javascript-utilities#getcomponent-components-key
     * @see https://github.com/formio/formio.js/blob/c582c5ca5bfc4947d9066f0e1545c7afd01be277/src/utils/formUtils.js#L163
     *
     * @param {Object} components - The components to iterate.
     * @param {String|Object} key - The key of the component to get, or a query of the component to search.
     *
     * @returns {Object} - The component that matches the given key, or undefined if not found.
     */
    // export function getComponent(components, key, includeAll)
    public function getComponent($components, $key) {
    //     let result;
    //   eachComponent(components, (component, path) => {
    //         if ((path === key) || (component.path === key)) {
    //             result = component;
    //             return true;
    //         }
    //     }, includeAll);
    //   return result;
    }

    /**
     * Find values that could potentially be related (have the same index suffix).
     * If field does not have delimiter with suffix, it is put under "etc" prop with key unchanged
     */
    public function groupRelatedSubmissionData($submissionData, $delimiter='_')
    {
        $parsedData = [];

        foreach ($submissionData as $key => $data) {
            $parts = explode($delimiter, $key);
            $lastPart = trim($parts[sizeof($parts) - 1]);
            $suffix = $lastPart === "" ? 0 : intval($lastPart);
            $newKey = $suffix;
            if(!is_numeric($suffix)){
                // this is not a submission field to group by
                $newKey = 'etc';
            }

            $baseKey = $newKey === "etc" ? $key : implode($delimiter, array_slice($parts, 0, -1));

            if (!isset($parsedData[$newKey])) {
                $parsedData[$newKey] = [];
            }

            $parsedData[$newKey][$baseKey] = $data;
        }

        return $parsedData;
    }
}
