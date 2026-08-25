<?php

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.html");
    exit;
}

// Ochrana proti spambotům (Honeypot)
if (!empty($_POST["website"])) {
    // Pokud bot vyplnil skryté pole, předstíráme úspěšné odeslání
    header("Location: index.html?status=success");
    exit;
}

// Načtení a očista dat z formuláře
$name = isset($_POST["name"]) ? trim(strip_tags($_POST["name"])) : '';
$email = isset($_POST["email"]) ? filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL) : false;
$subject = isset($_POST["subject"]) ? trim(strip_tags($_POST["subject"])) : 'Zpráva z webu';
$message = isset($_POST["message"]) ? trim(strip_tags($_POST["message"])) : '';

// Validace povinných polí
if (!$name || !$email || !$message) {
    echo "<script>alert('Prosím vyplňte všechna pole správně.'); window.history.back();</script>";
    exit;
}

// Nastavení e-mailu
$to = "info@rozephotography.cz"; 
$email_subject = "Kontaktní formulář: ".$subject;

$email_content = "Byla doručena nová zpráva z webu rozephotography.cz:\n\n";
$email_content.= "Jméno: $name\n";
$email_content.= "E-mail: $email\n";
$email_content.= "Předmět: $subject\n\n";
$email_content.= "Zpráva:\n$message\n";

// Hlavičky e-mailu
// Odesílatel MUSÍ být existující e-mail na vaší WEDOS doméně!
$headers = array();
$headers[] = "From: Roze Photography Web <info@rozephotography.cz>";
$headers[] = "Reply-To: $name <$email>"; // Když dáte "Odpovědět", napíše se přímo zákazníkovi
$headers[] = "X-Mailer: PHP/".phpversion();
$headers[] = "Content-Type: text/plain; charset=UTF-8";

// Odeslání e-mailu
if (mail($to, $email_subject, $email_content, implode("\r\n", $headers))) {
    
    echo "<script>alert('Děkuji! Vaše zpráva byla úspěšně odeslána.'); window.location.href='index.html';</script>";
} else {
   
    echo "<script>alert('Omlouváme se, při odesílání zprávy došlo k chybě. Zkuste to prosím později nebo napište přímo na info@rozephotography.cz'); window.history.back();</script>";
}
?>