<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use App\Models\AzubiEinsatz;
use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;

/**
 * Baut den ma_umsetzung-Payload für eine Azubi-Rotation aus Einsatz + Station.
 */
final class AzubiRotationPayloadBuilder
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
        private readonly BueRolesGatewayInterface $bue,
    ) {}

    /**
     * @return array{
     *     step1: array<string, mixed>,
     *     step2: array<string, mixed>,
     *     step3: array<string, mixed>,
     *     step4: array<string, mixed>,
     *     full: array<string, mixed>
     * }
     */
    public function build(AzubiEinsatz $einsatz, ?int $overrideAbteilungOld = null): array
    {
        $einsatz->loadMissing(['azubi', 'station.ansprechpartner', 'station.roles']);

        $azubi = $einsatz->azubi;
        if ($azubi === null) {
            throw new \InvalidArgumentException("AzubiEinsatz [{$einsatz->id}] hat keinen Azubi.");
        }

        $station = $einsatz->station;
        if ($station === null) {
            throw new \InvalidArgumentException("AzubiEinsatz [{$einsatz->id}] hat keine Station.");
        }

        $username = trim((string) ($azubi->username ?? ''));
        if ($username === '') {
            throw new \InvalidArgumentException("Azubi [{$azubi->id}] hat keinen Username.");
        }

        $ansprechpartnerId = $station->ansprechpartner->first()?->id;
        $pickup = trim((string) ($station->pickup_group ?? ''));

        $step1 = [
            'source' => (string) config('intranet-app-workflows.azubi_rotation.source', 'azubi_rotation'),
            'azubi_einsatz_id' => $einsatz->id,
            'azubistation_id' => $station->id,
            'mitarbeiter' => $azubi->id,
            'username' => $username,
            'abteilung' => $station->gvp_id,
            'abteilung_old' => $overrideAbteilungOld ?? $azubi->gvp_id,
            'einsatzab' => $einsatz->start?->format('Y-m-d'),
        ];

        $step2 = [
            'telefon' => (string) ($azubi->telefon ?? ''),
            'fax' => (string) ($azubi->fax ?? ''),
            'raum' => (string) ($azubi->raum ?? ''),
            'standort' => $station->standort_id,
            'cms_benoetigt' => '0',
            'farbdruck_benoetigt' => '0',
            'hardware' => '3',
            'bue_rechte_benoetigt' => '0',
            'unterstuetzung_it_benoetigt' => '0',
            'unterstuetzung_hausmeister_benoetigt' => '0',
            'anrufuebernahme_benoetigt' => $pickup !== '' ? '1' : '0',
            'laufwerke_analog_zu' => $ansprechpartnerId,
            'bue_rechte_analog_zu' => $ansprechpartnerId,
            'bemerkungen' => (string) config(
                'intranet-app-workflows.azubi_rotation.bemerkungen',
                'Automatische Azubirotation',
            ),
        ];

        $addLdap = $this->uniqueStrings(
            collect($station->ldap_gruppen ?? [])
                ->concat(config('intranet-app-workflows.ldap.default_azubis', []))
                ->concat(config('intranet-app-workflows.ldap.default_user_groups', [])),
        );

        $addIntranet = $this->uniqueStrings(
            collect($station->roles->pluck('name'))
                ->concat(config('intranet-app-workflows.phase_c.default_intranet_roles', [])),
        );

        $addBue = $this->uniqueStrings(
            collect($station->bue_roles ?? [])
                ->concat(config('intranet-app-workflows.phase_c.default_bue_roles', [])),
        );

        $removeLdap = $this->uniqueStrings(collect($this->ldap->getUserGroupNames($username)));
        $removeIntranet = $this->uniqueStrings(
            method_exists($azubi, 'getRoleNames')
                ? collect($azubi->getRoleNames())
                : collect(),
        );

        $removeBue = [];
        $bueUsername = trim((string) ($azubi->bue_username ?? ''));
        if ($bueUsername !== '') {
            $removeBue = $this->uniqueStrings($this->bue->getRoles($bueUsername));
        }

        $step3 = [
            'add_ldap_groups' => $addLdap,
            'remove_ldap_groups' => $removeLdap,
            'add_intranet_roles' => $addIntranet,
            'remove_intranet_roles' => $removeIntranet,
            'add_bue_roles' => $addBue,
            'remove_bue_roles' => $removeBue,
            'add_pickup_group' => $pickup,
            'pickup_uebernehmen' => $pickup !== '' ? '1' : '0',
        ];

        $step4 = [
            'hardware_benoetigt_check' => '1',
            'bue_check' => '1',
            'farbdruck_check' => '1',
            'cms_check' => '1',
        ];

        $full = array_merge($step1, $step2, $step3, $step4);

        return [
            'step1' => $step1,
            'step2' => $step2,
            'step3' => $step3,
            'step4' => $step4,
            'full' => $full,
        ];
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    private function uniqueStrings(iterable $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $string = trim((string) $value);
            if ($string !== '') {
                $out[] = $string;
            }
        }

        return array_values(array_unique($out));
    }
}
