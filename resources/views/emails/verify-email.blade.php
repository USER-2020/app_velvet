@php($logoUrl = asset('THEVELVETSTUDIO_BRANDBOOK-55.png'))
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirma tu correo · The Velvet Studio</title>
</head>
<body style="margin:0;padding:32px 14px;background:#080910;color:#f7f1fb;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Confirma tu dirección de correo para activar tu cuenta en The Velvet Studio.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;border:1px solid #30263b;border-radius:18px;overflow:hidden;background:#11121a;box-shadow:0 20px 55px rgba(0,0,0,.35);">
                    <tr>
                        <td align="center" style="padding:28px 28px 24px;background:#0c0d14;border-bottom:1px solid #292d39;">
                            <img src="{{ $logoUrl }}" width="62" height="62" alt="The Velvet Studio" style="display:block;width:62px;height:62px;object-fit:contain;margin:0 auto 14px;">
                            <div style="color:#f4eff6;font-family:Georgia,serif;font-size:18px;letter-spacing:3px;line-height:1.1;">THE VELVET</div>
                            <div style="margin-top:5px;color:#a6a0ae;font-size:9px;letter-spacing:5px;">STUDIO</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 36px;background:linear-gradient(135deg,#281331 0%,#15101e 58%,#10121a 100%);">
                            <p style="margin:0;color:#d56bea;font-size:10px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;">Verificación de cuenta</p>
                            <h1 style="margin:14px 0 0;color:#fff;font-family:Georgia,'Times New Roman',serif;font-size:34px;font-weight:400;line-height:1.12;">Confirma tu correo</h1>
                            <p style="margin:16px 0 0;color:#c9c1d0;font-size:15px;line-height:1.7;">Hola, {{ $recipientName }}. Confirma tu dirección de correo para continuar de forma segura en The Velvet Studio.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 36px 36px;background:#11121a;">
                            <p style="margin:0;color:#ded7e3;font-size:15px;line-height:1.7;">Haz clic en el botón para verificar tu cuenta:</p>
                            <p style="margin:26px 0 28px;text-align:center;">
                                <a href="{{ $verificationUrl }}" style="display:inline-block;padding:14px 26px;border-radius:9px;background:#a92ad8;color:#fff;font-size:14px;font-weight:bold;text-decoration:none;box-shadow:0 8px 22px rgba(169,42,216,.25);">Verificar mi correo →</a>
                            </p>
                            <div style="padding:15px 16px;border:1px solid #3b3046;border-radius:10px;background:#171522;">
                                <p style="margin:0;color:#b6adbd;font-size:12px;line-height:1.6;">Por seguridad, este enlace vence en {{ $expiresIn }} minutos.</p>
                            </div>
                            <p style="margin:24px 0 0;color:#aaa2b1;font-size:13px;line-height:1.65;">Si no solicitaste crear una cuenta, puedes ignorar este mensaje. No se realizará ninguna acción.</p>
                            <p style="margin:24px 0 0;color:#c9c1d0;font-size:14px;line-height:1.6;">Saludos,<br><strong style="color:#f0c4f8;">The Velvet Studio</strong></p>
                            <div style="margin-top:28px;padding-top:20px;border-top:1px solid #292d39;">
                                <p style="margin:0 0 8px;color:#777284;font-size:11px;line-height:1.5;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
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
