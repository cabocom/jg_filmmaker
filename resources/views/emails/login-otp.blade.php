<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Acceso :: jG Filmmaker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 24px;
            text-align: left;
        }
        .logo-badge {
            display: inline-block;
            background: #d97706;
            color: #ffffff;
            font-weight: 700;
            font-family: monospace;
            font-size: 13px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .header p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .content {
            padding: 28px 24px;
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
        }
        .otp-container {
            text-align: center;
            margin: 24px 0;
        }
        .otp-box {
            display: inline-block;
            background-color: #f8fafc;
            border: 2px dashed #d97706;
            border-radius: 8px;
            padding: 16px 28px;
            font-family: monospace, 'Courier New', Courier;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #1e293b;
        }
        .notice-box {
            background-color: #fef3c7;
            border-left: 4px solid #d97706;
            padding: 12px 16px;
            font-size: 12.5px;
            color: #92400e;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 24px;
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="logo-badge">jg_filmmaker</span>
            <h1>jG Filmmaker</h1>
            <p>Plataforma de Producción Cinematográfica Asistida por IA</p>
        </div>

        <div class="content">
            <p>Hola,</p>

            <p>Has solicitado iniciar sesión de forma segura y sin contraseña en <strong>jG Filmmaker</strong>.</p>

            <p>Introduce el siguiente código temporal de verificación de un solo uso para autenticarte en tu espacio de trabajo:</p>

            <div class="otp-container">
                <div class="otp-box">{{ $code }}</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
                    Código válido durante los próximos <strong>{{ $validityMinutes }} minutos</strong>.
                </div>
            </div>

            <div class="notice-box">
                <strong>Seguridad y Protección de Acceso:</strong><br>
                Este código es personal, de un solo uso e intransferible. Si tú no has iniciado este acceso, puedes ignorar este mensaje con total tranquilidad; tu cuenta permanece protegida y no se ha realizado ningún cambio.
            </div>

            <p>Un cordial saludo,<br>
            <strong>Equipo de jG Filmmaker</strong><br>
            <span style="font-size: 12px; color: #64748b;">Producción Audiovisual Inteligente · <a href="https://javiergalvez.com" style="color: #d97706; text-decoration: none;">javiergalvez.com</a></span></p>
        </div>

        <div class="footer">
            <p><strong>Información Legal y Privacidad (RGPD / LOPDGDD):</strong><br>
            <strong>Responsable:</strong> Javier Gálvez (jG Filmmaker) · <strong>Finalidad:</strong> Autenticación multifactor transaccional y acceso a la plataforma · <strong>Legitimación:</strong> Ejecución de la relación de servicio y consentimiento del usuario (Art. 6.1.b / 6.1.a RGPD).<br>
            Este es un correo electrónico transaccional emitido automáticamente por el sistema de seguridad de jG Filmmaker.</p>
        </div>
    </div>
</body>
</html>
