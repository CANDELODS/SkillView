<?php

namespace Classes;

use PHPMailer\PHPMailer\PHPMailer;

class Email
{
    private string $correo;
    private string $nombre;
    private string $token;

    public function __construct(
        string $correo,
        string $nombre,
        string $token
    ) {
        $this->correo = $correo;
        $this->nombre = $nombre;
        $this->token = $token;
    }

    public function enviarRecuperacion(): bool
    {
        //Con True PHPMailer lanza excepciones cuando ocurre un error, las capturamos con el try catch
        $mail = new PHPMailer(true);

        try {
            // Configuración SMTP
            $mail->isSMTP();
            $mail->Host = $_ENV['EMAIL_HOST'];
            $mail->SMTPAuth = true; //Indica que Gmail exige autenticación
            $mail->Username = $_ENV['EMAIL_USER'];
            $mail->Password = $_ENV['EMAIL_PASS'];

            //Conexión cifrada mediante STARTTLS usando el puerto 587
            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;

            $mail->Port = (int) $_ENV['EMAIL_PORT'];

            // Configuración general
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true); //El body contendrá HTML

            //Enviamos el mensaje desde la cuenta configurada
            $mail->setFrom(
                $_ENV['EMAIL_USER'],
                $_ENV['EMAIL_FROM_NAME'] ?? 'SkillView'
            );

            //Agregamos al destinatario
            $mail->addAddress(
                $this->correo,
                $this->nombre
            );

            $mail->Subject = 'Restablece tu contraseña de SkillView';

            // Construir enlace
            //Con rtrim(..., '/') eliminamos las barras del final -> http://localhost:3000/ -> http://localhost:3000 
            //Esto nos ayuda a evitar construir http://localhost:3000//restablecer-password
            $host = rtrim($_ENV['HOST'] ?? '', '/');

            if ($host === '') {
                throw new \RuntimeException('La variable HOST no está configurada');
            }

            //Generar URL
            //rawurlencode() transforma el token en un valor seguro para incluirlo dentro de una URL
            //http://localhost:3000/restablecer-password?token=abc123...
            $url = $host
                . '/restablecer-password?token='
                . rawurlencode($this->token);

            //htmlspecialchars() Evita que un nombre que contenga caracters HTML sea interpretado como código     
            $nombreSeguro = htmlspecialchars(
                $this->nombre,
                ENT_QUOTES,
                'UTF-8'
            );

            $urlSegura = htmlspecialchars(
                $url,
                ENT_QUOTES,
                'UTF-8'
            );

            //Versión HTML del correo
            $mail->Body = "
                <html>
                    <body style=\"font-family: Arial, sans-serif;\">

                        <h2>Recuperación de contraseña</h2>

                        <p>
                            Hola <strong>{$nombreSeguro}</strong>.
                        </p>

                        <p>
                            Recibimos una solicitud para restablecer
                            la contraseña de tu cuenta en SkillView.
                        </p>

                        <p>
                            Presiona el siguiente enlace:
                        </p>

                        <p>
                            <a href=\"{$urlSegura}\">
                                Restablecer mi contraseña
                            </a>
                        </p>

                        <p>
                            Este enlace será válido durante 30 minutos
                            y solo podrá utilizarse una vez.
                        </p>

                        <p>
                            Si no realizaste esta solicitud,
                            puedes ignorar este mensaje.
                        </p>

                    </body>
                </html>
            ";

            //Versión en texto plano del correo
            $mail->AltBody =
                "Recibimos una solicitud para restablecer "
                . "tu contraseña de SkillView.\n\n"
                . "Ingresa al siguiente enlace:\n"
                . $url
                . "\n\nEl enlace será válido durante 30 minutos.";

            //Envío del correo, si se envía, devuelte true
            return $mail->send();

        } catch (\Throwable $error) {
            error_log(
                'No se pudo enviar el correo de recuperación: '
                . $error->getMessage()
            );

            return false;
        }
    }
}