<?php

namespace App\Http\Controllers\Api;

use App\Domain\Media\ValidateMediaForTargetsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMediaRequest;
use App\Http\Resources\PostMediaResource;
use App\Models\PostMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request, ValidateMediaForTargetsAction $validator): JsonResponse
    {
        $file = $request->file('file');
        $mediaType = $validator->detectMediaType($file->getMimeType() ?? $file->getClientMimeType());
        $disk = config('filesystems.media_disk', 'public');

        $path = $file->store('media', $disk);

        $media = PostMedia::create([
            'user_id' => $request->user()->id,
            'type' => $mediaType->value,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'alt_text' => $request->input('alt_text'),
        ]);

        return (new PostMediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $media = PostMedia::findOrFail($id);

        if ($media->user_id !== $request->user()->id) {
            abort(403, 'You do not own this media.');
        }

        if ($media->post_id !== null) {
            abort(422, 'Cannot delete media that is attached to a post.');
        }

        Storage::disk($media->storage_disk)->delete($media->storage_path);

        $media->delete();

        return response()->json(null, 204);
    }
}
