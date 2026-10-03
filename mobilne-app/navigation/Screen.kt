package com.example.momjobs.navigation

sealed class Screen(val route: String) {
    object Home : Screen("home")
    object Blog : Screen("blog")
    object Swipe : Screen("swipe")
    object Profile : Screen("profile")
    object JobListings : Screen("job_listings")
    object CandidateRegistration : Screen("candidate_registration")
    object PostJobAd : Screen("post_job_ad")
    object AiAssistant : Screen("ai_assistant")
    object JobDetail : Screen("job_detail/{jobId}") {
        fun createRoute(jobId: Int) = "job_detail/$jobId"
    }
}
