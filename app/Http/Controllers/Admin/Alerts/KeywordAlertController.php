<?php

namespace Pterodactyl\Http\Controllers\Admin\Alerts;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\KeywordAlert;
use Pterodactyl\Http\Controllers\Controller;

/**
 * The triggered-alert inbox — the primary channel for this feature, always populated regardless
 * of whether mail delivery succeeded (see KeywordAlertTriggered's docblock). Root-admin-only for
 * v1: see the report accompanying this feature for why (no per-server permission model fits a
 * cross-server alert stream cleanly, and this data is flagged children's chat — see
 * KeywordAlert's docblock — so the narrower, safer default was chosen over building a new
 * permission model under time pressure).
 */
class KeywordAlertController extends Controller
{
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    public function index(Request $request): View
    {
        $query = KeywordAlert::query()->with(['rule', 'server'])->orderByDesc('last_seen_at');

        if ($request->query('status') === 'open') {
            $query->where('status', KeywordAlert::STATUS_OPEN);
        }

        $alerts = $query->paginate(50);

        return view('admin.keyword-alerts.alerts.index', ['alerts' => $alerts]);
    }

    public function resolve(Request $request, KeywordAlert $alert): RedirectResponse
    {
        $alert->forceFill([
            'status' => KeywordAlert::STATUS_RESOLVED,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ])->save();

        $this->alert->success('Alert marked resolved.')->flash();

        return redirect()->route('admin.keyword-alerts');
    }
}
