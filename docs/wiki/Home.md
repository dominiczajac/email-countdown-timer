# Dokumentacja Email Countdown Timer

Dokumentacja dotyczy wersji **12.1.1**, opartej na dostarczonym rendererze Easy Countdown v12.1. Wtyczka tworzy obrazy odliczające do daty zapisanej przez administratora WordPressa. Nie jest systemem wysyłki e-maili ani mechanizmem egzekwowania zakończenia promocji.

| Zadanie | Strona |
|---|---|
| Uruchomienie i zachowanie istniejących liczników | [Instalacja](Installation.md) |
| Pola panelu, fonty, daty i limity | [Konfiguracja](Configuration.md) |
| Shortcode, HTML e-mail, adresy obrazów | [Osadzanie](Embedding.md) |
| Cache, obciążenie, uprawnienia i prywatność | [Wydajność i bezpieczeństwo](Performance-and-Security.md) |
| Pusty obraz, brak animacji, stara data | [Diagnostyka](Troubleshooting.md) |
| Testy, pakowanie i aktualizacja Wiki | [Rozwój](Development.md) |

Najważniejsze ograniczenie: GIF ma 60 klatek, a klient pocztowy może pobrać go przed otwarciem wiadomości. Zawsze umieszczaj datę końca także jako zwykły tekst.

Źródłem dokumentacji są pliki `docs/wiki/` w głównym repozytorium. Publikacja w natywnej Wiki jest oddzielną operacją Git; sama obecność tych plików nie potwierdza jej wykonania.
