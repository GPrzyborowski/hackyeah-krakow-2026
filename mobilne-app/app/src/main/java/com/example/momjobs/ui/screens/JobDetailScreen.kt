package com.example.momjobs.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.StarBorder
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.example.momjobs.R
import com.example.momjobs.data.local.entities.Review
import com.example.momjobs.ui.viewmodels.JobViewModel
import com.example.momjobs.ui.viewmodels.ReviewViewModel
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun JobDetailScreen(
    jobId: Int,
    jobViewModel: JobViewModel,
    reviewViewModel: ReviewViewModel,
    onBack: () -> Unit
) {
    val jobs by jobViewModel.allJobAds.collectAsState()
    val job = jobs.find { it.id == jobId }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.job_details_title)) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer,
                    titleContentColor = MaterialTheme.colorScheme.onPrimaryContainer
                )
            )
        }
    ) { innerPadding ->
        if (job == null) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Text(stringResource(R.string.job_not_found))
            }
        } else {
            val reviews by reviewViewModel.getReviewsForCompany(job.companyName).collectAsState(initial = emptyList())

            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(innerPadding)
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                item {
                    Text(text = job.title, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                    Text(text = job.companyName, style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.secondary)
                    Text(text = job.location, style = MaterialTheme.typography.bodyLarge)
                    job.salary?.let {
                        if (it.isNotBlank()) {
                            Text(
                                text = stringResource(R.string.salary_label, it),
                                style = MaterialTheme.typography.bodyLarge,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }
                }

                item {
                    HorizontalDivider()
                }

                item {
                    Text(text = stringResource(R.string.description_label), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Text(text = job.description, style = MaterialTheme.typography.bodyMedium)
                }

                item {
                    Text(text = stringResource(R.string.requirements_label), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Text(text = job.requirements, style = MaterialTheme.typography.bodyMedium)
                }

                item {
                    HorizontalDivider()
                }

                item {
                    Text(text = stringResource(R.string.reviews_label), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                }

                if (reviews.isEmpty()) {
                    item {
                        Text(stringResource(R.string.no_reviews_yet), style = MaterialTheme.typography.bodyMedium)
                    }
                } else {
                    items(reviews) { review ->
                        ReviewItem(review)
                    }
                }
            }
        }
    }
}

@Composable
fun ReviewItem(review: Review) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        shape = MaterialTheme.shapes.large
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                repeat(5) { index ->
                    Icon(
                        imageVector = if (index < review.rating) Icons.Default.Star else Icons.Default.StarBorder,
                        contentDescription = null,
                        tint = if (index < review.rating) MaterialTheme.colorScheme.tertiary else Color.Gray,
                        modifier = Modifier.size(18.dp)
                    )
                }
                Spacer(modifier = Modifier.width(8.dp))
                Text(text = review.reviewerName, style = MaterialTheme.typography.labelLarge)
            }
            Spacer(modifier = Modifier.height(4.dp))
            Text(text = review.comment, style = MaterialTheme.typography.bodyMedium)
            if (review.isMaternityFriendly) {
                Spacer(modifier = Modifier.height(8.dp))
                AssistChip(
                    onClick = { },
                    label = { Text(stringResource(R.string.maternity_friendly)) },
                    leadingIcon = { Icon(Icons.Default.Favorite, contentDescription = null, modifier = Modifier.size(16.dp), tint = Color.Red) }
                )
            }
            Text(
                text = SimpleDateFormat("MMM dd, yyyy", Locale.getDefault()).format(Date(review.date)),
                style = MaterialTheme.typography.labelSmall,
                modifier = Modifier.align(Alignment.End)
            )
        }
    }
}
