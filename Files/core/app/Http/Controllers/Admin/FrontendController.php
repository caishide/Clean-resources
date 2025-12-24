<?php

namespace App\Http\Controllers\Admin;

use App\Models\Frontend;
use App\Http\Controllers\Controller;
use App\Rules\FileTypeValidate;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FrontendController extends Controller
{
    /**
     * @var FileUploadService
     */
    protected FileUploadService $fileUploadService;

    /**
     * 构造函数
     */
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
        // Laravel 11 中间件在路由中注册
    }

    public function index(){
        $pageTitle = 'Manage Frontend Content';
        return view('admin.frontend.index', compact('pageTitle'));
    }

    public function templates()
    {
        abort(404);
        $pageTitle = 'Templates';
        $temPaths = array_filter(glob('core/resources/views/templates/*'), 'is_dir');
        foreach ($temPaths as $key => $temp) {
            $arr = explode('/', $temp);
            $tempname = end($arr);
            $templates[$key]['name'] = $tempname;
            $templates[$key]['image'] = asset($temp) . '/preview.jpg';
        }
        $extraTemplates = json_decode(getTemplates(), true);
        return view('admin.frontend.templates', compact('pageTitle', 'templates', 'extraTemplates'));

    }

    public function templatesActive(Request $request)
    {
        $general = gs();

        $general->active_template = $request->name;
        $general->save();

        $notify[] = ['success', strtoupper($request->name).' template activated successfully'];
        return back()->withNotify($notify);
    }

    public function seoEdit()
    {
        $pageTitle = 'SEO Configuration';
        $seo = Frontend::where('data_keys', 'seo.data')->first();
        if(!$seo){
            $data_values = '{"keywords":[],"description":"","social_title":"","social_description":"","image":null}';
            $data_values = json_decode($data_values, true);
            $frontend = new Frontend();
            $frontend->data_keys = 'seo.data';
            $frontend->data_values = $data_values;
            $frontend->save();
        }
        return view('admin.frontend.seo', compact('pageTitle', 'seo'));
    }



    public function frontendSections($key)
    {
        $sections = getPageSections();
        abort_if(!$sections || !isset($sections->$key) || !$sections->$key->builder, 404);
        $section = $sections->$key;
        $content = Frontend::where('data_keys', $key . '.content')->where('tempname',activeTemplateName())->orderBy('id','desc')->first();
        $elements = Frontend::where('data_keys', $key . '.element')->where('tempname',activeTemplateName())->orderBy('id','desc')->get();
        $pageTitle = $section->name ;
        return view('admin.frontend.section', compact('section', 'content', 'elements', 'key', 'pageTitle'));
    }




    public function frontendContent(Request $request, $key)
    {
        try {
            // HTMLPurifier 可选依赖，如果不存在则跳过过滤
            $purifier = class_exists('HTMLPurifier') ? new \HTMLPurifier(\HTMLPurifier_Config::createDefault()) : null;
            $valInputs = $request->except('_token', 'image_input', 'key', 'status', 'type', 'id','slug');
            foreach ($valInputs as $keyName => $input) {
                if (gettype($input) == 'array') {
                    $inputContentValue[$keyName] = $input;
                    continue;
                }
                
                // 修复 nicEdit/Word 复制的 Unicode 转义序列问题（如 lu3010 -> \u3010）
                $input = preg_replace('/lu([0-9a-fA-F]{4})/', '\\u$1', $input);
                
                // 检测是否包含中文字符（包含中文则跳过 HTML 过滤）
                $hasChinese = preg_match('/[\x{4e00}-\x{9fff}]/u', $input) > 0;
                if ($hasChinese) {
                    // 中文字符只做基本转义，保留所有内容
                    $inputContentValue[$keyName] = htmlspecialchars_decode(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
                } elseif ($purifier && $input) {
                    // 非中文内容使用 HTMLPurifier 过滤
                    $inputContentValue[$keyName] = htmlspecialchars_decode($purifier->purify($input));
                } else {
                    // 没有 HTMLPurifier 时使用 strip_tags
                    $inputContentValue[$keyName] = htmlspecialchars_decode(strip_tags($input));
                }
            }
        $type = $request->type;
        if (!$type) {
            abort(404);
        }
        $imgJson = @getPageSections()->$key->$type->images;
        $validationRule = [];
        $validationMessage = [];
        foreach ($request->except('_token', 'video') as $inputField => $val) {
            if ($inputField == 'has_image' && $imgJson) {
                foreach ($imgJson as $imgValKey => $imgJsonVal) {
                    $validationRule['image_input.'.$imgValKey] = ['nullable','image',new FileTypeValidate(['jpg','jpeg','png'])];
                    $validationMessage['image_input.'.$imgValKey.'.image'] = keyToTitle($imgValKey).' must be an image';
                    $validationMessage['image_input.'.$imgValKey.'.mimes'] = keyToTitle($imgValKey).' file type not supported';
                }
                continue;
            }elseif($inputField == 'seo_image'){
                $validationRule['image_input'] = ['nullable', 'image', new FileTypeValidate(['jpeg', 'jpg', 'png'])];
                continue;
            }
            $validationRule[$inputField] = ['required'];
            if ($inputField == 'slug') {
                $validationRule[$inputField] = [Rule::unique('frontends')->where(function ($query) use ($request) {
                    return $query->where('id', '!=', $request->id)
                        ->where('tempname', activeTemplateName());
                })];
            }
        }

        $request->validate($validationRule, $validationMessage, ['image_input' => 'image']);

        if ($request->id) {
            $content = Frontend::findOrFail($request->id);
        } else {
            $content = Frontend::where('data_keys', $key . '.' . $request->type);
            if ($type != 'data') {
                $content = $content->where('tempname',activeTemplateName());
            }
            $content = $content->first();
            if (!$content || $request->type == 'element') {
                $content = new Frontend();
                $content->data_keys = $key . '.' . $request->type;
                $content->save();
            }
        }
        if ($type == 'data') {
            $inputContentValue['image'] = @$content->data_values->image;
            if ($request->hasFile('image_input')) {
                try {
                    // 使用安全的文件上传服务
                    $uploadResult = $this->fileUploadService->uploadImage(
                        $request->image_input,
                        getFilePath('seo'),
                        getFileSize('seo'),
                        @$content->data_values->image,
                        true // 创建缩略图
                    );
                    $inputContentValue['image'] = $uploadResult['path'];
                } catch (\Exception $exp) {
                    $notify[] = ['error', '图片上传失败: ' . $exp->getMessage()];
                    return back()->withNotify($notify);
                }
            }
        }else{
            if ($imgJson) {
                foreach ($imgJson as $imgKey => $imgValue) {
                    $imgData = @$request->image_input[$imgKey];
                    if (is_file($imgData)) {
                        try {
                            // 使用安全的文件上传服务
                            $uploadResult = $this->fileUploadService->uploadImage(
                                $imgData,
                                'assets/images/frontend/' . $key,
                                @$imgJson->$imgKey->size ?: getFileSize('seo'),
                                @$content->data_values->$imgKey,
                                !empty($imgJson->$imgKey->thumb)
                            );
                            $inputContentValue[$imgKey] = $uploadResult['path'];
                        } catch (\Exception $exp) {
                            $notify[] = ['error', '图片上传失败: ' . $exp->getMessage()];
                            return back()->withNotify($notify);
                        }
                    } else if (isset($content->data_values->$imgKey)) {
                        $inputContentValue[$imgKey] = $content->data_values->$imgKey;
                    }
                }
            }
        }
        $content->data_values = $inputContentValue;
        $rawSlug = (string) $request->slug;
        $normalizedSlug = Str::slug($rawSlug);
        if ($normalizedSlug === '' && $rawSlug !== '') {
            $normalizedSlug = preg_replace('/[\\s\\/]+/u', '-', trim($rawSlug));
        }
        $content->slug = $normalizedSlug;
        if ($type != 'data') {
            $content->tempname = activeTemplateName();
        }
        $content->save();

        if (!$request->id && @getPageSections()->$key->element->seo && $type != 'content') {
            $notify[] = ['info','Configure SEO content for ranking'];
            $notify[] = ['success', 'Content updated successfully'];
            return to_route('admin.frontend.sections.element.seo',[$key,$content->id])->withNotify($notify);
        }

        $notify[] = ['success', 'Content updated successfully'];
        return back()->withNotify($notify);
        } catch (\Exception $e) {
            \Log::error('Frontend content update error: ' . $e->getMessage(), [
                'key' => $key,
                'type' => $request->type,
                'trace' => $e->getTraceAsString()
            ]);
            $notify[] = ['error', '更新失败: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }



    public function frontendElement($key, $id = null)
    {
        $section = @getPageSections()->$key;
        if (!$section) {
            return abort(404);
        }

        unset($section->element->modal);
        unset($section->element->seo);
        $pageTitle = $section->name . ' Items';
        if ($id) {
            $data = Frontend::where('tempname',activeTemplateName())->findOrFail($id);
            return view('admin.frontend.element', compact('section', 'key', 'pageTitle', 'data'));
        }
        return view('admin.frontend.element', compact('section', 'key', 'pageTitle'));
    }


    public function frontendElementSlugCheck($key,$id = null){
        $content = Frontend::where('data_keys', $key . '.element')->where('tempname', activeTemplateName())->where('slug',request()->slug);
        if ($id) {
            $content = $content->where('id','!=',$id);
        }
        $exist = $content->exists();
        return response()->json([
            'exists'=>$exist
        ]);
    }


    public function frontendSeo($key,$id)
    {
        $hasSeo = @getPageSections()->$key->element->seo;
        if (!$hasSeo) {
            abort(404);
        }
        $data = Frontend::findOrFail($id);
        $pageTitle = 'SEO Configuration';
        return view('admin.frontend.frontend_seo', compact('pageTitle','key','data'));
    }

    public function frontendSeoUpdate(Request $request, $key,$id){
        $request->validate([
            'image'=>['nullable',new FileTypeValidate(['jpeg', 'jpg', 'png'])]
        ]);
        $hasSeo = @getPageSections()->$key->element->seo;
        if (!$hasSeo) {
            abort(404);
        }
        $data = Frontend::findOrFail($id);
        $image = @$data->seo_content->image;
        if ($request->hasFile('image')) {
            try {
                // 使用安全的文件上传服务
                $path = 'assets/images/frontend/' . $key . '/seo';
                $uploadResult = $this->fileUploadService->uploadImage(
                    $request->image,
                    $path,
                    getFileSize('seo'),
                    @$data->seo_content->image,
                    false // SEO图片不需要缩略图
                );
                $image = $uploadResult['path'];
            } catch (\Exception $exp) {
                $notify[] = ['error', '图片上传失败: ' . $exp->getMessage()];
                return back()->withNotify($notify);
            }
        }
        $data->seo_content = [
            'image'=>$image,
            'description'=>$request->description,
            'social_title'=>$request->social_title,
            'social_description'=>$request->social_description,
            'keywords'=>$request->keywords ,
        ];
        $data->save();

        $notify[] = ['success', 'SEO content updated successfully'];
        return back()->withNotify($notify);

    }


    protected function storeImage($imgJson,$type,$key,$image,$imgKey,$oldImage = null)
    {
        $path = 'assets/images/frontend/' . $key;
        if ($type == 'element' || $type == 'content') {
            $size = @$imgJson->$imgKey->size;
            $thumb = @$imgJson->$imgKey->thumb;
        }else{
            $path = getFilePath($key);
            $size = getFileSize($key);
            $thumb = @fileManager()->$key()->thumb;
        }
        return fileUploader($image, $path, $size, $oldImage, $thumb);
    }

    public function remove($id)
    {
        $frontend = Frontend::findOrFail($id);
        $key = explode('.', @$frontend->data_keys)[0];
        $type = explode('.', @$frontend->data_keys)[1];
        if (@$type == 'element' || @$type == 'content') {
            $path = 'assets/images/frontend/' . $key;
            $imgJson = @getPageSections()->$key->$type->images;
            if ($imgJson) {
                foreach ($imgJson as $imgKey => $imgValue) {
                    // 使用安全的文件删除服务
                    $filePath = $path . '/' . @$frontend->data_values->$imgKey;
                    $this->fileUploadService->deleteFile($filePath);
                }
            }
            if (@getPageSections()->$key->element->seo) {
                $seoImagePath = $path . '/seo/' . @$frontend->seo_content->image;
                $this->fileUploadService->deleteFile($seoImagePath);
            }
        }
        $frontend->delete();
        $notify[] = ['success', 'Content removed successfully'];
        return back()->withNotify($notify);
    }


}
