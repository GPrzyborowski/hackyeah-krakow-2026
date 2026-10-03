package com.example.momjobs

import android.app.Application
import androidx.room.Room
import com.example.momjobs.data.local.AppDatabase
import com.example.momjobs.data.local.TokenManager
import com.example.momjobs.data.local.entities.JobAd
import com.example.momjobs.data.local.entities.Review
import com.example.momjobs.data.local.entities.Invitation
import com.example.momjobs.data.remote.ApiClient
import com.example.momjobs.data.remote.AuthInterceptor
import com.example.momjobs.data.remote.GeminiApi
import com.example.momjobs.data.repository.AuthRepository
import com.example.momjobs.data.repository.MomjobsRepository
import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory

class MomjobsApplication : Application() {
    lateinit var database: AppDatabase
    lateinit var repository: MomjobsRepository
    lateinit var tokenManager: TokenManager
    lateinit var authRepository: AuthRepository
    private val applicationScope = CoroutineScope(SupervisorJob() + Dispatchers.Main)

    override fun onCreate() {
        super.onCreate()
        database = Room.databaseBuilder(
            applicationContext,
            AppDatabase::class.java,
            AppDatabase.DATABASE_NAME
        )
        .fallbackToDestructiveMigration()
        .build()

        tokenManager = TokenManager(this)
        val authInterceptor = AuthInterceptor(tokenManager)
        val authApi = ApiClient.createAuthApi(tokenManager, authInterceptor)
        authRepository = AuthRepository(authApi, tokenManager)
        authInterceptor.setOnUnauthorizedListener {
            authRepository.onUnauthorized()
        }

        val moshi = Moshi.Builder()
            .add(KotlinJsonAdapterFactory())
            .build()

        val retrofit = Retrofit.Builder()
            .baseUrl("https://generativelanguage.googleapis.com/")
            .addConverterFactory(MoshiConverterFactory.create(moshi))
            .build()

        val geminiApi = retrofit.create(GeminiApi::class.java)
        
        repository = MomjobsRepository(
            database.candidateDao(),
            database.jobDao(),
            database.reviewDao(),
            database.invitationDao(),
            geminiApi
        )

        applicationScope.launch(Dispatchers.IO) {
            authRepository.restoreSession()

            val jobs = repository.getAllJobAds().first()
            if (jobs.isEmpty()) {
                val sampleJobs = listOf(
                    JobAd(
                        title = "Specjalistka ds. HR - zdalnie",
                        companyName = "Zielone Biuro",
                        description = "Poszukujemy osoby do wsparcia procesów rekrutacyjnych. Oferujemy pełną elastyczność i możliwość pracy z domu. Rozumiemy potrzeby rodziców.",
                        requirements = "Doświadczenie w rekrutacji, doskonała organizacja pracy, empatia.",
                        location = "Zdalnie",
                        salary = "6000 - 8000 PLN"
                    ),
                    JobAd(
                        title = "Koordynatorka projektów",
                        companyName = "Kamienica Studio",
                        description = "Dołącz do naszego kreatywnego zespołu. Oferujemy hojny urlop macierzyński (6 miesięcy płatne) i żłobek na miejscu.",
                        requirements = "Minimum 3 lata doświadczenia w zarządzaniu projektami, pasja do designu.",
                        location = "Warszawa",
                        salary = "10000 - 12000 PLN"
                    ),
                    JobAd(
                        title = "Księgowa na pół etatu",
                        companyName = "EcoFamily",
                        description = "Obsługa raportowania finansowego w wymiarze pół etatu. Idealne dla rodziców szukających równowagi między pracą a życiem prywatnym.",
                        requirements = "Znajomość programu Optima, samodzielność, 20 godzin tygodniowo.",
                        location = "Kraków / Hybrydowo",
                        salary = "5000 PLN (pół etatu)"
                    ),
                    JobAd(
                        title = "UX Designer (Zastępstwo)",
                        companyName = "ParentTech",
                        description = "Dołącz do zespołu projektowego na roczne zastępstwo. Duża szansa na stałe zatrudnienie po tym okresie.",
                        requirements = "Figma, badania użytkowników, 2+ lata doświadczenia.",
                        location = "Wrocław",
                        salary = "12000 - 15000 PLN"
                    )
                )
                sampleJobs.forEach { repository.postJobAd(it) }
                
                val sampleReviews = listOf(
                    Review(
                        companyName = "Zielone Biuro",
                        reviewerName = "Sonia J.",
                        rating = 5,
                        comment = "Niesamowita równowaga praca-dom. Naprawdę rozumieją potrzeby pracujących matek. Nigdy nie czułam presji wyboru między karierą a dzieckiem.",
                        isMaternityFriendly = true
                    ),
                    Review(
                        companyName = "Kamienica Studio",
                        reviewerName = "Emilia R.",
                        rating = 4,
                        comment = "Świetne benefity, choć tempo pracy bywa szybkie. Żłobek na miejscu to absolutne wybawienie!",
                        isMaternityFriendly = true
                    ),
                    Review(
                        companyName = "EcoFamily",
                        reviewerName = "Jessica W.",
                        rating = 5,
                        comment = "Bardzo elastyczne godziny pracy. Mogę uczestniczyć we wszystkich szkolnych wydarzeniach mojego syna bez żadnych problemów.",
                        isMaternityFriendly = true
                    )
                )
                sampleReviews.forEach { repository.addReview(it) }

                val sampleInvitations = listOf(
                    Invitation(companyName = "Zielone Biuro"),
                    Invitation(companyName = "Kamienica Studio")
                )
                sampleInvitations.forEach { repository.addInvitation(it) }
            }
        }
    }
}
