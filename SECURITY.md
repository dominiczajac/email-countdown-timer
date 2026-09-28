# Zgłaszanie problemów bezpieczeństwa

Nie publikuj działających exploitów, danych dostępowych ani danych użytkowników w publicznym Issue. Jeżeli repozytorium udostępnia prywatne zgłoszenia w zakładce Security, użyj tej funkcji. Jeżeli jej nie ma, poproś właściciela o prywatny kanał kontaktu, podając publicznie wyłącznie ogólną informację, że chcesz zgłosić podatność. Ta instrukcja nie oznacza, że prywatne zgłaszanie zostało już włączone.

Prywatny raport powinien zawierać wersję/commit, minimalne kroki odtworzenia na testowych danych, wymagane uprawnienia, możliwy wpływ oraz wersje PHP, WordPress i rozszerzeń. Usuń sekrety i dane osobowe z logów. Nie testuj witryn innych osób bez ich zgody.

Wersja 12.1.1 zawiera poprawki walidacji, escapowania, obsługi fontów i limitów renderowania. Nie stanowi gwarancji braku podatności. Publiczny endpoint obrazów pozostaje podatny na przeciążenie przy dostatecznie dużym ruchu; nie ma blokady stampede ani ochrony WAF wbudowanej we wtyczkę. Raport: `docs/SECURITY-PERFORMANCE-AUDIT.md`.

Nie zadeklarowano SLA ani okresu wsparcia starszych wersji. Przed wdrożeniem sprawdź bieżące poprawki w repo i wykonaj testy na własnym środowisku.
