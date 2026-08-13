<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\User;

/** Surveillance : journal d'audit filtrable, réservé au super admin. */
final class SurveillanceController
{
    public function index(Request $request): void
    {
        $filters = [
            'user_id' => (int) ($request->input('user_id', '') ?? 0) ?: null,
            'action'  => $request->input('action', '') ?: null,
            'from'    => $request->input('from', '') ?: null,
            'to'      => $request->input('to', '') ?: null,
        ];
        $page = max(1, (int) ($request->input('page', '1') ?? 1));

        View::render('surveillance/index', [
            'title'   => 'Surveillance',
            'result'  => AuditLog::search($filters, $page),
            'filters' => $filters,
            'users'   => User::all(),
            'actions' => AuditLog::actions(),
        ]);
    }
}
