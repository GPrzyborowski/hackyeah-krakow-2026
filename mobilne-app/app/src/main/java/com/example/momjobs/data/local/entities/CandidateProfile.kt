package com.example.momjobs.data.local.entities

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "candidate_profiles")
data class CandidateProfile(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val name: String,
    val email: String,
    val bio: String,
    val availability: String,
    val returnToWorkDate: Long,
    val cvUri: String? = null
)
