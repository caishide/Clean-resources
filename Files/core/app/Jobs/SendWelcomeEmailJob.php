<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\WelcomeEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $failOnTimeout = true;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        try {
            Log::info("发送欢迎邮件给用户", ['user_id' => $this->user->id, 'email' => $this->user->email]);

            // 发送欢迎邮件
            Mail::to($this->user->email)->send(new WelcomeEmail($this->user));

            Log::info("欢迎邮件发送成功", ['user_id' => $this->user->id]);

        } catch (Exception $e) {
            Log::error("发送欢迎邮件失败", [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::critical("欢迎邮件任务最终失败", [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
