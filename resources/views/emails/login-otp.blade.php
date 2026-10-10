<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Código de Acceso - jG Filmmaker</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 40px 20px;">
    <div style="max-width: 520px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
        <div style="text-align: center; margin-bottom: 24px;">
            <h1 style="color: #f59e0b; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">jG Filmmaker</h1>
            <p style="color: #94a3b8; margin: 4px 0 0 0; font-size: 13px;">Plataforma de Producción Cinematográfica Asistida por IA</p>
        </div>

        <p style="font-size: 14px; line-height: 1.6; color: #cbd5e1; margin-bottom: 20px;">
            Has solicitado acceder a tu cuenta sin contraseña. Utiliza el siguiente código temporal para verificar tu identidad:
        </p>

        <div style="background-color: #0f172a; border: 1px dashed #f59e0b; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 24px;">
            <span style="font-family: monospace; font-size: 32px; font-weight: 800; color: #f59e0b; letter-spacing: 4px; display: inline-block;">
                {{ $code }}
            </span>
            <p style="color: #64748b; font-size: 12px; margin: 8px 0 0 0;">
                Válido durante los próximos {{ $validityMinutes }} minutos. Un solo uso.
            </p>
        </div>

        <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin: 0;">
            Si tú no solicitaste este código, puedes ignorar este correo de forma segura. Por motivos de seguridad, nunca compartas este PIN con nadie.
        </p>
    </div>
</body>
</html>
