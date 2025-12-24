<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Page;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Frontend;
use App\Models\Language;
use App\Constants\Status;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;


class SiteController extends Controller
{
    public function index(Request $request)
    {
        // Validate and sanitize the reference parameter to prevent IDOR attacks
        $reference = $request->input('reference');

        if ($reference) {
            // Validate the reference parameter
            $validated = $request->validate([
                'reference' => 'sometimes|string|max:50|regex:/^[a-zA-Z0-9_-]+$/',
            ], [
                'reference.regex' => 'The reference field may only contain letters, numbers, underscores, and hyphens.',
                'reference.max' => 'The reference field must not exceed 50 characters.',
            ]);

            // Sanitize the validated reference
            $sanitizedReference = strip_tags(trim($validated['reference']));

            // Additional check: ensure reference doesn't contain path traversal or other malicious patterns
            if (preg_match('/[\.\/\\\\:\*\?"<>\|]/', $sanitizedReference)) {
                // Log suspicious attempt
                Log::channel('security')->warning('Suspicious reference parameter detected', [
                    'reference' => $reference,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'uri' => $request->fullUrl(),
                ]);

                // Reject suspicious reference
                $notify[] = ['error', 'Invalid reference parameter.'];
                return back()->withNotify($notify)->withInput();
            }

            // Store the sanitized reference in session
            session()->put('reference', $sanitizedReference);

            // Log successful reference capture (without sensitive data)
            Log::channel('security')->info('Reference parameter stored', [
                'reference_length' => strlen($sanitizedReference),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $pageTitle = 'Home';

        // 缓存首页数据 - 5分钟TTL
        $cacheKey = 'bc20_page:home:' . activeTemplate();
        $pageData = Cache::remember($cacheKey, 300, function () {
            $sections = Page::where('tempname', activeTemplate())->where('slug', '/')->first();
            return [
                'sections' => $sections,
                'seoContents' => @$sections->seo_content,
            ];
        });

        $sections = $pageData['sections'];
        $seoContents = $pageData['seoContents'];
        $seoImage = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;

        return view('Template::home', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
    }

    public function pages($slug)
    {
        $page        = Page::where('tempname', activeTemplate())->where('slug', $slug)->firstOrFail();
        $pageTitle   = $page->name;
        $sections    = $page->secs;
        $seoContents = $page->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::pages', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
    }


    public function contact()
    {
        $pageTitle   = "Contact Us";
        $user        = auth()->user();
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'contact')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::contact', compact('pageTitle', 'user', 'sections', 'seoContents', 'seoImage'));
    }


    public function contactSubmit(Request $request)
    {
        $request->validate([
            'name'    => 'required',
            'email'   => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $request->session()->regenerateToken();

        $random = getNumber();

        $ticket           = new SupportTicket();
        $ticket->user_id  = auth()->id() ?? 0;
        $ticket->name     = $request->name;
        $ticket->email    = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;


        $ticket->ticket     = $random;
        $ticket->subject    = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status     = Status::TICKET_OPEN;
        $ticket->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = auth()->user() ? auth()->user()->id : 0;
        $adminNotification->title     = 'A new contact message has been submitted';
        $adminNotification->click_url = urlPath('admin.ticket.view', $ticket->id);
        $adminNotification->save();

        $message                    = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message           = $request->message;
        $message->save();

        $notify[] = ['success', 'Ticket created successfully!'];

        return to_route('ticket.view', [$ticket->ticket])->withNotify($notify);
    }

    public function policyPages($slug)
    {
        $policy      = Frontend::where('slug', $slug)->where('data_keys', 'policy_pages.element')->firstOrFail();
        $pageTitle   = $policy->data_values->title;
        $seoContents = $policy->seo_content;
        $seoImage    = @$seoContents->image ? frontendImage('policy_pages', $seoContents->image, getFileSize('seo'), true) : null;
        return view('Template::policy', compact('policy', 'pageTitle', 'seoContents', 'seoImage'));
    }

    public function changeLanguage($lang = null)
    {
        $language          = Language::where('code', $lang)->first();
        if (!$language) $lang = 'en';
        session()->put('lang', $lang);
        return back();
    }

    public function blog()
    {
        $pageTitle = 'Blogs';
        $cachePrefix = md5(activeTemplate() . '_' . app()->getLocale());

        // 获取sections（缓存1小时）
        $sections = Cache::remember('blog_sections_' . $cachePrefix, 3600, function () {
            return Page::where('tempname', activeTemplate())->where('slug', 'blog')->firstOrFail();
        });

        // 获取博客列表（缓存15分钟，因为有分页）
        $page = request()->get('page', 1);
        $blogsCacheKey = 'blog_list_' . $cachePrefix . '_page_' . $page;
        $blogs = Cache::remember($blogsCacheKey, 900, function () {
            return Frontend::where('data_keys', 'blog.element')->latest()->paginate(12);
        });

        return view('Template::blog', compact('pageTitle', 'blogs', 'sections'));
    }

    public function blogDetails($slug)
    {
        $pageTitle   = 'Blog Details';
        $blog        = Frontend::where('slug', $slug)->where('data_keys', 'blog.element')->firstOrFail();
        $latestBlogs = Frontend::where('id', '!=', $blog->id)->where('data_keys', 'blog.element')->take(5)->get();
        $seoContents = $blog->seo_content;
        $seoImage    = @$seoContents->image ? frontendImage('blog', $seoContents->image, getFileSize('seo'), true) : null;
        return view('Template::blog_details', compact('blog', 'latestBlogs', 'pageTitle', 'seoContents', 'seoImage'));
    }

    public function faq()
    {
        $pageTitle = 'FAQs';
        $cacheKey = 'faq_page_' . md5(activeTemplate() . '_' . app()->getLocale());

        // 尝试从缓存获取数据
        $sections = Cache::remember($cacheKey, 3600, function () {
            return Page::where('tempname', activeTemplate())->where('slug', 'faq')->firstOrFail();
        });

        return view('Template::faq', compact('pageTitle', 'sections'));
    }


    public function cookieAccept()
    {
        Cookie::queue('gdpr_cookie', gs('site_name'), 43200);
    }

    public function cookiePolicy()
    {
        $cookieContent = Frontend::where('data_keys', 'cookie.data')->first();
        abort_if($cookieContent->data_values->status != Status::ENABLE, 404);
        $pageTitle = 'Cookie Policy';
        $cookie    = Frontend::where('data_keys', 'cookie.data')->first();
        return view('Template::cookie', compact('pageTitle', 'cookie'));
    }

    public function placeholderImage($size = null)
    {
        // 解析尺寸
        $dimensions = explode('x', $size);
        $imgWidth = (int) ($dimensions[0] ?? 100);
        $imgHeight = (int) ($dimensions[1] ?? 100);

        // 限制最大尺寸
        $imgWidth = min(max($imgWidth, 10), 2000);
        $imgHeight = min(max($imgHeight, 10), 2000);

        $text = $imgWidth . '×' . $imgHeight;

        // 使用缓存键
        $cacheKey = 'placeholder_' . md5($size);
        $cacheDir = storage_path('app/public/placeholder-images');

        // 创建缓存目录
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/' . $cacheKey . '.svg';
        $cacheFileJpg = $cacheDir . '/' . $cacheKey . '.jpg';

        // 检查是否有缓存文件(SVG优先)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            // 优先返回SVG
            $svg = file_get_contents($cacheFile);

            // 检查浏览器是否支持SVG
            $accept = request()->header('Accept', '');
            if (strpos($accept, 'image/svg+xml') !== false || strpos($accept, '*/*') !== false) {
                header('Content-Type: image/svg+xml');
                header('Cache-Control: public, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                echo $svg;
                return;
            }

            // 否则返回JPEG
            if (file_exists($cacheFileJpg) && (time() - filemtime($cacheFileJpg)) < 86400) {
                header('Content-Type: image/jpeg');
                header('Cache-Control: public, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                readfile($cacheFileJpg);
                return;
            }
        }

        // 生成SVG（极快）
        $fontSize = min(max((int) (($imgWidth - 20) / 6), 10), 72);
        $bgColor = sprintf('#f0f4f8');
        $textColor = sprintf('#64748b');
        $textLength = strlen($text);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
                <rect width="100%%" height="100%%" fill="%s"/>
                <text x="50%%" y="50%%" font-family="Arial, sans-serif" font-size="%dpx" fill="%s" text-anchor="middle" dominant-baseline="middle" font-weight="500">%s</text>
            </svg>',
            $imgWidth,
            $imgHeight,
            $imgWidth,
            $imgHeight,
            $bgColor,
            $fontSize,
            $textColor,
            htmlspecialchars($text)
        );

        // 保存SVG缓存
        file_put_contents($cacheFile, $svg);

        // 检查浏览器Accept头决定返回格式
        $accept = request()->header('Accept', '');

        if (strpos($accept, 'image/svg+xml') !== false || strpos($accept, '*/*') !== false) {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=86400');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
            echo $svg;
            return;
        }

        // 对于需要JPEG的浏览器，生成并缓存JPEG
        if (!extension_loaded('gd')) {
            // 如果没有GD库，直接返回SVG
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=86400');
            echo $svg;
            return;
        }

        // 转换SVG为JPEG
        try {
            $image = imagecreatetruecolor($imgWidth, $imgHeight);
            $bgFill = imagecolorallocate($image, 240, 248, 255);
            $textFill = imagecolorallocate($image, 100, 116, 139);
            imagefill($image, 0, 0, $bgFill);

            // 使用内置字体
            $fontSizeScaled = min((int) ($fontSize * 0.8), 72);
            $textX = $imgWidth / 2;
            $textY = $imgHeight / 2 + $fontSizeScaled / 3;

            // 使用imagestring代替imagettftext（不需要字体文件）
            imagestring($image, 5, $textX - ($textLength * $fontSizeScaled / 4), $textY - $fontSizeScaled, $text, $textFill);

            // 保存JPEG缓存
            imagejpeg($image, $cacheFileJpg, 85);
            imagedestroy($image);

            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=86400');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
            readfile($cacheFileJpg);
        } catch (\Exception $e) {
            // 如果转换失败，返回SVG
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=86400');
            echo $svg;
        }
    }

    public function languageFlag($code = null)
    {
        $key = strtolower(trim((string) $code));

        $flags = [
            'zh' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
  <rect width="50" height="50" fill="#de2910"/>
  <polygon fill="#ffde00" points="12.00,5.00 13.76,9.57 18.66,9.84 14.85,12.93 16.11,17.66 12.00,15.00 7.89,17.66 9.15,12.93 5.34,9.84 10.24,9.57"/>
  <polygon fill="#ffde00" points="24.00,5.00 24.76,6.95 26.85,7.07 25.24,8.40 25.76,10.43 24.00,9.30 22.24,10.43 22.76,8.40 21.15,7.07 23.24,6.95"/>
  <polygon fill="#ffde00" points="28.00,11.00 28.76,12.95 30.85,13.07 29.24,14.40 29.76,16.43 28.00,15.30 26.24,16.43 26.76,14.40 25.15,13.07 27.24,12.95"/>
  <polygon fill="#ffde00" points="28.00,21.00 28.76,22.95 30.85,23.07 29.24,24.40 29.76,26.43 28.00,25.30 26.24,26.43 26.76,24.40 25.15,23.07 27.24,22.95"/>
  <polygon fill="#ffde00" points="24.00,27.00 24.76,28.95 26.85,29.07 25.24,30.40 25.76,32.43 24.00,31.30 22.24,32.43 22.76,30.40 21.15,29.07 23.24,28.95"/>
</svg>
SVG,
            'zh-cn' => 'zh',
            'zh-hans' => 'zh',
            'en' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
  <rect width="50" height="50" fill="#ffffff"/>
  <rect y="0.00" width="50" height="3.85" fill="#b22234"/>
  <rect y="7.69" width="50" height="3.85" fill="#b22234"/>
  <rect y="15.38" width="50" height="3.85" fill="#b22234"/>
  <rect y="23.08" width="50" height="3.85" fill="#b22234"/>
  <rect y="30.77" width="50" height="3.85" fill="#b22234"/>
  <rect y="38.46" width="50" height="3.85" fill="#b22234"/>
  <rect y="46.15" width="50" height="3.85" fill="#b22234"/>
  <rect width="20" height="26.92" fill="#3c3b6e"/>
  <circle cx="3" cy="3.5" r="0.9" fill="#ffffff"/>
  <circle cx="7" cy="3.5" r="0.9" fill="#ffffff"/>
  <circle cx="11" cy="3.5" r="0.9" fill="#ffffff"/>
  <circle cx="15" cy="3.5" r="0.9" fill="#ffffff"/>
  <circle cx="3" cy="9.5" r="0.9" fill="#ffffff"/>
  <circle cx="7" cy="9.5" r="0.9" fill="#ffffff"/>
  <circle cx="11" cy="9.5" r="0.9" fill="#ffffff"/>
  <circle cx="15" cy="9.5" r="0.9" fill="#ffffff"/>
  <circle cx="3" cy="15.5" r="0.9" fill="#ffffff"/>
  <circle cx="7" cy="15.5" r="0.9" fill="#ffffff"/>
  <circle cx="11" cy="15.5" r="0.9" fill="#ffffff"/>
  <circle cx="15" cy="15.5" r="0.9" fill="#ffffff"/>
  <circle cx="3" cy="21.5" r="0.9" fill="#ffffff"/>
  <circle cx="7" cy="21.5" r="0.9" fill="#ffffff"/>
  <circle cx="11" cy="21.5" r="0.9" fill="#ffffff"/>
  <circle cx="15" cy="21.5" r="0.9" fill="#ffffff"/>
</svg>
SVG,
            'en-us' => 'en',
            'en-gb' => 'en',
        ];

        $svg = $flags[$key] ?? $flags['en'];
        if ($svg === 'zh' || $svg === 'en') {
            $svg = $flags[$svg];
        }

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function maintenance()
    {
        $pageTitle = 'Maintenance Mode';
        if (gs('maintenance_mode') == Status::DISABLE) {
            return to_route('home');
        }
        $maintenance = Frontend::where('data_keys', 'maintenance.data')->first();
        return view('Template::maintenance', compact('pageTitle', 'maintenance'));
    }

    public function products($categoryId = null)
    {
        $pageTitle = "Products";
        $cachePrefix = md5(activeTemplate() . '_' . app()->getLocale());

        // 获取产品列表（缓存15分钟）
        $page = request()->get('page', 1);
        $productsCacheKey = 'products_list_' . md5($cachePrefix . '_cat_' . $categoryId . '_page_' . $page);
        $products = Cache::remember($productsCacheKey, 900, function () use ($categoryId) {
            $products = Product::query();
            if ($categoryId) {
                $products = $products->where('category_id', $categoryId);
            }
            return $products->active()->with('category')->hasCategory()->paginate(getPaginate(16));
        });

        // 获取分类列表（缓存1小时）
        $categoriesCacheKey = 'products_categories_' . $cachePrefix;
        $categories = Cache::remember($categoriesCacheKey, 3600, function () {
            return Category::active()->hasActiveProduct()->get()->take(5);
        });

        // 获取页面sections（缓存1小时）
        $sectionsCacheKey = 'products_sections_' . $cachePrefix;
        $sections = Cache::remember($sectionsCacheKey, 3600, function () {
            return Page::where('tempname', activeTemplate())->where('slug', 'products')->first();
        });

        $seoContents = @$sections->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;

        return view('Template::products', compact('pageTitle', 'products', 'categories', 'categoryId', 'sections', 'seoContents', 'seoImage'));
    }

    public function productDetails($id)
    {
        $pageTitle = "Product Details";
        $product   = Product::active()->hasCategory()->findOrFail($id);
        $relates   = Product::active()->hasCategory()->where('category_id', $product->category_id)->where('id', '!=', $product->id)->latest()->limit(10)->get();

        $seoContents['social_title']       = $product->meta_title;
        $seoContents['keywords']           = $product->meta_keyword;
        $seoContents['description']        = strLimit(strip_tags($product->meta_description), 150);
        $seoContents['social_description'] = strLimit(strip_tags($product->meta_description), 150);
        $seoContents['image_size']         = getFileSize('products');
        $seoImage                          = getImage(getFilePath('products') . '/' . @$product->thumbnail, getFileSize('products'));
        return view('Template::product_detail', compact('pageTitle', 'product', 'relates', 'seoContents', 'seoImage'));
    }

    public function checkUsername(Request $request)
    {
        $id = $this->resolveReferrerUser((string) $request->username);
        if ($id) {
            return response()->json(['success' => true, 'msg' => "<span class='help-block'><strong class='text-success'>Referrer matched</strong></span>
            <input type='hidden' id='referrer_id' value='$id->id' name='referrer_id'>"]);
        } else {
            return response()->json(['success' => false, 'msg' => "<span class='help-block'><strong class='text-danger'>Referrer not found</strong></span>"]);
        }
    }

    public function userPosition(Request $request)
    {

        if (!$request->referrer) {
            return response()->json(['success' => false, 'msg' => "<span class='help-block'><strong class='text-danger'>Inter Referral name first</strong></span>"]);
        }
        if (!$request->position) {
            return response()->json(['success' => false, 'msg' => "<span class='help-block'><strong class='text-danger'>Select your position*</strong></span>"]);
        }
        $user       = User::find($request->referrer);
        $pos        = getPosition($user->id, $request->position);
        $join_under = User::find($pos['pos_id']);
        if ($pos['position'] == 1)
            $position = 'Left';
        else {
            $position = 'Right';
        }
        return response()->json(['success' => true, 'msg' => "<span class='help-block'><strong class='text-success'>You are joining under $join_under->username at $position  </strong></span>"]);
    }

    private function resolveReferrerUser(string $input): ?User
    {
        $value = trim($input);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/\s+/', ' ', $value);

        if (ctype_digit($value)) {
            return User::find((int) $value);
        }

        return User::where('username', $value)
            ->orWhere('email', $value)
            ->orWhereRaw("concat(firstname, ' ', lastname) = ?", [$value])
            ->orWhereRaw("concat(lastname, ' ', firstname) = ?", [$value])
            ->first();
    }
}
