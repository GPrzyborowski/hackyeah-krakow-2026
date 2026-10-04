package com.example.momjobs.data.repository

import com.example.momjobs.data.local.dao.CandidateDao
import com.example.momjobs.data.local.dao.JobDao
import com.example.momjobs.data.local.dao.ReviewDao
import com.example.momjobs.data.local.dao.InvitationDao
import com.example.momjobs.data.local.entities.CandidateProfile
import com.example.momjobs.data.local.entities.JobAd
import com.example.momjobs.data.local.entities.Review
import com.example.momjobs.data.local.entities.Invitation
import com.example.momjobs.data.remote.GeminiApi
import com.example.momjobs.data.remote.models.Content
import com.example.momjobs.data.remote.models.GeminiRequest
import com.example.momjobs.data.remote.models.Part
import kotlinx.coroutines.flow.Flow

class MomjobsRepository(
    private val candidateDao: CandidateDao,
    private val jobDao: JobDao,
    private val reviewDao: ReviewDao,
    private val invitationDao: InvitationDao,
    private val geminiApi: GeminiApi? = null
) {
    fun getCandidateProfile(): Flow<CandidateProfile?> = candidateDao.getCandidateProfile()

    suspend fun saveCandidateProfile(profile: CandidateProfile) = candidateDao.insertCandidateProfile(profile)

    fun getAllJobAds(): Flow<List<JobAd>> = jobDao.getAllJobAds()

    suspend fun postJobAd(jobAd: JobAd) = jobDao.insertJobAd(jobAd)

    fun getReviewsForCompany(companyName: String): Flow<List<Review>> = reviewDao.getReviewsForCompany(companyName)

    suspend fun addReview(review: Review) = reviewDao.insertReview(review)

    suspend fun getAverageRating(companyName: String): Float? = reviewDao.getAverageRating(companyName)

    fun getAllInvitations(): Flow<List<Invitation>> = invitationDao.getAllInvitations()

    fun getNewInvitationsCount(): Flow<Int> = invitationDao.getNewInvitationsCount()

    suspend fun addInvitation(invitation: Invitation) = invitationDao.insertInvitation(invitation)

    suspend fun getAiResponse(prompt: String, apiKey: String): String? {
        val request = GeminiRequest(
            contents = listOf(
                Content(parts = listOf(Part(text = prompt)))
            )
        )
        return try {
            val response = geminiApi?.generateContent(apiKey, request)
            response?.candidates?.firstOrNull()?.content?.parts?.firstOrNull()?.text
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }
}
