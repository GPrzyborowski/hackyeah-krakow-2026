package com.example.momjobs.navigation

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.example.momjobs.ui.screens.*
import com.example.momjobs.ui.viewmodels.*

@Composable
fun MomjobsNavGraph(
    navController: NavHostController,
    candidateViewModel: CandidateViewModel,
    authViewModel: AuthViewModel,
    jobViewModel: JobViewModel,
    reviewViewModel: ReviewViewModel,
    aiAssistantViewModel: AiAssistantViewModel,
    invitationViewModel: InvitationViewModel,
    modifier: Modifier = Modifier
) {
    NavHost(
        navController = navController,
        startDestination = Screen.Home.route,
        modifier = modifier
    ) {
        composable(Screen.Home.route) {
            HomeScreen(
                candidateViewModel = candidateViewModel,
                authViewModel = authViewModel,
                jobViewModel = jobViewModel,
                invitationViewModel = invitationViewModel,
                onNavigateToRegistration = { navController.navigate(Screen.CandidateRegistration.route) },
                onNavigateToLogin = { navController.navigate(Screen.Login.route) },
                onNavigateToPostJob = { navController.navigate(Screen.PostJobAd.route) },
                onNavigateToSwipe = { navController.navigate(Screen.Swipe.route) },
                onNavigateToListings = { navController.navigate(Screen.JobListings.route) },
                onNavigateToBlog = { navController.navigate(Screen.Blog.route) },
                onNavigateToAiAssistant = { navController.navigate(Screen.AiAssistant.route) },
                onNavigateToProfile = { navController.navigate(Screen.Profile.route) }
            )
        }
        composable(Screen.Login.route) {
            LoginScreen(
                viewModel = authViewModel,
                onLoginSuccess = { navController.navigateUp() },
                onNavigateToRegistration = {
                    navController.navigate(Screen.CandidateRegistration.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onBack = { navController.navigateUp() }
            )
        }
        composable(Screen.Blog.route) {
            BlogScreen()
        }
        composable(Screen.AiAssistant.route) {
            AiAssistantScreen(
                viewModel = aiAssistantViewModel,
                candidateViewModel = candidateViewModel,
                jobViewModel = jobViewModel,
                onBack = { navController.navigateUp() }
            )
        }
        composable(Screen.Swipe.route) {
            SwipeScreen(
                viewModel = jobViewModel,
                onNavigateToDetails = { jobId ->
                    navController.navigate(Screen.JobDetail.createRoute(jobId))
                }
            )
        }
        composable(Screen.Profile.route) {
            ProfileScreen(
                viewModel = candidateViewModel,
                authViewModel = authViewModel,
                onNavigateToLogin = { navController.navigate(Screen.Login.route) },
                onNavigateToRegistration = { navController.navigate(Screen.CandidateRegistration.route) },
                onBack = { navController.navigateUp() }
            )
        }
        composable(Screen.JobListings.route) {
            JobListingsScreen(
                viewModel = jobViewModel,
                onJobClick = { jobId ->
                    navController.navigate(Screen.JobDetail.createRoute(jobId))
                }
            )
        }
        composable(Screen.CandidateRegistration.route) {
            CandidateRegistrationScreen(
                viewModel = candidateViewModel,
                authViewModel = authViewModel,
                onRegistrationSuccess = { navController.navigateUp() },
                onBack = { navController.navigateUp() }
            )
        }
        composable(Screen.PostJobAd.route) {
            PostJobAdScreen(
                viewModel = jobViewModel,
                onPostSuccess = { navController.navigateUp() }
            )
        }
        composable(
            route = Screen.JobDetail.route,
            arguments = listOf(navArgument("jobId") { type = NavType.IntType })
        ) { backStackEntry ->
            val jobId = backStackEntry.arguments?.getInt("jobId") ?: return@composable
            JobDetailScreen(
                jobId = jobId,
                jobViewModel = jobViewModel,
                reviewViewModel = reviewViewModel,
                onBack = { navController.navigateUp() }
            )
        }
    }
}
