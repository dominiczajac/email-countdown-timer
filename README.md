# Email Countdown Timer

Wtyczka WordPress generująca obrazy odliczające do ustalonej daty: animowany GIF do wiadomości e-mail i stron WWW oraz statyczny PNG/WebP. Obrazy powstają na własnym serwerze WordPress; wtyczka nie wysyła wiadomości i nie wymaga zewnętrznego SaaS ani klucza API.

**Wersja:** 12.1.1 · **Licencja:** GPL-3.0-only · **Panel:** Easy Countdown

## Wymagania

| Element | Wymaganie |
|---|---|
| WordPress | Nagłówek deklaruje minimum 6.4; przed wdrożeniem potrzebny test na własnej instalacji |
| PHP | Minimum 8.1; CI obejmuje 8.1–8.5 |
| GD | Wymagane, z obsługą PNG/GIF; WebP zależy od kompilacji GD |
| Imagick | Wymagane do animacji. Bez niego odpowiedź GIF jest statyczna |
| FreeType | Wymagane do własnych czcionek TTF/OTF |
| Dostęp sieciowy | Publicznie dostępny adres HTTPS WordPressa dla obrazów w e-mailach |

Nie ma zależności Composer/npm potrzebnych do uruchomienia. Pliki czcionek nie są dołączone.

## Instalacja i pierwszy licznik

1. Umieść pliki w `wp-content/plugins/email-countdown-timer/`, tak aby plik `email-countdown-timer.php` znajdował się bezpośrednio w tym katalogu. Aktywuj **Email Countdown Timer** w panelu WordPressa.
2. W menu **Easy Countdown** utwórz licznik, np. `promocja`: ustaw datę końca, strefę czasową, kolory i etykiety. Zapisz formularz.
3. Skopiuj shortcode albo link do mailingu z tabeli liczników.

**Aktualizacja z wcześniejszego kodu:** najpierw wykonaj kopię bazy i plików, a następnie wyłącz poprzednią wtyczkę lub snippet. Nie uruchamiaj obu implementacji jednocześnie: zachowana została nazwa klasy `ECD_Plugin_Colons_Fix`. Dane pozostają w opcji `easy_countdown_timers`; nie ma migracji ani automatycznego kasowania liczników przy dezaktywacji. Szczegóły: [instalacja i aktualizacja](docs/wiki/Installation.md).

## Osadzanie

Na stronie WordPress użyj bloku Shortcode:

```text
[ecd_timer id="promocja"]
```

W wiadomości HTML użyj adresu skopiowanego z panelu. Przykład — zastąp domenę i ID:

```html
<img src="https://example.com/?ecd_action=render&amp;ecd=promocja&amp;mode=email"
     alt="Czas do zakończenia promocji"
     style="display:block;max-width:100%;height:auto;border:0;">
```

Nie wklejaj shortcode do e-maila i nie załączaj pobranego GIF-a jako pliku, jeżeli licznik ma być wyliczany podczas pobierania obrazu z serwera. [Szczegóły osadzania](docs/wiki/Embedding.md).

## Zachowane funkcje i istotne ograniczenia

Konfiguracja obejmuje datę i strefę czasową, trzy kolory, cztery etykiety, czcionkę, rozmiary tekstu, szerokość oraz ukrywanie dni poniżej 24 godzin. Zachowano polskie wartości domyślne, shortcode, adresy obrazów i układ renderera v12.1.

GIF zawiera **60 klatek, po jednej sekundzie**. To skończona sekwencja obrazu, nie połączenie na żywo z serwerem. Shortcode pobiera nowy obraz po powrocie do widocznej karty; nie dodaje cyklicznego odpytywania co minutę. Wbudowany cache współdzieli obraz w 15-sekundowych przedziałach, więc pierwsza klatka nie musi odpowiadać dokładnie chwili pobrania.

**E-mail nie gwarantuje odliczania od chwili otwarcia.** Klient może wcześniej pobrać, buforować lub zablokować obraz. Apple Mail Privacy Protection może pobierać treści w tle; Gmail używa proxy obrazów. Nagłówki serwera nie dają kontroli nad całym tym procesem. W wiadomości podaj również bezwzględną datę zakończenia tekstem. [Źródła i ograniczenia](docs/wiki/Embedding.md#ograniczenia-klientow-pocztowych).

Pole szerokości zachowuje dotychczasowe znaczenie: jest **minimalną szerokością obrazu**, nie skalowaniem do dokładnego wymiaru; `0` oznacza auto. Bez własnej czcionki używane są bitmapowe fonty GD o stałym rozmiarze. Nie zmienia się ich wielkość przez pola rozmiaru cyfr i etykiet. [Konfiguracja i limity](docs/wiki/Configuration.md).

## Dokumentacja

[Spis Wiki](docs/wiki/Home.md) · [Instalacja](docs/wiki/Installation.md) · [Konfiguracja](docs/wiki/Configuration.md) · [Osadzanie](docs/wiki/Embedding.md) · [Wydajność i bezpieczeństwo](docs/wiki/Performance-and-Security.md) · [Diagnostyka](docs/wiki/Troubleshooting.md) · [Rozwój i publikacja Wiki](docs/wiki/Development.md)

Strony Wiki są wersjonowane w `docs/wiki/`. Ten katalog **nie jest automatycznie zakładką Wiki GitHuba**. Do publikacji w oddzielnym repozytorium Wiki służy `scripts/publish-wiki.sh`, uruchamiany przez właściciela z lokalnym uwierzytelnieniem Git.

## Weryfikacja i rozwój

```sh
php tests/run.php
node --check assets/countdown.js
```

CI sprawdza składnię i testy na PHP 8.1–8.5 z GD/Imagick oraz osobno PHP 8.4 bez Imagick. Testy obejmują walidację, zapisy z kontrolą uprawnień i nonce, cache, 60 klatek GIF oraz porównanie pikseli z oryginalnym rendererem. To testy z atrapami API WordPressa, **nie pełne testy integracyjne WordPressa**. Brak GD lokalnie jest jawnie zgłaszany jako pominięcie testów obrazów, a w CI jako błąd.

[Raport audytu](docs/SECURITY-PERFORMANCE-AUDIT.md) zawiera wyniki, zakres i ryzyka pozostałe. Nie deklarujemy zmierzonego procentowego przyspieszenia ani pełnej zgodności WPCS. Zasady zmian: [CONTRIBUTING.md](CONTRIBUTING.md); zgłoszenia bezpieczeństwa: [SECURITY.md](SECURITY.md).

## Licencja

Kod jest udostępniany na GNU GPL v3.0 (`GPL-3.0-only`). Zachowano plik [LICENSE](LICENSE) istniejący w repozytorium. Dodając własne czcionki, sprawdź ich odrębną licencję. [Historia zmian](CHANGELOG.md).
