package com.example.momjobs.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.momjobs.R
import com.example.momjobs.data.local.entities.JobAd
import com.example.momjobs.ui.theme.*
import com.example.momjobs.ui.viewmodels.CandidateViewModel
import com.example.momjobs.ui.viewmodels.InvitationViewModel
import com.example.momjobs.ui.viewmodels.JobViewModel

@Composable
fun HomeScreen(
    candidateViewModel: CandidateViewModel,
    jobViewModel: JobViewModel,
    invitationViewModel: InvitationViewModel,
    onNavigateToRegistration: () -> Unit,
    onNavigateToPostJob: () -> Unit,
    onNavigateToSwipe: () -> Unit,
    onNavigateToListings: () -> Unit,
    onNavigateToBlog: () -> Unit,
    onNavigateToAiAssistant: () -> Unit,
    onNavigateToProfile: () -> Unit
) {
    val profile by candidateViewModel.candidateProfile.collectAsState()
    val jobs by jobViewModel.allJobAds.collectAsState()
    val newInvitationsCount by invitationViewModel.newInvitationsCount.collectAsState()
    val invitations by invitationViewModel.allInvitations.collectAsState()
    
    val userName = profile?.name?.split(" ")?.firstOrNull() ?: "Marta"
    val invitationCompanies = if (invitations.isNotEmpty()) {
        invitations.take(2).joinToString(", ") { it.companyName }
    } else {
        "Zielone Biuro, Kamienica Studio"
    }
    
    val count = if (newInvitationsCount > 0) newInvitationsCount else 2
    
    // Dynamic Polish grammar for "firmy" vs "firm"
    val companiesText = when {
        count == 1 -> "Jedna firma już Cię zauważyła."
        count in 2..4 -> "$count firmy już Cię zauważyły."
        else -> "$count firm już Cię zauważyło."
    }
    
    val greetingText = "Cześć, $userName. $companiesText"

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(Background)
    ) {
        HomeTopBar(
            initial = userName.take(1),
            onProfileClick = onNavigateToProfile
        )
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 24.dp),
            verticalArrangement = Arrangement.spacedBy(20.dp)
        ) {
            item { Spacer(modifier = Modifier.height(12.dp)) }
            
            item {
                Text(
                    text = greetingText,
                    style = MaterialTheme.typography.headlineLarge.copy(
                        fontWeight = FontWeight.ExtraBold,
                        lineHeight = 42.sp,
                        color = OnBackground,
                        fontSize = 36.sp
                    ),
                    modifier = Modifier.padding(bottom = 8.dp)
                )
            }

            item { ReturnCalendarCard() }

            item { 
                CompanyInvitationsCard(
                    onClick = onNavigateToSwipe,
                    count = count,
                    companies = invitationCompanies
                ) 
            }

            item { 
                MatchingOffersCard(
                    jobs = jobs.filter { it.title.contains("HR") || it.title.contains("projektów") || it.title.contains("Księgowa") }.take(2),
                    onSeeAll = onNavigateToListings
                ) 
            }

            item { AssistantPromptCard(onClick = onNavigateToAiAssistant) }
            
            item { Spacer(modifier = Modifier.height(32.dp)) }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeTopBar(initial: String, onProfileClick: () -> Unit) {
    CenterAlignedTopAppBar(
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(modifier = Modifier.size(36.dp)) {
                    Box(
                        modifier = Modifier
                            .size(28.dp)
                            .align(Alignment.BottomStart)
                            .clip(CircleShape)
                            .background(DarkTeal)
                    )
                    Box(
                        modifier = Modifier
                            .size(16.dp)
                            .align(Alignment.TopEnd)
                            .clip(CircleShape)
                            .background(Secondary)
                    )
                }
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    text = "Wracam",
                    style = MaterialTheme.typography.titleLarge.copy(
                        fontWeight = FontWeight.Bold,
                        fontSize = 26.sp
                    ),
                    color = OnBackground
                )
            }
        },
        actions = {
            Surface(
                onClick = onProfileClick,
                modifier = Modifier
                    .padding(end = 16.dp)
                    .size(48.dp),
                shape = CircleShape,
                color = Secondary
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Text(text = initial, fontWeight = FontWeight.Bold, color = OnBackground, fontSize = 20.sp)
                }
            }
        },
        colors = TopAppBarDefaults.centerAlignedTopAppBarColors(containerColor = Background)
    )
}

@Composable
fun ReturnCalendarCard() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = DarkTeal),
        shape = MaterialTheme.shapes.extraLarge
    ) {
        Column(modifier = Modifier.padding(28.dp)) {
            Text(
                text = stringResource(R.string.return_calendar),
                style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 24.sp),
                color = Color.White
            )
            Spacer(modifier = Modifier.height(24.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Box(
                    modifier = Modifier
                        .weight(1.5f)
                        .height(14.dp)
                        .clip(CircleShape)
                        .background(ProgressGreen)
                )
                Box(
                    modifier = Modifier
                        .weight(2f)
                        .height(14.dp)
                        .clip(CircleShape)
                        .background(Secondary)
                )
                Box(
                    modifier = Modifier
                        .weight(1.2f)
                        .height(14.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.2f))
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth(0.5f)
                            .fillMaxHeight()
                            .clip(CircleShape)
                            .background(Tertiary)
                    )
                }
            }
            Spacer(modifier = Modifier.height(16.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                CalendarLabel(stringResource(R.string.week_24))
                CalendarLabel(stringResource(R.string.leave_from))
                CalendarLabel(stringResource(R.string.ready_on))
            }
        }
    }
}

@Composable
fun CalendarLabel(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium.copy(fontSize = 14.sp),
        color = Color.White.copy(alpha = 0.7f)
    )
}

@Composable
fun CompanyInvitationsCard(onClick: () -> Unit, count: Int, companies: String) {
    Card(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = MaterialTheme.shapes.extraLarge
    ) {
        Row(
            modifier = Modifier.padding(28.dp).fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = stringResource(R.string.company_invitations),
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 20.sp),
                    color = OnBackground
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = companies,
                    style = MaterialTheme.typography.bodyLarge,
                    color = OnBackground.copy(alpha = 0.5f)
                )
            }
            Surface(
                shape = MaterialTheme.shapes.large,
                color = Tertiary
            ) {
                Text(
                    text = "$count nowe",
                    style = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Bold, fontSize = 14.sp),
                    color = OnBackground,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp)
                )
            }
        }
    }
}

@Composable
fun MatchingOffersCard(jobs: List<JobAd>, onSeeAll: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = MaterialTheme.shapes.extraLarge
    ) {
        Column(modifier = Modifier.padding(28.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = stringResource(R.string.matching_offers),
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 20.sp),
                    color = OnBackground
                )
                Text(
                    text = stringResource(R.string.see_all),
                    style = MaterialTheme.typography.labelLarge.copy(
                        fontWeight = FontWeight.Bold,
                        textDecoration = TextDecoration.Underline,
                        fontSize = 15.sp
                    ),
                    color = OnBackground,
                    modifier = Modifier.clickable { onSeeAll() }
                )
            }
            Spacer(modifier = Modifier.height(20.dp))
            
            if (jobs.isEmpty()) {
                OfferItem("Specjalistka ds. HR - zdalnie", "92%")
                OfferItem("Koordynatorka projektów", "84%")
            } else {
                jobs.forEachIndexed { index, job ->
                    val percentage = if (index == 0) "92%" else "84%"
                    OfferItem(job.title, percentage)
                }
            }
        }
    }
}

@Composable
fun OfferItem(title: String, percentage: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = title,
            modifier = Modifier.weight(1f),
            style = MaterialTheme.typography.bodyLarge.copy(fontSize = 18.sp),
            color = OnBackground.copy(alpha = 0.8f)
        )
        Surface(
            shape = MaterialTheme.shapes.large,
            color = Tertiary
        ) {
            Text(
                text = percentage,
                style = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Bold, fontSize = 14.sp),
                color = OnBackground,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp)
            )
        }
    }
}

@Composable
fun AssistantPromptCard(onClick: () -> Unit) {
    Card(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = Secondary),
        shape = MaterialTheme.shapes.extraLarge
    ) {
        Column(modifier = Modifier.padding(28.dp)) {
            Text(
                text = stringResource(R.string.assistant_prompt_title),
                style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 24.sp),
                color = DarkTeal
            )
            Spacer(modifier = Modifier.height(12.dp))
            Text(
                text = stringResource(R.string.assistant_prompt_desc),
                style = MaterialTheme.typography.bodyLarge.copy(fontSize = 18.sp),
                color = DarkTeal.copy(alpha = 0.6f)
            )
        }
    }
}
