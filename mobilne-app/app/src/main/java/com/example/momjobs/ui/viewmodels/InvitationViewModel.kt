package com.example.momjobs.ui.viewmodels

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.momjobs.data.local.entities.Invitation
import com.example.momjobs.data.repository.MomjobsRepository
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

class InvitationViewModel(private val repository: MomjobsRepository) : ViewModel() {

    val allInvitations: StateFlow<List<Invitation>> = repository.getAllInvitations()
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    val newInvitationsCount: StateFlow<Int> = repository.getNewInvitationsCount()
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), 0)

    fun addInvitation(companyName: String) {
        viewModelScope.launch {
            repository.addInvitation(Invitation(companyName = companyName))
        }
    }
}

class InvitationViewModelFactory(private val repository: MomjobsRepository) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(InvitationViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return InvitationViewModel(repository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
