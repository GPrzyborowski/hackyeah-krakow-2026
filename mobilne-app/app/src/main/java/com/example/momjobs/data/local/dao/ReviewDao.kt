package com.example.momjobs.data.local.dao

import androidx.room.*
import com.example.momjobs.data.local.entities.Review
import kotlinx.coroutines.flow.Flow

@Dao
interface ReviewDao {
    @Query("SELECT * FROM employer_reviews WHERE companyName = :companyName")
    fun getReviewsForCompany(companyName: String): Flow<List<Review>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertReview(review: Review)

    @Query("SELECT AVG(rating) FROM employer_reviews WHERE companyName = :companyName")
    suspend fun getAverageRating(companyName: String): Float?
}
