=== Email Countdown Timer ===
Tags: countdown, email, timer, gif
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: trunk
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Obrazy odliczające do ustalonej daty, generowane na własnym serwerze WordPress.

== Description ==

Wersja kodu: 12.1.1. Panel Easy Countdown udostępnia datę, strefę czasową, kolory, etykiety, lokalne fonty i shortcode [ecd_timer id="promocja"].

GD jest wymagane. Imagick umożliwia animowany GIF z 60 klatkami; bez niego GIF jest statyczny. Klienci pocztowi mogą pobierać i buforować obrazy przed otwarciem wiadomości. Zawsze podawaj również tekstową datę zakończenia.

Pełna instrukcja znajduje się w README.md i docs/wiki/. Obecność tego pliku nie oznacza przyjęcia wtyczki do katalogu WordPress.org. Nie deklarujemy Tested up to bez testu pełnej instalacji WordPressa.

== Installation ==

1. Skopiuj wtyczkę do wp-content/plugins/email-countdown-timer/.
2. Przy aktualizacji wyłącz wcześniejszy plugin lub snippet tej implementacji.
3. Aktywuj Email Countdown Timer, otwórz Easy Countdown i utwórz licznik.
4. Skopiuj shortcode albo URL obrazu do własnego szablonu e-mail.

== Changelog ==

= 12.1.1 =
Walidacja i escaping, ograniczenie fontów i rozmiaru obrazu, naprawa kluczy cache, memoizacja metryk, testy regresji i dokumentacja. Zachowano dane oraz interfejsy v12.1; błędne lub nadmiernie duże konfiguracje są odrzucane.
