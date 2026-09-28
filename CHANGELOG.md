# Historia zmian

## 12.1.1 — 2026-09-28

Pierwsza uporządkowana wersja repozytoryjna oparta na dostarczonym kodzie Easy Countdown v12.1.

### Bezpieczeństwo

Usunięto nieescapowany nagłówek edycji. Dodano walidację typów wejścia, dat, stref, kolorów i zakresów; `wp_unslash()` przed zapisem; bezpieczne URL i przekierowania; ograniczenie ścieżek fontów oraz budżetu pikseli. Zachowano istniejące kontrole capability i nonce. Publiczny endpoint odrzuca nieznane ID oraz nieprawidłowe konfiguracje, obsługuje tylko GET/HEAD i nie podnosi limitu czasu PHP.

### Wydajność i niezawodność

Stałe sloty cache zastąpiły osobne klucze dla każdego przedziału czasu. Sygnatura zawiera teraz również kolor etykiet. Dodano memoizację metryk fontu, wykorzystano już narysowaną pierwszą klatkę i zastąpiono wiele listenerów jednym skryptem. Brak Imagick daje statyczny GIF zamiast pustej odpowiedzi. Uporządkowano zwalnianie zasobów i obsługę buforów.

### Kompatybilność i dokumentacja

Zachowano polski panel, dane, shortcode, URL, sekwencję 60 klatek i geometrię renderera. Nowe bezpieczne zakresy oraz odrzucanie błędnych danych są opisane w Wiki. Dodano testy regresji, CI PHP 8.1–8.5, README, dokumentację Wiki i raport audytu. Minimalna deklarowana wersja PHP tej paczki to 8.1; WordPress w nagłówku: 6.4. Nie dodano automatycznego aktualizatora ani potwierdzenia publikacji na WordPress.org.

Zachowano istniejący w repozytorium plik GNU GPL v3; deklaracja kodu: `GPL-3.0-only`.
