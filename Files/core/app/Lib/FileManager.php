<?php

namespace App\Lib;

use App\Constants\FileInfo;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileManager
{
    /*
    |--------------------------------------------------------------------------
    | File Manager
    |--------------------------------------------------------------------------
    |
    | FileManager class is using to manage edit, update, remove files. Developer
    | can manage any kind of files from here. But some limitations is here for image.
    | This class using a trait to manage the file paths and sizes. Developer can also
    | use this class as a helper function.
    |
    */

    /**
    * The file which will be uploaded
    *
    *
    * @var object
    */
	protected $file;

    /**
    * The path where will be uploaded
    *
    * @var string
    */
	public $path;

    /**
    * The size, if the file is image
    *
    * @var string
    */
	public $size;

    /**
    * Check the file is image or not
    *
    * @var boolean
    */
	protected $isImage;

    /**
    * Thumbnail version size, if required
    * and if the file is image
    *
    * @var string
    */
	public $thumb;

    /**
    * Old filename, which will be removed
    *
    * @var string
    */
	public $old;

    /**
    * Current filename, which is uploading
    *
    * @var string
    */
	public $filename;


    /**
    * Set the file and file type to properties if exist
    *
    * @param $file
    * @return void
    */
	public function __construct($file = null){
		$this->file = $file;
		if ($file) {
			$imageExtensions = ['jpg','jpeg','png','gif'];
			$extension = strtolower($file->getClientOriginalExtension());
			if (in_array($extension, $imageExtensions)) {
				$this->isImage = true;
				// 🔒 修复文件上传安全：验证MIME类型和扩展名（仅图片）
				$this->validateFileSecurity();
			}else{
				$this->isImage = false;
			}
		}
	}

    /**
     * 🔒 文件上传安全：验证MIME类型和文件内容
     *
     * @throws \Exception
     * @return void
     */
    private function validateFileSecurity(): void
    {
        // 允许的图片MIME类型
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
        ];

        // 允许的扩展名
        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif'
        ];

        // 获取文件的真实MIME类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file->path());
        finfo_close($finfo);

        // 验证MIME类型
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new \Exception('Invalid file type. Only JPEG, PNG, and GIF images are allowed.');
        }

        // 验证文件扩展名
        $extension = strtolower($this->file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception('Invalid file extension.');
        }

        // 验证MIME类型和扩展名是否匹配
        $mimeToExtension = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
        ];

        if (!isset($mimeToExtension[$mimeType]) ||
            !in_array($extension, $mimeToExtension[$mimeType])) {
            throw new \Exception('File extension does not match file type.');
        }

        // 检查文件大小（根据需求调整）
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($this->file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum allowed size (5MB).');
        }
    }

    /**
    * File upload process
    *
    * @return void
    */
	public function upload(){

        //create the directory if doesn't exists
		$path = $this->makeDirectory();
		if (!$path) throw new \Exception('File could not been created.');

        //remove the old file if exist
		if ($this->old) {
            $this->removeFile();
	    }

        //get the filename
        if(!$this->filename){
            $this->filename = $this->getFileName();
        }

        //upload file or image
	    if ($this->isImage == true) {
	    	$this->uploadImage();
	    }else{
	    	$this->uploadFile();
	    }
	}

    /**
    * Upload the file if this is image
    *
    * @return void
    */
	protected function uploadImage(){
        $manager = new ImageManager(new Driver());
        $image = $manager->read($this->file);

        //resize the
	    if ($this->size) {
	        $size = explode('x', strtolower($this->size));
	        $image->resize($size[0], $size[1]);
	    }
        //save the image
	    $image->save($this->path . '/' . $this->filename);

        //save the image as thumbnail version
	    if ($this->thumb) {
            if ($this->old) {
                $this->removeFile($this->path . '/thumb_' . $this->old);
            }
	        $thumb = explode('x', $this->thumb);
	        $manager->read($this->file)->resize($thumb[0], $thumb[1])->save($this->path . '/thumb_' . $this->filename);
	    }
	}


    /**
    * Upload the file if this is not a image
    *
    * @return void
    */
	protected function uploadFile(){
	    $this->file->move($this->path,$this->filename);
	}

    /**
    * Make directory doesn't exists
    * Developer can also call this method statically
    *
    * @param $location
    * @return string
    */
	public function makeDirectory($location = null){
		if (!$location) $location = $this->path;
		if (file_exists($location)) return true;
    	return mkdir($location, 0755, true);
	}

    /**
    * Remove all directory inside the location
    * Developer can also call this method statically
    *
    * @param $location
    * @return void
    */
	public function removeDirectory($location = null){
		if (!$location) $location = $this->path;
		if (! is_dir($location)) {
	        throw new \InvalidArgumentException("$location must be a directory");
	    }
	    if (substr($location, strlen($location) - 1, 1) != '/') {
	        $location .= '/';
	    }
	    $files = glob($location . '*', GLOB_MARK);
	    foreach ($files as $file) {
	        if (is_dir($file)) {
	            static::removeDirectory($file);
	        } else {
	            unlink($file);
	        }
	    }
	    rmdir($location);
	}

    /**
    * Remove the file if exists
    * Developer can also call this method statically
    *
    * @param $path
    * @return void
    */
	public function removeFile($path = null)
	{
		if (!$path) $path = $this->path . '/' . $this->old;

	    file_exists($path) && is_file($path) ? @unlink($path) : false;

	    if ($this->thumb) {
	    	if (!$path) $path = $this->path . '/thumb_' . $this->old;
	    	file_exists($path) && is_file($path) ? @unlink($path) : false;
	    }
	}

    /**
    * Generating the filename which is uploading
    *
    * @return string
    */
	protected function getFileName(){
		return uniqid() . time() . '.' . $this->file->getClientOriginalExtension();
	}

    /**
    * Get access of array from fileInfo method as non-static method.
    * Also get some others method
    *
    * @return string|void
    */
	public function __call($method,$args){
        $fileInfo = new FileInfo;
		$filePaths = $fileInfo->fileInfo();
		if (array_key_exists($method, $filePaths)) {
			$path = json_decode(json_encode($filePaths[$method]));
			return $path;
		}else{
			if (method_exists($this,$method)) {
				$this->$method(...$args);
			}else{
				throw new \Exception('File key or method doesn\'t exists.');
			}
		}
	}

    /**
    * Get access some non-static method as static method
    *
    * @return void
    */
	public static function __callStatic($method,$args){
		$selfClass = new FileManager;
		$selfClass->$method(...$args);
	}

}
