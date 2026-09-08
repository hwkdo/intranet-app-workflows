<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $betreff }}</title>
</head>
<body>
    <h2>{{ $betreff }}</h2>
    <br>
    {!! $inhalt !!}
    <br>
    <br>

    <p>Workflow Input Dump</p>
    @foreach($dump as $key => $value)
        {{ $key }} : {{ is_array($value) ? implode(', ', $value) : $value }}<br>
    @endforeach
    <br>
    <p>Dies ist eine automatisch generierte Mail (Intranet Workflows).</p>
</body>
</html>
