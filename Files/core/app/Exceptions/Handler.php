<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 全局异常处理器
 * 
 * 统一处理所有异常，返回标准化的错误响应
 */
class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // 记录所有异常
            $this->logException($e);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            return $this->handleException($e, $request);
        });
    }

    /**
     * 记录异常
     */
    protected function logException(Throwable $e): void
    {
        $context = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
        ];

        // 根据异常类型选择日志级别
        if ($e instanceof BusinessException) {
            Log::warning('Business Exception', $context);
        } elseif ($e instanceof UnauthorizedException) {
            Log::warning('Unauthorized Exception', $context);
        } elseif ($e instanceof ValidationException) {
            Log::info('Validation Exception', $context);
        } elseif ($e instanceof ModelNotFoundException) {
            Log::warning('Model Not Found', $context);
        } elseif ($e instanceof NotFoundHttpException) {
            Log::info('Not Found', $context);
        } else {
            Log::error('Unhandled Exception', array_merge($context, [
                'trace' => $e->getTraceAsString(),
            ]));
        }
    }

    /**
     * 检查请求是否期望 JSON 响应
     */
    protected function expectsJson(Request $request): bool
    {
        $accept = $request->header('Accept');
        if ($accept && str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = $request->header('Content-Type');
        if ($contentType && str_contains($contentType, 'application/json')) {
            return true;
        }

        if ($request->header('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        if ($request->is('api/*') || $request->is('api')) {
            return true;
        }

        return false;
    }

    /**
     * 渲染 HTML 错误页面
     */
    protected function renderHtmlError(string $message, int $statusCode, Throwable $e): Response
    {
        if (config('app.debug')) {
            $debugInfo = sprintf(
                '<pre><strong>Error:</strong> %s<br><strong>File:</strong> %s<br><strong>Line:</strong> %d<br><strong>Stack:</strong><br>%s</pre>',
                htmlspecialchars($message),
                htmlspecialchars($e->getFile()),
                $e->getLine(),
                htmlspecialchars($e->getTraceAsString())
            );
        } else {
            $debugInfo = '';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>错误 {$statusCode}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 50px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .code { font-size: 72px; font-weight: bold; color: #e74c3c; margin: 0; }
        .msg { font-size: 24px; color: #555; margin: 20px 0; }
        .debug { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 4px; overflow-x: auto; }
        .debug pre { margin: 0; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="code">{$statusCode}</h1>
        <p class="msg">{$message}</p>
        {$debugInfo}
    </div>
</body>
</html>
HTML;

        return response($html, $statusCode);
    }

    /**
     * 处理异常并返回响应
     */
    protected function handleException(Throwable $e, Request $request)
    {
        $isJson = $this->expectsJson($request);

        // 业务异常
        if ($e instanceof BusinessException) {
            if ($isJson) {
                return $e->render();
            }
            return $this->renderHtmlError($e->getMessage(), 400, $e);
        }

        // 权限异常
        if ($e instanceof UnauthorizedException) {
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => '权限不足',
                    'code' => 403,
                ], 403);
            }
            return $this->renderHtmlError('权限不足', 403, $e);
        }

        // 验证异常
        if ($e instanceof ValidationException) {
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => '验证失败',
                    'errors' => $e->errors(),
                    'code' => 422,
                ], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        }

        // 模型未找到异常
        if ($e instanceof ModelNotFoundException) {
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => '请求的资源不存在',
                    'code' => 404,
                ], 404);
            }
            return abort(404, '请求的资源不存在');
        }

        // HTTP 异常
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $msg = $e->getMessage() ?: '请求错误';
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => $msg,
                    'code' => $status,
                ], $status);
            }
            return abort($status, $msg);
        }

        // 未找到异常
        if ($e instanceof NotFoundHttpException) {
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => '请求的URL不存在',
                    'code' => 404,
                ], 404);
            }
            return abort(404, '请求的URL不存在');
        }

        // 默认错误响应
        $msg = config('app.debug') ? $e->getMessage() : '服务器内部错误';
        $status = 500;

        if ($isJson) {
            return response()->json([
                'status' => 'error',
                'message' => $msg,
                'code' => $status,
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ] : null,
            ], $status);
        }

        return $this->renderHtmlError($msg, $status, $e);
    }
}
