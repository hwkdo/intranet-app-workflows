<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

/**
 * Stichtag: Softphones löschen, Line anonymisieren, CUPI-User löschen (Hardphones → Hinweis).
 */
final class CiscoOffboardAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.cisco_offboard';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return ActionResult::failed('Mitarbeiter fehlt im Payload', retryable: false);
        }

        $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
        $username = trim((string) ($user?->username ?? $context->payloadValue('username', '')));
        if ($username === '') {
            return ActionResult::failed('Username für Cisco-Offboard fehlt', retryable: false);
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Cisco Offboard für [{$username}]",
            output: ['cisco_offboard_username' => $username],
        )) {
            return $dry;
        }

        $axlClass = \Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface::class;
        if (! interface_exists($axlClass) || ! app()->bound($axlClass)) {
            return ActionResult::succeeded(
                message: 'Cisco AXL nicht verfügbar – Offboard übersprungen',
                output: ['cisco_offboard_skipped' => true],
            );
        }

        /** @var \Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface $axl */
        $axl = app($axlClass);
        $messages = [];
        $errors = [];
        $hardphones = [];

        try {
            $phones = $axl->listPhonesForUser($username);
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Cisco listPhonesForUser fehlgeschlagen: '.$e->getMessage());
        }

        foreach ($phones as $phone) {
            $name = (string) ($phone['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $isSoft = (bool) preg_match('/^(CSF|TCT|BOT|TAB)/i', $name);
            if ($isSoft) {
                try {
                    $axl->removePhone($name);
                    $messages[] = "Softphone gelöscht: {$name}";
                } catch (Throwable $e) {
                    report($e);
                    $errors[] = "Softphone {$name}: ".$e->getMessage();
                }
            } else {
                $hardphones[] = $name;
            }
        }

        $linePattern = null;
        if (method_exists($axl, 'getLinePatternForUser')) {
            try {
                $linePattern = $axl->getLinePatternForUser($username);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (is_string($linePattern) && $linePattern !== '') {
            try {
                $axl->updateLineByPattern($linePattern, [
                    'description' => 'FREI',
                    'alertingName' => 'FREI',
                    'asciiAlertingName' => 'FREI',
                ]);
                $messages[] = "Line anonymisiert: {$linePattern}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "Line {$linePattern}: ".$e->getMessage();
            }
        }

        $cupiClass = \Hwkdo\CiscoPhoneServicesLaravel\Interfaces\CupiServiceInterface::class;
        if (interface_exists($cupiClass) && app()->bound($cupiClass)) {
            try {
                /** @var \Hwkdo\CiscoPhoneServicesLaravel\Interfaces\CupiServiceInterface $cupi */
                $cupi = app($cupiClass);
                if (method_exists($cupi, 'deleteUser')) {
                    $cupi->deleteUser($username);
                    $messages[] = "CUPI-User gelöscht: {$username}";
                }
            } catch (Throwable $e) {
                report($e);
                $errors[] = 'CUPI: '.$e->getMessage();
            }
        }

        if ($hardphones !== []) {
            $messages[] = 'Hardphones (manuell/Ticket): '.implode(', ', $hardphones);
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Cisco-Offboard fehlgeschlagen', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Cisco-Offboard teilweise',
                messages: $messages,
                errors: $errors,
                output: ['cisco_hardphones' => $hardphones],
            );
        }

        return ActionResult::succeeded(
            message: $messages === [] ? 'Keine Cisco-Geräte gefunden' : 'Cisco-Offboard abgeschlossen',
            messages: $messages,
            output: ['cisco_hardphones' => $hardphones],
        );
    }
}
