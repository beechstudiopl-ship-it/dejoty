<?php
/* =====================================================================
   The Loop — wysyłka zapytań z formularzy (koszyk + kontakt)
   Własny skrypt na hostingu. Bez zewnętrznych usług.
   Odbiera JSON (fetch) LUB zwykły POST i wysyła e-mail na skrzynkę firmy.
   ===================================================================== */

// --- KONFIGURACJA ---------------------------------------------------
$TO       = 'kontakt@theloop.pl';          // adres, na który mają przychodzić zapytania
$FROM     = 'kontakt@theloop.pl';          // nadawca — MUSI być w domenie strony (theloop.pl)
$SITENAME = 'The Loop';
// --------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');

// tylko POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

// wczytaj dane: JSON (fetch) albo klasyczny formularz
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

function field($d, $k, $def = '') {
    return isset($d[$k]) ? trim((string)$d[$k]) : $def;
}

// honeypot na boty — ukryte pole, jeśli wypełnione = spam, udajemy sukces
if (field($data, '_hp') !== '') { echo json_encode(['ok' => true]); exit; }

$subject = field($data, 'subject', 'Zapytanie ze strony — ' . $SITENAME);
$imie    = field($data, 'imie');
$email   = field($data, 'email');
$telefon = field($data, 'telefon');
$tresc   = field($data, 'tresc');

// walidacja minimalna
if ($imie === '' || $tresc === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Brak wymaganych danych.']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy e-mail.']);
    exit;
}

// zabezpieczenie tematu przed wstrzyknięciem nagłówków
$subject = str_replace(["\r", "\n"], ' ', $subject);

// treść wiadomości
$body  = $tresc . "\n\n";
$body .= "------------------------------\n";
$body .= "Nadawca: " . ($imie ?: '—') . "\n";
$body .= "E-mail:  " . ($email ?: '—') . "\n";
$body .= "Telefon: " . ($telefon ?: '—') . "\n";
$body .= "Wysłano: " . date('Y-m-d H:i') . "\n";

// nagłówki
$headers   = [];
$headers[] = 'From: ' . $SITENAME . ' <' . $FROM . '>';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'X-Mailer: TheLoop-Site';
// odpowiedź trafi bezpośrednio do klienta, jeśli podał e-mail
$replyEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $FROM;
$replyName  = $imie !== '' ? $imie : $SITENAME;
$headers[] = 'Reply-To: ' . $replyName . ' <' . $replyEmail . '>';

$ok = @mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));

if ($ok) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Serwer pocztowy odrzucił wysyłkę.']);
}
