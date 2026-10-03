<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LegalPageShell from '@/components/legal/LegalPageShell.vue';
import { contact, privacy } from '@/routes/public/legal';

defineProps<{
    contactEmail: string;
}>();
</script>

<template>
    <LegalPageShell
        title="Regulamin serwisu"
        lead="Zasady korzystania z mumjobs dla kandydatek i pracodawców: konto, zaproszenia, opinie o firmach i treści generowane przez AI."
        updated-at="3 października 2026"
    >
        <h2>1. Postanowienia ogólne</h2>
        <ol>
            <li>
                Regulamin określa zasady korzystania z serwisu mumjobs
                („Serwis”), prowadzonego przez
                <strong>[Nazwa administratora]</strong>, [adres siedziby], [NIP
                / KRS] („Usługodawca”).
            </li>
            <li>
                Regulamin jest regulaminem w rozumieniu ustawy o świadczeniu
                usług drogą elektroniczną. Zakładając konto, akceptujesz
                Regulamin i
                <Link :href="privacy()">Politykę prywatności</Link>.
            </li>
            <li>Korzystanie z Serwisu jest bezpłatne dla kandydatek.</li>
        </ol>

        <h2>2. Usługi</h2>
        <ul>
            <li>
                <strong>Dla kandydatek</strong> – anonimowy profil zawodowy,
                kalendarz powrotu do pracy, dopasowane oferty, zaproszenia od
                firm, czaty, job sharing, analiza CV i asystent AI, blog.
            </li>
            <li>
                <strong>Dla pracodawców</strong> – profil firmy, publikacja
                ogłoszeń, przeglądanie anonimowych profili kandydatek, wysyłanie
                zaproszeń i czaty.
            </li>
            <li>
                <strong>Dla wszystkich</strong> – przeglądanie ofert, stron
                firm, opinii i artykułów bez zakładania konta.
            </li>
        </ul>

        <h2>3. Konto</h2>
        <ol>
            <li>
                Konto może założyć osoba pełnoletnia. Podajesz prawdziwe dane i
                dbasz o poufność hasła.
            </li>
            <li>
                Konto pracodawcy może założyć osoba upoważniona do
                reprezentowania firmy. Usługodawca może weryfikować firmy i
                oznaczać zweryfikowane profile.
            </li>
            <li>
                Konto możesz usunąć w każdej chwili w ustawieniach. Umowa o
                świadczenie usług zostaje wtedy rozwiązana.
            </li>
        </ol>

        <h2>4. Zasady dla pracodawców</h2>
        <ol>
            <li>
                Zakazana jest dyskryminacja ze względu na ciążę, macierzyństwo,
                rodzicielstwo, płeć lub stan zdrowia (art. 18<sup>3a</sup> i
                nast. Kodeksu pracy).
            </li>
            <li>
                Nie wolno pytać kandydatek o ciążę, plany rodzinne ani terminy
                urlopów – ani na czacie, ani podczas rekrutacji.
            </li>
            <li>
                Dane kandydatki ujawnione po przyjęciu zaproszenia można
                wykorzystać wyłącznie w danej rekrutacji. Pracodawca staje się
                wtedy odrębnym administratorem tych danych.
            </li>
            <li>
                Ogłoszenia muszą dotyczyć prawdziwych ofert pracy i zawierać
                rzetelne informacje, w tym o wynagrodzeniu, jeśli je podano.
            </li>
        </ol>

        <h2>5. Opinie o firmach</h2>
        <ol>
            <li>
                Opinie publikujemy anonimowo po moderacji. Opinia musi opierać
                się na własnych doświadczeniach i nie może zawierać danych
                osobowych, wulgaryzmów ani treści bezprawnych.
            </li>
            <li>
                Usługodawca może odrzucić lub usunąć opinię naruszającą
                Regulamin. Firma może zgłosić opinię do ponownej weryfikacji.
            </li>
        </ol>

        <h2>6. Treści generowane przez AI</h2>
        <p>
            Analiza CV, podsumowania i odpowiedzi asystenta AI są generowane
            automatycznie i mogą zawierać błędy. Nie stanowią porady prawnej. W
            sprawach dotyczących urlopów i uprawnień pracowniczych sprawdź
            podane źródła lub skonsultuj się ze specjalistą.
        </p>

        <h2>7. Zakazane działania</h2>
        <ul>
            <li>
                podawanie fałszywych danych lub podszywanie się pod inne osoby;
            </li>
            <li>wysyłanie spamu i treści bezprawnych lub obraźliwych;</li>
            <li>
                pobieranie danych z Serwisu w sposób zautomatyzowany (scraping);
            </li>
            <li>działania zakłócające pracę Serwisu.</li>
        </ul>
        <p>
            W razie naruszenia Usługodawca może zablokować konto po uprzednim
            wezwaniu do zaprzestania naruszeń, a w poważnych przypadkach – od
            razu.
        </p>

        <h2>8. Reklamacje</h2>
        <p>
            Reklamacje dotyczące Serwisu zgłoś na
            <strong>{{ contactEmail }}</strong
            >. Odpowiemy w ciągu 14 dni. Konsumentka może też skorzystać z
            pozasądowych sposobów rozwiązywania sporów, np. pomocy miejskiego
            rzecznika konsumentów.
        </p>

        <h2>9. Wymagania techniczne</h2>
        <p>
            Do korzystania z Serwisu potrzebujesz urządzenia z dostępem do
            internetu, aktualnej przeglądarki z włączoną obsługą cookies
            niezbędnych oraz adresu e-mail.
        </p>

        <h2>10. Postanowienia końcowe</h2>
        <ol>
            <li>
                O zmianach Regulaminu poinformujemy z co najmniej 7-dniowym
                wyprzedzeniem. Jeśli nie akceptujesz zmian, możesz usunąć konto.
            </li>
            <li>
                W sprawach nieuregulowanych stosuje się prawo polskie, w
                szczególności Kodeks cywilny i ustawę o świadczeniu usług drogą
                elektroniczną.
            </li>
            <li>
                Pytania? Zajrzyj na stronę
                <Link :href="contact()">Kontakt</Link>.
            </li>
        </ol>
    </LegalPageShell>
</template>
