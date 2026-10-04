package com.example.momjobs.data.local.dao

import androidx.room.*
import com.example.momjobs.data.local.entities.Invitation
import kotlinx.coroutines.flow.Flow

@Dao
interface InvitationDao {
    @Query("SELECT * FROM invitations ORDER BY date DESC")
    fun getAllInvitations(): Flow<List<Invitation>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertInvitation(invitation: Invitation)

    @Query("SELECT COUNT(*) FROM invitations WHERE isNew = 1")
    fun getNewInvitationsCount(): Flow<Int>
}
