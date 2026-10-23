# SEO serwisu

Moduł `includes/seo.php` dodaje meta description, canonical, hreflang PL/EN, Open Graph i JSON-LD bez dodatkowej wtyczki SEO. Wyłącza własne metadane po wykryciu Yoast, Rank Math, AIOSEO, SEOPress lub The SEO Framework.

## Opisy

W edycji strony lub budynku znajduje się pole **SEO — opis w wyszukiwarce → Meta description**. Każde tłumaczenie ma własny opis. Puste pole oznacza opis automatyczny. Strony mapy i katalog mają teksty PL/EN; budynki korzystają kolejno z zajawki, ACF `short_description`, ACF `history`, treści i tytułu. Automatyczne opisy są skracane do 160 znaków z uwzględnieniem granic słów. Ręcznie wpisany tekst nie jest skracany.

## Schema.org

- `WebSite` i `WebPage`: witryna oraz bieżąca strona, język, URL, data aktualizacji.
- `LandmarksOrHistoricalBuildings`: budynek, opis, ilustracja, poprawne współrzędne i adres z widocznych faktów.
- `citation` na stronie budynku: bibliografia z istniejącej sekcji „Źródła”.
- `CollectionPage` i `ItemList`: wyłącznie budynki widoczne na bieżącej stronie katalogu.

Wydawca i autor nie są zgadywani. Konsultacja muzealna nie jest przedstawiana jako autorstwo lub własność serwisu. Dat historycznych z opisów nie interpretuje się automatycznie jako dat ISO.

## AI i publikacja

Od wersji 1.5.1 wtyczka sama obsługuje `/llms.txt`, korzystając z wersjonowanego pliku `resources/llms.txt`. Nie trzeba kopiować pliku do katalogu głównego ani zapisywać ponownie bezpośrednich odnośników. Adresy w treści są dopasowywane do adresu witryny WordPressa. Zwracany jest status 200 i typ `text/plain; charset=UTF-8`.

Jeżeli na serwerze istnieje już fizyczny `/llms.txt`, konfiguracja serwera może nadać mu pierwszeństwo przed WordPressem. Taki plik należy aktualizować osobno albo usunąć, aby korzystać z wersji wtyczki. Plik jest opcjonalnym przewodnikiem po źródłach; nie gwarantuje indeksacji ani cytowania i nie nadaje licencji do treści.

Nie dodajemy niestandardowych tagów `ai` ani instrukcji wymuszających cytowanie. Dostęp robotów wyszukiwarek zależy od rzeczywistego `/robots.txt`, konfiguracji serwera/CDN i dostępności publicznych treści.

Przed premierą zachowana jest blokada indeksowania. Przy włączonej ochronie dostępu nie są emitowane opisy ani JSON-LD treści prywatnych, również dla testerów po podaniu hasła. Podglądy, wyszukiwanie wewnętrzne, błędy 404 i strony chronione hasłem mają `noindex`. Moduł nie zmienia opcji WordPressa „Proś wyszukiwarki o nieindeksowanie tej witryny”.

Po opublikowaniu na `https://osadafabryczna.pl`:

1. Wyłączyć ochronę przed premierą i sprawdzić ustawienia indeksowania WordPressa.
2. Sprawdzić rzeczywiste odpowiedzi `robots.txt`, `llms.txt`, `wp-sitemap.xml`, canonicals i hreflang na docelowej domenie.
3. Sprawdzić reprezentatywne PL/EN w Schema Markup Validator oraz indeksowanie w Google Search Console i Bing Webmaster Tools. Typ budynku w schema.org nie gwarantuje rozszerzonego wyniku Google.
4. Zgłosić mapę witryny do narzędzi wyszukiwarek i monitorować indeksację oraz cytowania.

## Kontrola lokalna

Od wersji 1.5.2 moduł `includes/public-privacy.php` usuwa dostawcę `users` z natywnych sitemap WordPressa. Bezpośredni adres mapy użytkowników zwraca 404. Archiwa autorów, także `?author=ID`, zwracają 404 przed przekierowaniem kanonicznym. Anonimowe GET/HEAD do `/wp/v2/users` oraz `/wp/v2/users/ID` zwracają 401; po uwierzytelnieniu nadal obowiązują standardowe uprawnienia WordPressa. Publiczne API budynków pozostaje dostępne. Opcjonalne `author_name` i `author_url` są usuwane z odpowiedzi oEmbed.

Natywne sitemapy są wysyłane bez instrukcji XSLT. W przeglądarce jest widoczny surowy XML; adresy i struktura sitemap dla wyszukiwarek pozostają takie same, z wyjątkiem usunięcia mapy autorów. Nie trzeba instalować rozszerzeń przeglądarki ani zmieniać plików WordPressa. Po wdrożeniu należy wyczyścić istniejące kopie sitemap/API/archiwów autorów w cache hostingu lub CDN, jeśli występują.

To ograniczenie zbędnej ekspozycji kont, nie zastępstwo dla MFA, silnych haseł, aktualizacji, ograniczania prób logowania i kopii zapasowych. Nie zmieniamy loginów, ról, publicznych podpisów redakcyjnych ani konfiguracji uwierzytelniania. Wcześniej opublikowane nazwy mogą nadal występować w indeksach wyszukiwarek lub cudzych kopiach. Zmiany dotyczą natywnych sitemap WordPressa, nie niezależnych sitemap generowanych przez inne wtyczki SEO.

`php tests/seo-check.php` uruchamia test integracyjny z lokalnym WordPressem. Wymaga rozszerzeń `mysqli`, `mbstring` oraz portu lokalnej bazy zgodnego z konfiguracją Local. Test korzysta z zapytań, filtrów i pamięci procesu, bez zapisywania opisów ani opcji w bazie.

`node tests/llms-http-check.cjs` sprawdza rzeczywiste odpowiedzi GET/HEAD i routing WordPressa dla `/llms.txt` na izolowanym serwerze PHP, bez zmiany reguł w bazie. `node tests/live-seo-check.cjs` odczytuje publiczne strony produkcyjne z mapy witryny i sprawdza metadane, schema, wzajemne hreflang, robots.txt, llms.txt i API budynków.

Dokumentacja: https://developers.google.com/search/docs/appearance/snippet, https://developers.google.com/search/docs/appearance/ai-features, https://schema.org/LandmarksOrHistoricalBuildings.
