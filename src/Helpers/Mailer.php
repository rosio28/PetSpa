<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {  

    public static function enviarActivacion($email, $nombre, $token) {

        $mail = new PHPMailer(true);

        try {

            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('MAIL_USER');
            $mail->Password = getenv('MAIL_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = getenv('MAIL_PORT');

            $mail->setFrom(
                getenv('MAIL_FROM'),
                getenv('MAIL_FROM_NAME')
            );

            $mail->addAddress($email, $nombre);

            $url = APP_URL . '/activar?token=' . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Activar cuenta - Pet Spa';

            $mail->Body = "
                <h2>Hola {$nombre}</h2>
                <p>Gracias por registrarte en Pet Spa.</p>
                <p>
                    <a href='{$url}'>Activar cuenta</a>
                </p>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log($mail->ErrorInfo);
            return false;
        }
    }

    public static function enviarRecuperacion(string $emailDestino, string $token): bool {
    $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
    $link   = $appUrl . '/reset-password.html?token=' . $token;

    $html = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#FAF7F2;padding:32px;border-radius:12px">
      <div style="text-align:center;margin-bottom:24px">
        <h1 style="font-size:28px;color:#2C2C2C;font-family:Georgia,serif">🐾 Pet<span style="color:#7A9E87">Spa</span></h1>
      </div>
      <h2 style="color:#2C2C2C;font-size:20px">Recuperar contraseña</h2>
      <p style="color:#6B6B6B;line-height:1.6">
        Recibimos una solicitud para restablecer la contraseña de <strong>' . htmlspecialchars($emailDestino) . '</strong>.
      </p>
      <p style="color:#6B6B6B;line-height:1.6">
        Haz clic en el botón para crear una nueva contraseña:
      </p>
      <div style="text-align:center;margin:32px 0">
        <a href="' . $link . '"
           style="background:#C4714A;color:white;padding:14px 32px;border-radius:8px;
                  text-decoration:none;font-weight:600;font-size:16px;display:inline-block">
          🔑 Restablecer contraseña
        </a>
      </div>
      <p style="color:#9E9E9E;font-size:13px">
        ⏰ Este link expira en <strong>15 minutos</strong>.
      </p>
      <p style="color:#9E9E9E;font-size:13px">
        Si no solicitaste esto, ignora este correo. Tu contraseña no cambiará.
      </p>
      <hr style="border:none;border-top:1px solid #E8DDD0;margin:24px 0">
      <p style="color:#9E9E9E;font-size:11px;text-align:center">
        O copia este link en tu navegador:<br>
        <span style="word-break:break-all;color:#7A9E87">' . $link . '</span>
      </p>
    </div>';

    try {

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = getenv('MAIL_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('MAIL_USER');
    $mail->Password = getenv('MAIL_PASS');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = getenv('MAIL_PORT');

    $mail->setFrom(
        getenv('MAIL_FROM'),
        getenv('MAIL_FROM_NAME')
    );

    $mail->addAddress($emailDestino);

    $mail->isHTML(true);
    $mail->Subject = '🔑 Recuperar contraseña — Pet Spa (expira en 15 min)';
    $mail->Body    = $html;

    $mail->AltBody =
        "Recupera tu contraseña en Pet Spa.\n\n" .
        "Link (expira en 15 minutos):\n$link\n\n" .
        "Si no solicitaste esto, ignora este email.";

    $mail->send();

    return true;

} catch (Throwable $e) {

    error_log('Mailer::enviarRecuperacion error: ' . $e->getMessage());

    return false;
}
}
}