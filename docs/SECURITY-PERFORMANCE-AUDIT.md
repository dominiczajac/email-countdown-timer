# Audyt bezpieczeństwa i wydajności — 12.1.1

Data: 2026-09-28. Zakres: dostarczony kod Easy Countdown v12.1 oraz przygotowanie publikacji w `dominiczajac/email-countdown-timer`. Raport rozróżnia obserwacje kodu, wykonane testy i ograniczenia weryfikacji.

## Podstawa i zakres zmian

Oryginał użytkownika: `Wklejony tekst(2).txt`, SHA-256 `7b5203cc43181482a5f33d93109aaf169c25b8e559fe8a34b1121f185815b6e2`.

Bazowy commit repozytorium: `b258d4abcd5f7299d4191fd0ea507863548597ea` (README, .gitignore i GPLv3; bez kodu wtyczki). Commit implementacji i testów: [`2b88b16767ee1e4f37974c45b238824bd61b3b30`](https://github.com/dominiczajac/email-countdown-timer/commit/2b88b16767ee1e4f37974c45b238824bd61b3b30).

Wcześniejszy roboczy ZIP nie był bazą implementacji: zmieniał język panelu, a pole ID z `disabled` nie było przesyłane podczas edycji. Przywrócono zachowanie oryginału (`readonly`). Zachowano istniejący plik LICENSE, zamiast zastępować GPLv3 poprzednio proponowaną GPLv2.

## Ustalenia i poprawki

| Obserwacja w oryginale | Wpływ | Zmiana |
|---|---|---|
| `edit` trafiał do nagłówka bez escaping | Ryzyko reflected XSS w kontekście panelu administratora | Normalizacja ID i `esc_html()` na wyjściu |
| Surowe wartości POST i brak kontroli typów | Niepoprawne dane, ostrzeżenia/wyjątki, błędna konfiguracja | `wp_unslash()`, odrzucanie tablic, walidacja dat, kolorów i zakresów |
| Nazwa fontu była doklejana do ścieżki | Wyjście poza przewidziany katalog przy nieprawidłowej konfiguracji | Nazwa bazowa, allowlista rozszerzeń, realpath i kontrola katalogu docelowego |
| Brak limitów rozmiaru i powierzchni obrazu | Ryzyko nadmiernej alokacji i obciążenia publicznego endpointu | Budżet przed `imagecreatetruecolor()` i ograniczenia pól |
| Nieistniejący timer tworzył licznik `+1 hour` | Niepotrzebna praca dla dowolnych ID i fałszywy wynik | Tani 404 z przezroczystym PNG |
| Błędna data otrzymywała zastępczy termin | Wprowadzające w błąd odliczanie | Odrzucenie nieprawidłowej konfiguracji |
| Brak `lc` w sygnaturze cache | Kolor etykiet mógł pozostać stary | Sygnatura pełnej konfiguracji i unieważnienie przy zapisie |
| Timestamp bucket był częścią nazwy transientu | Kolejne klucze i wpisy bazy w czasie | Jeden slot licznik/format z numerem bucketu w wartości |
| Wielokrotne obliczanie tych samych metryk | Zbędne wywołania FreeType w każdej klatce | Memoizacja na czas renderowania |
| Klatka zerowa rysowana również jako master | Dodatkowe rysowanie | Ponowne użycie gotowej pierwszej klatki |
| Osobny inline listener dla każdego shortcode | Zbędne handlery i interpolacja JS | Jeden zewnętrzny skrypt, dane w escapowanych atrybutach |
| Brak Imagick dawał pusty string | Niedziałający obraz | Statyczny GIF bez Imagick |
| Bezwarunkowe zamykanie buforów i limit czasu 120 s | Ryzyko zapętlenia i wydłużania kosztownych żądań | Kontrola usuwalności bufora; brak podnoszenia limitu hosta |

Kontrola `manage_options` i nonce istniała już w oryginalnym zapisie; została zachowana, nie należy przedstawiać jej jako nowo odkrytego braku autoryzacji. Nie stwierdzono w ramach tego przeglądu podstaw do nazywania ścieżki fontu nieautoryzowanym zdalnym wykonaniem kodu.

## Zachowanie zachowane i świadome granice kompatybilności

Bez zmian pozostały: nazwa opcji, admin slug, shortcode, adresy obrazów, polskie domyślne etykiety, strefa Europe/Warsaw, geometria, kolory, minimalna szerokość, ukrywanie dni oraz 60 klatek po jednej sekundzie. Nie dodano odpytywania co minutę ani limitu odbiorców.

Zmiany dotyczą zachowania błędnych lub nadmiernych wejść: nie ma fikcyjnych timerów, tablice i nieprawidłowe daty są odrzucane, obowiązuje limit 400 000 pikseli i zakresy opisane w [konfiguracji](wiki/Configuration.md). Brak Imagick daje obraz statyczny. Paczka deklaruje PHP minimum 8.1 i WordPress minimum 6.4; nie twierdzimy, że przetestowano pełną instalację każdej wersji WordPressa.

## Dowody weryfikacji

[Przebieg CI 36394611197](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36394611197) dla commita `2b88b16`: wszystkie sześć zadań ukończone z wynikiem success — PHP 8.1, 8.2, 8.3, 8.4, 8.5 z GD/Imagick oraz PHP 8.4 z GD bez Imagick. Zweryfikowano statusy zadań i odczytano logi obu wariantów PHP 8.4.

| Przebieg | Wynik potwierdzony w logu |
|---|---|
| PHP 8.4.26 + GD/Imagick | 258 asercji PASS |
| PHP 8.4.26 + GD, bez Imagick | 137 asercji PASS |
| Lokalny PHP 8.4.23, bez GD | 28 asercji PASS; testy obrazów jawnie pominięte |

Zestaw obejmuje 40 porównań pikseli bitmapowych obrazów z oryginalną geometrią, 60 porównań z systemową czcionką TTF przy przejściu przez próg doby, liczbę i opóźnienia 60 klatek GIF, porównania odtworzonych klatek, obsługę cache oraz walidację i kontroler. Test metryk sprawdza mniej niż 100 unikatowych pomiarów zamiast wielokrotnego powtarzania ich w 60 klatkach. Testowy wzorzec nie obsługuje HTTP i jest zablokowany poza CLI.

Porównano lokalne hashe wszystkich dziewięciu plików implementacji/testów/CI z blobami opublikowanego drzewa `08ba7858b51cfef2c8d76dccd9eed52d6073dcf5`; wszystkie były zgodne. Późniejszy commit dokumentacji aktualizuje również przypięcie checkout po ostrzeżeniu CI o starym runtime Node. Status najnowszej rewizji należy sprawdzać osobno w Actions.

## Czego nie potwierdzono

Nie wykonano pełnych testów integracyjnych z bazą i rzeczywistym WordPressem, testu wszystkich klientów pocztowych, benchmarku obciążeniowego ani pełnego PHPCS/WPCS. Asercje kontrolera korzystają z atrap API WordPressa. Testy nie stanowią certyfikacji bezpieczeństwa ani gwarancji zerowych regresji.

Nie ma blokady stampede ani ochrony przed masowym ruchem do poprawnych ID. Równoległe cache misses mogą nadal renderować wiele GIF-ów. Nie podajemy procentowego przyspieszenia ani deklarowanej przepustowości bez pomiaru; potwierdzono redukcję zbędnych operacji oraz zachowanie wyniku w objętych testem przypadkach.

Własne fonty, nietypowe etykiety, zasoby PHP/ImageMagick i zachowanie klienta pocztowego wymagają testu stagingowego przed kampanią. Znany model GIF-a i cache nie pozwala gwarantować idealnie aktualnego odliczania przy każdym otwarciu e-maila.

## Podstawa zaleceń zewnętrznych

[WordPress Security APIs](https://developer.wordpress.org/apis/security/) — walidacja i escaping; [GitHub secure use](https://docs.github.com/en/actions/reference/security/secure-use) — minimalne uprawnienia i SHA akcji; [PHP imagettftext](https://www.php.net/manual/en/function.imagettftext.php) — FreeType i rozmiar w punktach. Te źródła opisują zasady platform, a nie wynik audytu tej wtyczki.
