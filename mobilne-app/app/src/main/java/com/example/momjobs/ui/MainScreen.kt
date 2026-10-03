package com.example.momjobs.ui

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BusinessCenter
import androidx.compose.material.icons.filled.ChatBubbleOutline
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.example.momjobs.R
import com.example.momjobs.navigation.MomjobsNavGraph
import com.example.momjobs.navigation.Screen
import com.example.momjobs.ui.viewmodels.*

@Composable
fun MainScreen(
    candidateViewModel: CandidateViewModel,
    jobViewModel: JobViewModel,
    reviewViewModel: ReviewViewModel,
    aiAssistantViewModel: AiAssistantViewModel,
    invitationViewModel: InvitationViewModel
) {
    val navController = rememberNavController()
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentDestination = navBackStackEntry?.destination

    val topLevelScreens = listOf(
        Screen.Home,
        Screen.JobListings,
        Screen.Blog,
        Screen.AiAssistant,
        Screen.Profile
    )

    val showBottomBar = currentDestination?.route in topLevelScreens.map { it.route }

    Scaffold(
        bottomBar = {
            if (showBottomBar) {
                NavigationBar(
                    containerColor = MaterialTheme.colorScheme.background,
                    tonalElevation = 0.dp
                ) {
                    val items = listOf(
                        NavigationItem(stringResource(R.string.nav_start), Icons.Default.Home, Screen.Home),
                        NavigationItem(stringResource(R.string.nav_offers), Icons.Default.BusinessCenter, Screen.JobListings),
                        NavigationItem(stringResource(R.string.nav_blog), Icons.Outlined.Description, Screen.Blog),
                        NavigationItem(stringResource(R.string.nav_assistant), Icons.Default.ChatBubbleOutline, Screen.AiAssistant),
                        NavigationItem(stringResource(R.string.nav_profile), Icons.Default.Person, Screen.Profile)
                    )

                    items.forEach { item ->
                        val selected = currentDestination?.hierarchy?.any { it.route == item.screen.route } == true
                        NavigationBarItem(
                            icon = { Icon(item.icon, contentDescription = null) },
                            label = { Text(item.label) },
                            selected = selected,
                            onClick = {
                                navController.navigate(item.screen.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            },
                            colors = NavigationBarItemDefaults.colors(
                                selectedIconColor = MaterialTheme.colorScheme.primary,
                                unselectedIconColor = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.4f),
                                indicatorColor = Color.Transparent
                            )
                        )
                    }
                }
            }
        }
    ) { innerPadding ->
        MomjobsNavGraph(
            navController = navController,
            candidateViewModel = candidateViewModel,
            jobViewModel = jobViewModel,
            reviewViewModel = reviewViewModel,
            aiAssistantViewModel = aiAssistantViewModel,
            invitationViewModel = invitationViewModel,
            modifier = Modifier.padding(innerPadding)
        )
    }
}

data class NavigationItem(
    val label: String,
    val icon: androidx.compose.ui.graphics.vector.ImageVector,
    val screen: Screen
)
