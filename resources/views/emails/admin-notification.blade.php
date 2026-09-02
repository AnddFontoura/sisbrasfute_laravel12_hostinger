<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - SisBrasFute</title>
</head>
<body style="margin: 0; padding: 0; background-color: #e5e7eb; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #e5e7eb;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; padding: 24px 20px;">
                            <span style="font-size: 28px; font-weight: bold; color: #f97316; letter-spacing: 2px;">SISBRASFUTE</span>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size: 20px; font-weight: bold; color: #1a1a1a; padding-bottom: 20px;">
                                        Olá{{ $recipientName ? ', ' . $recipientName : '' }}!
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 18px; font-weight: bold; color: #1a1a1a; padding-bottom: 16px;">
                                        {{ $title }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 16px; color: #374151; line-height: 1.6; padding-bottom: 32px; white-space: pre-line;">{{ $description }}</td>
                                </tr>
                                <!-- Button -->
                                <tr>
                                    <td align="center" style="padding-bottom: 32px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="background-color: #f97316; border-radius: 6px;">
                                                    <a href="{{ $notificationsUrl }}" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; letter-spacing: 0.5px;">{{ $notificationsLabel ?? 'VER NOTIFICAÇÕES' }}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #f3f4f6; padding: 24px 20px;">
                            <span style="font-size: 13px; color: #6b7280;">&copy; 2025 SisBrasFute. Todos os direitos reservados.</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
