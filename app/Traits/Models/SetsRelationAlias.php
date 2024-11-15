<?php

namespace App\Traits\Models;

/**
 * Override default Eloquent relation set/unset
 *
 * @property-read array RELATION_MAP
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait SetsRelationAlias
{
    /**
     * @throws \ErrorException
     */
    public function setRelation($relation, $value)
    {
        parent::setRelation($relation, $value);

        if(!defined(static::class."::RELATION_MAP")){
            throw new \ErrorException("The Class `".static::class."` must define the constant `RELATION_MAP` for use with the `SetsRelations` Trait");
        }

        if (isset(static::RELATION_MAP[$relation])) {
            parent::setRelation(static::RELATION_MAP[$relation], $value);
        }/*else {
            Log::warning("Failed to set relation '{$relation}' on class '".static::class."'. Make sure it exists in the RELATION_MAP.", static::RELATION_MAP);
        }*/
    }

    public function unsetRelation($relation)
    {
        parent::unsetRelation($relation);

        if (isset(static::RELATION_MAP[$relation])) {
            parent::unsetRelation(static::RELATION_MAP[$relation]);
        }/*else {
            Log::warning("Failed to unset relation '{$relation}' on class '".static::class."'. Make sure it exists in the RELATION_MAP.", static::RELATION_MAP);
        }*/
    }

    // probably not worth doing the below methods
    /*public function __call($method, $parameters)
    {
        try {
            return parent::__call($method, $parameters);
        } catch (\Error|\BadMethodCallException $e) {
            $foundMethod = $this->findMethod($method);
            if($foundMethod !== null){
               return $foundMethod;
            }
            throw $e;
        }
    }

    protected static function checkMethodName($method) : ?string
    {
        if(defined(static::class."::RELATION_MAP")) {
            $flip = array_flip(static::RELATION_MAP);
            $relationMethod = $flip[$method] ?? static::RELATION_MAP[$method] ?? null;
            if (isset($relationMethod)){
                return $relationMethod;
            }
        }

        return null;
    }

    protected function findMethod($method)
    {
        $relationMethod = static::checkMethodName($method);
        return isset($relationMethod) ? $this->$relationMethod() : null;
    }*/
}
