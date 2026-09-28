# Rozwój, testy i publikacja

## Struktura

`email-countdown-timer.php` ładuje konfigurację, renderer i kontroler. `includes/admin-view.php` zawiera odziedziczony polski panel. `assets/countdown.js` odświeża obrazy po powrocie do karty. `tests/legacy-frame.php` jest izolowanym, dostępnym tylko w CLI wzorcem geometrii z v12.1, a nie drugą aktywną wtyczką.

Nie zmieniaj nazw opcji, shortcode, parametrów URL ani sposobu liczenia i rozmieszczenia bloków bez jawnej decyzji o kompatybilności. Zmiana testowego wzorca, aby ukryć różnicę nowego renderera, nie jest naprawą regresji.

## Testy

```sh
php tests/run.php
node --check assets/countdown.js
```

Pełny przebieg wymaga GD, Imagick i jednej z systemowych czcionek obsługiwanych przez test. Test chwilowo kopiuje czcionkę i usuwa ją po porównaniu; żadnego fontu nie należy dodawać do repozytorium bez odpowiedniej licencji. Testy są uruchamiane z atrapami API WordPressa, nie z jego pełną instalacją. Nie potwierdzają zgodności każdej wersji WordPressa, wszystkich polityk ImageMagick ani klientów pocztowych.

CI wymaga GD, sprawdza oczekiwaną obecność/nieobecność Imagick i uruchamia matrycę PHP 8.1–8.5. Zmiany wchodzą przez gałąź roboczą, przegląd diff i zielone CI. Zalecany jest osobny test integracyjny na stagingu; pełne PHPCS/WPCS i benchmark produkcyjny nie są obecnie częścią tego zestawu.

## Paczka instalacyjna

W lokalnym klonie, po wybraniu sprawdzonego commita, można użyć:

```sh
git archive --format=zip --prefix=email-countdown-timer/ \
  -o ../email-countdown-timer.zip HEAD
```

`.gitattributes` wyłącza testy, skrypty i konfigurację CI z `git archive`. Pliki runtime, dokumentacja, `readme.txt` i licencja pozostają w paczce. Nie dodawaj `.git/`, plików środowiskowych ani prywatnych fontów do dystrybucji. Archiwum gałęzi roboczej nie jest automatycznie wydaniem stabilnym.

## Natywna Wiki GitHuba

GitHub przechowuje Wiki w oddzielnym repozytorium `email-countdown-timer.wiki.git`. Katalog `docs/wiki/` nie publikuje się sam do zakładki Wiki. Jeżeli Wiki nie była zainicjalizowana, właściciel musi najpierw utworzyć jej pierwszą stronę w interfejsie GitHuba.

Skrypt wymaga lokalnego Git, Python 3, dostępu do zapisu tej Wiki i skonfigurowanej tożsamości autora. Używa zwykłego uwierzytelnienia Git; nie zapisuj tokenu w repo ani nie wysyłaj go w rozmowie.

```sh
# Podgląd zmian, bez commita i bez push:
bash scripts/publish-wiki.sh

# Zapis do oddzielnego repozytorium Wiki:
bash scripts/publish-wiki.sh --publish
```

Skrypt klonuje Wiki do katalogu tymczasowego, kopiuje tylko strony dostarczone w `docs/wiki/`, dostosowuje lokalne linki Markdown i publikuje bez force-push. Nie usuwa innych stron. Zmiany już istniejących stron o tych samych nazwach zostaną zastąpione wersją z repozytorium — przed publikacją sprawdź wyświetlany diff. Przy równoległej zmianie zdalnej zwykły push może zostać odrzucony; wtedy ponów podgląd na świeżym klonie.

Źródło: [GitHub — adding or editing wiki pages](https://docs.github.com/en/communities/documenting-your-project-with-wikis/adding-or-editing-wiki-pages).
