<?php

namespace App\Traits\Models;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin \Illuminate\Database\Eloquent\SoftDeletes
 */
trait ListingPivotTrait
{
    public function saveOrRestore(array $options = [])
    {
        return $this->trashed() ? $this->restore() : $this->save($options);
    }

    public function restoreIfTrashed()
    {
        return $this->trashed() ? $this->restore() : true;
    }

    public function saveOrRestoreQuietly(array $options = [])
    {
        return $this->trashed() ? $this->restoreQuietly() : $this->saveQuietly($options);
    }

    public function restoreIfTrashedQuietly()
    {
        return $this->trashed() ? $this->restoreQuietly() : true;
    }
}
