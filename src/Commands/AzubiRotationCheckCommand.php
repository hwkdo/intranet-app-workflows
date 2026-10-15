<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Commands;

use App\Models\AzubiEinsatz;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityCatalog;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityConfigStore;
use Hwkdo\IntranetAppWorkflows\Services\AzubiRotationStarter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class AzubiRotationCheckCommand extends Command
{
    protected $signature = 'workflows:azubi-rotation-check
                            {--date= : Bezugstag (Y-m-d), Default heute → Einsätze mit Start = morgen}
                            {--force : Auch ausführen, wenn automatische Azubi-Rotationen deaktiviert sind}';

    protected $description = 'Startet Azubi-Rotationen für Einsätze, die morgen beginnen';

    public function handle(AzubiRotationStarter $starter): int
    {
        $definition = app(BackgroundActivityCatalog::class)->find('workflows.azubi-rotation');

        if ($definition !== null && ! app(BackgroundActivityConfigStore::class)->isEnabled($definition) && ! $this->option('force')) {
            $this->info('Automatische Azubi-Rotationen sind in den App-Einstellungen deaktiviert.');

            return self::SUCCESS;
        }

        $base = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();
        $targetStart = $base->copy()->addDay()->toDateString();

        $einsaetze = AzubiEinsatz::query()
            ->with(['azubi', 'station'])
            ->whereDate('start', $targetStart)
            ->orderBy('id')
            ->get();

        $this->info("Einsätze mit Start {$targetStart}: ".$einsaetze->count());

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($einsaetze as $einsatz) {
            try {
                $result = $starter->start($einsatz);
                if ($result['created']) {
                    $created++;
                    $this->line("  + Flow #{$result['flow']->id} für Einsatz {$einsatz->id}");
                } else {
                    $skipped++;
                    $this->line("  = Skip Einsatz {$einsatz->id} (Flow #{$result['flow']->id})");
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $this->error("  ! Einsatz {$einsatz->id}: ".$e->getMessage());
            }
        }

        $this->info("Fertig: created={$created} skipped={$skipped} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
