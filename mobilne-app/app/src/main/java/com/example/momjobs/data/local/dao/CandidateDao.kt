package com.example.momjobs.data.local.dao

import androidx.room.*
import com.example.momjobs.data.local.entities.CandidateProfile
import kotlinx.coroutines.flow.Flow

@Dao
interface CandidateDao {
    @Query("SELECT * FROM candidate_profiles LIMIT 1")
    fun getCandidateProfile(): Flow<CandidateProfile?>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertCandidateProfile(profile: CandidateProfile)

    @Delete
    suspend fun deleteCandidateProfile(profile: CandidateProfile)
}
