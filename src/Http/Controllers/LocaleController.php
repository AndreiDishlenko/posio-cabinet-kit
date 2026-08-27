<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;

// Переключатель языка дёргает адрес фоновым запросом, а не переходом Inertia,
// поэтому выбор закрепляется сразу и в сессии, и в куке, и в профиле.
class LocaleController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString();
        $locales = collect(config('cabinet-kit.translations.locales', []))
            ->keys()
            ->map(fn ($code) => (string) $code)
            ->all();

        abort_unless(in_array($locale, $locales, true), 422);

        session(['locale' => $locale]);
        app()->setLocale($locale);

        if ($user = $request->user()) {
            if (method_exists($user, 'setSetting')) {
                $user->setSetting('locale', $locale);
            }
        }

        return response()->json(['locale' => $locale])
            ->withCookie(Cookie::forever('locale', $locale));
    }
}
