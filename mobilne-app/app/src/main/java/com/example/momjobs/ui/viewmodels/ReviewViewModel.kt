package com.example.momjobs.ui.viewmodels

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.momjobs.data.local.entities.Review
import com.example.momjobs.data.repository.MomjobsRepository
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.launch

class ReviewViewModel(private val repository: MomjobsRepository) : ViewModel() {

    fun getReviewsForCompany(companyName: String): Flow<List<Review>> {
        return repository.getReviewsForCompany(companyName)
    }

    fun addReview(companyName: String, reviewerName: String, rating: Int, comment: String, isMaternityFriendly: Boolean) {
        viewModelScope.launch {
            val review = Review(
                companyName = companyName,
                reviewerName = reviewerName,
                rating = rating,
                comment = comment,
                isMaternityFriendly = isMaternityFriendly
            )
            repository.addReview(review)
        }
    }
}

class ReviewViewModelFactory(private val repository: MomjobsRepository) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(ReviewViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return ReviewViewModel(repository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
