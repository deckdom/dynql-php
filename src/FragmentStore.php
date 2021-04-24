<?php

namespace DynQL;

use DynQL\UnresolvedFragmentError;

class FragmentStore
{
    private $nameBlacklist = ['on'];
    private $validFragmentName = '/^[_a-zA-Z][_a-zA-Z0-9]+$/g';
    private $fragmentSpreadName = '/\.{3}[_a-zA-Z][_a-zA-Z0-9]+/g';
    private $fragmentDefinitionName = '/(?:^|[\\W])fragment[\\s]+([_a-zA-Z][_a-zA-Z0-9]+)[\\s]+on[\\s]+[_a-zA-Z][_a-zA-Z0-9]+/g';
    private $store = [];

    public function __constructor()
    {
    }

    public function getFragments()
    {
        return $this->store;
    }

    public function resolve(string $query)
    {
        $definedFragments = $this->getDefinedFragmentNames($query);

        $requiredFragments = array_filter($this->getSpreadFragmentNames($query), function ($val) use ($definedFragments) {
            return !in_array($val, $definedFragments);
        });

        $resolvedFragments = $this->resolveFragments($requiredFragments, array_map(function ($val) {
            return ['name' => $val];
        }, $definedFragments));

        return array_filter(array_map(function ($fragment) {
            return $fragment['definition'];
        }, $resolvedFragments), function ($def) {
            return !empty($def);
        });
    }

    public function autoRegisterFragment(string $query)
    {
        $found = $this->findFragmentsFromQuery($query);
        $names = array_keys($found);

        foreach ($found as $name => $defintion) {
            $this->registerFragment($name, $definition);
        }

        return $names;
    }

    public function registerFragment(string $name, string $definition)
    {
        if (!$this->isValidName($name)) {
            return false;
        }

        $this->store[$name] = [
            'defintion' => $definition,
            'dependsOn' => $this->getSpreadFragmentNames($definition),
        ];

        return true;
    }

    public function unregisterFragment(string $name)
    {
        if (isset($this->store[$name])) {
            unset($this->store[$name]);
            return true;
        }

        return false;
    }

    public function isValidName(string $name)
    {
        return preg_match($this->validFragmentName, $name)
            && !in_array($name, $this->nameBlacklist);
    }

    public function getSpreadFragmentNames(string $definition)
    {
        $matches = preg_grep($this->fragmentSpreadName, $definition);
        $dependencies = [];

        foreach ($matches as $match) {
            $name = $match[1];

            if ($this->isValidName($name) && !in_array($name, $dependencies)) {
                $dependencies[] = $name;
            }
        }

        return $dependencies;
    }

    public function getDefinedFragmentNames(string $definition)
    {
        $matches = preg_grep($this->fragmentDefinitionName, $definition);
        $names = [];

        foreach ($matches as $match) {
            $name = $match[1];

            if ($this->isValidName($name) && !in_array($name, $names)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function resolveFragments($toResolve, $resolvedFragments)
    {
        foreach ($toResolve as $fragmentName) {
            if (in_array($fragmentName, array_map(function ($fragment) {
                return $fragment['name'] === $fragmentName;
            }, $resolvedFragments))) {
                continue;
            }

            if (!isset($this->store[$fragmentName])) {
                throw new UnresolvedFragmentError("Could not resolve required fragment ${fragmentName}!");
            }

            $fragment = $this->store[$fragmentName];

            $resolvedFragments[] = [
                'name' => $fragmentName,
                'definition' => $fragment['definition'],
            ];

            if (!empty($fragment['dependsOn'])) {
                $this->resolveFragments($fragment['dependsOn'], $resolvedFragments);
            }
        }

        return $resolvedFragments;
    }

    private function findFragmentsFromQuery(string $query)
    {
        $out = [];

        $offset = 0;
        $buffer = 0;
        $level = 0;

        for ($index = 0; $index < strlen($query); $index++) {
            if ($query[$index] === '{') {
                if ($level === 0) {
                    $buffer = trim(substr($query, $offset, $index - 1));
                }
                $level++;
            } else if ($query[$index] === '}') {
                $level--;
                if ($level === 0) {
                    $match = preg_grep($this->fragmentDefinitionName, $buffer);
                    if (!empty($match)) {
                        $out[$match[1]] = trim(substr($query, $offset, $index + 1));
                    }
                    $offset = $index + 1;
                    $buffer = '';
                }
            }
        }

        return $out;
    }
}
