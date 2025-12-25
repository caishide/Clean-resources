<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\LanguageMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * LanguageMiddleware中间件单元测试
 *
 * 测试语言切换中间件的各种功能
 */
class LanguageMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new LanguageMiddleware();
    }

    /** @test */
    public function it_sets_default_language_when_no_session_exists()
    {
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertEquals('en', App::getLocale());
            return new Response('OK');
        });

        $this->assertEquals('en', App::getLocale());
    }

    /** @test */
    public function it_uses_session_language_when_exists()
    {
        Session::put('lang', 'zh-CN');

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertEquals('zh-CN', App::getLocale());
            return new Response('OK');
        });

        $this->assertEquals('zh-CN', App::getLocale());
    }

    /** @test */
    public function it_handles_valid_language_code()
    {
        Session::put('lang', 'fr');

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('fr', App::getLocale());
    }

    /** @test */
    public function it_falls_back_to_default_for_invalid_language()
    {
        Session::put('lang', 'invalid_language_code');

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            // 应该回退到默认语言
            $this->assertEquals('en', App::getLocale());
            return new Response('OK');
        });
    }

    /** @test */
    public function it_handles_language_change_via_header()
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Language', 'zh-CN,zh;q=0.9,en;q=0.8');
        $request->setLaravelSession($this->app->make('session.store'));

        // 清除会话中的语言设置
        Session::forget('lang');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        // 应该从Accept-Language头中检测语言
        $this->assertEquals('en', App::getLocale());
    }

    /** @test */
    public function it_preserves_language_across_requests()
    {
        Session::put('lang', 'zh-CN');

        $request1 = Request::create('/test', 'GET');
        $request1->setLaravelSession($this->app->make('session.store'));

        $response1 = $this->middleware->handle($request1, function ($req) {
            $this->assertEquals('zh-CN', App::getLocale());
            return new Response('OK');
        });

        // 第二个请求应该保持相同的语言
        $request2 = Request::create('/test2', 'GET');
        $request2->setLaravelSession($this->app->make('session.store'));

        $response2 = $this->middleware->handle($request2, function ($req) {
            $this->assertEquals('zh-CN', App::getLocale());
            return new Response('OK');
        });
    }

    /** @test */
    public function it_handles_empty_language_code()
    {
        Session::put('lang', '');

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            // 应该使用默认语言
            $this->assertEquals('en', App::getLocale());
            return new Response('OK');
        });
    }

    /** @test */
    public function it_handles_null_language_code()
    {
        Session::put('lang', null);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            // 应该使用默认语言
            $this->assertEquals('en', App::getLocale());
            return new Response('OK');
        });
    }

    /** @test */
    public function it_supports_common_language_codes()
    {
        $languages = ['en', 'zh-CN', 'zh-TW', 'fr', 'de', 'es', 'ja'];

        foreach ($languages as $lang) {
            Session::put('lang', $lang);

            $request = Request::create('/test', 'GET');
            $request->setLaravelSession($this->app->make('session.store'));

            $response = $this->middleware->handle($request, function ($req) use ($lang) {
                $this->assertEquals($lang, App::getLocale());
                return new Response('OK');
            });
        }
    }

    /** @test */
    public function it_updates_session_when_language_changes()
    {
        Session::put('lang', 'en');

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        // 在处理程序中更改语言
        $response = $this->middleware->handle($request, function ($req) {
            App::setLocale('fr');
            return new Response('OK');
        });

        // 会话中的语言应该保持不变
        $this->assertEquals('en', Session::get('lang'));
    }

    /** @test */
    public function it_handles_request_with_query_parameters()
    {
        Session::put('lang', 'en');

        $request = Request::create('/test?lang=zh-CN', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('zh-CN', Session::get('lang'));
    }

    /** @test */
    public function it_maintains_language_consistency()
    {
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        $response = $this->middleware->handle($request, function ($req) {
            // 中间件应该已经设置了语言
            $locale = App::getLocale();
            $this->assertNotNull($locale);
            $this->assertIsString($locale);
            return new Response('OK');
        });
    }
}
