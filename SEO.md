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

Plik `resources/llms.txt` jest wersjonowaną kopią do opublikowania jako `/llms.txt` w katalogu głównym WordPressa. Statyczny plik w katalogu głównym jest obsługiwany przez serwer; sama aktualizacja wtyczki nie zastąpi go. Dla innej domeny trzeba poprawić odnośniki w pliku. Plik jest opcjonalnym przewodnikiem po źródłach; nie gwarantuje indeksacji ani cytowania i nie nadaje licencji do treści.

Nie dodajemy niestandardowych tagów `ai` ani instrukcji wymuszających cytowanie. Dostęp robotów wyszukiwarek zależy od rzeczywistego `/robots.txt`, konfiguracji serwera/CDN i dostępności publicznych treści.

Przed premierą zachowana jest blokada indeksowania. Przy włączonej ochronie dostępu nie są emitowane opisy ani JSON-LD treści prywatnych, również dla testerów po podaniu hasła. Podglądy, wyszukiwanie wewnętrzne, błędy 404 i strony chronione hasłem mają `noindex`. Moduł nie zmienia opcji WordPressa „Proś wyszukiwarki o nieindeksowanie tej witryny”.

Po opublikowaniu na `https://osadafabryczna.pl`:

1. Wyłączyć ochronę przed premierą i sprawdzić ustawienia indeksowania WordPressa.
2. Sprawdzić rzeczywiste odpowiedzi `robots.txt`, `llms.txt`, `wp-sitemap.xml`, canonicals i hreflang na docelowej domenie.
3. Sprawdzić reprezentatywne PL/EN w Schema Markup Validator oraz indeksowanie w Google Search Console i Bing Webmaster Tools. Typ budynku w schema.org nie gwarantuje rozszerzonego wyniku Google.
4. Zgłosić mapę witryny do narzędzi wyszukiwarek i monitorować indeksację oraz cytowania.

## Kontrola lokalna

`php tests/seo-check.php` uruchamia test integracyjny z lokalnym WordPressem. Wymaga rozszerzeń `mysqli`, `mbstring` oraz portu lokalnej bazy zgodnego z konfiguracją Local. Test korzysta z zapytań, filtrów i pamięci procesu, bez zapisywania opisów ani opcji w bazie.

Dokumentacja: https://developers.google.com/search/docs/appearance/snippet, https://developers.google.com/search/docs/appearance/ai-features, https://schema.org/LandmarksOrHistoricalBuildings.
