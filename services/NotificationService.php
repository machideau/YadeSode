<?php
class NotificationService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function sendBulletinNotification($eleveId, $periodeId, $bulletinPath) {
        // Récupérer les informations de l'élève et parents
        $query = "SELECT u.email, u.nom, u.prenoms, e.telephone_tuteur,
                         c.nom as classe_nom, p.nom as periode_nom
                  FROM eleves e
                  JOIN users u ON e.user_id = u.id
                  JOIN classes c ON e.classe_id = c.id
                  JOIN periodes p ON p.id = :periode_id
                  WHERE e.id = :eleve_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $eleve = $stmt->fetch();
        
        if ($eleve && $eleve['email']) {
            $this->sendEmailNotification(
                $eleve['email'],
                "Bulletin disponible - {$eleve['periode_nom']}",
                $this->getBulletinEmailTemplate($eleve, $bulletinPath),
                $bulletinPath
            );
        }
        
        // SMS si numéro tuteur disponible
        if ($eleve && $eleve['telephone_tuteur']) {
            $this->sendSMSNotification(
                $eleve['telephone_tuteur'],
                "Bulletin de {$eleve['nom']} {$eleve['prenoms']} disponible pour {$eleve['periode_nom']}"
            );
        }
    }
    
    private function sendEmailNotification($to, $subject, $body, $attachment = null) {
        // Configuration email (adapter selon votre serveur SMTP)
        $headers = [
            'From' => 'noreply@etablissement.com',
            'Reply-To' => 'contact@etablissement.com',
            'X-Mailer' => 'PHP/' . phpversion(),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];
        
        // Si pièce jointe
        if ($attachment && file_exists($attachment)) {
            // Implémenter l'envoi avec pièce jointe
            // Utiliser une librairie comme PHPMailer pour plus de robustesse
        }
        
        return mail($to, $subject, $body, implode("\r\n", array_map(
            function($k, $v) { return "$k: $v"; },
            array_keys($headers),
            $headers
        )));
    }
    
    private function sendSMSNotification($phone, $message) {
        // Intégration avec un service SMS (Twilio, OVH, etc.)
        // Exemple avec une API générique
        
        $apiUrl = 'https://api.sms-service.com/send';
        $apiKey = 'VOTRE_CLE_API_SMS';
        
        $data = [
            'to' => $phone,
            'message' => $message,
            'api_key' => $apiKey
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
    
    private function getBulletinEmailTemplate($eleve, $bulletinPath) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f3f4f6; padding: 10px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Bulletin de Notes Disponible</h2>
            </div>
            <div class='content'>
                <p>Bonjour,</p>
                
                <p>Le bulletin de <strong>{$eleve['nom']} {$eleve['prenoms']}</strong> 
                pour la période <strong>{$eleve['periode_nom']}</strong> est maintenant disponible.</p>
                
                <p>Vous pouvez le consulter en vous connectant sur la plateforme ou 
                le télécharger en pièce jointe de cet email.</p>
                
                <p>Cordialement,<br>
                L'équipe pédagogique</p>
            </div>
            <div class='footer'>
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </body>
        </html>
        ";
    }
}
?>