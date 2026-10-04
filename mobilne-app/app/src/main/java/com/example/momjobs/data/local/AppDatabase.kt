package com.example.momjobs.data.local

import androidx.room.Database
import androidx.room.RoomDatabase
import com.example.momjobs.data.local.dao.CandidateDao
import com.example.momjobs.data.local.dao.JobDao
import com.example.momjobs.data.local.dao.ReviewDao
import com.example.momjobs.data.local.dao.InvitationDao
import com.example.momjobs.data.local.entities.CandidateProfile
import com.example.momjobs.data.local.entities.JobAd
import com.example.momjobs.data.local.entities.Review
import com.example.momjobs.data.local.entities.Invitation

@Database(entities = [CandidateProfile::class, JobAd::class, Review::class, Invitation::class], version = 3, exportSchema = false)
abstract class AppDatabase : RoomDatabase() {
    abstract fun candidateDao(): CandidateDao
    abstract fun jobDao(): JobDao
    abstract fun reviewDao(): ReviewDao
    abstract fun invitationDao(): InvitationDao

    companion object {
        const val DATABASE_NAME = "wracam_db"
    }
}
