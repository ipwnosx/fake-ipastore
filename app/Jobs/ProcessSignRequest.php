<?php

namespace FakeIpastore\Jobs;

use FakeIpastore\Models\SignRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessSignRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $signRequest;

    /**
     * Create a new job instance.
     *
     * @param SignRequest $signRequest
     */
    public function __construct(SignRequest $signRequest)
    {
        $this->signRequest = $signRequest;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        dump($this->signRequest);
    }
}
