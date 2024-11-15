<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Resources\CompanyResource;
use App\Api\V1\Resources\ProfileResource;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Api\V1\Requests\CompanyPreferences\UpdateRequest;


class CompanyPreferencesController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request)
    {
        $authUser = Auth::guard()->user();

        return new CompanyResource(
            tap($authUser->company)->update($request->validated())
        );
    }
}
