<?php

namespace Classes;

use PHPMailer\PHPMailer\PHPMailer;

class correo {

    public $correo;
    public $nombres;
    public $token;
    
    public function __construct($correo, $nombres, $token)
    {
        $this->correo = $correo;
        $this->nombres = $nombres;
        $this->token = $token;
    }

    public function enviarConfirmacion() {

         // create a new object
         $mail = new PHPMailer();
         $mail->isSMTP();
         $mail->Host = $_ENV['correo_HOST'];
         $mail->SMTPAuth = true;
         $mail->Port = $_ENV['correo_PORT'];
         $mail->Username = $_ENV['correo_USER'];
         $mail->Password = $_ENV['correo_PASS'];
     
         $mail->setFrom('cuentas@devwebcamp.com');
         $mail->addAddress($this->correo, $this->nombres);
         $mail->Subject = 'Confirma tu Cuenta';

         // Set HTML
         $mail->isHTML(TRUE);
         $mail->CharSet = 'UTF-8';

         $contenido = '<html>';
         $contenido .= "<p><strong>Hola " . $this->nombres .  "</strong> Has Registrado Correctamente tu cuenta en DevWebCamp; pero es necesario confirmarla</p>";
         $contenido .= "<p>Presiona aquí: <a href='" . $_ENV['HOST'] . "/confirmar-cuenta?token=" . $this->token . "'>Confirmar Cuenta</a>";       
         $contenido .= "<p>Si tu no creaste esta cuenta; puedes ignorar el mensaje</p>";
         $contenido .= '</html>';
         $mail->Body = $contenido;

         //Enviar el mail
         $mail->send();

    }

    public function enviarInstrucciones() {

        // create a new object
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = $_ENV['correo_HOST'];
        $mail->SMTPAuth = true;
        $mail->Port = $_ENV['correo_PORT'];
        $mail->Username = $_ENV['correo_USER'];
        $mail->Password = $_ENV['correo_PASS'];
    
        $mail->setFrom('cuentas@devwebcamp.com');
        $mail->addAddress($this->correo, $this->nombres);
        $mail->Subject = 'Reestablece tu password';

        // Set HTML
        $mail->isHTML(TRUE);
        $mail->CharSet = 'UTF-8';

        $contenido = '<html>';
        $contenido .= "<p><strong>Hola " . $this->nombres .  "</strong> Has solicitado reestablecer tu password, sigue el siguiente enlace para hacerlo.</p>";
        $contenido .= "<p>Presiona aquí: <a href='" . $_ENV['HOST'] . "/recuperar?token=" . $this->token . "'>Reestablecer Password</a>";        
        $contenido .= "<p>Si tu no solicitaste este cambio, puedes ignorar el mensaje</p>";
        $contenido .= '</html>';
        $mail->Body = $contenido;

        //Enviar el mail
        $mail->send();
    }
}