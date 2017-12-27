<?php

namespace FakeIpastore\Jobs;

use FakeIpastore\Models\SignRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DownloadIpa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $signRequest;
    protected $linkIpa;
    protected $linkInfo;

    /**
     * Create a new job instance.
     *
     * @param SignRequest $signRequest
     */
    public function __construct(SignRequest $signRequest, $linkIpa, $linkInfo)
    {
        $this->signRequest = $signRequest;
        $this->linkIpa = $linkIpa;
        $this->linkInfo = $linkInfo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->signRequest->downloadIpa($this->linkIpa, $this->linkInfo);
    }
}
