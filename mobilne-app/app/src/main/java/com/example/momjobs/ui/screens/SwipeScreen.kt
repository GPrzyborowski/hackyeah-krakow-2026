package com.example.momjobs.ui.screens

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.Info
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.example.momjobs.R
import com.example.momjobs.data.local.entities.JobAd
import com.example.momjobs.ui.theme.Background
import com.example.momjobs.ui.theme.OnBackground
import com.example.momjobs.ui.viewmodels.JobViewModel
import kotlinx.coroutines.launch

enum class SwipeDirection { LEFT, RIGHT }

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SwipeScreen(
    viewModel: JobViewModel,
    onNavigateToDetails: (Int) -> Unit
) {
    val jobs by viewModel.allJobAds.collectAsState()
    var currentIndex by remember { mutableIntStateOf(0) }
    val scope = rememberCoroutineScope()

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text(stringResource(R.string.swipe_title), style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold)) },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = Background,
                    titleContentColor = OnBackground
                )
            )
        }
    ) { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .background(Background)
                .padding(16.dp),
            contentAlignment = Alignment.Center
        ) {
            if (jobs.isEmpty()) {
                Text(stringResource(R.string.no_jobs), color = OnBackground)
            } else if (currentIndex >= jobs.size) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(stringResource(R.string.all_seen), style = MaterialTheme.typography.headlineSmall, color = OnBackground)
                    Spacer(modifier = Modifier.height(16.dp))
                    Button(onClick = { currentIndex = 0 }) {
                        Text(stringResource(R.string.start_over))
                    }
                }
            } else {
                // Show the next card behind the current one
                val displayedJobs = jobs.subList(currentIndex, (currentIndex + 2).coerceAtMost(jobs.size))
                
                displayedJobs.reversed().forEachIndexed { index, job ->
                    val isTopCard = index == displayedJobs.size - 1
                    key(job.id) {
                        JobSwipeCard(
                            job = job,
                            onSwiped = { direction ->
                                if (isTopCard) {
                                    currentIndex++
                                }
                            },
                            onInfoClick = if (isTopCard) { { onNavigateToDetails(job.id) } } else null,
                            isEnabled = isTopCard
                        )
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun JobSwipeCard(
    job: JobAd,
    onSwiped: (SwipeDirection) -> Unit,
    onInfoClick: (() -> Unit)? = null,
    isEnabled: Boolean = true
) {
    val configuration = LocalConfiguration.current
    val screenWidth = configuration.screenWidthDp.dp
    val density = LocalDensity.current
    val swipeThreshold = 120.dp
    
    val offsetX = remember { Animatable(0f) }
    val rotation = remember { Animatable(0f) }
    val scope = rememberCoroutineScope()

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .height(550.dp)
            .graphicsLayer {
                translationX = offsetX.value
                rotationZ = rotation.value
                if (!isEnabled) {
                    scaleX = 0.95f
                    scaleY = 0.95f
                }
            }
            .pointerInput(isEnabled) {
                if (!isEnabled) return@pointerInput
                detectDragGestures(
                    onDragEnd = {
                        scope.launch {
                            val thresholdPx = with(density) { swipeThreshold.toPx() }
                            val screenWidthPx = with(density) { screenWidth.toPx() }
                            
                            if (offsetX.value > thresholdPx) {
                                launch { rotation.animateTo(offsetX.value / 10f, tween(300)) }
                                offsetX.animateTo(screenWidthPx * 2, tween(300))
                                onSwiped(SwipeDirection.RIGHT)
                            } else if (offsetX.value < -thresholdPx) {
                                launch { rotation.animateTo(offsetX.value / 10f, tween(300)) }
                                offsetX.animateTo(-screenWidthPx * 2, tween(300))
                                onSwiped(SwipeDirection.LEFT)
                            } else {
                                launch { offsetX.animateTo(0f, tween(300)) }
                                launch { rotation.animateTo(0f, tween(300)) }
                            }
                        }
                    },
                    onDrag = { change, dragAmount ->
                        change.consume()
                        scope.launch {
                            offsetX.snapTo(offsetX.value + dragAmount.x)
                            rotation.snapTo(offsetX.value / 20f)
                        }
                    }
                )
            },
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = if (isEnabled) 8.dp else 2.dp),
        shape = MaterialTheme.shapes.extraLarge
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp)
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = job.title,
                    style = MaterialTheme.typography.headlineLarge,
                    fontWeight = FontWeight.ExtraBold,
                    color = MaterialTheme.colorScheme.primary
                )
                Text(
                    text = job.companyName,
                    style = MaterialTheme.typography.headlineSmall,
                    color = MaterialTheme.colorScheme.secondary,
                    fontWeight = FontWeight.SemiBold
                )
                Spacer(modifier = Modifier.height(12.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    SuggestionChip(
                        onClick = { },
                        label = { Text(job.location) }
                    )
                    job.salary?.let {
                        if (it.isNotBlank()) {
                            SuggestionChip(
                                onClick = { },
                                label = { Text(it) },
                                colors = SuggestionChipDefaults.suggestionChipColors(
                                    containerColor = MaterialTheme.colorScheme.tertiary,
                                    labelColor = MaterialTheme.colorScheme.onTertiary
                                )
                            )
                        }
                    }
                }
                Spacer(modifier = Modifier.height(20.dp))
                Text(
                    text = stringResource(R.string.description_label),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = job.description,
                    style = MaterialTheme.typography.bodyLarge,
                    maxLines = 8
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth().padding(top = 16.dp),
                horizontalArrangement = Arrangement.SpaceEvenly,
                verticalAlignment = Alignment.CenterVertically
            ) {
                LargeFloatingActionButton(
                    onClick = {
                        scope.launch {
                            val screenWidthPx = with(density) { screenWidth.toPx() }
                            launch { rotation.animateTo(-30f, tween(300)) }
                            offsetX.animateTo(-screenWidthPx * 2, tween(300))
                            onSwiped(SwipeDirection.LEFT)
                        }
                    },
                    containerColor = Color(0xFFFFEBEE),
                    contentColor = Color.Red,
                    shape = MaterialTheme.shapes.large
                ) {
                    Icon(Icons.Default.Close, contentDescription = stringResource(R.string.dislike), modifier = Modifier.size(32.dp))
                }
                
                if (onInfoClick != null) {
                    FilledTonalIconButton(
                        onClick = onInfoClick,
                        modifier = Modifier.size(56.dp)
                    ) {
                        Icon(Icons.Default.Info, contentDescription = stringResource(R.string.job_info), modifier = Modifier.size(28.dp))
                    }
                }

                LargeFloatingActionButton(
                    onClick = {
                        scope.launch {
                            val screenWidthPx = with(density) { screenWidth.toPx() }
                            launch { rotation.animateTo(30f, tween(300)) }
                            offsetX.animateTo(screenWidthPx * 2, tween(300))
                            onSwiped(SwipeDirection.RIGHT)
                        }
                    },
                    containerColor = MaterialTheme.colorScheme.tertiary,
                    contentColor = MaterialTheme.colorScheme.onTertiary,
                    shape = MaterialTheme.shapes.large
                ) {
                    Icon(Icons.Default.Favorite, contentDescription = stringResource(R.string.like), modifier = Modifier.size(32.dp))
                }
            }
        }
    }
}
