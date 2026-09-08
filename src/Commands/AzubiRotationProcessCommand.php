<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Commands;

use App\Models\AzubiEinsatz;
use Hwkdo\IntranetAppWorkflows\Services\AzubiRotationStarter;
use Illuminate\Console\Command;
use Throwable;

class AzubiRotationProcessCommand extends Command
{
    protected $signature = 'workflows:azubi-rotation-process
                            {einsatz_id : ID des AzubiEinsatzes}
                            {override_abteilung_old? : GVP-ID für abteilung_old überschreiben}';

    protected $description = 'Erzeugt einen vorausgefüllten ma_umsetzung-Flow für die Azubi-Rotation';

    public function handle(AzubiRotationStarter $starter): int
    {
        $einsatzId = (int) $this->argument('einsatz_id');
        $einsatz = AzubiEinsatz::query()->with(['azubi', 'station'])->find($einsatzId);

        if ($einsatz === null) {
            $this->error("AzubiEinsatz [{$einsatzId}] nicht gefunden.");

            return self::FAILURE;
        }

        $override = $this->argument('override_abteilung_old');
        $overrideId = is_numeric($override) ? (int) $override : null;

        $this->info("AzubiEinsatz {$einsatz->id}: {$einsatz->azubi?->username} → {$einsatz->station?->name}");
        $this->info('Start '.$einsatz->start?->format('d.m.Y').' / Ende '.$einsatz->ende?->format('d.m.Y'));

        try {
            $result = $starter->start($einsatz, $overrideId);
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $flow = $result['flow'];
        if (! $result['created']) {
            $this->warn("Bereits vorhanden: Flow #{$flow->id} (Status {$flow->status->value})");

            return self::SUCCESS;
        }

        $this->info("Flow #{$flow->id} erstellt (Status {$flow->status->value}, due {$flow->due_date?->format('Y-m-d')})");

        return self::SUCCESS;
    }
}
