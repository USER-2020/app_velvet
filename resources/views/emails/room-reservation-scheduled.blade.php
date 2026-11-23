@php($logoUrl = asset('THEVELVETSTUDIO_BRANDBOOK-55.png'))
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Room programada</title></head>
<body style="margin:0;padding:28px 14px;background:#100b18;color:#f7f1fb;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%"><tr><td align="center"><table role="presentation" width="100%" style="max-width:640px;background:#080a10;border:1px solid #292936;border-radius:14px;overflow:hidden">
<tr><td align="center" style="padding:28px"><img src="{{ $logoUrl }}" width="190" alt="The Velvet Studio"></td></tr>
<tr><td style="padding:30px 34px;background:linear-gradient(135deg,#26122f,#120b18)"><p style="color:#d96eea;font-size:12px;letter-spacing:3px;text-transform:uppercase">Room confirmada</p><h1 style="color:#fbf8fc;font-family:Georgia,serif;font-weight:400">Hola, {{ $modelName }}.</h1><p style="color:#c4beca;font-size:16px;line-height:25px">Tu espacio de trabajo quedó programado.</p></td></tr>
<tr><td style="padding:28px 34px;color:#c4beca;font-size:15px;line-height:25px"><p><strong style="color:#e0a0ed">Room:</strong> {{ $roomName }}{{ $roomCode ? ' · '.$roomCode : '' }}</p><p><strong style="color:#e0a0ed">Fecha y hora:</strong> {{ $startsAt->translatedFormat('l d \d\e F \d\e Y, H:i') }} a {{ $endsAt->format('H:i') }} (Bogotá)</p><p><strong style="color:#e0a0ed">Actividad:</strong> {{ $purpose }}</p><p>Adjuntamos un evento de calendario (.ics). Ábrelo o impórtalo para agregar esta programación a tu agenda.</p><p style="font-size:12px;color:#898291">Si necesitas cambiar la reserva, comunícate con tu monitor o con el equipo de operaciones.</p></td></tr>
</table></td></tr></table>
</body></html>
