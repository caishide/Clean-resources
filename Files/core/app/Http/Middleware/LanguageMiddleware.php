<?php

namespace App\Http\Middleware;

use App\Constants\Status;
use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LanguageMiddleware
{
    /** @var int Maximum language changes per minute */
    private const LANGUAGE_CHANGE_RATE_LIMIT = 10;

    /** @var int Rate limit time window in seconds (1 minute) */
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    /** @var int Cache duration for allowed languages in seconds (10 minutes) */
    private const LANGUAGE_CACHE_DURATION = 600;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->setLanguage($request);
        return $next($request);
    }

    protected function setLanguage(Request $request): void
    {
        $locale = $this->getLocale($request);
        session()->put('lang', $locale);
        app()->setLocale($locale);
    }

    protected function getLocale(Request $request): string
    {
        // Check for language parameter in request
        if ($request->has('lang')) {
            // Apply rate limiting to prevent abuse (10 changes per minute per IP)
            $rateLimitKey = 'lang_change:' . $request->ip();
            if (RateLimiter::tooManyAttempts($rateLimitKey, self::LANGUAGE_CHANGE_RATE_LIMIT)) {
                Log::channel('security')->warning('Rate limit exceeded for language change', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                return $this->getDefaultLocale();
            }

            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_WINDOW_SECONDS);

            // Get and validate language parameter
            $lang = $request->get('lang');

            // Strict validation: only allow alphanumeric and hyphen, max 10 chars
            if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,4})?$/', $lang)) {
                Log::channel('security')->warning('Invalid language parameter attempted', [
                    'lang' => $lang,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                return $this->getDefaultLocale();
            }

            $lang = strtolower($lang);

            // Support common language codes with proper validation
            $allowedLanguages = [
                'en' => 'en',
                'zh' => 'zh',
                'zh-cn' => 'zh-cn',
                'zh-tw' => 'zh-tw',
            ];

            // Check if language is in allowed list
            if (isset($allowedLanguages[$lang])) {
                Log::channel('security')->info('Language changed', [
                    'old_lang' => session('lang'),
                    'new_lang' => $allowedLanguages[$lang],
                    'ip' => $request->ip(),
                ]);
                return $allowedLanguages[$lang];
            }

            // If database has languages table, check if requested language exists (with caching)
            try {
                if (Schema::hasTable('languages')) {
                    // Cache allowed languages for 10 minutes to reduce database queries
                    $allowedLanguages = Cache::remember('allowed_languages_db', self::LANGUAGE_CACHE_DURATION, function () {
                        return Language::pluck('code')->toArray();
                    });

                    if (in_array($lang, $allowedLanguages)) {
                        Log::channel('security')->info('Language changed to DB language', [
                            'lang' => $lang,
                            'ip' => $request->ip(),
                        ]);
                        return strtolower($lang);
                    }
                }
            } catch (\Throwable $e) {
                // Log database errors but don't expose to user
                Log::channel('security')->error('Database error checking language', [
                    'error' => $e->getMessage(),
                    'ip' => $request->ip(),
                ]);
            }

            // Log invalid language attempt
            Log::channel('security')->warning('Invalid language code attempted', [
                'lang' => $lang,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Check session language setting
        if (session()->has('lang')) {
            $sessionLang = session('lang');

            // Validate session language is still valid
            if ($this->isValidLanguage($sessionLang)) {
                return strtolower($sessionLang);
            } else {
                // Clear invalid session language
                session()->forget('lang');
                Log::channel('security')->warning('Invalid language removed from session', [
                    'invalid_lang' => $sessionLang,
                ]);
            }
        }

        // Check database default language
        return $this->getDefaultLocale();
    }

    /**
     * Check if a language code is valid
     */
    protected function isValidLanguage(string $lang): bool
    {
        /** @var array<string> Allowed language codes */
        $allowedLanguages = ['en', 'zh', 'zh-cn', 'zh-tw'];

        if (in_array(strtolower($lang), $allowedLanguages, true)) {
            return true;
        }

        // Check database if languages table exists
        try {
            if (Schema::hasTable('languages')) {
                return Language::where('code', strtolower($lang))->exists();
            }
        } catch (\Throwable $e) {
            // Log error but return false for safety
            Log::channel('security')->error('Database error validating language', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Get default language from database or config
     */
    protected function getDefaultLocale(): string
    {
        try {
            if (Schema::hasTable('languages')) {
                $language = Language::where('is_default', Status::ENABLE)->first();
                if ($language) {
                    return strtolower($language->code);
                }
            }
        } catch (\Throwable $e) {
            // Log error but use config default
            Log::channel('security')->error('Error getting default language', [
                'error' => $e->getMessage(),
            ]);
        }

        return config('app.locale', 'en');
    }
}
