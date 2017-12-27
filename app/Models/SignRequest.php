<?php

namespace FakeIpastore\Models;

use Carbon\Carbon;
use FakeIpastore\Jobs\CheckForwardedRequestStatus;
use FakeIpastore\Jobs\DownloadIpa;
use FakeIpastore\Jobs\FinishSignRequest;
use FakeIpastore\Jobs\ResignIpa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * FakeIpastore\Models\SignRequest
 *
 * @property int $id
 * @property int $status
 * @property string|null $server
 * @property string|null $udid
 * @property string|null $icon
 * @property string|null $bid
 * @property string|null $ver
 * @property string|null $name
 * @property string|null $aid
 * @property string|null $cert
 * @property string|null $ipa_file
 * @property string|null $note
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereAid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereBid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereCert($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereIpaFile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereServer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereUdid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereVer($value)
 * @mixin \Eloquent
 */
class SignRequest extends Model
{
    const STATUS_NEW = 0;
    const STATUS_REQUEST_SENT = 1;
    const STATUS_DOWNLOADED = 2;
    const STATUS_SIGNING = 3;
    const STATUS_FAILED = 99;
    const STATUS_DONE = 100;

    protected $guarded = [];

    public function forwardSignRequest()
    {
        $this->updateServer();

        $data = [
            'icon' => $this->icon,
            'bid' => $this->bid,
            'name' => $this->name,
            'ver' => $this->ver,
            'aid' => $this->aid,
            'udid' => $this->udid,
            'cert' => $this->cert,
        ];
        $data = http_build_query($data);

        $url = $this->server . 'api_v3/resign_task.php?' . $data;

        \Log::debug('Forwarding sign request to ' . $url);

        $result = file_get_contents($url);

        $result = json_decode($result);

        if ($result->status === 'error') {
            $this->status = SignRequest::STATUS_FAILED;
            $this->note = $result->info;
            $this->save();

            \Log::debug('Forward failed');

            return false;
        }

        $this->status = SignRequest::STATUS_REQUEST_SENT;
        $this->save();
        \Log::debug('Forwarded. Now waiting for download link...', [$result]);

        CheckForwardedRequestStatus::dispatch($this)->delay(Carbon::now()->addSeconds(3));
    }

    public function updateServer()
    {
        $url = 'https://devmyi.com/api_v2/path_v2.php?appid=' . $this->aid . '&type=1';
        \Log::debug('Updating server from ' . $url);
        $result = file_get_contents($url);

        $result = json_decode($result);
        if ($result->status !== 'success') {
            \Log::debug('Updating server failed');

            return false;
        }

        $this->server = $result->info;
        $this->save();
        \Log::debug('Server updated to ' . $this->server);

        return true;
    }

    public function checkForwardedRequestStatus()
    {
        $url = $this->server . 'api_v3/task_status.php?udid=' . $this->udid;
        \Log::debug('Checking status of forwarded task ' . $url);
        $result = file_get_contents($url);

        $result = json_decode($result);

        switch ($result->status) {
            case 'preparing':
                \Log::debug('Forwarded task still being processed');
                CheckForwardedRequestStatus::dispatch($this)->delay(Carbon::now()->addSeconds(3));

                return true;
                break;

            case 'done':
                DownloadIpa::dispatch($this, $result->link, $result->info);
                break;

            case 'error':
                \Log::debug('Forwarded task ended with error: ', [$result]);

                return false;
                break;

            default:
                \Log::debug('Forwarded task returned unknown status: ', [$result]);

                return false;
        }

        return true;

    }

    public function downloadIpa($ipaLink, $plistLink = null)
    {
        \Log::debug('Starting download of ' . $ipaLink);
        $handle = fopen($ipaLink, 'r');
        Storage::disk('local')->put('ipa_unsigned/' . $this->aid . '.ipa', $handle);
        \Log::debug('Download done');

        if ($plistLink) {
            \Log::debug('Starting download of ' . $plistLink);
            $handle = fopen($plistLink, 'r');
            Storage::disk('local')->put('ipa_unsigned/' . $this->aid . '.plist', $handle);
            \Log::debug('Download done');
        }

        ResignIpa::dispatch($this);
    }

    public function resignIpa()
    {
        dump('Resigning');

        $appPath = dirname(app_path());
        $srcIpa = escapeshellarg($appPath . Storage::disk('local')->url('app/ipa_unsigned/' . $this->aid . '.ipa'));
        $targetIpa = escapeshellarg($appPath . Storage::disk('local')->url('app/ipa_signed/' . $this->aid . '.ipa'));

        $command = escapeshellarg($appPath . '/resign.sh') . ' ' . $srcIpa . ' ' . $targetIpa;
        dump($command);

        $process = shell_exec($appPath . ' ' . $srcIpa . ' ' . $targetIpa);
        dump($process);

        // dump($process->isSuccessful());



        FinishSignRequest::dispatch($this);
    }

    public function finishSignRequest()
    {
        dump('App resigned. Changing request status to "complete"');
    }

}
