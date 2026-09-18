<?php

namespace Pterodactyl\Http\Controllers\Admin\Alerts;

use Illuminate\View\View;
use Ramsey\Uuid\Uuid;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\KeywordAlertRule;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Alerts\KeywordAlertRuleFormRequest;

/**
 * Admin CRUD for keyword alert rules. Rules are global (see KeywordAlertRule's docblock) and
 * root-admin-only to create/edit — the trusted-admin threat model KeywordAlertMatchingService's
 * regex-safety section relies on. Mirrors EggConsoleShortcutController's shape.
 */
class KeywordAlertRuleController extends Controller
{
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        $rules = KeywordAlertRule::query()->orderBy('severity')->orderBy('label')->get();

        return view('admin.keyword-alerts.rules.index', ['rules' => $rules]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(KeywordAlertRuleFormRequest $request): RedirectResponse
    {
        KeywordAlertRule::query()->create($request->ruleData() + ['uuid' => Uuid::uuid4()->toString()]);
        $this->alert->success('The keyword alert rule was created.')->flash();

        return redirect()->route('admin.keyword-alerts.rules');
    }

    /**
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function update(KeywordAlertRuleFormRequest $request, KeywordAlertRule $rule): RedirectResponse
    {
        $rule->update($request->ruleData());
        $this->alert->success(sprintf('The "%s" rule was updated.', e($rule->label)))->flash();

        return redirect()->route('admin.keyword-alerts.rules');
    }

    public function destroy(KeywordAlertRule $rule): RedirectResponse
    {
        $rule->delete();
        $this->alert->success(sprintf('The "%s" rule was deleted.', e($rule->label)))->flash();

        return redirect()->route('admin.keyword-alerts.rules');
    }
}
