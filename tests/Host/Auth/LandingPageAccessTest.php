<?php

namespace Posio\CabinetKit\Tests\Host\Auth;

use Posio\CabinetKit\Support\CabinetRedirects;
use Posio\CabinetKit\Tests\Host\HostTestCase;

/**
 * Целевая страница — куда шёл пользователь или назначенная в карте перенаправлений —
 * открывается, только если её пропускают все проверки прав маршрута. Иначе профиль;
 * туда же ведёт страница кабинета, на которую не хватило прав.
 */
class LandingPageAccessTest extends HostTestCase
{
    protected function signIn($user)
    {
        return $this->post(route('login'), [
            'email' => $user->email,
            'password' => $this->password,
        ]);
    }

    // Страница по умолчанию — пользователи, а у новичка нет права управлять ими.
    public function test_sign_in_leads_to_profile_when_default_page_is_closed(): void
    {
        config(['cabinet-kit-redirects.after_login' => config('cabinet-kit.route_name_prefix', 'cabinet-kit.').'users']);

        $this->signIn($this->makeUser())
            ->assertRedirect(CabinetRedirects::profile());
    }

    public function test_sign_in_leads_to_default_page_when_it_is_open(): void
    {
        config(['cabinet-kit-redirects.after_login' => config('cabinet-kit.route_name_prefix', 'cabinet-kit.').'users']);

        $this->signIn($this->makeSystemAdministrator())
            ->assertRedirect($this->cabinetRoute('users'));
    }

    public function test_sign_in_leads_to_profile_when_remembered_page_is_closed(): void
    {
        $this->withSession(['url.intended' => $this->cabinetRoute('users')])
            ->signIn($this->makeUser())
            ->assertRedirect(CabinetRedirects::profile());
    }

    public function test_sign_in_returns_to_remembered_page_when_it_is_open(): void
    {
        $this->withSession(['url.intended' => $this->cabinetRoute('sitesettings')])
            ->signIn($this->makeSystemAdministrator())
            ->assertRedirect($this->cabinetRoute('sitesettings'));
    }

    public function test_page_denied_for_lack_of_permission_leads_to_profile(): void
    {
        $this->actingAs($this->makeUser());

        $this->get($this->cabinetRoute('users'))
            ->assertRedirect(CabinetRedirects::profile());

        // Запросы данных остаются с отказом.
        $this->getJson($this->cabinetRoute('users'))->assertForbidden();
    }

    public function test_cabinet_root_leads_to_profile_when_home_page_is_closed(): void
    {
        config(['cabinet-kit-redirects.home' => config('cabinet-kit.route_name_prefix', 'cabinet-kit.').'users']);

        $this->actingAs($this->makeUser())
            ->get($this->cabinetRoute('home'))
            ->assertRedirect(CabinetRedirects::profile());
    }
}
