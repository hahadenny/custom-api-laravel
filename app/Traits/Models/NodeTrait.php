<?php

namespace App\Traits\Models;

use App\Models\Schedule\ScheduleListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kalnoy\Nestedset\NestedSet;
use \Kalnoy\Nestedset\NodeTrait as NodeBaseTrait;

/**
 * @property int                                                                              $_lft
 * @property int                                                                              $_rgt
 * @property int|null                                                                         $parent_id
 *
 * @property-read \Illuminate\Database\Eloquent\Model|null                                   $parent
 * @property-read \Illuminate\Database\Eloquent\Model|null                                   $trashedParent
 * @property-read \Kalnoy\Nestedset\Collection<\Illuminate\Database\Eloquent\Model>          $ancestors
 * @property-read \Kalnoy\Nestedset\Collection<\Illuminate\Database\Eloquent\Model>          $children
 * @property-read \Kalnoy\Nestedset\Collection<\Illuminate\Database\Eloquent\Model>          $descendants
 *
 * @method Builder whereRootOf(int|\Illuminate\Database\Eloquent\Model $node)
 */
trait NodeTrait
{
    use NodeBaseTrait;

    /**
     * Relation to the parent, including trashed parents.
     * Modification of @see \Kalnoy\Nestedset\NodeTrait::parent()
     */
    public function trashedParent() : BelongsTo
    {
        return $this->belongsTo(get_class($this), $this->getParentIdName())
                    ->withTrashed()
                    ->setModel($this);
    }

    /**
     * @see \Kalnoy\Nestedset\NodeTrait::isDescendantOf()
     */
    public function isNotDescendantOf(self $other) : bool
    {
        return $this->getLft() <= $other->getLft() ||
            $this->getLft() > $other->getRgt();
    }

    /**
     * @see \Kalnoy\Nestedset\NodeTrait::isSelfOrDescendantOf()
     */
    public function isNotSelfOrDescendantOf(self $other) : bool
    {
        return $this->getLft() < $other->getLft() ||
            $this->getLft() >= $other->getRgt();
    }

    /**
     * @param Builder   $query
     * @param int|Model $node - Model that uses NodeTrait or \Kalnoy\Nestedset\NodeTrait
     *
     * @return mixed
     */
    public function scopeWhereRootOf(Builder $query, int|Model $node)
    {
        return $query->whereAncestorOrSelf($node)->whereIsRoot();
    }
}
