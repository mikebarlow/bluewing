<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Jobs\PublishPostTargetJob;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchDuePostsCommand extends Command
{
    protected $signature = 'bluewing:dispatch-due-posts';

    protected $description = 'Find scheduled posts that are due and dispatch publish jobs for each target';

    public function handle(): int
    {
        $duePosts = Post::where('status', PostStatus::Scheduled)
            ->where('scheduled_for', '<=', Carbon::now())
            ->with('targets')
            ->get();

        if ($duePosts->isEmpty()) {
            $this->info('No due posts found.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($duePosts as $post) {
            $post->update(['status' => PostStatus::Queued]);

            foreach ($post->targets as $target) {
                $target->update(['status' => PostTargetStatus::Queued]);

                PublishPostTargetJob::dispatch($target->id);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} publish jobs for {$duePosts->count()} posts.");

        return self::SUCCESS;
    }
}
