package com.example.momjobs.data.local.entities

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "employer_reviews")
data class Review(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val companyName: String,
    val reviewerName: String,
    val rating: Int, // 1-5
    val comment: String,
    val isMaternityFriendly: Boolean,
    val date: Long = System.currentTimeMillis()
)
