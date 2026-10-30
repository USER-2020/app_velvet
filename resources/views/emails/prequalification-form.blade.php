@php
    $logoUrl = asset('THEVELVETSTUDIO_BRANDBOOK-55.png');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $identityRetry ? 'Nueva verificación de identidad' : 'Requisitos iniciales' }} · The Velvet Studio</title>
</head>
<body style="margin:0; padding:0; background:#100b18; color:#f7f1fb; font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#100b18; padding:28px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background:#080a10; border:1px solid #292936; border-radius:14px; overflow:hidden;">
                    <tr><td align="center" style="padding:28px 30px 20px;"><img src="{{ $logoUrl }}" width="190" alt="The Velvet Studio" style="display:block; width:190px; max-width:100%; height:auto; border:0;"></td></tr>
                    <tr><td style="padding:30px 34px 10px; background:linear-gradient(135deg,#26122f,#120b18);"><p style="margin:0 0 12px; color:#d96eea; font-size:12px; letter-spacing:3px; text-transform:uppercase;">Siguiente paso</p><h1 style="margin:0; color:#fbf8fc; font-family:Georgia,'Times New Roman',serif; font-size:36px; font-weight:400; line-height:1.08;">Hola, {{ $applicantName }}.</h1><p style="margin:20px 0 12px; color:#c4beca; font-size:16px; line-height:25px;">{{ $identityRetry ? 'Necesitamos repetir tu verificación de identidad. Usa este enlace para realizar nuevamente el proceso.' : 'Tu perfil avanzó en nuestro proceso. Para continuar con la precalificación, necesitamos que completes tus requisitos iniciales.' }}</p></td></tr>
                    <tr><td style="padding:26px 34px 8px;"><p style="margin:0; color:#aaa5b2; font-size:13px; line-height:21px;">Código de candidata: <strong style="color:#e0a0ed;">{{ $candidateCode }}</strong></p><p style="margin:18px 0 0; color:#c4beca; font-size:15px; line-height:24px;">{{ $identityRetry ? 'El enlace abrirá el formulario para iniciar una nueva verificación de identidad.' : 'El formulario toma pocos minutos. Usa el botón para abrir tu enlace personal y revisa que la información esté completa.' }}</p></td></tr>
                    <tr><td align="center" style="padding:26px 34px 8px;"><a href="{{ $formUrl }}" target="_blank" rel="noopener" style="display:inline-block; border-radius:8px; background:#a92ad8; padding:15px 28px; color:#ffffff; font-size:15px; font-weight:bold; text-decoration:none;">{{ $identityRetry ? 'Repetir verificación →' : 'Completar formulario →' }}</a></td></tr>
                    <tr><td style="padding:8px 34px 16px; color:#898291; font-size:12px; line-height:20px;">Si el botón no se abre, copia y pega este enlace en tu navegador:<br><a href="{{ $formUrl }}" target="_blank" rel="noopener" style="color:#d96eea; word-break:break-all;">{{ $formUrl }}</a></td></tr>
                    <tr><td style="padding:16px 34px 32px; color:#898291; font-size:12px; line-height:20px;">Este enlace es personal y estará disponible durante 7 días. Si no solicitaste continuar con el proceso, puedes ignorar este mensaje.</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
