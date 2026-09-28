# Konfiguracja licznika

Panel **Easy Countdown** jest dostępny dla użytkowników z uprawnieniem `manage_options`. Zapis i usuwanie wymagają również prawidłowego nonce formularza.

| Pole | Znaczenie i ograniczenia |
|---|---|
| ID Licznika | Identyfikator w shortcode i URL; normalizowany przez `sanitize_title`, maksymalnie 200 bajtów wejścia. Najprościej używać małych liter ASCII, cyfr i myślników. Przy edycji pole pozostaje `readonly`, nie `disabled` |
| Data Końca | Konkretna data i godzina, z opcjonalnymi sekundami; format zapisu `YYYY-MM-DDTHH:MM[:SS]` lub odpowiednik ze spacją |
| Strefa Czasowa | Identyfikator obsługiwany przez PHP, np. `Europe/Warsaw`; puste pole przyjmuje tę wartość |
| Kolory | Tło `bg`, cyfry `dc`, etykiety `lc`; hex `#RGB` lub `#RRGGBB` |
| Etykiety | Domyślnie `Dni`, `Godz`, `Min`, `Sek`; każda do 256 bajtów UTF-8, niekoniecznie 256 znaków |
| Czcionka | Lokalny plik TTF/OTF z `fonts/`; ścieżki poza katalogiem są niedozwolone |
| Rozmiar Cyfr | 1–200; domyślnie 40; wartość przekazywana do FreeType |
| Rozmiar Etykiet | 1–100; domyślnie 12; wartość przekazywana do FreeType |
| Szerokość | 0–4000 px; `0` oznacza auto, liczba dodatnia to minimum szerokości, bez skalowania zawartości |
| Ukryj dni | Pomija blok dni, gdy pozostało mniej niż 24 godziny |

Etykiety pól rozmiaru w odziedziczonym panelu używają określenia „px”, ale funkcje GD/FreeType interpretują argument rozmiaru w punktach. Zachowano przekazywanie tej samej wartości jak w v12.1, bez zmiany wyglądu. Fonty bitmapowe GD mają stały rozmiar i te pola ich nie skalują.

Oprócz limitów pól obowiązuje budżet całego obrazu: najwyżej 4000 × 1000 wymiarów granicznych i **400 000 pikseli powierzchni**. Nie każda kombinacja dozwolonych rozmiarów i długich etykiet zmieści się w budżecie. Dla 60 klatek limit sumaryczny wynosi 24 miliony pikseli; to ograniczenie wejścia, nie gwarancja konkretnego zużycia RAM.

## Daty i wygaśnięcie

Datę interpretuje PHP w wybranej strefie czasowej. Nie są przyjmowane względne terminy takie jak `+1 hour` ani nieistniejące daty kalendarzowe. Zachowano reguły PHP dotyczące zmiany czasu; godziny niejednoznaczne przy przejściu lato/zima należy przetestować przed kampanią.

Po upływie terminu pozostały czas jest ograniczany do zera. Widoczność bloku dni nadal zależy od ustawienia ukrywania. Licznik nie blokuje zakupów, formularzy ani kuponów — termin oferty musi być egzekwowany przez właściwy system sprzedaży.

## Fonty i polskie znaki

Pliki nie są dostarczane z wtyczką. Wybierz legalnie posiadany font i sprawdź obsługiwane znaki. Brak pliku lub FreeType powoduje użycie bitmapowego fontu GD, którego możliwości Unicode są ograniczone. Lista pomija dowiązania prowadzące poza katalog fontów. Nie ma publicznego uploadu czcionek.

Źródło jednostek rozmiaru i wymagań FreeType: [PHP — imagettftext](https://www.php.net/manual/en/function.imagettftext.php).
