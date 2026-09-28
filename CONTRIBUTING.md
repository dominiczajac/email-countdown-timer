# Wprowadzanie zmian

Pracuj na osobnej gałęzi i opisuj problem, zakres oraz wpływ na kompatybilność. Do poprawki dodaj test odtwarzający błąd, uruchom `php tests/run.php` i `node --check assets/countdown.js`, a następnie sprawdź wszystkie zadania CI dla bieżącego commita.

Bez uzgodnienia nie zmieniaj opcji `easy_countdown_timers`, shortcode `ecd_timer`, adresów obrazów, polskich wartości domyślnych, układu obrazu ani liczby/czasu klatek. Utrzymuj testowy renderer v12.1 jako wzorzec, nie dopasowuj go do nowej implementacji. Zmiany zachowania błędnych wejść i limitów bezpieczeństwa opisuj w changelogu.

Waliduj dane przed użyciem, escapuj na wyjściu i zachowuj kontrolę capability oraz nonce. Nie dodawaj sekretów, danych produkcyjnych, plików fontów ani nowych zewnętrznych usług bez analizy uprawnień i licencji. Akcje CI przypinaj do pełnych SHA i pozostawiaj minimalne uprawnienia tokenu.

README jest punktem startowym dla administratora, `docs/wiki/` zawiera instrukcje, a raport audytu zapisuje dowody i ograniczenia testów. Aktualizuj dokumentację wraz ze zmianą kodu. Testy izolowane nie zastępują stagingu z rzeczywistym WordPressem. Zgłoszenia podatności opisuje `SECURITY.md`.
