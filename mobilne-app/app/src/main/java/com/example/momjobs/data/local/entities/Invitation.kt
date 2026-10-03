package com.example.momjobs.data.local.entities

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "invitations")
data class Invitation(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val companyName: String,
    val date: Long = System.currentTimeMillis(),
    val isNew: Boolean = true
)
