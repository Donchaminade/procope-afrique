<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\Mailer;

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
        $notifyRaw = (string) ($request->input('mail_notify') ?? '');
        $notifyList = Mailer::parseNotifyList($notifyRaw);
        $tokens = array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $notifyRaw) ?: [])));
        if ($tokens !== [] && $notifyList === []) {
            flash('error', 'Aucune adresse e-mail valide pour les alertes internes. Séparez-les par des virgules.');
            Response::redirect('/admin/settings');
        }

        foreach (Setting::SETTINGS_FORM_KEYS as $key) {
            if ($key === 'mail_notify') {
                Setting::set($key, implode(',', $notifyList));
                continue;
            }
            Setting::set($key, $request->input($key) ?? '');
        }
        Audit::log('settings.update', 'settings');
        flash('success', 'Réglages enregistrés.');
        Response::redirect('/admin/settings');
    }
}
