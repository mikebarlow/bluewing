<?php

namespace App\Http\Controllers\Api;

use App\Domain\SocialAccounts\GetAccessibleAccountsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListSocialAccountsRequest;
use App\Http\Resources\SocialAccountResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SocialAccountController extends Controller
{
    public function index(ListSocialAccountsRequest $request): AnonymousResourceCollection
    {
        $accounts = (new GetAccessibleAccountsQuery($request->user()))
            ->provider($request->input('provider'))
            ->get();

        return SocialAccountResource::collection($accounts);
    }
}
