<?php

namespace App\Services;

use App\Models\Field;
use App\Models\User;

class FieldService
{
    public function store(User $authUser, array $params = []): Field
    {
        $page = new Field($params);
        $page->company()->associate($authUser->company);
        $page->save();

        return $page;
    }
}
