<?php

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.html");
    exit;
}

// Omezeni frekvence odesilani z jedne IP adresy.
$clientIp = $_SERVER["REMOTE_ADDR"] ?? "unknown";
$rateLimitFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "roze_contact_" . hash("sha256", $clientIp) . ".lock";
$rateLimitHandle = @fopen($rateLimitFile, "c+");

if ($rateLimitHandle !== false) {
    flock($rateLimitHandle, LOCK_EX);
    rewind($rateLimitHandle);
    $lastRequest = (int) trim(stream_get_contents($rateLimitHandle));

    if ($lastRequest > time() - 60) {
        flock($rateLimitHandle, LOCK_UN);
        fclose($rateLimitHandle);
        http_response_code(429);
        exit("Příliš mnoho požadavků. Zkuste to prosím za chvíli.");
    }

    ftruncate($rateLimitHandle, 0);
    rewind($rateLimitHandle);
    fwrite($rateLimitHandle, (string) time());
    fflush($rateLimitHandle);
    flock($rateLimitHandle, LOCK_UN);
    fclose($rateLimitHandle);
}

// Ochrana proti spambotům (Honeypot)
if (!empty($_POST["website"])) {
    header("Location: index.html?status=success");
    exit;
}

// Načtení a očista dat z formuláře
$nameInput = isset($_POST["name"]) && is_string($_POST["name"]) ? $_POST["name"] : '';
$emailInput = isset($_POST["email"]) && is_string($_POST["email"]) ? $_POST["email"] : '';
$subjectInput = isset($_POST["subject"]) && is_string($_POST["subject"]) ? $_POST["subject"] : '';
$messageInput = isset($_POST["message"]) && is_string($_POST["message"]) ? $_POST["message"] : '';

$name = trim(strip_tags(str_replace(["\r", "\n"], " ", $nameInput)));
$email = filter_var(trim($emailInput), FILTER_VALIDATE_EMAIL);
$subject = trim(strip_tags(str_replace(["\r", "\n"], " ", $subjectInput))) ?: 'Zpráva z webu';
$message = trim(strip_tags($messageInput));

// Validace povinných polí
if (!$name || !$email || !$message || strlen($name) > 100 || strlen($subject) > 160 || strlen($message) > 5000) {
    echo "<script>alert('Prosím vyplňte všechna pole správně.'); window.history.back();</script>";
    exit;
}

// Nastavení e-mailu
$to = "info@rozephotography.cz";
$email_subject = "Kontaktní formulář: " . $subject;

$email_content = "Byla doručena nová zpráva z webu rozephotography.cz:\n\n";
$email_content .= "Jméno: $name\n";
$email_content .= "E-mail: $email\n";
$email_content .= "Předmět: $subject\n\n";
$email_content .= "Zpráva:\n$message\n";

// Hlavičky e-mailu
$headers = array();
$headers[] = "From: Roze Photography Web <info@rozephotography.cz>";
$headers[] = "Reply-To: $name <$email>";
$headers[] = "X-Mailer: PHP/" . phpversion();
$headers[] = "Content-Type: text/plain; charset=UTF-8";

// Odeslání e-mailu
if (mail($to, $email_subject, $email_content, implode("\r\n", $headers), "-f info@rozephotography.cz")) {

    echo "<script>alert('Děkuji! Vaše zpráva byla úspěšně odeslána.'); window.location.href='index.html';</script>";
} else {

    echo "<script>alert('Omlouváme se, při odesílání zprávy došlo k chybě. Zkuste to prosím později nebo napište přímo na info@rozephotography.cz'); window.history.back();</script>";
}
