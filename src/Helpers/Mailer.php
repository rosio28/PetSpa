<?php
// src/Helpers/Mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {

    private static function make(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('MAIL_USER');
        $mail->Password   = getenv('MAIL_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(
            getenv('MAIL_FROM') ?: getenv('MAIL_USER'),
            getenv('MAIL_FROM_NAME') ?: 'Pet Spa'
        );
        return $mail;
    }

    private static function header(): string {
        return '
        <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#FAF7F2;border-radius:12px;overflow:hidden">
        <div style="background:#2C2C2C;padding:24px 32px;text-align:center">
            <h1 style="font-size:26px;color:#FAF7F2;margin:0;font-family:Georgia,serif">🐾 Pet<span style="color:#A8C4B0">Spa</span></h1>
            <p style="color:#9E9E9E;font-size:12px;margin:4px 0 0">Sistema de Gestión</p>
        </div>
        <div style="padding:32px">';
    }

    private static function footer(): string {
        return '
        </div>
        <div style="background:#F0EAE0;padding:16px 32px;text-align:center">
            <p style="color:#9E9E9E;font-size:11px;margin:0">
                © 2026 PawSpa · La Paz, Bolivia<br>
                Si no solicitaste esto, ignora este email.
            </p>
        </div>
        </div>';
    }

    // ── Activación de cuenta ─────────────────────────────────
    public static function enviarActivacion(string $email, string $nombre, string $token): bool {
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        $link   = $appUrl . '/verify.html?token=' . $token;

        $html = self::header() . '
            <h2 style="color:#2C2C2C;font-size:22px;margin-top:0">¡Bienvenido a PawSpa! 🐾</h2>
            <p style="color:#6B6B6B;line-height:1.7">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p style="color:#6B6B6B;line-height:1.7">Tu cuenta ha sido creada exitosamente. Solo necesitas activarla haciendo clic en el botón:</p>
            <div style="text-align:center;margin:32px 0">
                <a href="' . $link . '"
                   style="background:#7A9E87;color:white;padding:14px 36px;border-radius:8px;
                          text-decoration:none;font-weight:600;font-size:16px;display:inline-block">
                    ✅ Activar mi cuenta
                </a>
            </div>
            <p style="color:#9E9E9E;font-size:13px;text-align:center">⏰ El link expira en <strong>15 minutos</strong>.</p>
            <hr style="border:none;border-top:1px solid #E8DDD0;margin:24px 0">
            <p style="color:#9E9E9E;font-size:11px;word-break:break-all">O copia: ' . $link . '</p>'
            . self::footer();

        try {
            $mail = self::make();
            $mail->addAddress($email, $nombre);
            $mail->isHTML(true);
            $mail->Subject = '✅ Activa tu cuenta — PawSpa';
            $mail->Body    = $html;
            $mail->AltBody = "Activa tu cuenta en PawSpa:\n$link\n\nExpira en 15 minutos.";
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Mailer::enviarActivacion error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Recuperación de contraseña ──────────────────────────
    public static function enviarRecuperacion(string $email, string $token): bool {
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        $link   = $appUrl . '/reset-password.html?token=' . $token;

        $html = self::header() . '
            <h2 style="color:#2C2C2C;font-size:22px;margin-top:0">Recuperar contraseña</h2>
            <p style="color:#6B6B6B;line-height:1.7">
                Recibimos una solicitud para restablecer la contraseña de <strong>' . htmlspecialchars($email) . '</strong>.
            </p>
            <div style="text-align:center;margin:32px 0">
                <a href="' . $link . '"
                   style="background:#C4714A;color:white;padding:14px 32px;border-radius:8px;
                          text-decoration:none;font-weight:600;font-size:16px;display:inline-block">
                    🔑 Restablecer contraseña
                </a>
            </div>
            <p style="color:#9E9E9E;font-size:13px;text-align:center">⏰ El link expira en <strong>15 minutos</strong>.</p>
            <hr style="border:none;border-top:1px solid #E8DDD0;margin:24px 0">
            <p style="color:#9E9E9E;font-size:11px;word-break:break-all">O copia: ' . $link . '</p>'
            . self::footer();

        try {
            $mail = self::make();
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = '🔑 Recuperar contraseña — PawSpa (expira en 15 min)';
            $mail->Body    = $html;
            $mail->AltBody = "Recupera tu contraseña:\n$link\n\nExpira en 15 minutos.";
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Mailer::enviarRecuperacion error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Contraseña temporal reseteada por admin ───────────────
    public static function enviarPasswordReseteada(string $email, string $nombre, string $passwordTemporal): bool {
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';

        $html = self::header() . '
            <h2 style="color:#2C2C2C;font-size:22px;margin-top:0">Tu contraseña fue reseteada</h2>
            <p style="color:#6B6B6B;line-height:1.7">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p style="color:#6B6B6B;line-height:1.7">
                Un administrador ha reseteado tu contraseña. Tu nueva contraseña temporal es:
            </p>
            <div style="background:#2C2C2C;border-radius:8px;padding:20px;text-align:center;margin:24px 0">
                <span style="color:#FAF7F2;font-family:monospace;font-size:22px;letter-spacing:3px">
                    ' . htmlspecialchars($passwordTemporal) . '
                </span>
            </div>
            <p style="color:#6B6B6B;line-height:1.7">
                Por seguridad, <strong>cambia tu contraseña</strong> apenas inicies sesión.
            </p>
            <div style="text-align:center;margin:24px 0">
                <a href="' . $appUrl . '/login.html"
                   style="background:#7A9E87;color:white;padding:12px 28px;border-radius:8px;
                          text-decoration:none;font-weight:600;display:inline-block">
                    Iniciar sesión →
                </a>
            </div>'
            . self::footer();

        try {
            $mail = self::make();
            $mail->addAddress($email, $nombre);
            $mail->isHTML(true);
            $mail->Subject = '🔐 Tu contraseña fue reseteada — PawSpa';
            $mail->Body    = $html;
            $mail->AltBody = "Tu nueva contraseña temporal en PawSpa es: $passwordTemporal\n\nCámbiala al iniciar sesión.";
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Mailer::enviarPasswordReseteada error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Confirmación de cita ─────────────────────────────────
    public static function enviarConfirmacionCita(string $email, string $nombre, array $cita): bool {
        $html = self::header() . '
            <h2 style="color:#2C2C2C;font-size:22px;margin-top:0">✅ Cita confirmada</h2>
            <p style="color:#6B6B6B;line-height:1.7">Hola <strong>' . htmlspecialchars($nombre) . '</strong>, tu cita ha sido agendada:</p>
            <div style="background:#F0EAE0;border-radius:8px;padding:20px;margin:20px 0">
                <table style="width:100%;border-collapse:collapse">
                    <tr><td style="padding:6px 0;color:#6B6B6B;font-size:14px">🐾 Mascota</td>
                        <td style="padding:6px 0;font-weight:600">' . htmlspecialchars($cita['mascota'] ?? '—') . '</td></tr>
                    <tr><td style="padding:6px 0;color:#6B6B6B;font-size:14px">✂️ Servicio</td>
                        <td style="padding:6px 0;font-weight:600">' . htmlspecialchars($cita['servicio'] ?? '—') . '</td></tr>
                    <tr><td style="padding:6px 0;color:#6B6B6B;font-size:14px">👤 Groomer</td>
                        <td style="padding:6px 0;font-weight:600">' . htmlspecialchars($cita['groomer'] ?? '—') . '</td></tr>
                    <tr><td style="padding:6px 0;color:#6B6B6B;font-size:14px">📅 Fecha</td>
                        <td style="padding:6px 0;font-weight:600">' . htmlspecialchars($cita['fecha'] ?? '—') . '</td></tr>
                </table>
            </div>
            <p style="color:#9E9E9E;font-size:13px">Recibirás recordatorios 24h y 2h antes de tu cita.</p>'
            . self::footer();

        try {
            $mail = self::make();
            $mail->addAddress($email, $nombre);
            $mail->isHTML(true);
            $mail->Subject = '✅ Cita agendada — PawSpa';
            $mail->Body    = $html;
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Mailer::enviarConfirmacionCita error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Recordatorio de cita ────────────────────────────────
    public static function enviarRecordatorio(string $email, string $nombre, array $cita, string $tipo = '24h'): bool {
        $emoji = $tipo === '2h' ? '⏰' : '📅';
        $texto = $tipo === '2h' ? 'en 2 horas' : 'mañana';

        $html = self::header() . '
            <h2 style="color:#2C2C2C;font-size:22px;margin-top:0">' . $emoji . ' Recordatorio de cita</h2>
            <p style="color:#6B6B6B;line-height:1.7">
                Hola <strong>' . htmlspecialchars($nombre) . '</strong>, tu cita es <strong>' . $texto . '</strong>:
            </p>
            <div style="background:#F0EAE0;border-radius:8px;padding:20px;margin:20px 0">
                <p style="margin:0;font-size:16px"><strong>' . htmlspecialchars($cita['mascota'] ?? '') . '</strong> — ' . htmlspecialchars($cita['servicio'] ?? '') . '</p>
                <p style="margin:8px 0 0;color:#6B6B6B;font-size:14px">📅 ' . htmlspecialchars($cita['fecha'] ?? '') . ' · 👤 ' . htmlspecialchars($cita['groomer'] ?? '') . '</p>
            </div>'
            . self::footer();

        try {
            $mail = self::make();
            $mail->addAddress($email, $nombre);
            $mail->isHTML(true);
            $mail->Subject = "$emoji Recordatorio: tu cita es $texto — PawSpa";
            $mail->Body    = $html;
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Mailer::enviarRecordatorio error: ' . $e->getMessage());
            return false;
        }
    }
}
