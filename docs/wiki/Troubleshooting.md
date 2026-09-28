# Rozwiązywanie problemów

| Objaw | Co sprawdzić |
|---|---|
| Brak wtyczki na liście | Czy główny plik PHP jest bezpośrednio w katalogu wtyczki; czy PHP spełnia wymaganie 8.1 |
| Błąd ponownej deklaracji klasy | Wyłącz stary plugin/snippet przed uruchomieniem nowego |
| GIF jest statyczny | Sprawdź dostępność Imagick w PHP obsługującym WWW, nie tylko w CLI |
| Przezroczysty punkt / pusty obraz | Sprawdź status HTTP: 404 nieznane ID, 422 błędne zapisane dane, 503 brak GD lub błąd renderera/budżetu zasobów |
| 405 | Endpoint przyjmuje wyłącznie GET i HEAD |
| Font nie pojawia się na liście | Wymagane rozszerzenie TTF/OTF, czytelny plik w `fonts/`, bez dowiązania poza katalog |
| Rozmiar fontu nic nie zmienia | Bez TTF/OTF + FreeType używany jest font bitmapowy o stałej wielkości |
| Nieprawidłowe polskie znaki | Użyj lokalnego fontu zawierającego potrzebne glify; bitmapowy fallback nie zapewnia pełnego Unicode |
| Obraz jest szerszy niż wartość w panelu | Zachowano minimalną szerokość, a nie skalowanie; długie etykiety mogą poszerzać obraz |
| Licznik pokazuje wcześniejszy stan | Sprawdź przedział cache 15 s, cache hostingu/CDN i zachowanie klienta pocztowego |
| Zapis formularza odrzucony | Odśwież nonce, sprawdź uprawnienia, datę i strefę oraz zakresy pól |
| Czas nie zgadza się z oczekiwanym | Sprawdź strefę licznika, zegar serwera, termin kampanii i zmianę czasu lato/zima |

Publiczny błąd celowo nie ujawnia wyjątków ani ścieżek serwera. `503` może mieć kilka przyczyn; sam kod statusu nie identyfikuje jednej. Sprawdź konfigurację rozszerzeń, limity i bezpieczne logi serwera na środowisku testowym. Wtyczka nie tworzy własnego szczegółowego dziennika wyjątków.

Do zgłoszenia błędu dołącz wersję wtyczki, WordPress/PHP, dostępność GD/Imagick, status HTTP, kroki odtworzenia i niesensytywną konfigurację testową. Nie wklejaj haseł, kluczy, cookies administratora, bazy danych ani list odbiorców. Dla podejrzeń podatności postępuj według `SECURITY.md` w głównym repozytorium.
