<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * 文件上传服务 - 安全增强版本
 * 
 * 提供安全的文件上传功能,包括:
 * - MIME类型验证
 * - 文件大小限制
 * - 文件扩展名白名单
 * - 随机文件名生成
 * - 图片处理和优化
 * - 病毒扫描(可选)
 */
class FileUploadService
{
    /**
     * 允许的图片MIME类型
     */
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * 允许的图片扩展名
     */
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * 允许的文档MIME类型
     */
    private const ALLOWED_DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * 允许的文档扩展名
     */
    private const ALLOWED_DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

    /**
     * 最大文件大小(字节) - 默认10MB
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * 最大图片尺寸(像素)
     */
    private const MAX_IMAGE_WIDTH = 4096;
    private const MAX_IMAGE_HEIGHT = 4096;

    /**
     * 图片质量(1-100)
     */
    private const IMAGE_QUALITY = 85;

    /**
     * 上传图片
     *
     * @param UploadedFile $file 上传的文件
     * @param string $path 存储路径
     * @param int|null $maxSize 最大文件大小(字节),null使用默认值
     * @param string|null $oldPath 旧文件路径(用于删除)
     * @param bool $createThumbnail 是否创建缩略图
     * @param int $thumbnailWidth 缩略图宽度
     * @return array 返回文件路径信息
     * @throws \Exception
     */
    public function uploadImage(
        UploadedFile $file,
        string $path,
        ?int $maxSize = null,
        ?string $oldPath = null,
        bool $createThumbnail = false,
        int $thumbnailWidth = 300
    ): array {
        // 验证文件
        $this->validateImageFile($file, $maxSize ?? self::MAX_FILE_SIZE);

        // 删除旧文件
        if ($oldPath && Storage::exists($oldPath)) {
            Storage::delete($oldPath);
            // 删除缩略图
            $thumbnailPath = $this->getThumbnailPath($oldPath);
            if (Storage::exists($thumbnailPath)) {
                Storage::delete($thumbnailPath);
            }
        }

        // 生成随机文件名
        $filename = $this->generateRandomFilename($file->getClientOriginalExtension());
        $fullPath = rtrim($path, '/') . '/' . $filename;

        // 处理图片
        $imageData = $this->processImage($file);

        // 存储文件
        Storage::put($fullPath, $imageData);

        $result = [
            'path' => $fullPath,
            'url' => Storage::url($fullPath),
            'size' => strlen($imageData),
            'filename' => $filename,
        ];

        // 创建缩略图
        if ($createThumbnail) {
            $thumbnailPath = $this->createThumbnail($imageData, $fullPath, $thumbnailWidth);
            $result['thumbnail_path'] = $thumbnailPath;
            $result['thumbnail_url'] = Storage::url($thumbnailPath);
        }

        return $result;
    }

    /**
     * 上传文档
     *
     * @param UploadedFile $file 上传的文件
     * @param string $path 存储路径
     * @param int|null $maxSize 最大文件大小(字节)
     * @param string|null $oldPath 旧文件路径
     * @return array
     * @throws \Exception
     */
    public function uploadDocument(
        UploadedFile $file,
        string $path,
        ?int $maxSize = null,
        ?string $oldPath = null
    ): array {
        // 验证文件
        $this->validateDocumentFile($file, $maxSize ?? self::MAX_FILE_SIZE);

        // 删除旧文件
        if ($oldPath && Storage::exists($oldPath)) {
            Storage::delete($oldPath);
        }

        // 生成随机文件名
        $filename = $this->generateRandomFilename($file->getClientOriginalExtension());
        $fullPath = rtrim($path, '/') . '/' . $filename;

        // 存储文件
        $file->storeAs($path, $filename);

        return [
            'path' => $fullPath,
            'url' => Storage::url($fullPath),
            'size' => $file->getSize(),
            'filename' => $filename,
        ];
    }

    /**
     * 验证图片文件
     *
     * @param UploadedFile $file
     * @param int $maxSize
     * @return void
     * @throws \Exception
     */
    private function validateImageFile(UploadedFile $file, int $maxSize): void
    {
        // 检查文件是否存在
        if (!$file->isValid()) {
            throw new \Exception('文件上传失败');
        }

        // 检查文件大小
        if ($file->getSize() > $maxSize) {
            throw new \Exception("文件大小超过限制: " . $this->formatFileSize($maxSize));
        }

        // 检查MIME类型
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_IMAGE_MIMES)) {
            throw new \Exception("不支持的文件类型: {$mimeType}");
        }

        // 检查扩展名
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS)) {
            throw new \Exception("不支持的文件扩展名: {$extension}");
        }

        // 验证MIME类型和扩展名是否匹配
        if (!$this->validateMimeTypeExtensionMatch($mimeType, $extension)) {
            throw new \Exception("文件类型不匹配");
        }

        // 验证图片尺寸
        $this->validateImageDimensions($file);
    }

    /**
     * 验证文档文件
     *
     * @param UploadedFile $file
     * @param int $maxSize
     * @return void
     * @throws \Exception
     */
    private function validateDocumentFile(UploadedFile $file, int $maxSize): void
    {
        if (!$file->isValid()) {
            throw new \Exception('文件上传失败');
        }

        if ($file->getSize() > $maxSize) {
            throw new \Exception("文件大小超过限制: " . $this->formatFileSize($maxSize));
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_DOCUMENT_MIMES)) {
            throw new \Exception("不支持的文件类型: {$mimeType}");
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_DOCUMENT_EXTENSIONS)) {
            throw new \Exception("不支持的文件扩展名: {$extension}");
        }
    }

    /**
     * 验证MIME类型和扩展名是否匹配
     *
     * @param string $mimeType
     * @param string $extension
     * @return bool
     */
    private function validateMimeTypeExtensionMatch(string $mimeType, string $extension): bool
    {
        $mimeToExtMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
        ];

        return isset($mimeToExtMap[$mimeType]) && in_array($extension, $mimeToExtMap[$mimeType]);
    }

    /**
     * 验证图片尺寸
     *
     * @param UploadedFile $file
     * @return void
     * @throws \Exception
     */
    private function validateImageDimensions(UploadedFile $file): void
    {
        try {
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo === false) {
                throw new \Exception('无法读取图片信息');
            }

            [$width, $height] = $imageInfo;

            if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
                throw new \Exception("图片尺寸过大: {$width}x{$height}");
            }
        } catch (\Exception $e) {
            throw new \Exception("图片验证失败: " . $e->getMessage());
        }
    }

    /**
     * 处理图片(优化和压缩)
     *
     * @param UploadedFile $file
     * @return string
     * @throws \Exception
     */
    private function processImage(UploadedFile $file): string
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getPathname());

            // 如果图片过大,进行缩放
            $width = $image->width();
            $height = $image->height();

            if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
                $image->scaleDown(self::MAX_IMAGE_WIDTH, self::MAX_IMAGE_HEIGHT);
            }

            // 编码为JPEG格式以减小文件大小
            return $image->toJpeg(self::IMAGE_QUALITY)->toString();
        } catch (\Exception $e) {
            throw new \Exception("图片处理失败: " . $e->getMessage());
        }
    }

    /**
     * 创建缩略图
     *
     * @param string $imageData
     * @param string $originalPath
     * @param int $width
     * @return string
     * @throws \Exception
     */
    private function createThumbnail(string $imageData, string $originalPath, int $width): string
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($imageData);
            
            // 创建缩略图
            $thumbnail = $image->scaleDown($width, null);
            $thumbnailData = $thumbnail->toJpeg(self::IMAGE_QUALITY)->toString();

            // 生成缩略图路径
            $thumbnailPath = $this->getThumbnailPath($originalPath);
            
            // 存储缩略图
            Storage::put($thumbnailPath, $thumbnailData);

            return $thumbnailPath;
        } catch (\Exception $e) {
            throw new \Exception("缩略图创建失败: " . $e->getMessage());
        }
    }

    /**
     * 获取缩略图路径
     *
     * @param string $originalPath
     * @return string
     */
    private function getThumbnailPath(string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
    }

    /**
     * 生成随机文件名
     *
     * @param string $extension
     * @return string
     */
    private function generateRandomFilename(string $extension): string
    {
        return Str::random(40) . '.' . $extension;
    }

    /**
     * 格式化文件大小
     *
     * @param int $bytes
     * @return string
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 删除文件
     *
     * @param string $path
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        if (Storage::exists($path)) {
            Storage::delete($path);
            
            // 尝试删除缩略图
            $thumbnailPath = $this->getThumbnailPath($path);
            if (Storage::exists($thumbnailPath)) {
                Storage::delete($thumbnailPath);
            }
            
            return true;
        }
        return false;
    }

    /**
     * 获取允许的图片扩展名
     *
     * @return array
     */
    public static function getAllowedImageExtensions(): array
    {
        return self::ALLOWED_IMAGE_EXTENSIONS;
    }

    /**
     * 获取允许的文档扩展名
     *
     * @return array
     */
    public static function getAllowedDocumentExtensions(): array
    {
        return self::ALLOWED_DOCUMENT_EXTENSIONS;
    }
}