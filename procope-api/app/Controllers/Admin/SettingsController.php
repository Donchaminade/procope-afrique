<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Setting;
use App\Services\Audit;

final class SettingsController
{
    public function index(Request $request): void
    {
        $values = [];
        foreach (Setting::SETTINGS_FORM_KEYS as $key) {
            $values[$key] = Setting::get($key, '');
        }
        View::render('settings/index', [
            'title'  => 'Réglages',
            'values' => $values,
        ]);
    }

    public function update(Request $request): void
    {
        foreach (Setting::SETTINGS_FORM_KEYS as $key) {
            Setting::set($key, $request->input($key) ?? '');
        }
        Audit::log('settings.update', 'settings');
        flash('success', 'Réglages enregistrés.');
        Response::redirect('/admin/settings');
    }
}
