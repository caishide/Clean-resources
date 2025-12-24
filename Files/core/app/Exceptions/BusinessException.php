<?php

namespace App\Exceptions;

use Exception;

/**
 * 业务异常类
 * 
 * 用于处理业务逻辑中的异常情况
 */
class BusinessException extends Exception
{
    protected $code;
    protected $message;
    protected $data;

    /**
     * 创建业务异常
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param mixed $data 附加数据
     */
    public function __construct(string $message = "业务处理失败", int $code = 400, $data = null)
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }

    /**
     * 获取附加数据
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 渲染异常为 HTTP 响应
     */
    public function render()
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->message,
            'code' => $this->code,
            'data' => $this->data,
        ], $this->code);
    }

    /**
     * 创建结算异常
     */
    public static function settlement(string $message, $data = null): self
    {
        return new self($message, 400, $data);
    }

    /**
     * 创建调整异常
     */
    public static function adjustment(string $message, $data = null): self
    {
        return new self($message, 400, $data);
    }

    /**
     * 创建权限异常
     */
    public static function permission(string $message = "权限不足"): self
    {
        return new self($message, 403);
    }

    /**
     * 创建验证异常
     */
    public static function validation(string $message, $errors = null): self
    {
        return new self($message, 422, $errors);
    }

    /**
     * 创建未找到异常
     */
    public static function notFound(string $message = "资源不存在"): self
    {
        return new self($message, 404);
    }

    /**
     * 创建冲突异常
     */
    public static function conflict(string $message = "数据冲突"): self
    {
        return new self($message, 409);
    }
}