package com.example.momjobs.data.local.dao

import androidx.room.*
import com.example.momjobs.data.local.entities.JobAd
import kotlinx.coroutines.flow.Flow

@Dao
interface JobDao {
    @Query("SELECT * FROM job_ads ORDER BY postedDate DESC")
    fun getAllJobAds(): Flow<List<JobAd>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertJobAd(jobAd: JobAd)

    @Delete
    suspend fun deleteJobAd(jobAd: JobAd)
}
