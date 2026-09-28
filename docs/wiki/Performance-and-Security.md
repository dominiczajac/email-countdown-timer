# Wydajność i bezpieczeństwo

## Co jest cache'owane

Gotowy obraz jest zapisywany w WordPress Transients API jako Base64 wraz z sygnaturą konfiguracji i numerem przedziału czasu. Fizyczny klucz jest stały dla pary licznik/format. Zmiana 15-sekundowego przedziału nadpisuje ten sam slot zamiast tworzyć kolejne wpisy opcji. Termin ważności wpisu wynosi 60 sekund, ale do odpowiedzi wykorzystywany jest tylko obraz z bieżącego przedziału.

Sygnatura obejmuje całą konfigurację, również kolor etykiet, termin, format, ścieżkę i czas modyfikacji fontu, dostępność Imagick oraz wersję implementacji. Zapis lub usunięcie licznika unieważnia jego trzy sloty PNG/GIF/WebP. Parametry `_t` i inne przypadkowe parametry URL nie dzielą cache serwera.

W rendererze metryki tej samej czcionki i tekstu są obliczane raz na generowanie, a pierwsza klatka nie jest rysowana dwukrotnie. Na stronie wiele shortcode korzysta z jednego listenera. Nie przeprowadzono miarodajnego benchmarku RPS ani pomiaru procentowego przyspieszenia na serwerze produkcyjnym.

## Ochrona endpointu i panelu

Panel wymaga `manage_options`, a operacje zapisu nonce. Wejście formularza przechodzi `wp_unslash()` i walidację typów, dat, kolorów i zakresów. Wyjście HTML jest escapowane, URL budowane przez API WordPressa, a przekierowania są lokalne i bezpieczne. Publiczne renderowanie ponownie waliduje zapisane dane.

Fonty muszą pozostać w lokalnym katalogu. Wymiary obrazu są sprawdzane przed alokacją GD. Nieistniejące ID kończy się 404 bez tworzenia animacji. Wtyczka nie podnosi limitu czasu PHP do 120 sekund. Bufory są zamykane tylko wtedy, gdy są usuwalne.

Odpowiedź zawiera `X-Content-Type-Options: nosniff`, `Vary: Accept` oraz `Cache-Control: no-cache, no-store, must-revalidate`. Nie oznacza to gwarancji braku kopii u pośredników pocztowych; patrz [osadzanie](Embedding.md).

## Ryzyka pozostałe i hosting

Endpoint jest z założenia publiczny. Nadal może być celem nadużyć. **Nie ma blokady cache stampede**: równoległe żądania dla pustego cache mogą jednocześnie generować animację. Nie ma też limitu na IP. Liczby klatek nie zmniejszono, aby nie zmieniać działania poprawnych liczników.

Przed dużą kampanią wykonaj test równoległych pobrań, obserwuj pamięć i CPU procesów PHP-FPM oraz liczbę połączeń do bazy. Trwały object cache może odciążyć bazę; nie rozwiązuje samodzielnie stampede. Ochronę przed nadmiernym ruchem ustawiaj na poziomie hostingu/WAF, uwzględniając wspólne adresy proxy klientów pocztowych. Zbyt restrykcyjne limity per-IP mogą blokować prawidłowe pobrania.

Nie włączaj bezrefleksyjnie pełnego cache CDN dla tego endpointu: wydłużenie ważności obrazu zmienia dokładność odliczania. Limity ImageMagick/PHP i polityka kodeków są zależne od serwera. Budżet pikseli ogranicza rozmiar wejścia, ale nie zastępuje konfiguracji zasobów.

## Dane i prywatność

Wtyczka zapisuje konfiguracje liczników i cache obrazów. Nie dodaje analityki odbiorców, identyfikatorów śledzących ani zewnętrznych żądań API. Zwykłe logi HTTP hostingu/CDN mogą jednak rejestrować pobrania obrazów. Nie utożsamiaj tego opisu implementacji z audytem prawnym całego mailingu.

Podstawa zasad walidacji: [WordPress Security APIs](https://developer.wordpress.org/apis/security/). Szczegółowe wyniki znajdują się w `docs/SECURITY-PERFORMANCE-AUDIT.md` głównego repozytorium.
