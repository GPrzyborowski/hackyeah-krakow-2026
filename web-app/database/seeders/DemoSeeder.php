<?php

namespace Database\Seeders;

use App\Enums\CandidateDecisionType;
use App\Enums\DayPart;
use App\Enums\EmploymentFraction;
use App\Enums\JobSharePairStatus;
use App\Enums\OfferStatus;
use App\Enums\ReviewStatus;
use App\Enums\SkillImportance;
use App\Enums\SkillSource;
use App\Enums\UserRole;
use App\Enums\WorkMode;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo data mirroring the MomJobs design mockups: companies, offers, reviews and candidates.
 */
class DemoSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const array SKILLS = [
        'Rekrutacja IT' => ['rekrutacja techniczna', 'it recruitment', 'tech recruiter'],
        'Onboarding' => ['wdrożenie pracowników'],
        'Prawo pracy' => ['kodeks pracy', 'labour law'],
        'Employer branding' => ['marka pracodawcy'],
        'Excel' => ['ms excel', 'arkusze kalkulacyjne'],
        'Prowadzenie zespołu' => ['zarządzanie zespołem', 'team lead', 'kierowanie zespołem'],
        'Kadry i płace' => ['kadry', 'płace', 'payroll'],
        'Szkolenia' => ['trener', 'prowadzenie szkoleń'],
        'Zarządzanie projektami' => ['project management', 'kierownik projektu', 'pm'],
        'Scrum' => ['agile', 'scrum master'],
        'Jira' => [],
        'Komunikacja z klientem' => ['relacje z klientem', 'customer success'],
        'Obsługa klienta' => ['customer service', 'call center', 'helpdesk'],
        'Język angielski' => ['angielski', 'english'],
        'Język niemiecki' => ['niemiecki', 'german'],
        'Księgowość' => ['rachunkowość', 'accounting'],
        'Rozliczenia VAT' => ['vat', 'podatki'],
        'Optima' => ['comarch optima'],
        'SAP' => [],
        'Analiza danych' => ['data analysis', 'power bi', 'raportowanie'],
        'SQL' => ['bazy danych'],
        'Marketing' => ['marketing internetowy', 'digital marketing'],
        'Social media' => ['media społecznościowe', 'facebook ads'],
        'Copywriting' => ['pisanie treści', 'content'],
        'Grafika' => ['figma', 'canva', 'photoshop'],
        'Sprzedaż B2B' => ['sprzedaż', 'handlowiec', 'key account'],
        'Logistyka' => ['spedycja', 'transport'],
        'Administracja biurowa' => ['office manager', 'asystentka'],
        'HR Business Partnering' => ['hrbp', 'hr business partner'],
        'Testowanie oprogramowania' => ['qa', 'tester', 'testy manualne'],
    ];

    public function run(): void
    {
        $skills = collect(self::SKILLS)->map(fn (array $synonyms, string $name): Skill => Skill::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'synonyms' => $synonyms,
        ]));

        $companies = $this->seedCompanies();
        $this->seedOffers($companies, $skills);
        $this->seedDemoCandidate($skills);
        $this->seedRandomCandidates($skills);
        $this->seedDemoRecruitment();
        $this->seedAdmin();
        $this->seedPendingReviews();
        $this->seedJobSharing($skills);
    }

    /**
     * @return array<string, Company>
     */
    private function seedCompanies(): array
    {
        $definitions = [
            'Zielone Biuro' => ['city' => 'Poznań', 'email' => 'hr@zielonebiuro.test', 'reviews' => [
                [5, 4, 5, '„Po powrocie dostałam miesiąc na wdrożenie i nikt nie liczył mi nadgodzin.”', 'Mama dwójki, księgowość'],
                [4, 5, 5, '„Zespół sam zaproponował mi 3/5 etatu po urlopie.”', 'Mama jednego dziecka, HR'],
            ]],
            'Kamienica Studio' => ['city' => 'Poznań', 'email' => 'rekrutacja@kamienica.test', 'reviews' => [
                [4, 5, 4, '„Zdalnie od pierwszego dnia, a spotkania są przed 15:00.”', 'Mama jednego dziecka, projekty'],
            ]],
            'Nadrzeczna Fintech' => ['city' => 'Kraków', 'email' => 'people@nadrzeczna.test', 'reviews' => []],
            'Biuro Rachunkowe Warta' => ['city' => 'Swarzędz', 'email' => 'biuro@warta.test', 'reviews' => [
                [4, 4, 4, '„Dużo zrozumienia, ale okres rozliczeń jest ciężki.”', 'Mama jednego dziecka, księgowość'],
            ]],
            'Północ Logistyka' => ['city' => 'Gdańsk', 'email' => 'kadry@polnoc.test', 'reviews' => [
                [3, 3, 4, '„Dobry zespół, ale grafik zmieniano z dnia na dzień.”', 'Mama dwójki, obsługa klienta'],
            ]],
        ];

        $companies = [];

        foreach ($definitions as $name => $definition) {
            $company = Company::create([
                'name' => $name,
                'city' => $definition['city'],
                'description' => "{$name} to firma, która stawia na elastyczną pracę i powroty po urlopie rodzicielskim.",
            ]);

            User::factory()->create([
                'name' => 'Rekruter '.$name,
                'email' => $definition['email'],
                'role' => UserRole::Employer,
                'company_id' => $company->id,
            ]);

            foreach ($definition['reviews'] as [$return, $flexibility, $noQuestions, $quote, $label]) {
                CompanyReview::factory()->create([
                    'company_id' => $company->id,
                    'rating_return' => $return,
                    'rating_flexibility' => $flexibility,
                    'rating_no_pregnancy_questions' => $noQuestions,
                    'quote' => $quote,
                    'author_label' => $label,
                ]);
            }

            $companies[$name] = $company;
        }

        return $companies;
    }

    /**
     * @param  array<string, Company>  $companies
     * @param  Collection<string, Skill>  $skills
     */
    private function seedOffers(array $companies, $skills): void
    {
        $offers = [
            ['Zielone Biuro', 'Specjalistka ds. rekrutacji', 'Poznań', WorkMode::Remote, EmploymentFraction::ThreeFifths, 8500, 11000, '2027-09-01', true, true, false,
                'Prowadzenie procesów rekrutacyjnych w zespołach IT, współpraca z menedżerami, onboarding nowych osób.',
                ['Rekrutacja IT', 'Onboarding', 'Prawo pracy'], ['Employer branding', 'Excel']],
            ['Zielone Biuro', 'Specjalistka ds. HR', 'Poznań', WorkMode::Remote, EmploymentFraction::ThreeFifths, 8000, 10500, '2027-09-01', true, true, true,
                'Obsługa procesów kadrowych, wsparcie menedżerów i rozwój programów szkoleniowych.',
                ['Kadry i płace', 'Prawo pracy'], ['Szkolenia', 'Excel']],
            ['Kamienica Studio', 'Koordynatorka projektów', 'Poznań', WorkMode::Hybrid, EmploymentFraction::ThreeQuarters, 9000, 12500, '2027-09-15', true, true, false,
                'Koordynacja projektów wnętrzarskich, kontakt z klientami i podwykonawcami. Spotkania przed 15:00.',
                ['Zarządzanie projektami', 'Komunikacja z klientem'], ['Jira', 'Scrum']],
            ['Nadrzeczna Fintech', 'Specjalistka ds. obsługi klienta', 'Kraków', WorkMode::Remote, EmploymentFraction::Half, 6200, 7800, '2027-08-01', false, true, false,
                'Wsparcie klientów aplikacji płatniczej przez czat i e-mail. Grafik ustalany z miesięcznym wyprzedzeniem.',
                ['Obsługa klienta', 'Język angielski'], ['Komunikacja z klientem']],
            ['Biuro Rachunkowe Warta', 'Księgowa', 'Swarzędz', WorkMode::Hybrid, EmploymentFraction::Full, 7000, 9000, '2027-09-01', false, false, true,
                'Prowadzenie pełnej księgowości dla klientów biura. Przedszkole 2 km od biura.',
                ['Księgowość', 'Rozliczenia VAT'], ['Optima', 'Excel']],
            ['Północ Logistyka', 'Specjalistka ds. kadr i płac', 'Gdańsk', WorkMode::Onsite, EmploymentFraction::Full, 7500, 9500, '2027-07-01', false, false, false,
                'Naliczanie wynagrodzeń, dokumentacja kadrowa i kontakt z ZUS.',
                ['Kadry i płace', 'Prawo pracy'], ['Excel', 'SAP']],
            ['Nadrzeczna Fintech', 'Analityczka danych', 'Kraków', WorkMode::Remote, EmploymentFraction::ThreeQuarters, 11000, 15000, '2027-10-01', true, true, true,
                'Raportowanie i analiza danych produktowych, współpraca z zespołem produktu.',
                ['Analiza danych', 'SQL'], ['Excel', 'Język angielski']],
        ];

        foreach ($offers as [$companyName, $title, $city, $mode, $fraction, $min, $max, $start, $flexible, $meetings, $childcare, $description, $required, $niceToHave]) {
            $offer = JobOffer::create([
                'title' => $title,
                'city' => $city,
                'work_mode' => $mode,
                'employment_fraction' => $fraction,
                'salary_min' => $min,
                'salary_max' => $max,
                'start_date' => $start,
                'description' => $description,
                'flexible_hours' => $flexible,
                'fixed_meeting_hours' => $meetings,
                'childcare_subsidy' => $childcare,
                'status' => OfferStatus::Published,
                'published_at' => now(),
                'company_id' => $companies[$companyName]->id,
            ]);

            foreach ($required as $name) {
                $offer->skills()->attach($skills[$name]->id, ['importance' => SkillImportance::Required->value]);
            }

            foreach ($niceToHave as $name) {
                $offer->skills()->attach($skills[$name]->id, ['importance' => SkillImportance::NiceToHave->value]);
            }
        }
    }

    /**
     * @param  Collection<string, Skill>  $skills
     */
    private function seedDemoCandidate($skills): void
    {
        $user = User::factory()->create(['name' => 'Marta Kowalska', 'email' => 'marta@momjobs.test']);

        $profile = $user->candidateProfile()->create([
            'headline' => 'Specjalistka ds. rekrutacji',
            'years_of_experience' => 6,
            'city' => 'Poznań',
            'ai_summary' => 'Prowadziła rekrutacje techniczne w firmie programistycznej, wdrożyła proces onboardingu dla ponad 40 osób i zna prawo pracy w praktyce.',
            'due_date' => '2027-01-23',
            'leave_starts_on' => '2027-01-09',
            'available_from' => '2027-09-01',
            'work_modes' => [WorkMode::Remote->value, WorkMode::Hybrid->value],
            'employment_fractions' => [EmploymentFraction::ThreeFifths->value, EmploymentFraction::ThreeQuarters->value],
            'wants_flexible_hours' => true,
            'open_to_job_sharing' => true,
            'onboarding_step' => 4,
            'suggested_positions' => [
                ['title' => 'Specjalistka ds. rekrutacji', 'score' => 92],
                ['title' => 'HR Business Partner', 'score' => 81],
                ['title' => 'Koordynatorka ds. kadr', 'score' => 74],
                ['title' => 'Specjalistka ds. szkoleń', 'score' => 66],
            ],
            'published_at' => now(),
        ]);

        foreach (['Rekrutacja IT', 'Onboarding', 'Prawo pracy', 'Excel', 'Prowadzenie zespołu'] as $name) {
            $profile->skills()->attach($skills[$name]->id, ['source' => SkillSource::Ai->value, 'confirmed_at' => now()]);
        }
    }

    /**
     * @param  Collection<string, Skill>  $skills
     */
    private function seedRandomCandidates($skills): void
    {
        $profiles = [
            ['Specjalistka ds. rekrutacji', ['Rekrutacja IT', 'Onboarding', 'Prawo pracy', 'Employer branding']],
            ['HR Business Partner', ['HR Business Partnering', 'Prawo pracy', 'Onboarding', 'Szkolenia']],
            ['Specjalistka ds. kadr i płac', ['Kadry i płace', 'Prawo pracy', 'Excel']],
            ['Koordynatorka projektów', ['Zarządzanie projektami', 'Scrum', 'Jira', 'Komunikacja z klientem']],
            ['Konsultantka obsługi klienta', ['Obsługa klienta', 'Język angielski', 'Komunikacja z klientem']],
            ['Księgowa', ['Księgowość', 'Rozliczenia VAT', 'Optima', 'Excel']],
            ['Analityczka danych', ['Analiza danych', 'SQL', 'Excel', 'Język angielski']],
            ['Specjalistka ds. marketingu', ['Marketing', 'Social media', 'Copywriting', 'Grafika']],
        ];

        $firstNames = ['Anna', 'Karolina', 'Joanna', 'Ewa', 'Magdalena', 'Katarzyna', 'Agnieszka', 'Monika', 'Natalia', 'Aleksandra', 'Paulina', 'Justyna'];
        $lastNames = ['Wiśniewska', 'Sikora', 'Pawlak', 'Nowak', 'Zielińska', 'Lewandowska', 'Dąbrowska', 'Kamińska', 'Wójcik', 'Mazur'];
        $availability = ['2027-06-01', '2027-07-01', '2027-08-01', '2027-09-01', '2027-09-15', '2027-10-01', '2027-11-01'];

        for ($index = 0; $index < 48; $index++) {
            [$headline, $profileSkills] = $profiles[$index % count($profiles)];

            $user = User::factory()->create([
                'name' => $firstNames[$index % count($firstNames)].' '.$lastNames[$index % count($lastNames)],
            ]);

            $profile = CandidateProfile::factory()->published()->create([
                'user_id' => $user->id,
                'headline' => $headline,
                'available_from' => Carbon::parse($availability[$index % count($availability)]),
                'open_to_job_sharing' => $index % 3 === 0,
            ]);

            $chosenSkills = collect($profileSkills)->take(3)->merge(collect($profileSkills)->slice(3)->filter(fn (): bool => (bool) random_int(0, 1)));

            foreach ($chosenSkills as $name) {
                $profile->skills()->attach($skills[$name]->id, ['source' => SkillSource::Manual->value, 'confirmed_at' => now()]);
            }
        }
    }

    /**
     * Invitations and a conversation for Marta, so every demo screen has something to show.
     */
    private function seedDemoRecruitment(): void
    {
        $marta = User::firstWhere('email', 'marta@momjobs.test')->candidateProfile;
        $recruiterOffer = JobOffer::firstWhere('title', 'Specjalistka ds. rekrutacji');
        $projectOffer = JobOffer::firstWhere('title', 'Koordynatorka projektów');
        $hrOffer = JobOffer::firstWhere('title', 'Specjalistka ds. HR');
        $greenOfficeRecruiter = User::firstWhere('email', 'hr@zielonebiuro.test');
        $studioRecruiter = User::firstWhere('email', 'rekrutacja@kamienica.test');

        $acceptedInvitation = Invitation::create([
            'job_offer_id' => $recruiterOffer->id,
            'candidate_profile_id' => $marta->id,
            'sent_by_user_id' => $greenOfficeRecruiter->id,
            'message' => 'Dzień dobry! Szukamy specjalistki ds. rekrutacji IT na 3/5 etatu, zdalnie, 8 500–11 000 zł brutto. Godziny pracy ustalamy elastycznie. Chętnie porozmawiamy.',
        ]);
        CandidateDecision::create(['job_offer_id' => $recruiterOffer->id, 'candidate_profile_id' => $marta->id, 'decision' => CandidateDecisionType::Invited]);
        $conversation = $acceptedInvitation->accept();

        $messages = [
            [$greenOfficeRecruiter, 'Dziękujemy za przyjęcie zaproszenia! Czy pasowałaby Pani krótka rozmowa online w przyszłym tygodniu?'],
            [$marta->user, 'Dzień dobry, bardzo chętnie. Najlepiej pasują mi poranki, np. wtorek o 10:00.'],
            [$greenOfficeRecruiter, 'Wtorek 10:00 jest super. Wyślę link do spotkania. Start planujemy od 1 września 2027.'],
        ];

        foreach ($messages as $index => [$author, $body]) {
            $conversation->messages()->create([
                'user_id' => $author->id,
                'body' => $body,
                'created_at' => now()->subHours(count($messages) - $index),
            ]);
        }

        $conversation->update(['last_message_at' => now()->subHour()]);

        Invitation::create([
            'job_offer_id' => $projectOffer->id,
            'candidate_profile_id' => $marta->id,
            'sent_by_user_id' => $studioRecruiter->id,
            'message' => 'Dzień dobry! Koordynujemy projekty wnętrzarskie, 3/4 etatu hybrydowo, spotkania zawsze przed 15:00. Czy chciałaby Pani porozmawiać?',
        ]);
        CandidateDecision::create(['job_offer_id' => $projectOffer->id, 'candidate_profile_id' => $marta->id, 'decision' => CandidateDecisionType::Invited]);

        OfferInterest::create(['job_offer_id' => $hrOffer->id, 'candidate_profile_id' => $marta->id]);
    }

    private function seedAdmin(): void
    {
        User::factory()->admin()->create(['name' => 'Admin MomJobs', 'email' => 'admin@momjobs.test']);
    }

    /**
     * Two reviews waiting for moderation, so the admin panel has something to approve.
     */
    private function seedPendingReviews(): void
    {
        $authors = User::query()->where('role', UserRole::Candidate)->where('email', '!=', 'marta@momjobs.test')->oldest('id')->limit(2)->get();
        $reviews = [
            ['Nadrzeczna Fintech', [5, 5, 4, '„Na rozmowie pytano tylko o doświadczenie, a powrót na 3/4 etatu ustaliłyśmy od ręki.”', 'Mama jednego dziecka, analityka']],
            ['Północ Logistyka', [3, 2, 4, '„Ludzie życzliwi, ale elastyczne godziny są raczej na papierze.”', 'Mama dwójki, spedycja']],
        ];

        foreach ($authors as $index => $author) {
            [$companyName, [$return, $flexibility, $noQuestions, $quote, $label]] = $reviews[$index];

            CompanyReview::create([
                'company_id' => Company::firstWhere('name', $companyName)->id,
                'user_id' => $author->id,
                'rating_return' => $return,
                'rating_flexibility' => $flexibility,
                'rating_no_pregnancy_questions' => $noQuestions,
                'quote' => $quote,
                'author_label' => $label,
                'status' => ReviewStatus::Pending,
            ]);
        }
    }

    /**
     * Job-sharing demo: a two-person offer at Zielone Biuro and the Marta + Ewa pair from the mockup,
     * with the schedule accepted by Ewa only (Marta accepts and sends it during the demo).
     *
     * @param  Collection<string, Skill>  $skills
     */
    private function seedJobSharing($skills): void
    {
        $offer = JobOffer::create([
            'company_id' => Company::firstWhere('name', 'Zielone Biuro')->id,
            'title' => 'Specjalistka ds. rekrutacji – job sharing',
            'city' => 'Poznań',
            'work_mode' => WorkMode::Hybrid,
            'employment_fraction' => EmploymentFraction::Half,
            'salary_min' => 4500,
            'salary_max' => 5800,
            'start_date' => '2027-09-01',
            'description' => 'Jedno stanowisko, dwie osoby po 4 godziny. Rekrutacje IT i onboarding nowych osób – jedna z Was prowadzi poranne spotkania, druga popołudniowe. Podział dnia ustalacie same.',
            'flexible_hours' => true,
            'fixed_meeting_hours' => true,
            'childcare_subsidy' => true,
            'is_job_share' => true,
            'workday_starts_at' => '08:00',
            'workday_ends_at' => '16:00',
            'status' => OfferStatus::Published,
            'published_at' => now(),
        ]);
        $offer->skills()->attach([
            $skills['Rekrutacja IT']->id => ['importance' => SkillImportance::Required->value],
            $skills['Onboarding']->id => ['importance' => SkillImportance::Required->value],
            $skills['Employer branding']->id => ['importance' => SkillImportance::NiceToHave->value],
        ]);

        $marta = User::firstWhere('email', 'marta@momjobs.test')->candidateProfile;
        $marta->update([
            'open_to_job_sharing' => true,
            'preferred_day_part' => DayPart::Morning,
            'employment_fractions' => [EmploymentFraction::Half->value, EmploymentFraction::ThreeFifths->value, EmploymentFraction::ThreeQuarters->value],
        ]);

        $ewa = $this->jobSharingCandidate($skills, 'Ewa Nowak', 'ewa@momjobs.test', 'Specjalistka ds. rekrutacji i onboardingu', DayPart::Afternoon, ['Rekrutacja IT', 'Onboarding', 'Employer branding', 'Szkolenia']);

        $this->jobSharingCandidate($skills, 'Joanna Sikora', null, 'Rekruterka IT', DayPart::Afternoon, ['Rekrutacja IT', 'Employer branding', 'Język angielski']);
        $this->jobSharingCandidate($skills, 'Karolina Pawlak', null, 'HR generalistka', DayPart::Morning, ['Onboarding', 'Prawo pracy', 'Kadry i płace']);
        $this->jobSharingCandidate($skills, 'Natalia Mazur', null, 'Specjalistka ds. employer brandingu', DayPart::Any, ['Rekrutacja IT', 'Onboarding', 'Social media']);

        $pair = $offer->jobSharePairs()->create([
            'status' => JobSharePairStatus::Formed,
            'proposed_schedule' => [
                ['candidate_profile_id' => $marta->id, 'starts_at' => '08:00', 'ends_at' => '12:00'],
                ['candidate_profile_id' => $ewa->id, 'starts_at' => '12:00', 'ends_at' => '16:00'],
            ],
        ]);
        $pair->members()->attach([
            $marta->id => ['is_initiator' => true, 'accepted_at' => now()->subDays(2), 'schedule_confirmed_at' => null],
            $ewa->id => ['is_initiator' => false, 'accepted_at' => now()->subDays(2), 'schedule_confirmed_at' => now()->subMinutes(30)],
        ]);

        $messages = [
            [$marta->user, 'Mogę brać poranki. O 13:00 odbieram małą z żłobka.'],
            [$ewa->user, 'Super, ja wolę popołudnia. Biorę 12:00–16:00.'],
            [$marta->user, 'To zamieniamy się w środy, kiedy mam wizytę kontrolną?'],
        ];

        foreach ($messages as $index => [$author, $body]) {
            $pair->messages()->create([
                'user_id' => $author->id,
                'body' => $body,
                'created_at' => now()->subHours(count($messages) - $index),
            ]);
        }
    }

    /**
     * @param  Collection<string, Skill>  $skills
     * @param  list<string>  $skillNames
     */
    private function jobSharingCandidate($skills, string $name, ?string $email, string $headline, DayPart $dayPart, array $skillNames): CandidateProfile
    {
        $user = User::factory()->create(array_filter(['name' => $name, 'email' => $email]));

        $profile = CandidateProfile::factory()->published()->create([
            'user_id' => $user->id,
            'headline' => $headline,
            'years_of_experience' => 5,
            'ai_summary' => 'Od pięciu lat pracuje w obszarach: '.implode(', ', $skillNames).'. Dobrze odnajduje się we współpracy z zespołem i chętnie dzieli obowiązki na stanowisku w modelu job sharing.',
            'city' => 'Poznań',
            'available_from' => '2027-08-01',
            'work_modes' => [WorkMode::Hybrid->value, WorkMode::Remote->value],
            'employment_fractions' => [EmploymentFraction::Half->value],
            'open_to_job_sharing' => true,
            'preferred_day_part' => $dayPart,
        ]);

        foreach ($skillNames as $skillName) {
            $profile->skills()->attach($skills[$skillName]->id, ['source' => SkillSource::Manual->value, 'confirmed_at' => now()]);
        }

        return $profile;
    }
}
