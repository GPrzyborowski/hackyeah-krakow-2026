package com.example.momjobs.ui.viewmodels

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.momjobs.data.local.entities.JobAd
import com.example.momjobs.data.repository.MomjobsRepository
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

class JobViewModel(private val repository: MomjobsRepository) : ViewModel() {

    val allJobAds: StateFlow<List<JobAd>> = repository.getAllJobAds()
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    fun postJob(title: String, company: String, description: String, requirements: String, location: String, salary: String?) {
        viewModelScope.launch {
            val jobAd = JobAd(
                title = title,
                companyName = company,
                description = description,
                requirements = requirements,
                location = location,
                salary = salary
            )
            repository.postJobAd(jobAd)
        }
    }
}

class JobViewModelFactory(private val repository: MomjobsRepository) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(JobViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return JobViewModel(repository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
