<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LegalPageShell from '@/components/legal/LegalPageShell.vue';
import { contact, terms } from '@/routes/public/legal';

defineProps<{
    contactEmail: string;
}>();
</script>

<template>
    <LegalPageShell
        title="Polityka prywatności"
        lead="Jak MomJobs przetwarza Twoje dane osobowe – prostym językiem, zgodnie z RODO."
        updated-at="3 października 2026"
    >
        <h2>1. Administrator danych</h2>
        <p>
            Administratorem Twoich danych osobowych jest
            <strong>[Nazwa administratora]</strong>, [adres siedziby], [NIP /
            KRS] („Administrator”, „my”). We wszystkich sprawach dotyczących
            danych osobowych napisz do nas: <strong>{{ contactEmail }}</strong
            >.
        </p>

        <h2>2. Jakie dane przetwarzamy</h2>
        <ul>
            <li>
                <strong>Dane konta</strong> – imię i nazwisko, adres e-mail,
                hasło (przechowywane wyłącznie w postaci zaszyfrowanej), rola
                (kandydatka lub pracodawca).
            </li>
            <li>
                <strong>Dane profilu kandydatki</strong> – nagłówek zawodowy,
                doświadczenie, umiejętności, CV, zdjęcie (opcjonalnie), telefon
                (opcjonalnie), preferencje pracy (wymiar etatu, tryb pracy,
                lokalizacja) oraz data „Dostępna od”.
            </li>
            <li>
                <strong>Dane o ciąży i urlopie (opcjonalne)</strong> – termin
                porodu oraz daty urlopu macierzyńskiego i rodzicielskiego. Są to
                dane dotyczące zdrowia w rozumieniu art. 9 RODO.
            </li>
            <li>
                <strong>Dane pracodawcy</strong> – dane firmy, osoby kontaktowej
                i członków zespołu, ogłoszenia o pracę.
            </li>
            <li>
                <strong>Treści, które tworzysz</strong> – wiadomości na czatach,
                zaproszenia, opinie o firmach, pytania do asystenta AI.
            </li>
            <li>
                <strong>Dane techniczne</strong> – adres IP, informacje o
                przeglądarce i logi serwera potrzebne do bezpieczeństwa serwisu.
            </li>
        </ul>

        <h2>3. Cele i podstawy prawne</h2>
        <ul>
            <li>
                <strong
                    >Założenie i prowadzenie konta, dopasowywanie ofert,
                    zaproszenia i czaty</strong
                >
                – wykonanie umowy o świadczenie usług drogą elektroniczną (art.
                6 ust. 1 lit. b RODO).
            </li>
            <li>
                <strong
                    >Kalendarz powrotu na podstawie dat ciąży i urlopu</strong
                >
                – Twoja wyraźna zgoda (art. 9 ust. 2 lit. a w zw. z art. 6 ust.
                1 lit. a RODO). Podanie tych dat jest całkowicie dobrowolne –
                bez nich możesz korzystać ze wszystkich funkcji serwisu,
                wystarczy data „Dostępna od”. Zgodę możesz wycofać w każdej
                chwili, usuwając daty z profilu; wycofanie zgody nie wpływa na
                zgodność z prawem wcześniejszego przetwarzania.
            </li>
            <li>
                <strong>Analiza CV przez AI i asystent AI</strong> – wykonanie
                umowy (art. 6 ust. 1 lit. b RODO), na Twoje żądanie.
            </li>
            <li>
                <strong>Newsletter</strong> – Twoja zgoda (art. 6 ust. 1 lit. a
                RODO), potwierdzona linkiem w e-mailu.
            </li>
            <li>
                <strong
                    >Moderacja opinii, bezpieczeństwo, dochodzenie
                    roszczeń</strong
                >
                – nasz prawnie uzasadniony interes (art. 6 ust. 1 lit. f RODO).
            </li>
            <li>
                <strong>Obowiązki księgowe i podatkowe</strong> (dotyczy
                pracodawców) – obowiązek prawny (art. 6 ust. 1 lit. c RODO).
            </li>
        </ul>

        <h2>4. Co widzi pracodawca</h2>
        <h3>Przed przyjęciem zaproszenia</h3>
        <p>
            Pracodawca widzi Twój profil <strong>anonimowo</strong>: pseudonim,
            nagłówek zawodowy, lata doświadczenia, umiejętności, podsumowanie
            AI, preferowany wymiar etatu i tryb pracy oraz datę „Dostępna od”.
            Nie widzi imienia, nazwiska, e-maila, telefonu ani zdjęcia.
        </p>
        <h3>Po przyjęciu zaproszenia</h3>
        <p>
            Dopiero gdy przyjmiesz zaproszenie konkretnej firmy, ta firma
            zobaczy Twoje imię i nazwisko, e-mail, telefon i zdjęcie (jeśli je
            dodałaś) i otworzy się czat.
        </p>
        <h3>Czego pracodawca nie zobaczy nigdy</h3>
        <p>
            Terminu porodu ani dat urlopu macierzyńskiego i rodzicielskiego. Te
            daty są prywatne i służą wyłącznie Twojemu kalendarzowi powrotu i
            przypomnieniom. Pracodawcy nie zobaczą też, kto napisał konkretną
            opinię o firmie.
        </p>

        <h2>5. Przetwarzanie CV przez AI</h2>
        <p>
            Gdy przesyłasz CV lub korzystasz z asystenta AI, treść CV lub
            pytania jest przekazywana do modelu językowego
            <strong>Claude firmy Anthropic, PBC</strong> (USA), który działa
            jako nasz <strong>podmiot przetwarzający</strong> (art. 28 RODO). AI
            wyciąga z CV umiejętności i przygotowuje podsumowanie – to Ty
            decydujesz, które umiejętności potwierdzić, i możesz poprawić
            podsumowanie. Wyniki AI nie wywołują wobec Ciebie skutków prawnych
            ani nie decydują automatycznie o zatrudnieniu (art. 22 RODO).
        </p>
        <p>
            Przekazanie danych do USA odbywa się na podstawie standardowych
            klauzul umownych Komisji Europejskiej lub decyzji o adekwatności
            (EU-US Data Privacy Framework). Dane nie są wykorzystywane do
            trenowania modeli. Nie wpisuj do CV informacji o ciąży ani zdrowiu –
            nie są nam potrzebne.
        </p>

        <h2>6. Komu przekazujemy dane</h2>
        <ul>
            <li>pracodawcom – w zakresie opisanym w punkcie 4;</li>
            <li>
                dostawcom usług IT: hosting i baza danych, wysyłka e-maili,
                model AI (Anthropic) – na podstawie umów powierzenia;
            </li>
            <li>organom publicznym – tylko gdy wymaga tego prawo.</li>
        </ul>
        <p>Nie sprzedajemy Twoich danych i nie używamy ich do reklam.</p>

        <h2>7. Jak długo przechowujemy dane</h2>
        <ul>
            <li>dane konta i profilu – do czasu usunięcia konta;</li>
            <li>
                daty ciąży i urlopu – do czasu ich usunięcia przez Ciebie lub
                wycofania zgody, najpóźniej do usunięcia konta;
            </li>
            <li>
                wiadomości na czatach – do usunięcia konta jednej ze stron, a
                następnie do 30 dni w kopiach zapasowych;
            </li>
            <li>
                opinie o firmach – do czasu usunięcia (opinia pozostaje
                anonimowa);
            </li>
            <li>newsletter – do wypisania się;</li>
            <li>
                logi techniczne – do 90 dni; dane potrzebne do dochodzenia
                roszczeń – do upływu terminu przedawnienia.
            </li>
        </ul>

        <h2>8. Twoje prawa</h2>
        <p>Masz prawo do:</p>
        <ul>
            <li>dostępu do swoich danych i otrzymania ich kopii;</li>
            <li>sprostowania danych;</li>
            <li>usunięcia danych („prawo do bycia zapomnianą”);</li>
            <li>ograniczenia przetwarzania;</li>
            <li>przenoszenia danych;</li>
            <li>
                sprzeciwu wobec przetwarzania opartego na prawnie uzasadnionym
                interesie;
            </li>
            <li>
                wycofania zgody w dowolnym momencie (bez wpływu na wcześniejsze
                przetwarzanie);
            </li>
            <li>
                wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych
                (ul. Stawki 2, 00-193 Warszawa, uodo.gov.pl).
            </li>
        </ul>
        <p>
            Większość danych edytujesz i usuniesz samodzielnie w ustawieniach
            profilu, a konto usuniesz w ustawieniach konta. W pozostałych
            sprawach napisz na <strong>{{ contactEmail }}</strong> – odpowiemy w
            ciągu 30 dni.
        </p>

        <h2>9. Pliki cookies</h2>
        <p>
            Używamy wyłącznie <strong>niezbędnych plików cookies</strong>:
            cookie sesji (utrzymuje zalogowanie) i cookie zabezpieczające
            formularze (XSRF). Nie używamy cookies analitycznych ani
            reklamowych, dlatego nie pytamy o zgodę na cookies. Możesz je
            zablokować w przeglądarce, ale wtedy logowanie nie zadziała.
        </p>

        <h2>10. Kontakt i zmiany polityki</h2>
        <p>
            Pytania o prywatność kieruj na
            <strong>{{ contactEmail }}</strong> lub przez stronę
            <Link :href="contact()">Kontakt</Link>. O istotnych zmianach tej
            polityki poinformujemy e-mailem lub komunikatem w serwisie. Zasady
            korzystania z serwisu opisuje
            <Link :href="terms()">Regulamin</Link>.
        </p>
    </LegalPageShell>
</template>
