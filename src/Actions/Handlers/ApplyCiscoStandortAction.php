<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use App\Models\Standort;
use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoStandortGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\CiscoOutboundCssRemapper;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;

final class ApplyCiscoStandortAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly CiscoStandortGatewayInterface $cisco,
    ) {}

    public static function key(): string
    {
        return 'ma_umsetzung.apply_cisco_standort';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $user = $this->resolveUser($context);
        if ($user === null) {
            return ActionResult::failed('Mitarbeiter für Cisco-Standort nicht gefunden', retryable: false);
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username === '') {
            return ActionResult::failed('Mitarbeiter hat keinen Username', retryable: false);
        }

        $standortId = $context->payloadValue('standort');
        if (! is_numeric($standortId)) {
            return ActionResult::failed('Standort fehlt im Payload', retryable: false);
        }

        $standort = Standort::query()->find((int) $standortId);
        if (! $standort) {
            return ActionResult::failed("Standort [{$standortId}] nicht gefunden", retryable: false);
        }

        $siteCode = trim((string) ($standort->cisco_site_code ?? ''));
        $devicePool = trim((string) ($standort->cisco_device_pool ?? ''));
        $location = trim((string) ($standort->cisco_location ?? ''));

        if ($siteCode === '' || $devicePool === '' || $location === '') {
            return ActionResult::failed(
                "Standort [{$standort->name}] hat unvollständige Cisco-Felder (site_code/device_pool/location)",
                retryable: false,
            );
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Cisco Standort Handling → {$siteCode} ({$devicePool} / {$location})",
            output: [
                'cisco_site_code' => $siteCode,
                'cisco_device_pool' => $devicePool,
                'cisco_location' => $location,
                'username' => $username,
            ],
        )) {
            return $dry;
        }

        $phones = $this->cisco->listPhonesForUser($username);
        $primaryLinePattern = $this->cisco->linePatternForUser($user);

        $processedLinePatterns = [];
        $messages = [];
        $errors = [];
        $phonesUpdated = 0;
        $linesUpdated = 0;

        foreach ($phones as $phoneSummary) {
            $phoneName = $phoneSummary['name'];
            $config = $this->cisco->getPhoneConfig($phoneName);
            if ($config === null) {
                $errors[] = "Phone [{$phoneName}]: Konfiguration nicht lesbar";

                continue;
            }

            $rerouteResult = CiscoOutboundCssRemapper::remap($config['reroute_css'], $siteCode);
            if ($rerouteResult['status'] === 'error') {
                $errors[] = "Phone [{$phoneName}]: ".$rerouteResult['message'];

                continue;
            }

            $rerouteCss = $rerouteResult['status'] === 'ok' ? $rerouteResult['value'] : null;

            if (! $this->cisco->updatePhoneStandort($phoneName, $devicePool, $location, $rerouteCss)) {
                $errors[] = "Phone [{$phoneName}]: Update fehlgeschlagen";

                continue;
            }

            $lineOk = true;
            foreach ($phoneSummary['lines'] as $lineSummary) {
                $pattern = $lineSummary['pattern'];
                $lineResult = $this->applyLineCss($pattern, $siteCode, $messages, $errors);
                $processedLinePatterns[$pattern] = true;
                if ($lineResult === 'updated') {
                    $linesUpdated++;
                }
                if ($lineResult === 'error') {
                    $lineOk = false;
                }
            }

            if (! $this->cisco->applyPhone($phoneName)) {
                $errors[] = "Phone [{$phoneName}]: applyPhone fehlgeschlagen";
                $lineOk = false;
            }

            if ($lineOk || $rerouteCss !== null) {
                $phonesUpdated++;
                $messages[] = "Phone [{$phoneName}]: Pool={$devicePool}, Location={$location}"
                    .($rerouteCss !== null ? ", Reroute={$rerouteCss}" : '');
            }
        }

        if ($primaryLinePattern !== null && ! isset($processedLinePatterns[$primaryLinePattern])) {
            $lineConfig = $this->cisco->getLineConfig($primaryLinePattern);
            if ($lineConfig !== null) {
                $lineResult = $this->applyLineCss($primaryLinePattern, $siteCode, $messages, $errors);
                if ($lineResult === 'updated') {
                    $linesUpdated++;
                }
            } elseif ($phones === []) {
                $messages[] = "Primär-Line [{$primaryLinePattern}] nicht in CUCM gefunden";
            }
        }

        if ($phones === [] && $linesUpdated === 0 && $errors === []) {
            return ActionResult::succeeded(
                message: 'Keine Cisco-Geräte/Lines – nichts zu ändern',
                output: [
                    'cisco_site_code' => $siteCode,
                    'phones_updated' => 0,
                    'lines_updated' => 0,
                    'line_pattern' => $primaryLinePattern,
                ],
                messages: $messages,
            );
        }

        if ($phones === [] && $linesUpdated > 0) {
            $messages[] = 'Keine Phones am CUCM-User – nur Line-CSS angepasst (Device Pool/Location entfallen)';
        }

        $output = [
            'cisco_site_code' => $siteCode,
            'cisco_device_pool' => $devicePool,
            'cisco_location' => $location,
            'phones_updated' => $phonesUpdated,
            'lines_updated' => $linesUpdated,
            'line_pattern' => $primaryLinePattern,
        ];

        if ($errors !== [] && $phonesUpdated === 0 && $linesUpdated === 0) {
            return ActionResult::failed(
                message: 'Cisco Standort Handling fehlgeschlagen',
                errors: $errors,
            );
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Cisco Standort Handling teilweise erfolgreich',
                messages: $messages,
                errors: $errors,
                output: $output,
            );
        }

        return ActionResult::succeeded(
            message: "Cisco Standort Handling: {$phonesUpdated} Phone(s), {$linesUpdated} Line(s)",
            output: $output,
            messages: $messages,
        );
    }

    /**
     * @param  list<string>  $messages
     * @param  list<string>  $errors
     * @return 'updated'|'skipped'|'error'
     */
    private function applyLineCss(string $pattern, string $siteCode, array &$messages, array &$errors): string
    {
        $lineConfig = $this->cisco->getLineConfig($pattern);
        if ($lineConfig === null) {
            $errors[] = "Line [{$pattern}]: Konfiguration nicht lesbar";

            return 'error';
        }

        $cssResult = CiscoOutboundCssRemapper::remap($lineConfig['calling_search_space'], $siteCode);
        if ($cssResult['status'] === 'error') {
            $errors[] = "Line [{$pattern}]: ".$cssResult['message'];

            return 'error';
        }

        if ($cssResult['status'] === 'skip') {
            $messages[] = "Line [{$pattern}]: CSS übersprungen".($cssResult['message'] ? ' ('.$cssResult['message'].')' : '');

            return 'skipped';
        }

        if (! $this->cisco->updateLineCallingSearchSpace($pattern, (string) $cssResult['value'])) {
            $errors[] = "Line [{$pattern}]: CSS-Update fehlgeschlagen";

            return 'error';
        }

        $messages[] = "Line [{$pattern}]: CSS → {$cssResult['value']}";

        return 'updated';
    }

    private function resolveUser(ActionContext $context): ?object
    {
        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (is_numeric($mitarbeiterId)) {
            $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
            if ($user) {
                return $user;
            }
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username === '') {
            return null;
        }

        return WorkflowModels::userQuery()->where('username', $username)->first();
    }
}
