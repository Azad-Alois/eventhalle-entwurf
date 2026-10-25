<?php
// Versand-Skript fuer das Anfrageformular: prueft die Angaben und schickt sie per Mail an den Betreiber.
// Empfaenger/Absender stehen in anfrage-konfig.json (wird beim Bauen aus der Mandanten-Konfiguration erzeugt).
// Antwortet immer mit JSON: {"ok": true} oder {"ok": false, "meldung": "..."}.

header('Content-Type: application/json; charset=utf-8');

function antwort(bool $ok, string $meldung = '', int $status = 200): void {
    http_response_code($status);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'meldung' => $meldung], JSON_UNESCAPED_UNICODE);
    exit;
}

function feld(string $name, int $max): string {
    $wert = trim((string)($_POST[$name] ?? ''));
    // Zeilenumbrueche nur im Nachrichtenfeld erlaubt; sonst entfernen (Schutz gegen Kopfzeilen-Einschleusung)
    if ($name !== 'nachricht') $wert = preg_replace('/[\r\n]+/', ' ', $wert);
    return mb_substr($wert, 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') antwort(false, 'Nur POST erlaubt.', 405);

$konfig = json_decode((string)@file_get_contents(__DIR__ . '/anfrage-konfig.json'), true);
if (!$konfig || empty($konfig['empfaenger'])) antwort(false, 'Versand ist nicht eingerichtet.', 500);

// Falle fuer Spam-Programme: dieses Feld ist fuer Menschen unsichtbar
if (feld('webseite', 200) !== '') antwort(true);

// Einfache Bremse: hoechstens 5 Anfragen pro Stunde je Adresse
$sperre = sys_get_temp_dir() . '/anfrage_' . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.txt';
$zeiten = array_filter(array_map('intval', @file($sperre, FILE_IGNORE_NEW_LINES) ?: []), fn($z) => $z > time() - 3600);
if (count($zeiten) >= 5) antwort(false, 'Zu viele Anfragen. Bitte versuchen Sie es später noch einmal oder rufen Sie uns an.', 429);

$name = feld('name', 100);
$telefon = feld('telefon', 40);
$email = feld('email', 120);
$anlass = feld('anlass', 60);
$datum = feld('datum', 20);
$gaeste = feld('gaeste', 6);
$nachricht = feld('nachricht', 3000);

if ($name === '' || $telefon === '' || $anlass === '') antwort(false, 'Bitte Name, Telefon und Anlass angeben.', 422);
if (!preg_match('/^[0-9 +()\/-]{6,40}$/', $telefon)) antwort(false, 'Bitte eine gültige Telefonnummer angeben.', 422);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) antwort(false, 'Bitte eine gültige E-Mail-Adresse angeben.', 422);

$datumText = 'offen';
if ($datum !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $datum);
    $datumText = $d ? $d->format('d.m.Y') : $datum;
}

$text = "Neue Anfrage über die Webseite\n\n"
    . "Name: $name\n"
    . "Telefon: $telefon\n"
    . "E-Mail: " . ($email ?: '-') . "\n"
    . "Anlass: $anlass\n"
    . "Wunschdatum: $datumText\n"
    . "Gäste: " . ($gaeste ?: '-') . "\n\n"
    . ($nachricht !== '' ? "Nachricht:\n$nachricht\n" : '');

$betreff = '=?UTF-8?B?' . base64_encode("Anfrage $anlass – $datumText – $name") . '?=';
$kopf = [
    'From: ' . '=?UTF-8?B?' . base64_encode(($konfig['name'] ?? 'Webseite') . ' Webseite') . '?= <' . $konfig['absender'] . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];
if ($email !== '') $kopf[] = "Reply-To: $email";

if (!mail($konfig['empfaenger'], $betreff, $text, implode("\r\n", $kopf), '-f' . $konfig['absender'])) {
    antwort(false, 'Das hat leider nicht geklappt. Bitte rufen Sie uns an oder schreiben Sie per WhatsApp.', 500);
}

$zeiten[] = time();
@file_put_contents($sperre, implode("\n", $zeiten));
antwort(true);
