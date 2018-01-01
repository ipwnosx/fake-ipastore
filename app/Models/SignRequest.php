<?php

namespace FakeIpastore\Models;

use Carbon\Carbon;
use FakeIpastore\Jobs\CheckDownloadIpaStatus;
use FakeIpastore\Jobs\CheckForwardedRequestStatus;
use FakeIpastore\Jobs\DownloadIpa;
use FakeIpastore\Jobs\FinishSignRequest;
use FakeIpastore\Jobs\ResignIpa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

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

    const PATH_SIGNED = 'ipa_signed';
    const PATH_UNSIGNED = 'ipa_unsigned';

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
            case 'queue':
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
        if ($plistLink) {
            \Log::debug('Starting download of ' . $plistLink);
            $handle = fopen($plistLink, 'r');
            Storage::disk('local')->put('ipa_unsigned/' . $this->aid . '.plist', $handle);
            \Log::debug('Download done');
        }

        \Log::debug('Starting download of ' . $ipaLink);
//        $handle = fopen($ipaLink, 'r');
//        Storage::disk('local')->put('ipa_unsigned/' . $this->aid . '.ipa', $handle);
//        \Log::debug('Download done');

        $command = '/usr/local/bin/wget -b'
            . ' -O ' . escapeshellarg($this->getUnSignedIpaLocalPath())
            . ' -o ' . escapeshellarg($this->getWgetLogPath())
            . ' ' . escapeshellarg($ipaLink);
        $process = shell_exec($command);
        dump($process);

        CheckDownloadIpaStatus::dispatch($this);
    }

    public function checkDownloadIpaStatus()
    {
        $wgetLog = file_get_contents($this->getWgetLogPath());

        if (strpos($wgetLog, 'saved [') !== false) {
            ResignIpa::dispatch($this);
        } else {
            CheckDownloadIpaStatus::dispatch($this);
        }

    }

    public function resignIpa()
    {
        dump('Resigning');

        $targetIpa = $this->getSignedIpaLocalPath();

        $command = escapeshellarg(dirname(app_path()) . '/resign.sh') . ' ' . escapeshellarg($this->getUnSignedIpaLocalPath()) . ' ' . escapeshellarg($targetIpa);
        dump($command);

        $process = shell_exec($command);
        dump($process);

        // dump($process->isSuccessful());

        \Log::debug('Uploading IPA to S3');
        \Storage::disk('s3')->putFileAs(SignRequest::PATH_SIGNED, new File($targetIpa), basename($targetIpa), 'public');
        \Log::debug('Upload IPA to S3 finished');

        $this->updateAppPlist();

        FinishSignRequest::dispatch($this);
    }

    public function updateAppPlist()
    {
        $plist = file_get_contents($this->getUnSignedPlistLocalPath());
        $plist = preg_replace('/<string>https.*ipa<\/string>/i', '<string>' . $this->getResignedIpaUrl() . '</string>', $plist);

        $targetPath = $this->getSignedPlistLocalPath();

        file_put_contents($targetPath, $plist);

        \Storage::disk('s3')->putFileAs(SignRequest::PATH_SIGNED, new File($targetPath), basename($targetPath), 'public');
    }

    public function getLocalPath($type)
    {
        return dirname(app_path()) . Storage::disk('local')->url('app/' . $type . '/' . $this->aid);
    }

    public function getSignedIpaLocalPath()
    {
        return $this->getLocalPath(self::PATH_SIGNED) . '.ipa';
    }

    public function getUnSignedIpaLocalPath()
    {
        return $this->getLocalPath(self::PATH_UNSIGNED) . '.ipa';
    }

    public function getSignedPlistLocalPath()
    {
        return $this->getLocalPath(self::PATH_SIGNED) . '.plist';
    }

    public function getUnSignedPlistLocalPath()
    {
        return $this->getLocalPath(self::PATH_UNSIGNED) . '.plist';
    }

    public function getWgetLogPath()
    {
        return $this->getLocalPath(self::PATH_UNSIGNED) . '.log';
    }

    public function getResignedIpaUrl()
    {
        return \Storage::disk('s3')->url(SignRequest::PATH_SIGNED . '/' . $this->aid . '.ipa');
    }

    public function getResignedPlistUrl()
    {
        return \Storage::disk('s3')->url(SignRequest::PATH_SIGNED . '/' . $this->aid . '.plist');
    }

    public function finishSignRequest()
    {
        dump('App resigned. Changing request status to "complete"');
        $this->status = SignRequest::STATUS_DONE;
        $this->save();
    }

}
