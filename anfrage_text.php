<?php
// Baut den Mailtext einer Anfrage und sammelt die erlaubten Zusatzfelder.
// Welche Zusatzfelder erlaubt sind und woher die Anfrage kommt, steht in anfrage-konfig.json –
// nie in der Kundeneingabe.

// Liefert [[Label, Wert], ...] fuer die in der Konfiguration erlaubten, ausgefuellten Zusatzfelder.
function zusatz_werte(array $konfigFelder, array $post): array {
    $werte = [];
    foreach ($konfigFelder as $f) {
        $name = (string)($f['name'] ?? '');
        if (!preg_match('/^[a-z_]{2,30}$/', $name)) continue;
        $wert = trim(preg_replace('/[\r\n]+/', ' ', (string)($post[$name] ?? '')));
        if ($wert === '') continue;
        $werte[] = [(string)$f['label'], mb_substr($wert, 0, 200)];
    }
    return $werte;
}

function anfrage_text(array $f, array $zusatz, string $quelle): string {
    $text = "Neue Anfrage über die Webseite\n\n"
        . "Name: {$f['name']}\n"
        . "Telefon: {$f['telefon']}\n"
        . "E-Mail: {$f['email']}\n"
        . "Anlass: {$f['anlass']}\n"
        . "Wunschdatum: {$f['datumText']}\n"
        . "Gäste: " . ($f['gaeste'] ?: '-') . "\n"
        . "Quelle: $quelle\n";
    foreach ($zusatz as [$label, $wert]) $text .= "$label: $wert\n";
    return $text . "\n" . ($f['nachricht'] !== '' ? "Nachricht:\n{$f['nachricht']}\n" : '');
}
