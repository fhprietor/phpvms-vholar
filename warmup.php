<?php
require '/var/www/phpvms/bootstrap/autoload.php';
$app = require_once '/var/www/phpvms/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Lista de correos reales (amigos, otro correo tuyo, etc.)
$emails = [
    'fhprietor@gmail.com',  // Tu principal
    'fhprietor@outlook.com', // Si tienes
    'fhprietor@hotmail.com',
    'vholar@outlook.com',
    'kensatogonzalez@gmail.com',
    'luis.hurtado.aerojet@gmail.com',
    'santiago.bernal10f@gmail.com',
    'juandavid.gonzalez94@gmail.com'
];

echo "🚀 Iniciando calentamiento de correos...\n";
echo "====================================\n\n";

$total = 0;
$exitosos = 0;
$fallidos = 0;

foreach ($emails as $email) {
    $total++;
    echo "📧 Enviando a: $email... ";
    
    try {
        // Opción 1: Usar send() con vista (recomendado)
        Mail::send([], [], function ($message) use ($email) {
            $message->to($email)
                    ->subject('Notificación VHOLAR - ' . date('d/m/Y'))
                    ->from('noreply@vholar.co', 'VHOLAR Aerolínea Virtual')
                    ->replyTo('soporte@vholar.co', 'Soporte VHOLAR')
                    ->html("
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                        </head>
                        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                                <div style='background-color: #0066cc; padding: 10px; text-align: center;'>
                                    <h2 style='color: white; margin: 0;'>VHOLAR</h2>
                                </div>
                                <div style='background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd;'>
                                    <h3 style='color: #0066cc;'>Estimado capi VHOLAR,</h3>
                                    <p>Este es un correo automático de calentamiento del nuevo portal de VHOLAR Aerolínea Virtual.</p>
                                    <p>Por favor, si recibes este correo en spam:</p>
                                    <ol>
                                        <li>Márcalo como 'No es spam'</li>
                                        <li>Añade noreply@vholar.co a tu libreta de direcciones</li>
                                        <li>La propina es voluntaria mis capis</li>
                                    </ol>
                                    <p>Cordial saludo, Franklin Prieto.</p>
                                    <p style='background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>
                                        <strong>📅 Fecha y hora:</strong> " . date('Y-m-d H:i:s') . "<br>
                                        <strong>📨 ID del mensaje:</strong> " . uniqid() . "
                                    </p>
                                </div>
                                <div style='margin-top: 20px; font-size: 12px; color: #666; text-align: center;'>
                                    <p>© " . date('Y') . " VHOLAR. Todos los derechos reservados.</p>
                                    <p>
                                        <a href='#' style='color: #0066cc;'>Cancelar suscripción</a> | 
                                        <a href='#' style='color: #0066cc;'>Política de privacidad</a>
                                    </p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ");
        });
        
        echo "✅ ENVIADO\n";
        $exitosos++;
        
        // Esperar entre 10 y 30 segundos entre envíos
        $espera = rand(10, 30);
        echo "   ⏳ Esperando $espera segundos...\n";
        sleep($espera);
        
    } catch (Exception $e) {
        echo "❌ FALLÓ: " . $e->getMessage() . "\n";
        $fallidos++;
    }
    
    echo "\n";
}

echo "\n📊 RESUMEN FINAL\n";
echo "================\n";
echo "Total procesados: $total\n";
echo "✅ Exitosos: $exitosos\n";
echo "❌ Fallidos: $fallidos\n";
echo "📅 Fecha: " . date('Y-m-d H:i:s') . "\n";
