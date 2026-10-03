<?php

namespace Database\Seeders;

use App\Enums\EmploymentFraction;
use App\Enums\OfferStatus;
use App\Enums\ReviewStatus;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Polishes the demo data created by DemoSeeder: realistic Polish candidate summaries,
 * company NIPs and descriptions, plus extra Kraków/remote companies with published offers.
 */
class DemoPolishSeeder extends Seeder
{
    private const array CITIES = ['Kraków', 'Warszawa', 'Poznań', 'Wrocław', 'Gdańsk', 'Katowice', 'Łódź'];

    /**
     * Third-person AI summaries per DemoSeeder headline. They only mention the first three skills
     * of each DemoSeeder profile, which are always attached and confirmed.
     *
     * @var array<string, list<string>>
     */
    private const array SUMMARIES = [
        'Specjalistka ds. rekrutacji' => [
            'Prowadziła pełne procesy rekrutacyjne na stanowiska programistyczne – od briefu z menedżerem po ofertę. Zaprojektowała onboarding, który skrócił czas wdrożenia nowych osób o kilka tygodni.',
            'Rekruterka IT z doświadczeniem w software house i firmie produktowej. Dba o zgodność procesów z prawem pracy i o dobre pierwsze tygodnie nowych pracowników.',
            'Zamykała rocznie kilkadziesiąt rekrutacji technicznych, współpracując bezpośrednio z liderami zespołów. Odpowiadała też za program onboardingu dla działu IT.',
            'Specjalizuje się w rekrutacji developerów i testerów, sprawnie prowadzi rozmowy techniczne razem z zespołem. Zna prawo pracy na tyle, by samodzielnie przygotować dokumenty dla nowych osób.',
            'Budowała od podstaw dział rekrutacji w rosnącej firmie technologicznej. Wprowadziła ustrukturyzowany onboarding i standardy przygotowania umów zgodnych z kodeksem pracy.',
            'Łączy rekrutację IT z dbałością o doświadczenie kandydatów – każdy proces kończy informacją zwrotną. Koordynowała wdrożenia kilkudziesięciu osób rocznie.',
            'Rekrutowała specjalistów IT na rynek polski i zagraniczny, korzystając z sourcingu i poleceń. Przygotowuje plany wdrożenia i pilnuje formalności wynikających z prawa pracy.',
            'Ma doświadczenie zarówno w agencji, jak i po stronie pracodawcy, głównie w rekrutacjach technicznych. Prowadziła onboarding hybrydowy dla zespołów rozproszonych w kilku miastach.',
        ],
        'HR Business Partner' => [
            'Jako HR Business Partner wspierała menedżerów w strukturze kilkuset pracowników, od planowania zatrudnienia po rozmowy rozwojowe. Dobrze porusza się w prawie pracy i procesach wdrożenia.',
            'Partnerka biznesowa dla działów sprzedaży i operacji, odpowiadała za politykę wynagrodzeń i ścieżki awansu. Zaprojektowała onboarding dla nowych liderów zespołów.',
            'Prowadziła projekty zmian organizacyjnych i restrukturyzacje zgodnie z prawem pracy. Wspiera menedżerów w trudnych rozmowach i budowaniu zespołów.',
            'Doświadczona HRBP w firmie produkcyjnej i w centrum usług wspólnych. Wdrożyła ustandaryzowany proces onboardingu oraz badanie zaangażowania pracowników.',
            'Łączy perspektywę biznesu i ludzi – przygotowywała analizy rotacji i plany sukcesji. Konsultuje kwestie z zakresu prawa pracy dla kadry kierowniczej.',
            'Odpowiadała za obszar HR dla kilku działów jednocześnie, w tym za przeglądy roczne i budżety szkoleniowe. Usprawniła wdrożenie nowych pracowników w modelu hybrydowym.',
            'Wspierała menedżerów w procesach zatrudnienia, ocen okresowych i rozwiązywaniu konfliktów. Zna prawo pracy w praktyce i potrafi przełożyć je na proste procedury.',
            'HR Business Partner z doświadczeniem w środowisku międzynarodowym. Prowadziła programy onboardingowe i rozwojowe dla zespołów liczących łącznie ponad 300 osób.',
        ],
        'Specjalistka ds. kadr i płac' => [
            'Samodzielnie naliczała wynagrodzenia dla ponad 200 pracowników i prowadziła pełną dokumentację kadrową. Bardzo dobrze zna prawo pracy oraz Excela w codziennym raportowaniu.',
            'Specjalistka ds. kadr i płac z doświadczeniem w biurze rachunkowym i dużej firmie usługowej. Przygotowuje zestawienia płacowe i kontroluje zgodność z przepisami prawa pracy.',
            'Prowadziła kadry i płace dla kilku spółek jednocześnie, w tym rozliczenia z ZUS i PIT. Automatyzuje raporty w Excelu, co skraca zamknięcie miesiąca.',
            'Odpowiadała za listy płac, urlopy i ewidencję czasu pracy w firmie z pracą zmianową. Na bieżąco śledzi zmiany w prawie pracy.',
            'Przeprowadziła migrację danych kadrowych do nowego systemu i przygotowała procedury dla zespołu. Świetnie posługuje się Excelem przy uzgadnianiu list płac.',
            'Ma doświadczenie w obsłudze umów o pracę i umów cywilnoprawnych oraz w kontaktach z ZUS. Konsultuje menedżerów w kwestiach czasu pracy zgodnie z kodeksem pracy.',
            'Naliczała wynagrodzenia z uwzględnieniem premii, nadgodzin i dodatków zmianowych. Przygotowuje analizy kosztów osobowych w Excelu dla działu finansów.',
            'Prowadziła dokumentację kadrową od zatrudnienia po rozwiązanie umowy, w tym akta osobowe w wersji elektronicznej. Rzetelna i dokładna w terminowych rozliczeniach płac.',
        ],
        'Koordynatorka projektów' => [
            'Koordynowała równolegle kilka projektów wdrożeniowych dla klientów biznesowych, pracując w Scrumie. Prowadzi backlog w Jirze i dba o przejrzystą komunikację z klientem.',
            'Doświadczona koordynatorka projektów IT, pełniła też rolę Scrum Mastera w dwóch zespołach. Potrafi utrzymać harmonogram i budżet bez nadgodzin zespołu.',
            'Zarządzała projektami wdrożenia systemów dla klientów z branży finansowej. Sprawnie organizuje pracę w Jirze i prowadzi regularne przeglądy z interesariuszami.',
            'Prowadziła projekty od fazy ofertowania po odbiór, będąc głównym punktem kontaktu dla klienta. Wprowadziła w zespole praktyki Scrum i retrospektywy.',
            'Koordynatorka z doświadczeniem w agencji i software house, przyzwyczajona do pracy z wieloma klientami naraz. Raportuje postępy w Jirze i dba o jakość dokumentacji.',
            'Zarządzała portfelem projektów o łącznym budżecie kilku milionów złotych. Facylitowała ceremonie scrumowe i warsztaty z klientami.',
            'Łączy podejście zwinne z dobrą organizacją pracy – planuje sprinty i kamienie milowe w Jirze. Potrafi spokojnie prowadzić trudne rozmowy z klientem o zakresie.',
            'Prowadziła projekty rozproszonych zespołów pracujących zdalnie w kilku strefach czasowych. Dba o przejrzyste zadania w Jirze i rytm pracy oparty na Scrumie.',
        ],
        'Konsultantka obsługi klienta' => [
            'Obsługiwała klientów przez czat, telefon i e-mail, także w języku angielskim. Ma wysokie wyniki satysfakcji klientów i doświadczenie w szkoleniu nowych konsultantów.',
            'Konsultantka w dziale wsparcia aplikacji mobilnej, rozwiązywała zgłoszenia klientów z Polski i zagranicy. Spokojnie prowadzi trudne rozmowy i dba o dobrą komunikację.',
            'Pracowała w zespole obsługi klienta sklepu internetowego, odpowiadała za reklamacje i zwroty. Swobodnie komunikuje się po angielsku w mowie i piśmie.',
            'Ma doświadczenie w helpdesku pierwszej linii i w obsłudze kluczowych klientów. Tworzyła bazę wiedzy i szablony odpowiedzi, które skróciły czas obsługi zgłoszeń.',
            'Obsługiwała klientów anglojęzycznych w centrum usług wspólnych, z naciskiem na jakość i terminowość. Potrafi tłumaczyć złożone kwestie prostym językiem.',
            'Konsultantka z doświadczeniem w branży ubezpieczeniowej i telekomunikacyjnej. Buduje długofalowe relacje z klientami i dba o ich satysfakcję.',
            'Prowadziła obsługę klienta przez czat i media społecznościowe, w języku polskim i angielskim. Regularnie osiągała najlepsze wyniki NPS w zespole.',
            'Odpowiadała za wsparcie klientów biznesowych i eskalacje trudniejszych spraw. Dobrze odnajduje się w pracy zdalnej i komunikacji pisemnej.',
        ],
        'Księgowa' => [
            'Prowadziła pełną księgowość dla kilkunastu spółek z o.o. w biurze rachunkowym. Samodzielnie przygotowuje rozliczenia VAT i pracuje na co dzień w Comarch Optima.',
            'Księgowa z doświadczeniem w rozrachunkach i zamknięciach miesiąca. Rozlicza VAT, w tym transakcje wewnątrzwspólnotowe, i sprawnie korzysta z Optimy.',
            'Odpowiadała za księgowanie dokumentów kosztowych i przychodowych oraz deklaracje VAT. Wdrożyła w zespole obieg dokumentów w Optimie.',
            'Ma doświadczenie w księgowości firm handlowych i usługowych, w tym w ewidencji środków trwałych. Terminowo przygotowuje JPK i rozliczenia VAT.',
            'Prowadziła księgi dla klientów biura rachunkowego od dokumentu źródłowego po sprawozdanie. Bardzo dobrze zna Comarch Optima i przepisy o VAT.',
            'Specjalizuje się w rozliczeniach VAT i uzgadnianiu sald z kontrahentami. Pracowała w Optimie przy obsłudze kilkudziesięciu klientów jednocześnie.',
            'Księgowa z doświadczeniem w spółkach produkcyjnych, odpowiedzialna za rozrachunki i rozliczenia podatkowe. Dokładna, dobrze zorganizowana w okresie zamknięć.',
            'Wspierała głównego księgowego przy bilansie i audycie, prowadząc ewidencję VAT. Automatyzuje powtarzalne księgowania w Optimie.',
        ],
        'Analityczka danych' => [
            'Budowała raporty sprzedażowe i dashboardy w Power BI, a dane przygotowywała w SQL. Potrafi zamienić liczby w konkretne rekomendacje dla biznesu.',
            'Analityczka danych z doświadczeniem w e-commerce, analizowała ścieżki klientów i wyniki kampanii. Swobodnie pisze zapytania SQL i modele w Excelu.',
            'Odpowiadała za raportowanie zarządcze w centrum usług wspólnych, automatyzując zestawienia w SQL. Prezentuje wyniki menedżerom w przystępnej formie.',
            'Prowadziła analizy danych produktowych i testy A/B we współpracy z zespołem produktu. Sprawnie łączy dane z wielu źródeł za pomocą SQL i Excela.',
            'Zbudowała od podstaw hurtownię raportów dla działu finansów, skracając czas przygotowania raportu miesięcznego o połowę. Dobrze zna SQL i zaawansowany Excel.',
            'Analizowała dane operacyjne i logistyczne, szukając oszczędności kosztowych. Tworzy czytelne wizualizacje i dokumentuje swoje analizy.',
            'Ma doświadczenie w analizie danych klientów w banku, w tym segmentacji i prognozowaniu odejść. Pisze wydajne zapytania SQL na dużych zbiorach danych.',
            'Łączy analizę danych z dobrą komunikacją – regularnie prowadziła warsztaty z interpretacji raportów. Pracuje w SQL, Excelu i narzędziach BI.',
        ],
        'Specjalistka ds. marketingu' => [
            'Planowała i prowadziła kampanie marketingowe dla marek konsumenckich, od strategii po raport wyników. Tworzy treści do mediów społecznościowych, które realnie zwiększały zasięgi.',
            'Specjalistka ds. marketingu internetowego z doświadczeniem w e-commerce. Prowadziła profile marki w social mediach i pisała teksty na stronę oraz do newslettera.',
            'Odpowiadała za komunikację marki w mediach społecznościowych i współpracę z twórcami. Świetnie pisze – od krótkich postów po artykuły eksperckie.',
            'Prowadziła kampanie płatne i organiczne, samodzielnie przygotowując copy i harmonogram publikacji. Analizuje wyniki działań i optymalizuje budżet.',
            'Budowała strategię contentową dla firmy B2B i prowadziła bloga eksperckiego. Ma doświadczenie w social mediach i marketingu automation.',
            'Marketerka z doświadczeniem w agencji, obsługiwała kilka marek jednocześnie. Tworzy spójne komunikaty i kampanie w mediach społecznościowych.',
            'Przygotowywała kampanie produktowe i premiery, koordynując pracę grafików i copywriterów. Pisze angażujące treści i dba o spójny ton marki.',
            'Rozwinęła kanały social media marki od zera do kilkudziesięciu tysięcy obserwujących. Łączy kreatywne podejście z pracą na danych.',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const array COMPANY_DESCRIPTIONS = [
        'Zielone Biuro' => 'Zielone Biuro świadczy usługi HR i księgowe dla małych i średnich firm z Wielkopolski. Większość zespołu pracuje zdalnie lub na część etatu, a godziny pracy ustalamy indywidualnie. Po powrocie z urlopu każda osoba dostaje miesiąc spokojnego wdrożenia.',
        'Kamienica Studio' => 'Kamienica Studio to pracownia projektowania wnętrz z siedzibą w poznańskiej kamienicy. Pracujemy hybrydowo, spotkania z klientami planujemy wyłącznie przed 15:00. Chętnie zatrudniamy na 3/4 etatu.',
        'Nadrzeczna Fintech' => 'Nadrzeczna Fintech rozwija aplikację płatniczą dla małych firm, z biurem nad Wisłą w Krakowie. Zespół pracuje w modelu remote-first z grafikiem ustalanym z miesięcznym wyprzedzeniem. Na rozmowach pytamy wyłącznie o doświadczenie i kompetencje.',
        'Biuro Rachunkowe Warta' => 'Biuro Rachunkowe Warta od 15 lat prowadzi księgowość firm z okolic Poznania. Oferujemy pracę hybrydową i dofinansowanie do opieki nad dziećmi. W okresie rozliczeń wspieramy się w zespole, by nikt nie zostawał po godzinach.',
        'Północ Logistyka' => 'Północ Logistyka to operator logistyczny z centrum dystrybucyjnym w Gdańsku. Pracujemy w biurze, ale część stanowisk administracyjnych umożliwia ruchomy start pracy. Wspieramy powroty do pracy indywidualnym planem wdrożenia.',
    ];

    /**
     * Distance (km) from each company's office to the nearest nursery or kindergarten, as employers would enter it.
     *
     * @var array<string, int>
     */
    private const array NURSERY_DISTANCES_KM = [
        'Biuro Rachunkowe Warta' => 2,
        'Kamienica Studio' => 1,
        'Zielone Biuro' => 3,
        'Północ Logistyka' => 6,
        'Nadrzeczna Fintech' => 2,
        'Wawelski Software House' => 1,
        'Wiślany Bank – Centrum Usług Wspólnych' => 4,
        'Koszyk Online' => 3,
        'Fundacja Dobry Start' => 1,
    ];

    /**
     * Demo company left unverified, so the admin can show the verification flow.
     */
    private const string UNVERIFIED_DEMO_COMPANY = 'Północ Logistyka';

    public function run(): void
    {
        $this->polishCandidateSummaries();
        $this->polishExistingCompanies();
        $this->seedKrakowCompanies();
        $this->seedNurseryDistances();
        $this->verifyDemoCompanies();
    }

    /**
     * Marks the demo companies as verified by the admin, except one left waiting for verification.
     */
    private function verifyDemoCompanies(): void
    {
        $admin = User::query()->where('email', 'admin@mumjobs.test')->first();

        Company::query()
            ->whereNull('verified_at')
            ->where('name', '!=', self::UNVERIFIED_DEMO_COMPANY)
            ->update(['verified_at' => now(), 'verified_by_user_id' => $admin?->id]);
    }

    /**
     * Fills the nursery/kindergarten distance of onsite and hybrid offers that do not have it yet.
     */
    private function seedNurseryDistances(): void
    {
        JobOffer::query()
            ->with('company:id,name')
            ->whereIn('work_mode', [WorkMode::Onsite, WorkMode::Hybrid])
            ->whereNull('nursery_distance_km')
            ->each(function (JobOffer $offer): void {
                $distance = self::NURSERY_DISTANCES_KM[$offer->company->name] ?? null;

                if ($distance !== null) {
                    $offer->update(['nursery_distance_km' => $distance]);
                }
            });
    }

    /**
     * Replaces faker (latin) summaries of generated candidates with realistic Polish ones.
     */
    private function polishCandidateSummaries(): void
    {
        $headlineCounters = [];

        $profiles = CandidateProfile::query()
            ->with(['user', 'confirmedSkills'])
            ->oldest('id')
            ->get();

        foreach ($profiles as $profile) {
            if ($profile->user === null || Str::endsWith($profile->user->email, '@mumjobs.test')) {
                continue;
            }

            if (! $this->looksLikeFakerText($profile->ai_summary)) {
                continue;
            }

            $headline = (string) $profile->headline;
            $counter = $headlineCounters[$headline] ?? 0;
            $headlineCounters[$headline] = $counter + 1;

            $profile->ai_summary = $this->summaryFor($profile, $headline, $counter);

            if ($profile->preferred_day_part === null) {
                $profile->years_of_experience = 2 + (($profile->id * 7) % 11);
                $profile->city = self::CITIES[($profile->id * 3) % count(self::CITIES)];
            }

            $profile->save();
        }
    }

    private function looksLikeFakerText(?string $summary): bool
    {
        if ($summary === null || trim($summary) === '') {
            return true;
        }

        return preg_match('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', $summary) !== 1;
    }

    private function summaryFor(CandidateProfile $profile, string $headline, int $counter): string
    {
        $templates = self::SUMMARIES[$headline] ?? null;

        if ($templates !== null) {
            return $templates[$counter % count($templates)];
        }

        $skillNames = $profile->confirmedSkills->pluck('name')->map(fn (string $name): string => Str::lower($name))->values()->all();

        if ($skillNames === []) {
            return "Doświadczona specjalistka na stanowisku: {$headline}. Pracuje samodzielnie, dobrze organizuje swój czas i współpracę w zespole.";
        }

        $lastSkill = array_pop($skillNames);
        $skillsSentence = $skillNames === [] ? $lastSkill : implode(', ', $skillNames).' oraz '.$lastSkill;

        return "Ma kilkuletnie doświadczenie na stanowisku: {$headline}. Jej mocne strony to {$skillsSentence}, a w zespole ceniono ją za samodzielność i dobrą komunikację.";
    }

    /**
     * Adds valid unique NIPs and descriptions to companies created by DemoSeeder.
     */
    private function polishExistingCompanies(): void
    {
        $nipPrefixes = ['778134250', '779202113', '676241087', '777301562', '583317204'];

        foreach (array_keys(self::COMPANY_DESCRIPTIONS) as $index => $name) {
            $company = Company::firstWhere('name', $name);

            if ($company === null) {
                continue;
            }

            $company->description = self::COMPANY_DESCRIPTIONS[$name];

            if ($company->nip === null) {
                $company->nip = $this->uniqueNip($nipPrefixes[$index]);
            }

            $company->save();
        }
    }

    /**
     * Four Kraków companies with employer accounts, approved reviews and published offers.
     */
    private function seedKrakowCompanies(): void
    {
        foreach ($this->krakowCompanies() as $definition) {
            if (Company::query()->where('name', $definition['name'])->exists() || User::query()->where('email', $definition['email'])->exists()) {
                continue;
            }

            $company = Company::create([
                'name' => $definition['name'],
                'nip' => $this->uniqueNip($definition['nip_prefix']),
                'city' => 'Kraków',
                'description' => $definition['description'],
            ]);

            User::factory()->employer($company)->create([
                'name' => $definition['recruiter'],
                'email' => $definition['email'],
            ]);

            foreach ($definition['reviews'] as [$return, $flexibility, $noQuestions, $quote, $label]) {
                CompanyReview::create([
                    'company_id' => $company->id,
                    'rating_return' => $return,
                    'rating_flexibility' => $flexibility,
                    'rating_no_pregnancy_questions' => $noQuestions,
                    'quote' => $quote,
                    'author_label' => $label,
                    'status' => ReviewStatus::Approved,
                ]);
            }

            foreach ($definition['offers'] as $offerDefinition) {
                $this->createOffer($company, $offerDefinition);
            }
        }
    }

    /**
     * @param  array{title: string, mode: WorkMode, fraction: EmploymentFraction, salary: array{int, int}, start: string, flexible: bool, meetings: bool, childcare: bool, description: string, required: list<string>, nice: list<string>}  $definition
     */
    private function createOffer(Company $company, array $definition): void
    {
        $offer = new JobOffer([
            'title' => $definition['title'],
            'city' => 'Kraków',
            'work_mode' => $definition['mode'],
            'employment_fraction' => $definition['fraction'],
            'salary_min' => $definition['salary'][0],
            'salary_max' => $definition['salary'][1],
            'start_date' => $definition['start'],
            'description' => $definition['description'],
            'flexible_hours' => $definition['flexible'],
            'fixed_meeting_hours' => $definition['meetings'],
            'childcare_subsidy' => $definition['childcare'],
            'status' => OfferStatus::Published,
            'published_at' => now(),
        ]);
        $offer->company_id = $company->id;
        $offer->save();

        foreach ($definition['required'] as $skillName) {
            $offer->skills()->attach(Skill::findOrCreateByName($skillName)->id, ['importance' => SkillImportance::Required->value]);
        }

        foreach ($definition['nice'] as $skillName) {
            $offer->skills()->attach(Skill::findOrCreateByName($skillName)->id, ['importance' => SkillImportance::NiceToHave->value]);
        }
    }

    /**
     * Builds a valid NIP (mod-11 checksum, see App\Rules\ValidNip) from a 9-digit prefix that is not used yet.
     */
    private function uniqueNip(string $prefix): string
    {
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $base = (int) $prefix;

        while (true) {
            $digits = str_pad((string) $base, 9, '0', STR_PAD_LEFT);
            $checksum = 0;

            foreach ($weights as $index => $weight) {
                $checksum += (int) $digits[$index] * $weight;
            }

            $control = $checksum % 11;
            $nip = $digits.$control;

            if ($control !== 10 && ! Company::query()->where('nip', $nip)->exists()) {
                return $nip;
            }

            $base++;
        }
    }

    /**
     * @return list<array{name: string, nip_prefix: string, email: string, recruiter: string, description: string, reviews: list<array{int, int, int, string, string}>, offers: list<array{title: string, mode: WorkMode, fraction: EmploymentFraction, salary: array{int, int}, start: string, flexible: bool, meetings: bool, childcare: bool, description: string, required: list<string>, nice: list<string>}>}>
     */
    private function krakowCompanies(): array
    {
        return [
            [
                'name' => 'Wawelski Software House',
                'nip_prefix' => '676259813',
                'email' => 'hr@wawelskisoftware.test',
                'recruiter' => 'Agata Nowicka',
                'description' => 'Wawelski Software House tworzy aplikacje webowe i mobilne dla klientów z Europy, z biurem na krakowskim Zabłociu. Pracujemy w modelu hybrydowym z elastycznym startem dnia między 7:00 a 10:00. Spotkania zespołowe kończymy przed 15:00, a część etatu to u nas standard, nie wyjątek.',
                'reviews' => [
                    [5, 5, 5, '„Wróciłam na 3/4 etatu i nikt nie robił z tego problemu – zespół sam przesunął daily na 9:30.”', 'Mama jednego dziecka, IT'],
                    [4, 5, 5, '„Na rozmowie pytano wyłącznie o projekty i doświadczenie. Elastyczne godziny działają naprawdę.”', 'Mama dwójki, zarządzanie projektami'],
                ],
                'offers' => [
                    [
                        'title' => 'Specjalistka ds. rekrutacji IT',
                        'mode' => WorkMode::Hybrid,
                        'fraction' => EmploymentFraction::ThreeQuarters,
                        'salary' => [9500, 12500],
                        'start' => '2027-09-01',
                        'flexible' => true,
                        'meetings' => true,
                        'childcare' => false,
                        'description' => 'Prowadzenie rekrutacji developerów i testerów we współpracy z liderami zespołów, dbanie o doświadczenie kandydatów i onboarding nowych osób. Biuro na Zabłociu, w biurze 2 dni w tygodniu.',
                        'required' => ['Rekrutacja IT', 'Onboarding'],
                        'nice' => ['Employer branding', 'Język angielski'],
                    ],
                    [
                        'title' => 'Koordynatorka projektów IT',
                        'mode' => WorkMode::Remote,
                        'fraction' => EmploymentFraction::ThreeFifths,
                        'salary' => [9000, 11500],
                        'start' => '2027-10-01',
                        'flexible' => true,
                        'meetings' => true,
                        'childcare' => false,
                        'description' => 'Koordynacja dwóch zespołów developerskich pracujących w Scrumie, kontakt z klientami z Niemiec i Skandynawii, prowadzenie backlogu w Jirze. Praca w pełni zdalna, spotkania z klientem do 15:00.',
                        'required' => ['Zarządzanie projektami', 'Scrum', 'Jira'],
                        'nice' => ['Komunikacja z klientem', 'Język angielski'],
                    ],
                ],
            ],
            [
                'name' => 'Wiślany Bank – Centrum Usług Wspólnych',
                'nip_prefix' => '677312458',
                'email' => 'kariera@wislanybank.test',
                'recruiter' => 'Zespół Kariery Wiślany Bank',
                'description' => 'Centrum Usług Wspólnych Wiślanego Banku w Krakowie obsługuje procesy finansowe, kadrowe i analityczne dla całej grupy. Oferujemy pracę hybrydową, ruchomy czas pracy i dofinansowanie do żłobka lub przedszkola. Program powrotów zapewnia mentora i stopniowe zwiększanie zakresu obowiązków.',
                'reviews' => [
                    [4, 4, 5, '„Dofinansowanie do przedszkola i ruchomy start dnia bardzo ułatwiły mi powrót.”', 'Mama jednego dziecka, finanse'],
                    [4, 3, 5, '„Procedury bywają długie, ale nikt nie pytał mnie o plany rodzinne.”', 'Mama dwójki, kadry i płace'],
                ],
                'offers' => [
                    [
                        'title' => 'Specjalistka ds. kadr i płac',
                        'mode' => WorkMode::Hybrid,
                        'fraction' => EmploymentFraction::Full,
                        'salary' => [8500, 10500],
                        'start' => '2027-07-01',
                        'flexible' => true,
                        'meetings' => false,
                        'childcare' => true,
                        'description' => 'Naliczanie wynagrodzeń dla spółek grupy, prowadzenie dokumentacji kadrowej i rozliczeń z ZUS. Ruchomy czas pracy między 7:00 a 17:00, 3 dni pracy zdalnej w tygodniu.',
                        'required' => ['Kadry i płace', 'Prawo pracy'],
                        'nice' => ['Excel', 'SAP'],
                    ],
                    [
                        'title' => 'Księgowa ds. rozrachunków',
                        'mode' => WorkMode::Hybrid,
                        'fraction' => EmploymentFraction::ThreeQuarters,
                        'salary' => [8000, 10000],
                        'start' => '2027-08-01',
                        'flexible' => true,
                        'meetings' => false,
                        'childcare' => true,
                        'description' => 'Księgowanie faktur kosztowych, uzgadnianie sald i przygotowanie rozliczeń VAT dla spółek grupy. Wsparcie mentora przez pierwsze trzy miesiące.',
                        'required' => ['Księgowość', 'Rozliczenia VAT'],
                        'nice' => ['SAP', 'Excel', 'Język angielski'],
                    ],
                    [
                        'title' => 'Analityczka danych – raportowanie',
                        'mode' => WorkMode::Remote,
                        'fraction' => EmploymentFraction::ThreeQuarters,
                        'salary' => [12000, 15500],
                        'start' => '2027-11-01',
                        'flexible' => true,
                        'meetings' => true,
                        'childcare' => true,
                        'description' => 'Budowa raportów zarządczych i dashboardów dla działów finansów i operacji, automatyzacja zestawień w SQL. Praca zdalna, spotkania zespołu we wtorki i czwartki rano.',
                        'required' => ['Analiza danych', 'SQL', 'Excel'],
                        'nice' => ['Język angielski'],
                    ],
                ],
            ],
            [
                'name' => 'Koszyk Online',
                'nip_prefix' => '675148392',
                'email' => 'ludzie@koszykonline.test',
                'recruiter' => 'Zespół People Koszyk Online',
                'description' => 'Koszyk Online to krakowski sklep internetowy z produktami dla domu, obsługujący klientów w całej Polsce. Większość stanowisk jest w pełni zdalna, a grafik ustalamy z wyprzedzeniem i w porozumieniu z zespołem. Chętnie łączymy etaty w modelu part-time.',
                'reviews' => [
                    [5, 4, 4, '„Pracuję na pół etatu zdalnie, grafik znam z miesięcznym wyprzedzeniem.”', 'Mama jednego dziecka, obsługa klienta'],
                ],
                'offers' => [
                    [
                        'title' => 'Specjalistka ds. marketingu',
                        'mode' => WorkMode::Remote,
                        'fraction' => EmploymentFraction::ThreeFifths,
                        'salary' => [7500, 9500],
                        'start' => '2027-09-01',
                        'flexible' => true,
                        'meetings' => false,
                        'childcare' => false,
                        'description' => 'Planowanie kampanii sezonowych, prowadzenie profili marki w mediach społecznościowych i tworzenie treści na stronę oraz do newslettera.',
                        'required' => ['Marketing', 'Social media'],
                        'nice' => ['Copywriting', 'Grafika'],
                    ],
                    [
                        'title' => 'Konsultantka obsługi klienta',
                        'mode' => WorkMode::Remote,
                        'fraction' => EmploymentFraction::Half,
                        'salary' => [4200, 5200],
                        'start' => '2027-08-01',
                        'flexible' => true,
                        'meetings' => false,
                        'childcare' => false,
                        'description' => 'Obsługa zamówień, zwrotów i reklamacji przez czat i e-mail. Praca zdalna na pół etatu, grafik ustalany z miesięcznym wyprzedzeniem.',
                        'required' => ['Obsługa klienta', 'Komunikacja z klientem'],
                        'nice' => ['Język angielski', 'Język niemiecki'],
                    ],
                ],
            ],
            [
                'name' => 'Fundacja Dobry Start',
                'nip_prefix' => '676402917',
                'email' => 'kadry@dobrystart.test',
                'recruiter' => 'Biuro Fundacji Dobry Start',
                'description' => 'Fundacja Dobry Start realizuje programy aktywizacji zawodowej w Małopolsce we współpracy z samorządami. Pracujemy hybrydowo w stałych godzinach 8:00–16:00, z możliwością wcześniejszego wyjścia. Oferujemy dofinansowanie do opieki nad dziećmi i pracę na część etatu.',
                'reviews' => [
                    [5, 4, 5, '„Bardzo ludzkie podejście – po powrocie mogłam wybrać 3/4 etatu i stałe godziny.”', 'Mama dwójki, HR'],
                ],
                'offers' => [
                    [
                        'title' => 'HR Business Partner',
                        'mode' => WorkMode::Hybrid,
                        'fraction' => EmploymentFraction::ThreeQuarters,
                        'salary' => [8000, 10000],
                        'start' => '2027-09-15',
                        'flexible' => false,
                        'meetings' => true,
                        'childcare' => true,
                        'description' => 'Wsparcie koordynatorów programów w sprawach kadrowych, wdrażanie nowych pracowników i organizacja szkoleń wewnętrznych. Stałe godziny pracy, w biurze 2 dni w tygodniu.',
                        'required' => ['HR Business Partnering', 'Prawo pracy'],
                        'nice' => ['Onboarding', 'Szkolenia'],
                    ],
                ],
            ],
        ];
    }
}
