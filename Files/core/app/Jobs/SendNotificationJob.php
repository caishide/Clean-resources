<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $failOnTimeout = true;

    protected $user;
    protected $type;
    protected $message;

    public function __construct(User $user, string $type, string $message)
    {
        $this->user = $user;
        $this->type = $type;
        $this->message = $message;
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        try {
            Log::info("发送通知给用户", [
                'user_id' => $this->user->id,
                'type' => $this->type
            ]);

            // 这里可以实现发送邮件、短信、推送等逻辑
            // 例如：Mail::to($this->user->email)->send(new NotificationEmail($this->message));

            // 记录通知日志
            NotificationLog::create([
                'user_id' => $this->user->id,
                'type' => $this->type,
                'message' => $this->message,
                'status' => 1
            ]);

            Log::info("通知发送成功", ['user_id' => $this->user->id]);

        } catch (\Exception $e) {
            Log::error("通知发送失败", [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("通知任务最终失败", [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}
