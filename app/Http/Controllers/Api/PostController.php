<?php

namespace App\Http\Controllers\Api;

use App\Domain\Posts\CreatePostAction;
use App\Domain\Posts\ListPostsQuery;
use App\Domain\Posts\PostData;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListPostsRequest;
use App\Http\Requests\Api\StorePostRequest;
use App\Http\Resources\PostResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(ListPostsRequest $request): AnonymousResourceCollection
    {
        $query = (new ListPostsQuery($request->user()))
            ->status($request->input('status'))
            ->provider($request->input('provider'))
            ->from($request->input('from'))
            ->to($request->input('to'));

        $includes = array_filter(explode(',', $request->input('include', '')));

        $builder = $query->query()->orderByDesc('scheduled_for');

        if (! in_array('variants', $includes)) {
            $builder->without('variants');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        return PostResource::collection($builder->paginate($perPage));
    }

    public function store(StorePostRequest $request, CreatePostAction $action): JsonResponse
    {
        $post = $action->execute(
            $request->user(),
            new PostData(
                scheduledFor: $request->input('scheduled_for'),
                bodyText: $request->input('body_text'),
                targetAccountIds: $request->input('targets'),
                providerOverrides: $request->input('provider_overrides', []),
                accountOverrides: $request->input('account_overrides', []),
                status: PostStatus::Scheduled,
                mediaIds: $request->input('media_ids', []),
                altTexts: $request->input('alt_texts', []),
            ),
        );

        $post->load(['targets.socialAccount', 'variants', 'media']);

        return (new PostResource($post))
            ->response()
            ->setStatusCode(201);
    }
}
