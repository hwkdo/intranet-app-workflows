<x-mail::message>
# Initialpasswort für {{ $employeeName }}

für den neuen Mitarbeiter **{{ $employeeName }}** (AD-Benutzername: `{{ $username }}`) wurde ein Initialpasswort gesetzt.

Das Passwort steht **nicht** in dieser E-Mail. Öffnen Sie den folgenden Link, um es über Bitwarden Send abzurufen:

<x-mail::button :url="$accessUrl">
Passwort abrufen
</x-mail::button>

Link zum Passwort: {{ $accessUrl }}

Der Link ist nur begrenzt nutzbar. Nach dem Abruf können Sie ihn ggf. nicht erneut öffnen – kopieren Sie das Passwort daher direkt und geben Sie es persönlich weiter.

Dies ist eine automatisch generierte Mail (Intranet Workflows).
</x-mail::message>
