<?php

namespace Database\Seeders;

use App\Enums\ArticleCategory;
use App\Models\Article;
use App\Models\LegalSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Blog articles and the legal knowledge base used by the AI assistant (general information, Polish law 2025/2026).
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->legalSources() as $source) {
            LegalSource::updateOrCreate(
                ['act' => $source['act'], 'article' => $source['article']],
                $source,
            );
        }

        foreach ($this->articles() as $position => $article) {
            Article::updateOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    ...$article,
                    'published_at' => now()->subDays(3 + $position * 6)->setTime(9, 0),
                ],
            );
        }
    }

    /**
     * @return list<array{act: string, article: string, title: string, content: string, keywords: list<string>}>
     */
    private function legalSources(): array
    {
        return [
            [
                'act' => 'Kodeks pracy',
                'article' => '22¹',
                'title' => 'Dane, których pracodawca może żądać od kandydata',
                'content' => 'Pracodawca może żądać od kandydata do pracy tylko: imienia (imion) i nazwiska, daty urodzenia, danych kontaktowych wskazanych przez kandydata, a gdy jest to niezbędne do pracy na danym stanowisku – także informacji o wykształceniu, kwalifikacjach zawodowych i przebiegu dotychczasowego zatrudnienia. Inne dane może pozyskać tylko wtedy, gdy wymaga tego przepis prawa. Pytania o ciążę, plany rodzinne, liczbę dzieci czy stan cywilny wykraczają poza ten katalog – kandydatka nie musi na nie odpowiadać.',
                'keywords' => ['rozmowa kwalifikacyjna', 'rekrutacja', 'kandydat', 'ciąża', 'pytania pracodawcy', 'dane osobowe', 'plany rodzinne', 'dzieci', 'stan cywilny', 'powiedzieć o ciąży'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '18³a',
                'title' => 'Równe traktowanie i zakaz dyskryminacji',
                'content' => 'Pracownicy powinni być równo traktowani w zakresie nawiązania i rozwiązania stosunku pracy, warunków zatrudnienia, awansowania oraz dostępu do szkoleń – w szczególności bez względu na płeć, wiek, niepełnosprawność, wyznanie czy rodzaj umowy i wymiar etatu. Zakaz obejmuje dyskryminację bezpośrednią i pośrednią i dotyczy także kandydatów do pracy. Gorsze traktowanie z powodu ciąży lub macierzyństwa jest traktowane jako dyskryminacja ze względu na płeć. Osoba, wobec której naruszono tę zasadę, może domagać się odszkodowania nie niższego niż minimalne wynagrodzenie (art. 18³d).',
                'keywords' => ['dyskryminacja', 'równe traktowanie', 'odmowa zatrudnienia', 'odszkodowanie', 'ciąża', 'macierzyństwo', 'kandydat'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '18³b',
                'title' => 'Czym jest naruszenie zasady równego traktowania',
                'content' => 'Naruszeniem zasady równego traktowania jest różnicowanie sytuacji pracownika z przyczyn dyskryminujących, którego skutkiem jest m.in. odmowa nawiązania lub rozwiązanie stosunku pracy, niekorzystne ukształtowanie wynagrodzenia lub innych warunków zatrudnienia, pominięcie przy awansie albo przy typowaniu do szkoleń. Nie jest naruszeniem stosowanie rozwiązań, które różnicują sytuację pracownika ze względu na ochronę rodzicielstwa. W sporze to pracodawca musi wykazać, że kierował się obiektywnymi powodami.',
                'keywords' => ['dyskryminacja', 'odmowa zatrudnienia', 'awans', 'wynagrodzenie', 'ochrona rodzicielstwa', 'równe traktowanie'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '177',
                'title' => 'Ochrona przed zwolnieniem w ciąży i na urlopie',
                'content' => 'W okresie ciąży oraz urlopu macierzyńskiego pracodawca nie może wypowiedzieć ani rozwiązać umowy o pracę, chyba że zachodzą przyczyny uzasadniające rozwiązanie umowy bez wypowiedzenia z winy pracownicy, a reprezentująca ją organizacja związkowa wyraziła zgodę. Ochrona obejmuje też okres od złożenia wniosku o urlop rodzicielski lub ojcowski do jego zakończenia. Nie dotyczy umowy na okres próbny nieprzekraczający miesiąca. Umowa na czas określony lub na okres próbny powyżej miesiąca, która rozwiązałaby się po upływie trzeciego miesiąca ciąży, przedłuża się do dnia porodu. Wyjątkiem jest upadłość lub likwidacja pracodawcy.',
                'keywords' => ['zwolnienie', 'wypowiedzenie', 'ochrona', 'ciąża', 'umowa na czas określony', 'przedłużenie umowy', 'urlop macierzyński', 'po podpisaniu umowy', 'okres próbny'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '176',
                'title' => 'Prace wzbronione w ciąży i podczas karmienia',
                'content' => 'Kobiety w ciąży ani kobiety karmiącej dziecko piersią nie wolno zatrudniać przy pracach szczególnie uciążliwych lub szkodliwych dla zdrowia. Wykaz takich prac (m.in. dźwiganie ciężarów, praca w hałasie, z niektórymi substancjami chemicznymi, a w ciąży także praca przy monitorze ekranowym dłużej niż łącznie 8 godzin na dobę z przerwami) określa rozporządzenie Rady Ministrów. Jeśli pracownica wykonuje taką pracę, pracodawca musi ją przenieść do innej pracy, a gdy to niemożliwe – zwolnić z obowiązku świadczenia pracy, z zachowaniem wynagrodzenia (art. 179).',
                'keywords' => ['prace wzbronione', 'praca szkodliwa', 'dźwiganie', 'karmienie piersią', 'ciąża', 'monitor', 'przeniesienie'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '178',
                'title' => 'Nadgodziny, noce i delegacje w ciąży i przy małym dziecku',
                'content' => 'Pracownicy w ciąży nie wolno zatrudniać w godzinach nadliczbowych ani w porze nocnej. Bez jej zgody nie wolno jej też delegować poza stałe miejsce pracy ani zatrudniać w przerywanym systemie czasu pracy. Pracownika opiekującego się dzieckiem do ukończenia przez nie 8. roku życia nie wolno bez jego zgody zatrudniać w nadgodzinach, w porze nocnej, w przerywanym systemie czasu pracy ani delegować poza stałe miejsce pracy.',
                'keywords' => ['nadgodziny', 'praca w nocy', 'delegacja', 'wyjazd służbowy', 'ciąża', 'małe dziecko', 'godziny nadliczbowe'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '185',
                'title' => 'Zwolnienie od pracy na badania w ciąży',
                'content' => 'Pracodawca musi zwolnić od pracy pracownicę w ciąży na zlecone przez lekarza badania lekarskie związane z ciążą, jeżeli nie mogą one zostać przeprowadzone poza godzinami pracy. Za czas tej nieobecności pracownica zachowuje prawo do wynagrodzenia. Do korzystania z uprawnień związanych z ciążą zwykle potrzebne jest zaświadczenie lekarskie potwierdzające ciążę.',
                'keywords' => ['badania', 'wizyta u lekarza', 'kontrola', 'ciąża', 'zwolnienie od pracy', 'wynagrodzenie', 'zaświadczenie'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '180',
                'title' => 'Urlop macierzyński',
                'content' => 'Urlop macierzyński trwa 20 tygodni przy urodzeniu jednego dziecka, a przy porodzie mnogim odpowiednio dłużej (31 tygodni przy bliźniętach, do 37 tygodni przy pięciorgu i więcej dzieci). Przed przewidywaną datą porodu można wykorzystać nie więcej niż 6 tygodni urlopu. Po porodzie matka musi wykorzystać co najmniej 14 tygodni; z pozostałej części może zrezygnować na rzecz ojca dziecka, który wtedy korzysta z niej zamiast niej. Za czas urlopu przysługuje zasiłek macierzyński z ZUS.',
                'keywords' => ['urlop macierzyński', '20 tygodni', 'poród', 'przed porodem', 'wniosek o urlop', 'macierzyński'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '182¹a',
                'title' => 'Urlop rodzicielski',
                'content' => 'Po urlopie macierzyńskim każdy z rodziców może skorzystać z urlopu rodzicielskiego – łącznie do 41 tygodni przy jednym dziecku (43 tygodnie przy porodzie mnogim). Każdemu z rodziców przysługuje wyłączne prawo do 9 tygodni, których nie można przenieść na drugiego rodzica. Urlop można wykorzystać jednorazowo albo w maksymalnie 5 częściach, nie później niż do końca roku kalendarzowego, w którym dziecko kończy 6 lat. Wniosek składa się co najmniej 21 dni przed planowanym rozpoczęciem. W czasie urlopu rodzicielskiego można pracować u swojego pracodawcy w wymiarze nie wyższym niż połowa etatu.',
                'keywords' => ['urlop rodzicielski', '41 tygodni', '9 tygodni', 'wniosek', 'części', 'część etatu', 'łączenie z pracą', 'podział urlopu'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '183¹',
                'title' => 'Powrót na dotychczasowe stanowisko po urlopie',
                'content' => 'Po zakończeniu urlopu macierzyńskiego, ojcowskiego lub rodzicielskiego pracodawca dopuszcza pracownika do pracy na dotychczasowym stanowisku, a jeżeli nie jest to możliwe – na stanowisku równorzędnym z zajmowanym przed urlopem lub na innym odpowiadającym jego kwalifikacjom. Wynagrodzenie nie może być niższe niż to, które przysługiwałoby, gdyby pracownik nie korzystał z urlopu – obejmuje więc podwyżki przyznane w tym czasie na tym stanowisku.',
                'keywords' => ['powrót do pracy', 'po urlopie', 'to samo stanowisko', 'stanowisko równorzędne', 'wynagrodzenie po powrocie', 'podwyżka'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '186',
                'title' => 'Urlop wychowawczy',
                'content' => 'Pracownik zatrudniony co najmniej 6 miesięcy może skorzystać z urlopu wychowawczego w wymiarze do 36 miesięcy w celu osobistej opieki nad dzieckiem, nie dłużej niż do końca roku kalendarzowego, w którym dziecko kończy 6 lat. Każdemu z rodziców przysługuje wyłączne prawo do 1 miesiąca tego urlopu. Urlop jest bezpłatny i można go wykorzystać w maksymalnie 5 częściach; wniosek składa się co najmniej 21 dni przed rozpoczęciem.',
                'keywords' => ['urlop wychowawczy', '36 miesięcy', 'bezpłatny', 'opieka nad dzieckiem'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '186⁷',
                'title' => 'Obniżony wymiar czasu pracy zamiast urlopu wychowawczego',
                'content' => 'Pracownik uprawniony do urlopu wychowawczego może zamiast niego złożyć wniosek o obniżenie wymiaru czasu pracy do wymiaru nie niższego niż połowa pełnego etatu – na okres, w którym mógłby korzystać z urlopu wychowawczego. Pracodawca jest obowiązany uwzględnić taki wniosek. Wniosek składa się co najmniej 21 dni przed planowanym obniżeniem. Od złożenia wniosku do powrotu do pełnego wymiaru (maksymalnie przez 12 miesięcy) pracownik jest chroniony przed wypowiedzeniem (art. 186⁸).',
                'keywords' => ['część etatu', 'pół etatu', 'obniżony wymiar', 'niepełny etat', 'powrót na część etatu', 'skrócony czas pracy', 'wniosek'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '188',
                'title' => 'Zwolnienie od pracy na opiekę nad dzieckiem',
                'content' => 'Pracownikowi wychowującemu przynajmniej jedno dziecko w wieku do 14 lat przysługuje w ciągu roku kalendarzowego zwolnienie od pracy w wymiarze 16 godzin albo 2 dni, z zachowaniem prawa do wynagrodzenia. O sposobie wykorzystania (godziny czy dni) decyduje się w pierwszym wniosku złożonym w danym roku. Jeśli oboje rodzice pracują, z uprawnienia może korzystać jedno z nich albo mogą się nim podzielić.',
                'keywords' => ['opieka nad dzieckiem', '2 dni', '16 godzin', 'wolne', 'zwolnienie od pracy', 'dziecko do 14 lat'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '188¹',
                'title' => 'Elastyczna organizacja pracy dla rodziców dziecka do 8 lat',
                'content' => 'Pracownik wychowujący dziecko do ukończenia przez nie 8. roku życia może złożyć wniosek o elastyczną organizację pracy: pracę zdalną, system przerywanego czasu pracy, skróconego tygodnia pracy, pracy weekendowej, ruchomy czas pracy, indywidualny rozkład czasu pracy lub obniżenie wymiaru czasu pracy. Wniosek składa się w postaci papierowej lub elektronicznej co najmniej 21 dni przed planowanym rozpoczęciem. Pracodawca rozpatruje go z uwzględnieniem potrzeb obu stron i w ciągu 7 dni informuje o uwzględnieniu wniosku albo o przyczynie odmowy lub innym możliwym terminie.',
                'keywords' => ['elastyczna organizacja pracy', 'elastyczne godziny', 'praca zdalna', 'ruchomy czas pracy', 'indywidualny rozkład', 'przedszkole', 'żłobek', 'godziny pracy'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '67¹⁹',
                'title' => 'Wniosek o pracę zdalną w ciąży i przy małym dziecku',
                'content' => 'Pracodawca ma obowiązek uwzględnić wniosek o pracę zdalną złożony m.in. przez pracownicę w ciąży oraz pracownika wychowującego dziecko do ukończenia 4. roku życia, chyba że praca zdalna nie jest możliwa ze względu na organizację pracy lub jej rodzaj. O przyczynie odmowy pracodawca informuje pracownika w postaci papierowej lub elektronicznej w terminie 7 dni roboczych od dnia złożenia wniosku.',
                'keywords' => ['praca zdalna', 'home office', 'zdalnie', 'ciąża', 'dziecko do 4 lat', 'wniosek o pracę zdalną'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '187',
                'title' => 'Przerwy na karmienie piersią',
                'content' => 'Pracownica karmiąca dziecko piersią ma prawo do dwóch półgodzinnych przerw w pracy wliczanych do czasu pracy; przy karmieniu więcej niż jednego dziecka – do dwóch przerw po 45 minut. Przerwy mogą być udzielane łącznie. Pracownicy zatrudnionej krócej niż 4 godziny dziennie przerwy nie przysługują, a przy pracy od 4 do 6 godzin dziennie przysługuje jedna przerwa.',
                'keywords' => ['karmienie piersią', 'przerwa na karmienie', 'karmiąca', 'powrót do pracy'],
            ],
            [
                'act' => 'Kodeks pracy',
                'article' => '163',
                'title' => 'Urlop wypoczynkowy bezpośrednio po urlopie macierzyńskim',
                'content' => 'Na wniosek pracownicy pracodawca ma obowiązek udzielić jej urlopu wypoczynkowego bezpośrednio po urlopie macierzyńskim (§ 3) – nie musi on wynikać z planu urlopów. Prawo do urlopu wypoczynkowego nabywa się także za czas urlopu macierzyńskiego i rodzicielskiego, więc po powrocie często jest do wykorzystania zaległy urlop za ten okres.',
                'keywords' => ['urlop wypoczynkowy', 'zaległy urlop', 'po macierzyńskim', 'plan urlopów', 'powrót do pracy'],
            ],
            [
                'act' => 'Ustawa zasiłkowa',
                'article' => '29',
                'title' => 'Zasiłek macierzyński – komu przysługuje',
                'content' => 'Zasiłek macierzyński przysługuje osobie objętej ubezpieczeniem chorobowym (obowiązkowo – np. na umowie o pracę, albo dobrowolnie – np. przy działalności gospodarczej lub umowie zlecenia), która w okresie ubezpieczenia urodziła dziecko, za okres urlopu macierzyńskiego i rodzicielskiego. W przeciwieństwie do zasiłku chorobowego nie ma okresu wyczekiwania – wystarczy, że w dniu porodu jesteś ubezpieczona. Wysokość (art. 31): 100% podstawy za okres urlopu macierzyńskiego i 70% za urlop rodzicielski albo 81,5% za cały okres, jeśli wniosek o urlop rodzicielski złożysz w ciągu 21 dni po porodzie. Ustawa: o świadczeniach pieniężnych z ubezpieczenia społecznego w razie choroby i macierzyństwa.',
                'keywords' => ['zasiłek macierzyński', 'ZUS', 'nowa praca', 'okres wyczekiwania', 'ubezpieczenie chorobowe', '81,5%', 'działalność gospodarcza', 'umowa zlecenie', 'ile wynosi zasiłek'],
            ],
            [
                'act' => 'Ustawa zasiłkowa',
                'article' => '11',
                'title' => 'Zasiłek chorobowy w ciąży – 100% podstawy',
                'content' => 'Miesięczny zasiłek chorobowy wynosi co do zasady 80% podstawy wymiaru, ale za okres niezdolności do pracy przypadającej w czasie ciąży wynosi 100% podstawy (ust. 2). Przez pierwsze dni choroby w roku (zwykle 33 dni) pracownikowi wypłaca wynagrodzenie chorobowe pracodawca – w ciąży także w wysokości 100% (art. 92 Kodeksu pracy), a dalej zasiłek wypłaca ZUS. Ustawa: o świadczeniach pieniężnych z ubezpieczenia społecznego w razie choroby i macierzyństwa.',
                'keywords' => ['zwolnienie lekarskie', 'L4', 'zasiłek chorobowy', '100%', 'ciąża', 'wypłata', 'wynagrodzenie chorobowe'],
            ],
            [
                'act' => 'Ustawa zasiłkowa',
                'article' => '4',
                'title' => 'Okres wyczekiwania na zasiłek chorobowy',
                'content' => 'Prawo do zasiłku chorobowego nabywa się po 30 dniach nieprzerwanego ubezpieczenia chorobowego, gdy jest ono obowiązkowe (np. umowa o pracę), albo po 90 dniach, gdy jest dobrowolne (np. działalność gospodarcza, umowa zlecenia). Do tego okresu wlicza się wcześniejsze okresy ubezpieczenia, jeśli przerwa między nimi nie przekroczyła 30 dni. Okres wyczekiwania nie dotyczy zasiłku macierzyńskiego. Ustawa: o świadczeniach pieniężnych z ubezpieczenia społecznego w razie choroby i macierzyństwa.',
                'keywords' => ['okres wyczekiwania', 'nowa praca', 'zasiłek chorobowy', '30 dni', '90 dni', 'L4', 'zwolnienie lekarskie'],
            ],
        ];
    }

    /**
     * @return list<array{title: string, category: ArticleCategory, excerpt: string, body: string, reading_minutes: int, is_featured: bool}>
     */
    private function articles(): array
    {
        return [
            [
                'title' => 'Urlop rodzicielski: jak ułożyć go z pracodawcą',
                'category' => ArticleCategory::Return,
                'excerpt' => 'Kiedy złożyć wniosek, jak podzielić urlop z partnerem i o co zapytać przed powrotem na część etatu.',
                'reading_minutes' => 9,
                'is_featured' => true,
                'body' => <<<'MD'
                    Urlop rodzicielski możesz dzielić na części, łączyć z pracą na część etatu i rozłożyć między rodziców. Jeśli zaplanujesz go wcześniej, wrócisz do pracy stopniowo.

                    ## Ile go jest i do kogo należy

                    Po 20 tygodniach urlopu macierzyńskiego rodzicom przysługuje łącznie do **41 tygodni** urlopu rodzicielskiego (43 przy porodzie mnogim). Każde z rodziców ma wyłączne prawo do **9 tygodni** – tej części nie można oddać drugiemu rodzicowi. Jeśli partner nie skorzysta ze swoich 9 tygodni, po prostu przepadają.

                    Urlop możesz wykorzystać od razu albo w maksymalnie pięciu częściach, najpóźniej do końca roku, w którym dziecko kończy 6 lat.

                    ## Kiedy złożyć wniosek

                    Wniosek składasz co najmniej **21 dni** przed planowanym rozpoczęciem urlopu. Jeśli od razu po porodzie wiesz, że weźmiesz cały urlop rodzicielski, rozważ złożenie wniosku w ciągu 21 dni po porodzie – wtedy zasiłek za cały okres urlopów wynosi 81,5% podstawy zamiast 100% za macierzyński i 70% za rodzicielski. Policz, która opcja jest dla Ciebie korzystniejsza.

                    ## Urlop rodzicielski a część etatu

                    W czasie urlopu rodzicielskiego możesz pracować u swojego pracodawcy w wymiarze **do połowy etatu**. Zachowujesz wtedy kontakt z zespołem, a urlop wydłuża się proporcjonalnie. Pracodawca może odmówić tylko wtedy, gdy organizacja lub rodzaj pracy na to nie pozwalają.

                    ## Rozmowa z pracodawcą – lista pytań

                    Zanim złożysz wniosek, umów krótką rozmowę i zapytaj:

                    - kto przejmie Twoje obowiązki i jak wygląda przekazanie,
                    - czy możliwa jest praca na część etatu w trakcie urlopu,
                    - jak będziecie w kontakcie (i czy w ogóle – masz prawo do spokoju),
                    - czy przed powrotem przewidziane jest spotkanie wdrożeniowe.

                    ## Po urlopie

                    Po urlopie wracasz na to samo stanowisko, a jeśli to niemożliwe – na równorzędne, z wynagrodzeniem nie niższym niż przed urlopem, uwzględniającym podwyżki z tego czasu. Jeśli chcesz dalej pracować krócej, możesz złożyć wniosek o obniżony wymiar czasu pracy zamiast urlopu wychowawczego.

                    > To informacja ogólna. W konkretnej sytuacji sprawdź aktualne przepisy lub zapytaj kadry.
                    MD,
            ],
            [
                'title' => 'Rozmowa w ciąży: co musisz powiedzieć, a czego nie',
                'category' => ArticleCategory::CvAndInterviews,
                'excerpt' => 'Pracodawca nie może pytać o ciążę ani plany rodzinne. Podpowiadamy, jak odpowiadać na niewygodne pytania.',
                'reading_minutes' => 6,
                'is_featured' => false,
                'body' => <<<'MD'
                    Szukasz pracy w ciąży i zastanawiasz się, czy musisz o niej powiedzieć? Krótko: **nie musisz**. Rekrutacja dotyczy Twoich kompetencji, a nie planów rodzinnych.

                    ## Co pracodawca może wiedzieć

                    Kodeks pracy (art. 22¹) wymienia dane, których pracodawca może żądać od kandydatki: imię i nazwisko, datę urodzenia, dane kontaktowe, a jeśli to potrzebne na danym stanowisku – wykształcenie, kwalifikacje i przebieg zatrudnienia. Ciąża, liczba dzieci, stan cywilny czy plany na powiększenie rodziny do tego katalogu nie należą.

                    ## Co zrobić, gdy padnie niewygodne pytanie

                    Masz kilka możliwości:

                    - **Przekieruj rozmowę na dostępność**: „Mogę zacząć od 1 marca i pracować w pełnym wymiarze.”
                    - **Odmów uprzejmie**: „Wolałabym skupić się na moim doświadczeniu.”
                    - **Zanotuj sytuację** – datę, osoby i treść pytania. Odmowa zatrudnienia z powodu ciąży to dyskryminacja ze względu na płeć, za którą przysługuje odszkodowanie.

                    ## Czy warto powiedzieć?

                    To Twoja decyzja. Niektóre kandydatki mówią o ciąży, gdy rozmowy są już zaawansowane, żeby wspólnie ustalić termin startu. Inne mówią dopiero po podpisaniu umowy. Pamiętaj, że ochrona przed zwolnieniem z art. 177 Kodeksu pracy działa od początku ciąży, a z niektórych uprawnień (np. zwolnienia na badania) skorzystasz po przedstawieniu zaświadczenia lekarskiego.

                    ## Jak mumjobs Ci pomaga

                    W mumjobs pracodawca widzi Twój anonimowy profil: umiejętności, doświadczenie i datę, od kiedy jesteś dostępna. Nie widzi przyczyny przerwy, terminu porodu ani zdjęcia. Wiadomości od firm przechodzą przez filtr, który blokuje pytania o ciążę i plany rodzinne.

                    > To informacja ogólna, a nie porada prawna.
                    MD,
            ],
            [
                'title' => 'Jak opisać przerwę w CV i nie tłumaczyć się',
                'category' => ArticleCategory::CvAndInterviews,
                'excerpt' => 'Nie musisz ukrywać przerwy w zatrudnieniu. Napisz, od kiedy jesteś dostępna i co umiesz.',
                'reading_minutes' => 5,
                'is_featured' => false,
                'body' => <<<'MD'
                    Przerwa w CV stresuje wiele mam wracających do pracy. Rekruterzy chcą jednak głównie wiedzieć, co umiesz i od kiedy możesz zacząć. Z życia prywatnego nie musisz się tłumaczyć.

                    ## Nie musisz podawać powodu

                    Pracodawca może pytać o przebieg zatrudnienia, ale powód przerwy to Twoja prywatna sprawa. W CV wystarczy uczciwie podać daty zatrudnienia. Zamiast „urlop macierzyński” możesz nic nie wpisywać albo dodać neutralną linię, np. „Przerwa w zatrudnieniu – rozwój kompetencji”, jeśli faktycznie coś w tym czasie robiłaś.

                    ## Pokaż dostępność zamiast luki

                    Na górze CV, w podsumowaniu, dopisz jedno zdanie: **„Dostępna od września 2027, preferowany wymiar: 3/4 etatu, praca hybrydowa.”** Rekruter od razu wie, czego się spodziewać, a przerwa przestaje być pierwszym pytaniem.

                    ## Co liczy się jako doświadczenie

                    - kursy online i certyfikaty (nawet krótkie),
                    - wolontariat, projekty w radzie rodziców, pomoc w firmie bliskich,
                    - samodzielna nauka narzędzi, których używa się w Twojej branży.

                    Wpisz je w sekcji „Rozwój” lub „Projekty”, z konkretnym efektem.

                    ## Odśwież umiejętności przed wysyłką

                    Przejrzyj kilka ogłoszeń na stanowisko, które Cię interesuje, i wypisz powtarzające się umiejętności. Jeśli któraś Ci umknęła, zrób krótki kurs, zanim wyślesz CV.

                    ## Na rozmowie

                    Jeśli padnie pytanie o przerwę, odpowiedz krótko i wróć do konkretów: „Miałam przerwę w pracy, teraz jestem gotowa wrócić od marca. W ostatnim projekcie odpowiadałam za…”. Nie musisz mówić więcej.

                    W mumjobs profil pokazuje datę dostępności zamiast przerwy – pracodawca widzi Twoje umiejętności, a nie lukę w kalendarzu.
                    MD,
            ],
            [
                'title' => 'Plan powrotu do pracy na pierwsze 8 tygodni',
                'category' => ArticleCategory::Return,
                'excerpt' => 'Tydzień po tygodniu: od rozmowy z przełożoną do podsumowania pierwszych dwóch miesięcy.',
                'reading_minutes' => 8,
                'is_featured' => false,
                'body' => <<<'MD'
                    Powrót po urlopie łatwiej rozłożyć na kilka tygodni. Ten plan dzieli go na cztery etapy po dwa tygodnie.

                    ## Tydzień 1–2: zanim wrócisz

                    - Umów rozmowę z przełożoną: zakres obowiązków, godziny, możliwość pracy zdalnej.
                    - Jeśli chcesz pracować krócej, złóż wniosek o obniżony wymiar czasu pracy lub elastyczną organizację pracy – najlepiej co najmniej 21 dni wcześniej.
                    - Przećwicz adaptację w żłobku lub u niani, zanim zaczniesz pracę.
                    - Sprawdź, czy masz zaległy urlop wypoczynkowy – możesz go wziąć bezpośrednio po urlopie macierzyńskim.

                    ## Tydzień 3–4: pierwsze dni w pracy

                    Wracasz na swoje stanowisko lub równorzędne, z wynagrodzeniem nie niższym niż przed urlopem. Poproś o krótkie wdrożenie: co się zmieniło w procesach, narzędziach, zespole. Nie bierz od razu wszystkich projektów – ustal priorytety na pierwszy miesiąc.

                    Jeśli karmisz piersią, masz prawo do przerw na karmienie wliczanych do czasu pracy. Powiedz o tym wcześniej, żeby łatwiej zaplanować dzień.

                    ## Tydzień 5–6: rytm

                    - Zablokuj w kalendarzu stałe godziny wyjścia.
                    - Ustal z partnerem dyżury na chorobę dziecka. Każde z Was ma prawo do 2 dni (lub 16 godzin) płatnego zwolnienia na opiekę nad dzieckiem w roku.
                    - Zapisz pierwsze sukcesy – przydadzą się na rozmowie podsumowującej.

                    ## Tydzień 7–8: podsumowanie

                    Umów rozmowę podsumowującą okres powrotu. Co działa? Co trzeba zmienić? Jeśli godziny się nie sprawdzają, możesz poprosić o inny rozkład czasu pracy – rodzic dziecka do 8 lat ma prawo złożyć wniosek o elastyczną organizację pracy.

                    ## Na koniec

                    Pierwsze tygodnie zwykle są nierówne, więc zmieniaj plan, kiedy przestaje pasować do Twojej sytuacji.
                    MD,
            ],
            [
                'title' => 'Zasiłek macierzyński a nowa praca: kiedy przysługuje',
                'category' => ArticleCategory::Rights,
                'excerpt' => 'Zmieniasz pracę w ciąży? Sprawdź, od kiedy masz prawo do zasiłku macierzyńskiego i chorobowego.',
                'reading_minutes' => 7,
                'is_featured' => false,
                'body' => <<<'MD'
                    Wiele kobiet boi się zmiany pracy w ciąży, bo „straci zasiłek”. Zasiłek macierzyński ma jednak inne zasady niż chorobowy.

                    ## Zasiłek macierzyński – bez okresu wyczekiwania

                    Zasiłek macierzyński przysługuje, jeśli w dniu porodu jesteś objęta ubezpieczeniem chorobowym. **Nie ma okresu wyczekiwania** – nawet jeśli zaczęłaś nową pracę na krótko przed porodem, masz prawo do zasiłku. Ubezpieczenie chorobowe jest obowiązkowe na umowie o pracę, a dobrowolne przy działalności gospodarczej i umowie zlecenia – w tych dwóch przypadkach musisz się do niego zgłosić.

                    Wysokość zasiłku liczy się z przeciętnego wynagrodzenia z ostatnich 12 miesięcy; gdy pracujesz krócej, z pełnych miesięcy w nowej pracy.

                    ## Ile wynosi

                    - 100% podstawy za urlop macierzyński i 70% za urlop rodzicielski, **albo**
                    - 81,5% za cały okres, jeśli wniosek o urlop rodzicielski złożysz w ciągu 21 dni po porodzie.

                    ## Zasiłek chorobowy – tu jest okres wyczekiwania

                    Prawo do zasiłku chorobowego nabywasz po **30 dniach** ubezpieczenia obowiązkowego (90 dniach dobrowolnego). Wlicza się poprzednie okresy ubezpieczenia, jeśli przerwa między nimi nie była dłuższa niż 30 dni. Jeśli więc przechodzisz z jednej umowy o pracę na drugą bez przerwy, nie zaczynasz od zera.

                    W ciąży zasiłek chorobowy wynosi 100% podstawy.

                    ## Umowa na czas określony

                    Jeśli masz umowę na czas określony (dłuższą niż miesiąc), która skończyłaby się po trzecim miesiącu ciąży, z mocy prawa przedłuża się ona do dnia porodu. Dzięki temu w dniu porodu jesteś ubezpieczona.

                    ## Co sprawdzić przed zmianą pracy

                    1. Czy między umowami nie będzie przerwy dłuższej niż 30 dni.
                    2. Rodzaj umowy – na zleceniu i B2B zgłoś się do dobrowolnego ubezpieczenia chorobowego.
                    3. Termin porodu względem daty startu.

                    > To informacja ogólna. Szczegóły Twojej sytuacji potwierdzi ZUS.
                    MD,
            ],
            [
                'title' => 'Praca zdalna a przedszkole: 5 pytań do ogłoszenia',
                'category' => ArticleCategory::Return,
                'excerpt' => 'Zanim odpowiesz na ogłoszenie, sprawdź, czy godziny i tryb pracy zgrają się z odbiorem dziecka.',
                'reading_minutes' => 4,
                'is_featured' => false,
                'body' => <<<'MD'
                    „Praca zdalna” w ogłoszeniu może oznaczać różne rzeczy. Te pięć pytań pomoże Ci sprawdzić, czy oferta naprawdę pasuje do rytmu dnia z przedszkolakiem.

                    ## 1. Czy są stałe godziny spotkań?

                    Elastyczne godziny to jedno, a codzienne spotkanie o 16:30 – drugie. Zapytaj, kiedy zwykle odbywają się spotkania zespołu i czy są nagrywane.

                    ## 2. Ile dni w biurze to „hybrydowo”?

                    Jeden dzień w miesiącu czy trzy w tygodniu? To zmienia logistykę odbioru z przedszkola i koszty dojazdu.

                    ## 3. Czy można pracować na część etatu?

                    Jeśli planujesz 3/4 lub 1/2 etatu, zapytaj od razu. W mumjobs wymiar etatu jest widoczny w każdej ofercie.

                    ## 4. Co się dzieje, gdy dziecko zachoruje?

                    Odpowiedź dużo mówi o firmie. Każdy rodzic dziecka do 14 lat ma prawo do 2 dni (lub 16 godzin) płatnego zwolnienia w roku, a przy chorobie dziecka – do zasiłku opiekuńczego. Warto wiedzieć, jak zespół radzi sobie z zastępstwami.

                    ## 5. Czy firma ma doświadczenie z rodzicami?

                    Zapytaj o osoby, które wróciły po urlopie rodzicielskim. Sprawdź też oceny firmy wystawione przez mamy – na mumjobs znajdziesz je przy profilu pracodawcy.

                    ## Twoje prawa

                    Jeśli już pracujesz, pamiętaj: pracodawca musi co do zasady uwzględnić wniosek o pracę zdalną pracownicy w ciąży i rodzica dziecka do 4 lat, a rodzic dziecka do 8 lat może wnioskować o elastyczną organizację pracy.
                    MD,
            ],
            [
                'title' => 'Zwolnienie lekarskie w ciąży: co z pracą i wypłatą',
                'category' => ArticleCategory::Pregnancy,
                'excerpt' => 'Ile wynosi wypłata na L4 w ciąży, kto ją płaci i czy pracodawca może Cię zwolnić.',
                'reading_minutes' => 6,
                'is_featured' => false,
                'body' => <<<'MD'
                    Jeśli lekarz wystawi Ci zwolnienie w ciąży, masz prawo z niego skorzystać. Poniżej zasady wypłaty i ochrony przed zwolnieniem z pracy.

                    ## Ile dostaniesz

                    Za czas niezdolności do pracy w ciąży przysługuje **100% podstawy** – zamiast standardowych 80%. Przez pierwsze dni choroby w roku (zwykle 33 dni) płaci pracodawca jako wynagrodzenie chorobowe, a później zasiłek chorobowy wypłaca ZUS. Podstawą jest przeciętne wynagrodzenie z ostatnich 12 miesięcy.

                    ## Czy mogę stracić pracę?

                    W ciąży pracodawca **nie może wypowiedzieć ani rozwiązać umowy o pracę**, poza wyjątkami (np. ciężkie naruszenie obowiązków za zgodą związku zawodowego, upadłość lub likwidacja firmy). Umowa na czas określony dłuższa niż miesiąc, która skończyłaby się po trzecim miesiącu ciąży, przedłuża się do dnia porodu.

                    ## Co z obowiązkami w pracy

                    - Na zwolnieniu nie pracujesz – także „tylko zdalnie” czy „tylko maile”.
                    - Zwolnienie (e-ZLA) trafia do pracodawcy automatycznie.
                    - W okresie zwolnienia ZUS może skontrolować, czy wykorzystujesz je zgodnie z celem – wyjście do lekarza czy na spacer zalecony przez lekarza to nie problem.

                    ## Gdy pracujesz i czujesz się dobrze

                    Nie musisz iść na zwolnienie, jeśli lekarz nie widzi przeciwwskazań. Masz za to prawo do zwolnienia od pracy na badania związane z ciążą, gdy nie da się ich zrobić poza godzinami pracy – z zachowaniem wynagrodzenia. Nie możesz też pracować w nadgodzinach ani w nocy, a pracodawca musi przenieść Cię od prac szkodliwych w ciąży.

                    ## Nowa praca

                    Jeśli zaczęłaś pracę niedawno, sprawdź okres wyczekiwania: prawo do zasiłku chorobowego masz po 30 dniach ubezpieczenia (wlicza się wcześniejsze umowy, gdy przerwa nie przekroczyła 30 dni).

                    > To informacja ogólna, a nie porada prawna.
                    MD,
            ],
            [
                'title' => 'Urlop macierzyński krok po kroku',
                'category' => ArticleCategory::Leave,
                'excerpt' => 'Ile trwa, kiedy możesz go zacząć i jakie dokumenty przygotować.',
                'reading_minutes' => 5,
                'is_featured' => false,
                'body' => <<<'MD'
                    Zasady urlopu macierzyńskiego sprowadzają się do kilku liczb i terminów.

                    ## Ile trwa

                    - **20 tygodni** przy urodzeniu jednego dziecka,
                    - 31 tygodni przy bliźniętach i odpowiednio więcej przy kolejnych dzieciach (do 37 tygodni).

                    ## Kiedy możesz go zacząć

                    Do **6 tygodni** urlopu możesz wykorzystać przed przewidywaną datą porodu – na Twój wniosek. Pozostała część przypada po porodzie. Jeśli nie zaczniesz urlopu wcześniej, rozpoczyna się on w dniu porodu.

                    ## Obowiązkowe 14 tygodni

                    Po porodzie musisz wykorzystać co najmniej **14 tygodni** urlopu macierzyńskiego. Z pozostałej części możesz zrezygnować na rzecz ojca dziecka – wtedy to on wykorzysta resztę urlopu.

                    ## Pieniądze

                    Za czas urlopu przysługuje zasiłek macierzyński z ZUS: 100% podstawy za urlop macierzyński albo 81,5% za cały okres urlopów, jeśli w ciągu 21 dni po porodzie złożysz wniosek o urlop rodzicielski. Zasiłek przysługuje także osobom prowadzącym działalność lub pracującym na zleceniu, jeśli opłacają dobrowolne ubezpieczenie chorobowe.

                    ## Dokumenty

                    1. Wniosek o urlop przed porodem (jeśli chcesz zacząć wcześniej).
                    2. Skrócony odpis aktu urodzenia dziecka dla pracodawcy.
                    3. Ewentualnie wniosek o urlop rodzicielski – w ciągu 21 dni po porodzie, jeśli wybierasz wariant 81,5%.

                    ## Po urlopie

                    Na Twój wniosek pracodawca musi udzielić Ci urlopu wypoczynkowego bezpośrednio po urlopie macierzyńskim. Potem możesz przejść na urlop rodzicielski albo wrócić do pracy – na to samo lub równorzędne stanowisko.

                    > To informacja ogólna. W razie wątpliwości zapytaj w kadrach lub w ZUS.
                    MD,
            ],
        ];
    }
}
