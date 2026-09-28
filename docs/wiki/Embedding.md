# Osadzanie na stronie i w e-mailu

## WordPress

```text
[ecd_timer id="promocja"]
```

Shortcode tworzy responsywny znacznik `img` i ładuje jeden wspólny skrypt. Po zmianie widoczności dokumentu na `visible` obrazy są pobierane ponownie z parametrem `_t`. Nie ma stałego odpytywania serwera ani nowego żądania po każdej klatce.

## HTML wiadomości

Skopiuj **Link do mailingu (GIF)** z panelu i użyj go jako `src` obrazu. Przykład z fikcyjną domeną:

```html
<p>Promocja trwa do 31 grudnia 2027 r., godz. 23:59:59 czasu Europe/Warsaw.</p>
<img src="https://example.com/?ecd_action=render&amp;ecd=promocja&amp;mode=email"
     alt="Odliczanie do zakończenia promocji"
     style="display:block;max-width:100%;height:auto;border:0;">
```

Edytor mailingowy musi zachować zewnętrzny URL. Import obrazu do biblioteki edytora, dołączenie pliku lub osadzenie go jako załącznika może zamienić dynamiczne pobieranie na wcześniej wygenerowaną kopię. Nie wklejaj shortcode ani JavaScript do maila.

## Kontrakt adresu obrazu

| Parametr | Zachowanie |
|---|---|
| `ecd_action=render` | Włącza endpoint obrazu |
| `ecd=ID` | Wybiera istniejącą konfigurację |
| `mode=email` lub `mode=anim` | GIF: 60 klatek z Imagick, statyczny GIF bez Imagick |
| Inny lub brak `mode` | PNG; WebP, gdy obsługuje go GD i nagłówek `Accept` zawiera `image/webp` |
| `_t` | Parametr odświeżania przeglądarki; nie tworzy osobnego cache po stronie wtyczki |

Obsługiwane są GET i HEAD. HEAD nie generuje obrazu i nie daje pełnego testu działania kodeka. ID i konfiguracja nie stanowią tajemnicy: endpoint służy do publicznego osadzania. Nie przekazuj adresów e-mail ani tokenów odbiorców w URL.

GIF jest skończoną sekwencją 60 sekund. Jego ponowne odtwarzanie przez aplikację nie pobiera automatycznie nowej wartości z serwera. Wspólny cache ma przedziały 15 sekund, więc pierwsza klatka może pochodzić z wcześniejszego żądania w tym samym przedziale.

<a id="ograniczenia-klientow-pocztowych"></a>
## Ograniczenia klientów pocztowych

Nie można obiecać identycznego zachowania we wszystkich klientach ani świeżego odliczania przy każdym otwarciu. Apple opisuje automatyczne pobieranie zdalnej treści w tle przez Mail Privacy Protection, niezależnie od zaangażowania odbiorcy. Google dokumentuje serwowanie obrazów Gmaila przez bezpieczne proxy. Blokowanie obrazów, pobranie przed otwarciem oraz sposób obsługi animacji pozostają poza kontrolą tej wtyczki.

Nie traktuj licznika jako dowodu momentu otwarcia wiadomości ani elementu egzekwującego termin oferty. Umieść tekstową datę, przygotuj czytelną pierwszą klatkę i wykonaj testy w aplikacjach używanych przez odbiorców.

Źródła: [Apple — Mail Privacy Protection](https://www.apple.com/legal/privacy/data/en/mail-privacy-protection/), [Google — image URL proxy](https://knowledge.workspace.google.com/admin/gmail/advanced/set-up-an-image-url-proxy-allowlist).
