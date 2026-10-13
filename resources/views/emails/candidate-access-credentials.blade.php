@php($logoUrl = asset('THEVELVETSTUDIO_BRANDBOOK-55.png'))
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activa tu acceso · The Velvet Studio</title>
</head>
<body style="margin:0;padding:32px 14px;background:#080910;color:#f7f1fb;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Verifica tu correo y activa tu acceso a The Velvet Studio.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;border:1px solid #30263b;border-radius:18px;overflow:hidden;background:#11121a;box-shadow:0 20px 55px rgba(0,0,0,.35);">
                    <tr>
                        <td align="center" style="padding:28px 28px 24px;background:#0c0d14;border-bottom:1px solid #292d39;">
                            <img src="{{ $logoUrl }}" width="62" height="62" alt="The Velvet Studio" style="display:block;width:62px;height:62px;object-fit:contain;margin:0 auto 14px;">
                            <div style="color:#f4eff6;font-family:Georgia,serif;font-size:18px;letter-spacing:3px;line-height:1.1;">THE VELVET</div>
                            <div style="margin-top:5px;color:#a6a0ae;font-size:9px;letter-spacing:5px;">STUDIO</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 36px;background:linear-gradient(135deg,#281331 0%,#15101e 58%,#10121a 100%);">
                            <p style="margin:0;color:#d56bea;font-size:10px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;">Siguiente etapa</p>
                            <h1 style="margin:14px 0 0;color:#fff;font-family:Georgia,'Times New Roman',serif;font-size:34px;font-weight:400;line-height:1.12;">Activa tu acceso</h1>
                            <p style="margin:16px 0 0;color:#c9c1d0;font-size:15px;line-height:1.7;">Hola, {{ $applicantName }}. Tu perfil ya está listo. Verifica tu correo para ingresar de forma segura al dashboard.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 36px 36px;background:#11121a;">
                            <p style="margin:0;color:#ded7e3;font-size:15px;line-height:1.7;">Primero confirma tu dirección de correo:</p>
                            <p style="margin:24px 0 30px;text-align:center;">
                                <a href="{{ $verificationUrl }}" style="display:inline-block;padding:14px 26px;border-radius:9px;background:#a92ad8;color:#fff;font-size:14px;font-weight:bold;text-decoration:none;box-shadow:0 8px 22px rgba(169,42,216,.25);">Verificar mi correo →</a>
                            </p>
                            <div style="padding:18px 18px;border:1px solid #3b3046;border-radius:10px;background:#171522;">
                                <p style="margin:0 0 12px;color:#d56bea;font-size:10px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">Tus credenciales</p>
                                <p style="margin:7px 0;color:#c9c1d0;font-size:14px;"><strong style="color:#f0c4f8;">Usuario:</strong> {{ $email }}</p>
                                <p style="margin:7px 0;color:#c9c1d0;font-size:14px;"><strong style="color:#f0c4f8;">Contraseña temporal:</strong> {{ $temporaryPassword }}</p>
                            </div>
                            <p style="margin:24px 0 0;color:#aaa2b1;font-size:13px;line-height:1.65;">Después de verificar tu correo, utiliza tus credenciales para ingresar. En tu primer acceso comenzarás la etapa de inducción.</p>
                            <p style="margin:24px 0;text-align:center;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 24px;border:1px solid #754082;border-radius:9px;color:#e8b1f3;font-size:13px;font-weight:bold;text-decoration:none;">Ir al dashboard →</a>
                            </p>
                            <p style="margin:0;color:#898291;font-size:12px;line-height:1.6;">Por seguridad, cambia la contraseña temporal después de iniciar sesión y no la compartas.</p>
                            <div style="margin-top:28px;padding-top:20px;border-top:1px solid #292d39;">
                                <p style="margin:0 0 8px;color:#777284;font-size:11px;line-height:1.5;">Si el botón de verificación no funciona, copia y pega este enlace:</p>
                                <a href="{{ $verificationUrl }}" style="color:#d56bea;font-size:11px;line-height:1.5;word-break:break-all;text-decoration:underline;">{{ $verificationUrl }}</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 24px;background:#0c0d14;border-top:1px solid #292d39;">
                            <p style="margin:0;color:#716a7b;font-size:11px;line-height:1.5;">© {{ date('Y') }} The Velvet Studio · Este correo fue enviado automáticamente.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
