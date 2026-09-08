<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Support\Collection;

/**
 * Inventar für ma_austritt Step 2 (Dokumente, AK, Beauftragungen, Assets).
 */
final class MaAustrittInventoryService
{
    /**
     * @return array{
     *     dokumente: list<array{key: string, document_id: int, role: string, label: string}>,
     *     arbeitskreise: list<array{key: string, ak_id: int, role: string, label: string}>,
     *     beauftragungen: list<array{key: string, bw_id: int, label: string}>,
     *     assets: list<array{id: int, label: string, standort_id: ?int}>,
     *     default_standort_id: ?int
     * }
     */
    public function forMitarbeiter(int $mitarbeiterId): array
    {
        $user = WorkflowModels::userQuery()->find($mitarbeiterId);

        return [
            'dokumente' => $this->dokumente($mitarbeiterId),
            'arbeitskreise' => $user ? $this->arbeitskreise($user) : [],
            'beauftragungen' => $user ? $this->beauftragungen($user) : [],
            'assets' => $this->assets($mitarbeiterId),
            'default_standort_id' => $user?->standort_id !== null ? (int) $user->standort_id : null,
        ];
    }

    /**
     * @return list<array{key: string, document_id: int, role: string, label: string}>
     */
    private function dokumente(int $userId): array
    {
        if (! class_exists(\Hwkdo\IntranetAppDokumente\Models\Document::class)) {
            return [];
        }

        $rows = [];
        $docs = \Hwkdo\IntranetAppDokumente\Models\Document::query()
            ->where(function ($q) use ($userId): void {
                $q->where('responsible_id', $userId)->orWhere('uploader_id', $userId);
            })
            ->orderBy('id')
            ->get(['id', 'title', 'responsible_id', 'uploader_id']);

        foreach ($docs as $doc) {
            $title = trim((string) ($doc->title ?? ''));
            if ($title === '') {
                $title = 'Dokument #'.$doc->id;
            }
            if ((int) $doc->responsible_id === $userId) {
                $rows[] = [
                    'key' => $doc->id.'_responsible',
                    'document_id' => (int) $doc->id,
                    'role' => 'responsible',
                    'label' => $title.' (Verantwortlich)',
                ];
            }
            if ((int) $doc->uploader_id === $userId) {
                $rows[] = [
                    'key' => $doc->id.'_uploader',
                    'document_id' => (int) $doc->id,
                    'role' => 'uploader',
                    'label' => $title.' (Uploader)',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, ak_id: int, role: string, label: string}>
     */
    private function arbeitskreise(object $user): array
    {
        $rows = [];

        if (method_exists($user, 'arbeitskreiseVertreter')) {
            foreach ($user->arbeitskreiseVertreter()->orderBy('name')->get() as $ak) {
                $rows[] = [
                    'key' => $ak->id.'_vertreter',
                    'ak_id' => (int) $ak->id,
                    'role' => 'vertreter',
                    'label' => (string) ($ak->name ?? 'AK #'.$ak->id).' (Vertreter)',
                ];
            }
        }

        if (method_exists($user, 'arbeitskreiseStv')) {
            foreach ($user->arbeitskreiseStv()->orderBy('name')->get() as $ak) {
                $rows[] = [
                    'key' => $ak->id.'_stv',
                    'ak_id' => (int) $ak->id,
                    'role' => 'stv',
                    'label' => (string) ($ak->name ?? 'AK #'.$ak->id).' (Stv.)',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, bw_id: int, label: string}>
     */
    private function beauftragungen(object $user): array
    {
        if (! method_exists($user, 'beauftragtenwesen')) {
            return [];
        }

        $rows = [];
        foreach ($user->beauftragtenwesen()->orderBy('name')->get() as $bw) {
            $rows[] = [
                'key' => (string) $bw->id,
                'bw_id' => (int) $bw->id,
                'label' => (string) ($bw->name ?? $bw->bezeichnung ?? 'Beauftragung #'.$bw->id),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, label: string, standort_id: ?int}>
     */
    private function assets(int $userId): array
    {
        if (! class_exists(\Hwkdo\IntranetAppAssets\Models\Asset::class)) {
            return [];
        }

        /** @var Collection<int, \Hwkdo\IntranetAppAssets\Models\Asset> $assets */
        $assets = \Hwkdo\IntranetAppAssets\Models\Asset::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($assets as $asset) {
            $name = (string) ($asset->name ?? $asset->inventory_number ?? 'Asset #'.$asset->id);
            $inv = trim((string) ($asset->inventory_number ?? ''));
            $rows[] = [
                'id' => (int) $asset->id,
                'label' => $inv !== '' ? "{$name} ({$inv})" : $name,
                'standort_id' => null,
            ];
        }

        return $rows;
    }
}
