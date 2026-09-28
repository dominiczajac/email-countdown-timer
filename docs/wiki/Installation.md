# Instalacja i aktualizacja

## Nowa instalacja

Przygotuj WordPress, PHP co najmniej 8.1 i GD z obsługą PNG/GIF. Do animacji potrzebne jest dodatkowo rozszerzenie PHP Imagick oraz działający kodek GIF w ImageMagick. Czcionki TTF/OTF wymagają FreeType w GD. Nagłówek wtyczki deklaruje WordPress 6.4 jako minimum; testy CI nie zastępują sprawdzenia na rzeczywistej instalacji.

Pobierz kod, rozpakuj go do katalogu `email-countdown-timer` i umieść w `wp-content/plugins/`. W tym katalogu muszą znaleźć się: `email-countdown-timer.php`, `includes/` oraz `assets/`. Zachowaj również `LICENSE` i `readme.txt`. Nie twórz dodatkowego, zagnieżdżonego katalogu z drugą kopią nazwy wtyczki.

Aktywuj wtyczkę w WordPressie. Otwórz **Easy Countdown**, utwórz testowy licznik i sprawdź jego link w oknie bez zalogowanej sesji. Obraz w e-mailu musi być dostępny publicznie; Basic Auth na środowisku testowym lub ochrona całej witryny może zablokować jego pobieranie.

## Przejście z v12.1 lub snippetu

Wykonaj kopię bazy i starego kodu oraz osobną kopię własnych fontów. Wyłącz poprzedni plugin/snippet przed aktywacją tej wersji. Obie implementacje używają klasy `ECD_Plugin_Colons_Fix`, więc jednoczesne wczytanie spowoduje konflikt.

Zachowano opcję `easy_countdown_timers`, shortcode `ecd_timer`, menu `ecd-timers` oraz parametry `ecd_action`, `ecd`, `mode`. Prawidłowe zapisane daty i konfiguracje są odczytywane bez migracji. Dane uszkodzone lub przekraczające nowe limity bezpieczeństwa są odrzucane zamiast powodować kosztowne renderowanie. Popraw je w panelu; patrz [konfiguracja](Configuration.md).

Własne pliki TTF/OTF skopiuj do `fonts/` obok głównego pliku PHP. Nie ma automatycznego pobierania fontów ani instalatora ich licencji. Zachowaj kopię: wymiana całego katalogu wtyczki może je usunąć.

## Aktualizacje i odinstalowanie

Repozytorium nie zawiera automatycznego aktualizatora z GitHuba. Kolejne wersje wdrażaj świadomie po sprawdzeniu zmian i wykonaniu kopii. Dezaktywacja ani usunięcie plików nie kasują opcji z licznikami. Trwałe usunięcie konkretnego licznika wykonuje jego formularz w panelu.

Przed uruchomieniem kampanii sprawdź zapis/edycję/usuwanie, wszystkie używane fonty, próg 24 godzin, datę po zakończeniu i odbiór w docelowych aplikacjach pocztowych. Cofnięcie wdrożenia nie powinno oznaczać powrotu do znanej podatnej wersji na publicznym serwerze.
