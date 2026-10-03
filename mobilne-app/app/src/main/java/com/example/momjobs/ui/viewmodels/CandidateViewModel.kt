package com.example.momjobs.ui.viewmodels

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.momjobs.data.local.entities.CandidateProfile
import com.example.momjobs.data.repository.MomjobsRepository
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

class CandidateViewModel(private val repository: MomjobsRepository) : ViewModel() {

    val candidateProfile: StateFlow<CandidateProfile?> = repository.getCandidateProfile()
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), null)

    fun registerCandidate(name: String, email: String, bio: String, availability: String, returnToWorkDate: Long, cvUri: String?) {
        viewModelScope.launch {
            val profile = CandidateProfile(
                name = name,
                email = email,
                bio = bio,
                availability = availability,
                returnToWorkDate = returnToWorkDate,
                cvUri = cvUri
            )
            repository.saveCandidateProfile(profile)
        }
    }
}

class CandidateViewModelFactory(private val repository: MomjobsRepository) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(CandidateViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return CandidateViewModel(repository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
