<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Commands;

use Hwkdo\IntranetAppWorkflows\Services\Ldap\LdapUserAttributeDumper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class DumpLdapUserCommand extends Command
{
    protected $signature = 'workflows:ldap-dump
                            {username : sAMAccountName des AD-Users}
                            {--label=before : Label für den Dateinamen (z. B. before, after-easy365)}
                            {--path= : Optionales Zielverzeichnis (Default: storage/app/ldap-dumps/{username})}
                            {--diff= : Pfad zu einem vorherigen Dump — schreibt zusätzlich eine Diff-Datei}';

    protected $description = 'Vollständigen LDAP-Attribut-Dump eines Users (für Easy365Manager-Diff-Analyse)';

    public function handle(LdapUserAttributeDumper $dumper): int
    {
        $username = (string) $this->argument('username');
        $label = Str::slug((string) $this->option('label')) ?: 'dump';

        try {
            $dump = $dumper->dump($username);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dir = $this->option('path')
            ? (string) $this->option('path')
            : storage_path('app/ldap-dumps/'.$username);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Verzeichnis konnte nicht erstellt werden: {$dir}");

            return self::FAILURE;
        }

        $stamp = now()->format('Ymd-His');
        $file = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."{$label}-{$stamp}.json";

        $json = json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($file, $json."\n") === false) {
            $this->error("Dump konnte nicht geschrieben werden: {$file}");

            return self::FAILURE;
        }

        $this->info("Dump geschrieben: {$file}");
        $this->line('DN: '.$dump['meta']['dn']);
        $this->line('Attribute: '.$dump['meta']['attribute_count']);
        $this->line('Gruppen: '.$dump['meta']['group_count']);

        $diffPath = $this->option('diff');
        if (is_string($diffPath) && $diffPath !== '') {
            try {
                $before = $dumper->loadDumpFile($diffPath);
                $diff = $dumper->diff($before, $dump);
            } catch (Throwable $e) {
                $this->error('Diff fehlgeschlagen: '.$e->getMessage());

                return self::FAILURE;
            }

            $diffFile = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."diff-{$label}-{$stamp}.json";
            $diffJson = json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($diffJson === false || file_put_contents($diffFile, $diffJson."\n") === false) {
                $this->error("Diff konnte nicht geschrieben werden: {$diffFile}");

                return self::FAILURE;
            }

            $this->newLine();
            $this->info("Diff geschrieben: {$diffFile}");
            $this->renderDiffSummary($diff);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     attributes: array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{before: mixed, after: mixed}>},
     *     groups: array{added: list<string>, removed: list<string>}
     * }  $diff
     */
    private function renderDiffSummary(array $diff): void
    {
        $added = count($diff['attributes']['added']);
        $removed = count($diff['attributes']['removed']);
        $changed = count($diff['attributes']['changed']);

        $this->line("Attribute: +{$added} / -{$removed} / ~{$changed}");
        $this->line('Gruppen: +'.count($diff['groups']['added']).' / -'.count($diff['groups']['removed']));

        if ($changed > 0) {
            $this->newLine();
            $this->comment('Geänderte Attribute:');
            foreach (array_keys($diff['attributes']['changed']) as $name) {
                $this->line('  ~ '.$name);
            }
        }

        if ($added > 0) {
            $this->newLine();
            $this->comment('Neue Attribute:');
            foreach (array_keys($diff['attributes']['added']) as $name) {
                $this->line('  + '.$name);
            }
        }

        if ($removed > 0) {
            $this->newLine();
            $this->comment('Entfernte Attribute:');
            foreach (array_keys($diff['attributes']['removed']) as $name) {
                $this->line('  - '.$name);
            }
        }

        if ($diff['groups']['added'] !== [] || $diff['groups']['removed'] !== []) {
            $this->newLine();
            $this->comment('Gruppen-Diff:');
            foreach ($diff['groups']['added'] as $group) {
                $this->line('  + '.$group);
            }
            foreach ($diff['groups']['removed'] as $group) {
                $this->line('  - '.$group);
            }
        }
    }
}
