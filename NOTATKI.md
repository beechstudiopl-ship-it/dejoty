# The Loop — wypożyczalnia sprzętu DJ

Strona statyczna, 5 plików HTML. Każdy plik jest samowystarczalny: CSS w `<style>`,
JS w `<script>`, obrazy i wideo wklejone jako base64. Brak zależności zewnętrznych,
brak build stepu — wgrywasz pliki na serwer i działa.

Wersja BETA, wdrożona testowo na `tst3.beechstudio.pl` (Cyberfolks, DirectAdmin).

---

## Pliki

| Plik | Rozmiar | Zawartość |
|---|---|---|
| `index.html` | ~2,8 MB | Strona główna (wideo hero w base64 — stąd rozmiar) |
| `oferta.html` | ~280 KB | 8 kategorii sprzętu |
| `produkt.html` | ~266 KB | Karta produktu (Xone:96) + koszyk + formularz zamówienia |
| `kontakt.html` | ~107 KB | Dane kontaktowe + prosty formularz zapytania |
| `o-nas.html` | ~322 KB | O firmie, mozaika zdjęć, atuty |

---

## Tożsamość wizualna

```css
--bg:       #101217   /* tło strony */
--surface:  #181A22   /* karty, inputy */
--accent-a: #4E21C2   /* gradient od */
--accent-b: #7A01B6   /* gradient do */
```

- Zaokrąglenia: **8px** (karty, zdjęcia), **100px** (przyciski, pigułki)
- Font: **PP Neue Machina** — NIE osadzony, przeglądarka używa fallbacku
- Breakpointy: **991px**, **767px**, **600px**
- Dane firmy: tel. 690 414 092, kontakt@theloop.pl

---

## Architektura nawigacji

**Desktop:** logo → O nas | Nasz sprzęt | Kontakt → wyszukiwarka → „Lista zamówienia"

**Mobile (szuflada):** wyszukiwarka → linki → „Lista zamówienia" → telefon/e-mail/social

Kolejność w szufladzie jest celowa — wyszukiwarka na górze, bo to najczęstsza
akcja; lista zamówienia pod linkami, bo to akcja wtórna.

Logo znika przy otwartym menu (`body.menu-open .logo{opacity:0}`).

**Wyszukiwarka:** wspólna baza 19 pozycji w `DB`, funkcja `attach(inputId, boxId)`
obsługuje desktop (`navSearch`/`searchResults`) i mobile (`mSearch`/`mSearchResults`).

**Koszyk:** działa tylko na `produkt.html` (tam jest pełna logika `items[]`,
`render()`, `add()`). Na pozostałych stronach licznik `#mCartCount` czyta
`sessionStorage.tlCart` przy starcie.

---

## Znane pułapki (wchodziłem w nie wielokrotnie)

### 1. Uszkodzenia bloków `@media`

Najczęstsza i najbardziej podstępna awaria w tym projekcie. Objaw: reguła CSS
istnieje w pliku, ale nie działa — albo działa tylko na jednej szerokości ekranu.

Przyczyny, które faktycznie wystąpiły:
- pozostałość po usuniętej animacji: `to{transform:translateX(-75%)}}` — nadmiarowy `}`
- brakujące otwarcie `@media (max-width:767px){`
- urwana reguła bez nawiasów: `.m-group.open .m-group.open` (bez `{...}`)

Skutek: parser CSS gubi się i wszystko poniżej trafia do złego kontekstu.
Maska na zdjęciu w sekcji „Jesteśmy firmą z Trójmiasta" była przez to
niewidoczna na desktopie — reguła siedziała wewnątrz `@media (max-width:767px)`.

**Szybka diagnoza:**

```bash
# bilans nawiasów — musi się zgadzać
python3 -c "h=open('index.html').read();print(h.count('{'), h.count('}'))"
```

```javascript
// w konsoli przeglądarki — w jakim @media siedzi reguła?
(() => {
  const find = sel => {
    let out = null;
    const walk = (rules, chain) => [...rules].forEach(r => {
      if (r.selectorText === sel) out = { media: chain, css: r.cssText.slice(0, 80) };
      if (r.cssRules) walk([...r.cssRules], chain.concat([r.conditionText || '?']));
    });
    walk([...document.styleSheets[0].cssRules], []);
    return out;
  };
  console.log(find('.info::after'));
})();
```

### 2. Pięć kopii tego samego CSS

Fundament stylów (zmienne, nawigacja, stopka, szuflada) jest **powielony
w każdym z pięciu plików**. Zmiana w jednym miejscu wymaga zmiany w pięciu.
To główny kandydat do refaktoryzacji — patrz sekcja niżej.

### 3. Testy w headless Chromium

Zrzuty ekranu z Playwright wychodzą prawie czarne, bo wideo base64 się nie
renderuje. To **nie jest** błąd strony. Weryfikuj przez DOM
(`getComputedStyle`, `getBoundingClientRect`), nie przez zrzuty.

Przy tym: `loading="lazy"` na obrazach sprawia, że nie ładują się, dopóki
nie przewiniesz — kolejne źródło fałszywych alarmów.

---

## Do zrobienia

### Refaktoryzacja (priorytet, jeśli projekt ma żyć dłużej)

- [ ] **Wyciągnąć wspólny CSS do `style.css`** — usuwa problem pięciu kopii
- [ ] **Wypakować wideo z `index.html`** — 2,7 MB base64 → osobny plik `.webm`/`.mp4`.
      Zmniejszy stronę główną ~10×, drastycznie poprawi czas ładowania
- [ ] **Wyciągnąć wspólny JS** (wyszukiwarka, szuflada, stopka) do `app.js`

### Funkcjonalne

- [ ] **Osadzić font PP Neue Machina** — `@font-face` + pliki od klienta
- [ ] **Podpiąć formularze do backendu** — teraz dane lecą w `console.log`.
      Formspree albo własny endpoint
- [ ] **Uzupełnić podstrony za `data-todo`** — 97 placeholderów: FAQ, regulamin,
      cennik, podkategorie sprzętu. Kliknięcie jest zablokowane skryptem
- [ ] **Zweryfikować godziny otwarcia** na `kontakt.html` — obecnie przykładowe
- [ ] **Ujednolicić deklarowany czas odpowiedzi** — w jednym miejscu „15 minut",
      w innym „24 h"

---

## Wdrożenie

Wszystkie pliki idą do jednego katalogu, bez podfolderów — linkują się względnie.

**Cyberfolks / DirectAdmin:**
`domains → beechstudio.pl → public_html → tst3` → „Wgraj pliki z dysku"

Sam `index.html` też zadziała samodzielnie (jest samowystarczalny), ale linki
w menu dadzą 404 bez pozostałych plików.

---

## Historia sesji

Poprzednia praca odbywała się w interfejsie webowym Claude, gdzie każda zmiana
wymagała cyklu: edycja → ZIP → pobranie → wgranie przez panel. Przeniesienie
do Claude Code eliminuje ten cykl — praca odbywa się bezpośrednio na plikach.

Wykonane w ostatniej sesji:
- naprawa maski i zaokrąglenia zdjęcia w sekcji „Jesteśmy firmą z Trójmiasta"
- statystyki (>350 eventów / 15 lat) poziomo na mobile
- podpięcie CTA: „Wyceń swój sprzęt" → kontakt, „Zobacz ofertę" → oferta
- przebudowa menu mobilnego (kolejność, rozmiary, ukrycie logo, wyniki wyszukiwania)
- wyłączenie animacji kulki w stopce (kod `@keyframes` został, ale `animation:none`)
- ujednolicenie odstępów sekcji na `o-nas.html` (było 40–120px, jest ~88px)
