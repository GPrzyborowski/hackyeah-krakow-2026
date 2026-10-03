package com.example.momjobs.data.local.entities

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "job_ads")
data class JobAd(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val title: String,
    val companyName: String,
    val description: String,
    val requirements: String,
    val location: String,
    val salary: String? = null,
    val postedDate: Long = System.currentTimeMillis()
)
